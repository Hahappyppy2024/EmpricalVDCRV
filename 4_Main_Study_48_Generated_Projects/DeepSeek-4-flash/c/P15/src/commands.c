/*
 * commands.c -- application protocol command execution.
 * Bounded parsing, authentication state, conditional mutations, bounded
 * listing, health, and metrics. Sensitive fields (tokens, values) are never
 * written to logs or responses.
 */
#include "commands.h"

#include <errno.h>
#include <inttypes.h>
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "auth.h"
#include "metrics.h"
#include "netd.h"
#include "proto.h"
#include "storage.h"
#include "util.h"

#define TTL_MAX 315360000 /* 10 years in seconds */

static const char *role_name(netd_role_t r) {
    switch (r) {
        case NETD_ROLE_MONITORING:
            return "monitoring";
        case NETD_ROLE_ADMIN:
            return "administrator";
        default:
            return "client";
    }
}

static int name_valid(const char *s) {
    if (s == NULL || s[0] == '\0' || strlen(s) > NETD_KEY_MAX) return 0;
    for (const char *p = s; *p; p++) {
        char c = *p;
        if (!((c >= 'A' && c <= 'Z') || (c >= 'a' && c <= 'z') ||
              (c >= '0' && c <= '9') || c == '_' || c == '.' || c == '-')) {
            return 0;
        }
    }
    return 1;
}

static int parse_i64(const char *s, int64_t *out) {
    if (s == NULL || s[0] == '\0') return -1;
    char *end = NULL;
    errno = 0;
    long long v = strtoll(s, &end, 10);
    if (errno != 0 || end == NULL || *end != '\0') return -1;
    *out = (int64_t)v;
    return 0;
}

static int tokenize(char *line, char *argv[], int max) {
    int n = 0;
    char *p = line;
    for (;;) {
        while (*p == ' ') p++;
        if (*p == '\0') break;
        if (n >= max) return -1;
        argv[n++] = p;
        char *sp = strchr(p, ' ');
        if (sp == NULL) break;
        *sp = '\0';
        p = sp + 1;
    }
    return n;
}

static const char *expire_str(int64_t exp, char *buf, size_t buflen) {
    if (exp > 0) {
        snprintf(buf, buflen, "%" PRId64, exp);
    } else {
        snprintf(buf, buflen, "NONE");
    }
    return buf;
}

static void log_conn(netd_runtime_t *rt, const char *ev, const char *code,
                     const char *ns, const char *key, const char *extra_fmt,
                     ...) {
    char conn[32];
    snprintf(conn, sizeof(conn), "%" PRIu64, rt->conn_id);
    char extra[512];
    extra[0] = '\0';
    if (extra_fmt != NULL) {
        va_list ap;
        va_start(ap, extra_fmt);
        vsnprintf(extra, sizeof(extra), extra_fmt, ap);
        va_end(ap);
    }
    log_event(rt->lg, ev, code, conn,
              rt->principal ? rt->principal : "-", ns ? ns : "-",
              key ? key : "-", extra[0] ? extra : NULL);
}

/* ------------------------------------------------------------------ */
/* Commands.                                                           */
/* ------------------------------------------------------------------ */

static netd_cmd_rc_t cmd_auth(netd_runtime_t *rt, netd_conn_t *conn,
                              char *argv[], int n, char *out, size_t outlen) {
    if (n != 2) {
        snprintf(out, outlen, CODE_ERR_FIELD_COUNT " AUTH\n");
        return NETD_CMD_OK;
    }

    char hex[NETD_SHA256_HEX_LEN];
    if (auth_hash_token(argv[1], hex, sizeof(hex)) != 0) {
        snprintf(out, outlen, CODE_ERR_INTERNAL "\n");
        return NETD_CMD_OK;
    }

    netd_auth_result_t auth;
    netd_auth_rc_t rc = storage_auth(rt->db, hex, &auth);
    switch (rc) {
        case NETD_AUTH_OK:
            conn->authenticated = 1;
            conn->principal_id = auth.principal_id;
            conn->token_id = auth.token_id;
            conn->role = auth.role;
            snprintf(conn->principal_name, sizeof(conn->principal_name), "%s",
                     auth.principal_name);
            storage_session_open(rt->db, conn->id, &auth, util_now_epoch());
            metric_inc(M_AUTH_OK);
            log_conn(rt, EV_AUTH_OK, "OK", "-", "-", "role=%s",
                     role_name(auth.role));
            snprintf(out, outlen, "OK AUTH %s\n", role_name(auth.role));
            return NETD_CMD_OK;
        case NETD_AUTH_REVOKED:
            metric_inc(M_AUTH_FAIL);
            log_conn(rt, EV_AUTH_FAIL, "ERR", "-", "-", "code=revoked");
            snprintf(out, outlen, CODE_ERR_AUTH_REVOKED "\n");
            return NETD_CMD_OK;
        case NETD_AUTH_EXPIRED:
            metric_inc(M_AUTH_FAIL);
            log_conn(rt, EV_AUTH_FAIL, "ERR", "-", "-", "code=expired");
            snprintf(out, outlen, CODE_ERR_AUTH_EXPIRED "\n");
            return NETD_CMD_OK;
        case NETD_AUTH_DB_ERROR:
            metric_inc(M_AUTH_FAIL);
            log_conn(rt, EV_AUTH_FAIL, "ERR", "-", "-", "code=db_error");
            snprintf(out, outlen, CODE_ERR_DB "\n");
            return NETD_CMD_OK;
        case NETD_AUTH_UNKNOWN:
        default:
            metric_inc(M_AUTH_FAIL);
            log_conn(rt, EV_AUTH_FAIL, "ERR", "-", "-", "code=unknown");
            snprintf(out, outlen, CODE_ERR_AUTH_UNKNOWN "\n");
            return NETD_CMD_OK;
    }
}

static netd_cmd_rc_t cmd_put(netd_runtime_t *rt, netd_conn_t *conn,
                             char *argv[], int n, char *out, size_t outlen) {
    if (n != 5) {
        snprintf(out, outlen, CODE_ERR_FIELD_COUNT " PUT\n");
        return NETD_CMD_OK;
    }
    const char *ns = argv[1], *key = argv[2];
    if (!name_valid(ns) || !name_valid(key)) {
        snprintf(out, outlen, CODE_ERR_NAME_INVALID "\n");
        return NETD_CMD_OK;
    }
    int64_t ttl = 0;
    if (parse_i64(argv[3], &ttl) != 0 || ttl < 0 || ttl > TTL_MAX) {
        snprintf(out, outlen, CODE_ERR_TTL_RANGE "\n");
        return NETD_CMD_OK;
    }
    if (!storage_has_grant(rt->db, conn->principal_id, ns, 1)) {
        snprintf(out, outlen, CODE_ERR_NAMESPACE " %s\n", ns);
        log_conn(rt, EV_PUT_FAIL, "ERR", ns, key, "reason=namespace_denied");
        return NETD_CMD_OK;
    }

    size_t b64len = strlen(argv[4]);
    size_t cap = b64len / 4 * 3 + 4;
    uint8_t *decoded = malloc(cap);
    if (decoded == NULL) {
        snprintf(out, outlen, CODE_ERR_INTERNAL "\n");
        return NETD_CMD_OK;
    }
    char derr[128];
    size_t dlen = 0;
    if (proto_b64_decode(argv[4], b64len, decoded, cap, &dlen, derr,
                         sizeof(derr)) != 0) {
        free(decoded);
        snprintf(out, outlen, CODE_ERR_BASE64 "\n");
        return NETD_CMD_OK;
    }
    if (dlen > (size_t)rt->cfg.max_command_bytes) {
        free(decoded);
        snprintf(out, outlen, CODE_ERR_LINE_TOO_LONG "\n");
        return NETD_CMD_OK;
    }

    int64_t ver = 0, exp = 0;
    netd_store_rc_t rc = storage_put(rt->db, ns, key, ttl, decoded, dlen,
                                     util_now_epoch(), &ver, &exp);
    free(decoded);

    if (rc == NETD_STORE_OK) {
        metric_inc(M_PUT_OK);
        char eb[32];
        snprintf(out, outlen, "OK CREATED %" PRId64 " %s\n", ver,
                 expire_str(exp, eb, sizeof(eb)));
        log_conn(rt, EV_PUT_OK, "OK", ns, key, "version=%" PRId64 " ttl=%" PRId64,
                 ver, ttl);
        return NETD_CMD_OK;
    }
    if (rc == NETD_STORE_EXISTS) {
        metric_inc(M_PUT_FAIL);
        snprintf(out, outlen, CODE_ERR_KEY_EXISTS " %s %s\n", ns, key);
        log_conn(rt, EV_PUT_FAIL, "ERR", ns, key, "reason=exists");
        return NETD_CMD_OK;
    }
    metric_inc(M_PUT_FAIL);
    snprintf(out, outlen, CODE_ERR_DB "\n");
    log_conn(rt, EV_PUT_FAIL, "ERR", ns, key, "reason=db_error");
    return NETD_CMD_OK;
}

static netd_cmd_rc_t cmd_get(netd_runtime_t *rt, netd_conn_t *conn,
                             char *argv[], int n, char *out, size_t outlen) {
    if (n != 3) {
        snprintf(out, outlen, CODE_ERR_FIELD_COUNT " GET\n");
        return NETD_CMD_OK;
    }
    const char *ns = argv[1], *key = argv[2];
    if (!name_valid(ns) || !name_valid(key)) {
        snprintf(out, outlen, CODE_ERR_NAME_INVALID "\n");
        return NETD_CMD_OK;
    }
    if (!storage_has_grant(rt->db, conn->principal_id, ns, 0)) {
        snprintf(out, outlen, CODE_ERR_NAMESPACE " %s\n", ns);
        log_conn(rt, EV_GET_FAIL, "ERR", ns, key, "reason=namespace_denied");
        return NETD_CMD_OK;
    }

    netd_record_t rec;
    netd_store_rc_t rc = storage_get(rt->db, ns, key, util_now_epoch(), &rec);
    if (rc == NETD_STORE_OK) {
        size_t bcap = rec.value_len / 3 * 4 + 8;
        char *b64 = malloc(bcap);
        if (b64 == NULL || proto_b64_encode(rec.value, rec.value_len, b64,
                                            bcap) != 0) {
            free(b64);
            free(rec.value);
            snprintf(out, outlen, CODE_ERR_INTERNAL "\n");
            return NETD_CMD_OK;
        }
        char eb[32];
        snprintf(out, outlen, "OK VALUE %" PRId64 " %s %s\n", rec.version,
                 expire_str(rec.expires_at, eb, sizeof(eb)), b64);
        free(b64);
        free(rec.value);
        metric_inc(M_GET_OK);
        log_conn(rt, EV_GET_OK, "OK", ns, key, "version=%" PRId64, rec.version);
        return NETD_CMD_OK;
    }
    if (rc == NETD_STORE_NOT_FOUND) {
        metric_inc(M_GET_FAIL);
        snprintf(out, outlen, CODE_ERR_NOT_FOUND " %s %s\n", ns, key);
        log_conn(rt, EV_GET_FAIL, "ERR", ns, key, "reason=not_found");
        return NETD_CMD_OK;
    }
    metric_inc(M_GET_FAIL);
    snprintf(out, outlen, CODE_ERR_DB "\n");
    return NETD_CMD_OK;
}

static netd_cmd_rc_t cmd_list(netd_runtime_t *rt, netd_conn_t *conn,
                              char *argv[], int n, char *out, size_t outlen) {
    if (n != 5) {
        snprintf(out, outlen, CODE_ERR_FIELD_COUNT " LIST\n");
        return NETD_CMD_OK;
    }
    const char *ns = argv[1];
    const char *prefix = strcmp(argv[2], "-") == 0 ? "" : argv[2];
    const char *after = strcmp(argv[4], "-") == 0 ? "" : argv[4];
    if (!name_valid(ns) ||
        (prefix[0] != '\0' && !name_valid(prefix)) ||
        (after[0] != '\0' && !name_valid(after))) {
        snprintf(out, outlen, CODE_ERR_NAME_INVALID "\n");
        return NETD_CMD_OK;
    }
    int64_t limit = 0;
    if (parse_i64(argv[3], &limit) != 0 || limit < 1 ||
        limit > rt->cfg.list_max_limit) {
        snprintf(out, outlen, CODE_ERR_LIMIT_RANGE "\n");
        return NETD_CMD_OK;
    }
    if (!storage_has_grant(rt->db, conn->principal_id, ns, 0)) {
        snprintf(out, outlen, CODE_ERR_NAMESPACE " %s\n", ns);
        log_conn(rt, EV_LIST_FAIL, "ERR", ns, "-", "reason=namespace_denied");
        return NETD_CMD_OK;
    }

    int cap = (int)limit + 1;
    netd_list_row_t *rows = calloc((size_t)cap, sizeof(*rows));
    if (rows == NULL) {
        snprintf(out, outlen, CODE_ERR_INTERNAL "\n");
        return NETD_CMD_OK;
    }
    int count = 0, more = 0;
    char after_out[NETD_KEY_MAX + 1];
    netd_store_rc_t rc = storage_list(rt->db, ns, prefix, limit, after,
                                      util_now_epoch(), rows, &count, &more,
                                      after_out);
    if (rc != NETD_STORE_OK) {
        free(rows);
        snprintf(out, outlen, CODE_ERR_DB "\n");
        return NETD_CMD_OK;
    }

    size_t off = 0;
    off += (size_t)snprintf(out + off, outlen - off, "OK KEYS %d %d %s",
                            count, more ? 1 : 0, more ? after_out : "-");
    for (int i = 0; i < count && off + NETD_KEY_MAX + 2 < outlen; i++) {
        off += (size_t)snprintf(out + off, outlen - off, " %s", rows[i].key);
    }
    if (off + 1 < outlen) {
        out[off++] = '\n';
        out[off] = '\0';
    }
    free(rows);
    metric_inc(M_LIST_OK);
    log_conn(rt, EV_LIST_OK, "OK", ns, "-", "count=%d more=%d", count, more);
    return NETD_CMD_OK;
}

static netd_cmd_rc_t cmd_update(netd_runtime_t *rt, netd_conn_t *conn,
                                char *argv[], int n, char *out, size_t outlen) {
    if (n != 6) {
        snprintf(out, outlen, CODE_ERR_FIELD_COUNT " UPDATE\n");
        return NETD_CMD_OK;
    }
    const char *ns = argv[1], *key = argv[2];
    if (!name_valid(ns) || !name_valid(key)) {
        snprintf(out, outlen, CODE_ERR_NAME_INVALID "\n");
        return NETD_CMD_OK;
    }
    int64_t expected = 0, ttl = 0;
    if (parse_i64(argv[3], &expected) != 0 || expected < 1) {
        snprintf(out, outlen, CODE_ERR_FIELD_COUNT " UPDATE\n");
        return NETD_CMD_OK;
    }
    if (parse_i64(argv[4], &ttl) != 0 || ttl < 0 || ttl > TTL_MAX) {
        snprintf(out, outlen, CODE_ERR_TTL_RANGE "\n");
        return NETD_CMD_OK;
    }
    if (!storage_has_grant(rt->db, conn->principal_id, ns, 1)) {
        snprintf(out, outlen, CODE_ERR_NAMESPACE " %s\n", ns);
        log_conn(rt, EV_UPDATE_FAIL, "ERR", ns, key, "reason=namespace_denied");
        return NETD_CMD_OK;
    }

    size_t b64len = strlen(argv[5]);
    size_t cap = b64len / 4 * 3 + 4;
    uint8_t *decoded = malloc(cap);
    if (decoded == NULL) {
        snprintf(out, outlen, CODE_ERR_INTERNAL "\n");
        return NETD_CMD_OK;
    }
    char derr[128];
    size_t dlen = 0;
    if (proto_b64_decode(argv[5], b64len, decoded, cap, &dlen, derr,
                         sizeof(derr)) != 0) {
        free(decoded);
        snprintf(out, outlen, CODE_ERR_BASE64 "\n");
        return NETD_CMD_OK;
    }
    if (dlen > (size_t)rt->cfg.max_command_bytes) {
        free(decoded);
        snprintf(out, outlen, CODE_ERR_LINE_TOO_LONG "\n");
        return NETD_CMD_OK;
    }

    int64_t ver = 0, exp = 0, cur = 0;
    netd_store_rc_t rc = storage_update(rt->db, ns, key, expected, ttl,
                                        decoded, dlen, util_now_epoch(), &ver,
                                        &exp, &cur);
    free(decoded);

    if (rc == NETD_STORE_OK) {
        metric_inc(M_UPDATE_OK);
        char eb[32];
        snprintf(out, outlen, "OK UPDATED %" PRId64 " %s\n", ver,
                 expire_str(exp, eb, sizeof(eb)));
        log_conn(rt, EV_UPDATE_OK, "OK", ns, key,
                 "version=%" PRId64 " ttl=%" PRId64, ver, ttl);
        return NETD_CMD_OK;
    }
    if (rc == NETD_STORE_CONFLICT) {
        metric_inc(M_CONFLICT_TOTAL);
        metric_inc(M_UPDATE_FAIL);
        snprintf(out, outlen, CODE_ERR_CONFLICT " %" PRId64 "\n", cur);
        log_conn(rt, EV_UPDATE_FAIL, "ERR", ns, key,
                 "reason=conflict expected=%" PRId64 " current=%" PRId64,
                 expected, cur);
        return NETD_CMD_OK;
    }
    if (rc == NETD_STORE_NOT_FOUND) {
        metric_inc(M_UPDATE_FAIL);
        snprintf(out, outlen, CODE_ERR_NOT_FOUND " %s %s\n", ns, key);
        log_conn(rt, EV_UPDATE_FAIL, "ERR", ns, key, "reason=not_found");
        return NETD_CMD_OK;
    }
    metric_inc(M_UPDATE_FAIL);
    snprintf(out, outlen, CODE_ERR_DB "\n");
    return NETD_CMD_OK;
}

static netd_cmd_rc_t cmd_delete(netd_runtime_t *rt, netd_conn_t *conn,
                                char *argv[], int n, char *out, size_t outlen) {
    if (n != 4) {
        snprintf(out, outlen, CODE_ERR_FIELD_COUNT " DELETE\n");
        return NETD_CMD_OK;
    }
    const char *ns = argv[1], *key = argv[2];
    if (!name_valid(ns) || !name_valid(key)) {
        snprintf(out, outlen, CODE_ERR_NAME_INVALID "\n");
        return NETD_CMD_OK;
    }
    int64_t expected = 0;
    if (parse_i64(argv[3], &expected) != 0 || expected < 1) {
        snprintf(out, outlen, CODE_ERR_FIELD_COUNT " DELETE\n");
        return NETD_CMD_OK;
    }
    if (!storage_has_grant(rt->db, conn->principal_id, ns, 1)) {
        snprintf(out, outlen, CODE_ERR_NAMESPACE " %s\n", ns);
        log_conn(rt, EV_DELETE_FAIL, "ERR", ns, key, "reason=namespace_denied");
        return NETD_CMD_OK;
    }

    int64_t ver = 0, cur = 0;
    netd_store_rc_t rc = storage_delete(rt->db, ns, key, expected,
                                        util_now_epoch(), &ver, &cur);
    if (rc == NETD_STORE_OK) {
        metric_inc(M_DELETE_OK);
        snprintf(out, outlen, "OK DELETED %" PRId64 "\n", ver);
        log_conn(rt, EV_DELETE_OK, "OK", ns, key, "version=%" PRId64, ver);
        return NETD_CMD_OK;
    }
    if (rc == NETD_STORE_CONFLICT) {
        metric_inc(M_CONFLICT_TOTAL);
        metric_inc(M_DELETE_FAIL);
        snprintf(out, outlen, CODE_ERR_CONFLICT " %" PRId64 "\n", cur);
        log_conn(rt, EV_DELETE_FAIL, "ERR", ns, key,
                 "reason=conflict expected=%" PRId64 " current=%" PRId64,
                 expected, cur);
        return NETD_CMD_OK;
    }
    if (rc == NETD_STORE_NOT_FOUND) {
        metric_inc(M_DELETE_FAIL);
        snprintf(out, outlen, CODE_ERR_NOT_FOUND " %s %s\n", ns, key);
        log_conn(rt, EV_DELETE_FAIL, "ERR", ns, key, "reason=not_found");
        return NETD_CMD_OK;
    }
    metric_inc(M_DELETE_FAIL);
    snprintf(out, outlen, CODE_ERR_DB "\n");
    return NETD_CMD_OK;
}

static netd_cmd_rc_t cmd_health(netd_runtime_t *rt, netd_conn_t *conn,
                                char *argv[], int n, char *out, size_t outlen) {
    (void)conn;
    (void)argv;
    if (n != 1) {
        snprintf(out, outlen, CODE_ERR_FIELD_COUNT " HEALTH\n");
        return NETD_CMD_OK;
    }
    if (storage_probe(rt->db) != 0) {
        metric_inc(M_BUSY_TIMEOUTS);
        log_conn(rt, EV_DB_PROBE, "ERR", "-", "-", "result=degraded");
        snprintf(out, outlen, "OK HEALTH degraded reason=DB_PROBE_FAIL\n");
        return NETD_CMD_OK;
    }
    snprintf(out, outlen, "OK HEALTH healthy reason=-\n");
    return NETD_CMD_OK;
}

static netd_cmd_rc_t cmd_stats(netd_runtime_t *rt, netd_conn_t *conn,
                               char *argv[], int n, char *out, size_t outlen) {
    (void)conn;
    (void)argv;
    if (n != 1) {
        snprintf(out, outlen, CODE_ERR_FIELD_COUNT " STATS\n");
        return NETD_CMD_OK;
    }
    netd_metrics_t m;
    metrics_snapshot(&m);
    snprintf(out, outlen,
             "OK STATS uptime_seconds=%" PRId64
             " generation=%d worker_count=%d max_clients=%d"
             " idle_timeout_seconds=%d auth_timeout_seconds=%d"
             " max_command_bytes=%d expiry_scan_interval_seconds=%d"
             " expiry_batch_size=%d list_max_limit=%d"
             " connections_total=%" PRIu64 " current_connections=%" PRIu64
             " rejected_connections=%" PRIu64 " commands_total=%" PRIu64
             " auth_ok=%" PRIu64 " auth_fail=%" PRIu64 " put_ok=%" PRIu64
             " put_fail=%" PRIu64 " get_ok=%" PRIu64 " get_fail=%" PRIu64
             " list_ok=%" PRIu64 " list_fail=%" PRIu64 " update_ok=%" PRIu64
             " update_fail=%" PRIu64 " delete_ok=%" PRIu64 " delete_fail=%" PRIu64
             " conflict_total=%" PRIu64 " expiry_runs=%" PRIu64
             " expiry_deleted=%" PRIu64 " busy_timeouts=%" PRIu64
             " log_errors=%" PRIu64 " bytes_received=%" PRIu64
             " bytes_sent=%" PRIu64 " reload_count=%" PRIu64
             " reload_fail=%" PRIu64 "\n",
             metrics_uptime_seconds(), rt->cfg.generation,
             rt->cfg.worker_count, rt->cfg.max_clients,
             rt->cfg.idle_timeout_seconds, rt->cfg.auth_timeout_seconds,
             rt->cfg.max_command_bytes, rt->cfg.expiry_scan_interval_seconds,
             rt->cfg.expiry_batch_size, rt->cfg.list_max_limit,
             m.connections_total, m.current_connections,
             m.rejected_connections, m.commands_total, m.auth_ok, m.auth_fail,
             m.put_ok, m.put_fail, m.get_ok, m.get_fail, m.list_ok,
             m.list_fail, m.update_ok, m.update_fail, m.delete_ok,
             m.delete_fail, m.conflict_total, m.expiry_runs, m.expiry_deleted,
             m.busy_timeouts, m.log_errors, m.bytes_received, m.bytes_sent,
             m.reload_count, m.reload_fail);
    return NETD_CMD_OK;
}

/* ------------------------------------------------------------------ */
/* Dispatcher.                                                         */
/* ------------------------------------------------------------------ */

netd_cmd_rc_t cmd_execute(netd_runtime_t *rt, netd_conn_t *conn, char *line,
                          char *out, size_t outlen) {
    out[0] = '\0';

    char *argv[NETD_CMD_MAX_ARGS + 1];
    int n = tokenize(line, argv, NETD_CMD_MAX_ARGS);
    if (n < 0) {
        snprintf(out, outlen, CODE_ERR_FIELD_COUNT " -\n");
        return NETD_CMD_OK;
    }
    if (n == 0) {
        snprintf(out, outlen, CODE_ERR_FIELD_COUNT " -\n");
        return NETD_CMD_OK;
    }

    const char *cmd = argv[0];

    if (strcmp(cmd, "QUIT") == 0) {
        if (n != 1) {
            snprintf(out, outlen, CODE_ERR_FIELD_COUNT " QUIT\n");
            return NETD_CMD_OK;
        }
        snprintf(out, outlen, "OK QUIT\n");
        return NETD_CMD_CLOSE;
    }

    if (!conn->authenticated) {
        if (strcmp(cmd, "AUTH") == 0) {
            return cmd_auth(rt, conn, argv, n, out, outlen);
        }
        snprintf(out, outlen, CODE_ERR_AUTH_REQUIRED " %s\n", cmd);
        return NETD_CMD_OK;
    }

    if (strcmp(cmd, "PING") == 0) {
        if (n != 1) {
            snprintf(out, outlen, CODE_ERR_FIELD_COUNT " PING\n");
            return NETD_CMD_OK;
        }
        snprintf(out, outlen, "PONG\n");
        return NETD_CMD_OK;
    }

    if (strcmp(cmd, "AUTH") == 0) {
        /* Already authenticated: idempotent re-authentication. */
        return cmd_auth(rt, conn, argv, n, out, outlen);
    }

    if (strcmp(cmd, "HEALTH") == 0 || strcmp(cmd, "STATS") == 0) {
        if (conn->role != NETD_ROLE_MONITORING &&
            conn->role != NETD_ROLE_ADMIN) {
            snprintf(out, outlen, CODE_ERR_ROLE_DENIED " %s\n", cmd);
            return NETD_CMD_OK;
        }
        if (strcmp(cmd, "HEALTH") == 0) return cmd_health(rt, conn, argv, n, out, outlen);
        return cmd_stats(rt, conn, argv, n, out, outlen);
    }

    if (strcmp(cmd, "PUT") == 0 || strcmp(cmd, "GET") == 0 ||
        strcmp(cmd, "LIST") == 0 || strcmp(cmd, "UPDATE") == 0 ||
        strcmp(cmd, "DELETE") == 0) {
        if (conn->role == NETD_ROLE_MONITORING) {
            snprintf(out, outlen, CODE_ERR_ROLE_DENIED " %s\n", cmd);
            return NETD_CMD_OK;
        }
        if (strcmp(cmd, "PUT") == 0) return cmd_put(rt, conn, argv, n, out, outlen);
        if (strcmp(cmd, "GET") == 0) return cmd_get(rt, conn, argv, n, out, outlen);
        if (strcmp(cmd, "LIST") == 0) return cmd_list(rt, conn, argv, n, out, outlen);
        if (strcmp(cmd, "UPDATE") == 0) return cmd_update(rt, conn, argv, n, out, outlen);
        return cmd_delete(rt, conn, argv, n, out, outlen);
    }

    snprintf(out, outlen, CODE_ERR_UNKNOWN_CMD " %s\n", cmd);
    return NETD_CMD_OK;
}
