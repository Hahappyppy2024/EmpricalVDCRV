#include "netd/storage.h"
#include "netd/common.h"
#include "netd/util.h"

#include <errno.h>
#include <pthread.h>
#include <sqlite3.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define NETD_SCHEMA_VERSION 1

static const char *SCHEMA_STATEMENTS[] = {
    "PRAGMA foreign_keys = ON;",
    "PRAGMA journal_mode = WAL;",
    "PRAGMA synchronous = NORMAL;",
    "PRAGMA busy_timeout = 2000;",
    "CREATE TABLE IF NOT EXISTS schema_version ("
    "  version INTEGER PRIMARY KEY,"
    "  applied_at INTEGER NOT NULL);",
    "CREATE TABLE IF NOT EXISTS principals ("
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"
    "  name TEXT NOT NULL UNIQUE,"
    "  role TEXT NOT NULL CHECK (role IN ('client','monitoring','administrator')),"
    "  created_at INTEGER NOT NULL);",
    "CREATE TABLE IF NOT EXISTS access_tokens ("
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"
    "  principal_id INTEGER NOT NULL REFERENCES principals(id),"
    "  token_hash TEXT NOT NULL UNIQUE,"
    "  token_prefix TEXT NOT NULL,"
    "  role TEXT NOT NULL,"
    "  status TEXT NOT NULL DEFAULT 'active' "
    "    CHECK (status IN ('active','revoked','expired')),"
    "  expires_at INTEGER,"
    "  created_at INTEGER NOT NULL);",
    "CREATE TABLE IF NOT EXISTS namespace_grants ("
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"
    "  principal_id INTEGER NOT NULL REFERENCES principals(id),"
    "  namespace TEXT NOT NULL,"
    "  permission TEXT NOT NULL CHECK (permission IN ('read','write','admin')),"
    "  UNIQUE (principal_id, namespace));",
    "CREATE TABLE IF NOT EXISTS records ("
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"
    "  namespace TEXT NOT NULL,"
    "  key TEXT NOT NULL,"
    "  value BLOB NOT NULL,"
    "  version INTEGER NOT NULL DEFAULT 1,"
    "  expires_at INTEGER,"
    "  created_at INTEGER NOT NULL,"
    "  updated_at INTEGER NOT NULL,"
    "  UNIQUE (namespace, key));",
    "CREATE INDEX IF NOT EXISTS idx_records_ns_key ON records(namespace, key);",
    "CREATE INDEX IF NOT EXISTS idx_records_expires ON records(expires_at);",
    "CREATE TABLE IF NOT EXISTS service_instances ("
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"
    "  started_at INTEGER NOT NULL,"
    "  ended_at INTEGER,"
    "  pid INTEGER NOT NULL,"
    "  generation INTEGER NOT NULL DEFAULT 1);",
    "CREATE TABLE IF NOT EXISTS configuration_generations ("
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"
    "  generation INTEGER NOT NULL UNIQUE,"
    "  applied_at INTEGER NOT NULL,"
    "  source TEXT NOT NULL);",
    "CREATE TABLE IF NOT EXISTS audit_events ("
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"
    "  ts INTEGER NOT NULL,"
    "  event_type TEXT NOT NULL,"
    "  outcome TEXT NOT NULL,"
    "  connection_id TEXT,"
    "  principal_id INTEGER,"
    "  namespace TEXT,"
    "  key TEXT,"
    "  details TEXT);",
    "CREATE INDEX IF NOT EXISTS idx_audit_ts ON audit_events(ts);",
    "CREATE TABLE IF NOT EXISTS service_metrics ("
    "  name TEXT PRIMARY KEY,"
    "  value INTEGER NOT NULL DEFAULT 0,"
    "  updated_at INTEGER NOT NULL);"
};

static int exec_simple(sqlite3 *db, const char *sql)
{
    char *err = NULL;
    int rc = sqlite3_exec(db, sql, NULL, NULL, &err);
    if (rc != SQLITE_OK) {
        if (err != NULL) {
            sqlite3_free(err);
        }
        return -1;
    }
    return 0;
}

netd_storage_t *netd_storage_open(const char *path, int busy_timeout_ms)
{
    if (path == NULL || path[0] == '\0') {
        return NULL;
    }
    char dir[512];
    if (netd_path_dirname(dir, sizeof(dir), path) != 0) {
        return NULL;
    }
    netd_ensure_directory(dir);

    netd_storage_t *s = calloc(1, sizeof(*s));
    if (s == NULL) {
        return NULL;
    }
    netd_str_copy(s->path, sizeof(s->path), path);
    pthread_mutex_init(&s->lock, NULL);
    s->busy_timeout_ms = busy_timeout_ms > 0 ? busy_timeout_ms : 2000;

    int rc = sqlite3_open_v2(path, &s->db,
                             SQLITE_OPEN_READWRITE | SQLITE_OPEN_CREATE |
                             SQLITE_OPEN_FULLMUTEX,
                             NULL);
    if (rc != SQLITE_OK) {
        if (s->db != NULL) {
            sqlite3_close(s->db);
        }
        free(s);
        return NULL;
    }
    sqlite3_busy_timeout(s->db, s->busy_timeout_ms);
    return s;
}

void netd_storage_close(netd_storage_t *s)
{
    if (s == NULL) {
        return;
    }
    if (s->db != NULL) {
        sqlite3_close(s->db);
        s->db = NULL;
    }
    pthread_mutex_destroy(&s->lock);
    free(s);
}

int netd_storage_init_schema(netd_storage_t *s)
{
    if (s == NULL || s->db == NULL) {
        return -1;
    }
    pthread_mutex_lock(&s->lock);
    int rc = 0;
    for (size_t i = 0; i < sizeof(SCHEMA_STATEMENTS) / sizeof(SCHEMA_STATEMENTS[0]); i++) {
        if (exec_simple(s->db, SCHEMA_STATEMENTS[i]) != 0) {
            rc = -1;
            break;
        }
    }
    if (rc == 0) {
        sqlite3_stmt *stmt = NULL;
        if (sqlite3_prepare_v2(s->db,
                               "INSERT OR IGNORE INTO schema_version(version, applied_at) VALUES(?, ?)",
                               -1, &stmt, NULL) == SQLITE_OK) {
            sqlite3_bind_int64(stmt, 1, NETD_SCHEMA_VERSION);
            sqlite3_bind_int64(stmt, 2, netd_now_seconds());
            sqlite3_step(stmt);
            sqlite3_finalize(stmt);
        }
        exec_simple(s->db, "PRAGMA wal_checkpoint(PASSIVE);");
    }
    pthread_mutex_unlock(&s->lock);
    return rc;
}

int netd_storage_seeded(netd_storage_t *s, bool *out_seeded)
{
    if (s == NULL || out_seeded == NULL) {
        return -1;
    }
    pthread_mutex_lock(&s->lock);
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(s->db,
                                "SELECT COUNT(*) FROM principals",
                                -1, &stmt, NULL);
    int count = 0;
    if (rc == SQLITE_OK) {
        if (sqlite3_step(stmt) == SQLITE_ROW) {
            count = sqlite3_column_int(stmt, 0);
        }
        sqlite3_finalize(stmt);
    }
    pthread_mutex_unlock(&s->lock);
    *out_seeded = count > 0;
    return 0;
}

int netd_storage_register_instance(netd_storage_t *s, int pid, int64_t *out_id)
{
    if (s == NULL) {
        return -1;
    }
    pthread_mutex_lock(&s->lock);
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(s->db,
                                "INSERT INTO service_instances(started_at, pid, generation) VALUES(?, ?, ?)",
                                -1, &stmt, NULL);
    int64_t id = 0;
    if (rc == SQLITE_OK) {
        sqlite3_bind_int64(stmt, 1, netd_now_seconds());
        sqlite3_bind_int(stmt, 2, pid);
        sqlite3_bind_int64(stmt, 3, 1);
        if (sqlite3_step(stmt) == SQLITE_DONE) {
            id = sqlite3_last_insert_rowid(s->db);
        }
        sqlite3_finalize(stmt);
    }
    pthread_mutex_unlock(&s->lock);
    if (out_id != NULL) {
        *out_id = id;
    }
    return rc == SQLITE_OK ? 0 : -1;
}

static int bind_text_copy(sqlite3_stmt *stmt, int idx, const char *s)
{
    if (s == NULL) {
        return sqlite3_bind_null(stmt, idx);
    }
    return sqlite3_bind_text(stmt, idx, s, -1, SQLITE_TRANSIENT);
}

netd_storage_status_t netd_storage_find_token(netd_storage_t *s,
                                              const char *token_hash,
                                              netd_access_token_t *out_token,
                                              netd_principal_t *out_principal)
{
    if (s == NULL || token_hash == NULL || out_token == NULL || out_principal == NULL) {
        return NETD_STORAGE_ERR_INTERNAL;
    }
    pthread_mutex_lock(&s->lock);
    sqlite3_stmt *stmt = NULL;
    netd_storage_status_t status = NETD_STORAGE_ERR_NOT_FOUND;
    int rc = sqlite3_prepare_v2(s->db,
                                "SELECT t.id, t.principal_id, t.role, t.status, "
                                "  COALESCE(t.expires_at, 0), t.token_prefix, "
                                "  p.id, p.name, p.role "
                                "FROM access_tokens t "
                                "JOIN principals p ON p.id = t.principal_id "
                                "WHERE t.token_hash = ?",
                                -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_INTERNAL;
    }
    sqlite3_bind_text(stmt, 1, token_hash, -1, SQLITE_TRANSIENT);
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        memset(out_token, 0, sizeof(*out_token));
        memset(out_principal, 0, sizeof(*out_principal));
        out_token->id = sqlite3_column_int64(stmt, 0);
        out_token->principal_id = sqlite3_column_int64(stmt, 1);
        const unsigned char *role = sqlite3_column_text(stmt, 2);
        const unsigned char *status_col = sqlite3_column_text(stmt, 3);
        out_token->expires_at = sqlite3_column_int64(stmt, 4);
        const unsigned char *prefix = sqlite3_column_text(stmt, 5);
        out_principal->id = sqlite3_column_int64(stmt, 6);
        const unsigned char *pname = sqlite3_column_text(stmt, 7);
        const unsigned char *prole = sqlite3_column_text(stmt, 8);
        if (role != NULL) {
            netd_str_copy(out_token->role, sizeof(out_token->role),
                          (const char *)role);
        }
        if (status_col != NULL) {
            netd_str_copy(out_token->status, sizeof(out_token->status),
                          (const char *)status_col);
        }
        if (prefix != NULL) {
            netd_str_copy(out_token->token_prefix, sizeof(out_token->token_prefix),
                          (const char *)prefix);
        }
        if (pname != NULL) {
            netd_str_copy(out_principal->name, sizeof(out_principal->name),
                          (const char *)pname);
        }
        if (prole != NULL) {
            netd_str_copy(out_principal->role, sizeof(out_principal->role),
                          (const char *)prole);
        }
        status = NETD_STORAGE_OK;
    } else {
        status = NETD_STORAGE_ERR_NOT_FOUND;
    }
    sqlite3_finalize(stmt);
    pthread_mutex_unlock(&s->lock);
    return status;
}

netd_storage_status_t netd_storage_check_namespace_access(netd_storage_t *s,
                                                          int64_t principal_id,
                                                          const char *role,
                                                          const char *namespace,
                                                          const char *required_permission,
                                                          bool *out_allowed)
{
    if (s == NULL || role == NULL || namespace == NULL || required_permission == NULL ||
        out_allowed == NULL) {
        return NETD_STORAGE_ERR_INTERNAL;
    }
    *out_allowed = false;
    if (strcmp(role, NETD_ROLE_ADMINISTRATOR) == 0) {
        *out_allowed = true;
        return NETD_STORAGE_OK;
    }
    pthread_mutex_lock(&s->lock);
    sqlite3_stmt *stmt = NULL;
    bool allowed = false;
    int rc = sqlite3_prepare_v2(s->db,
                                "SELECT permission FROM namespace_grants "
                                "WHERE principal_id = ? "
                                "  AND (namespace = ? OR namespace = '*')",
                                -1, &stmt, NULL);
    if (rc == SQLITE_OK) {
        sqlite3_bind_int64(stmt, 1, principal_id);
        sqlite3_bind_text(stmt, 2, namespace, -1, SQLITE_TRANSIENT);
        while (sqlite3_step(stmt) == SQLITE_ROW) {
            const unsigned char *perm = sqlite3_column_text(stmt, 0);
            if (perm == NULL) {
                continue;
            }
            if (strcmp(required_permission, NETD_PERM_READ) == 0 &&
                (strcmp((const char *)perm, NETD_PERM_READ) == 0 ||
                 strcmp((const char *)perm, NETD_PERM_WRITE) == 0 ||
                 strcmp((const char *)perm, NETD_PERM_ADMIN) == 0)) {
                allowed = true;
                break;
            }
            if (strcmp(required_permission, NETD_PERM_WRITE) == 0 &&
                (strcmp((const char *)perm, NETD_PERM_WRITE) == 0 ||
                 strcmp((const char *)perm, NETD_PERM_ADMIN) == 0)) {
                allowed = true;
                break;
            }
        }
        sqlite3_finalize(stmt);
    }
    pthread_mutex_unlock(&s->lock);
    *out_allowed = allowed;
    return NETD_STORAGE_OK;
}

netd_storage_status_t netd_storage_record_get(netd_storage_t *s,
                                              const char *namespace,
                                              const char *key,
                                              int64_t now,
                                              netd_record_t *out,
                                              bool *out_expired)
{
    if (s == NULL || namespace == NULL || key == NULL || out == NULL) {
        return NETD_STORAGE_ERR_INTERNAL;
    }
    if (out_expired != NULL) {
        *out_expired = false;
    }
    memset(out, 0, sizeof(*out));
    pthread_mutex_lock(&s->lock);
    sqlite3_stmt *stmt = NULL;
    netd_storage_status_t status = NETD_STORAGE_ERR_NOT_FOUND;
    int rc = sqlite3_prepare_v2(s->db,
                                "SELECT id, namespace, key, value, version, "
                                "  COALESCE(expires_at, 0), created_at, updated_at "
                                "FROM records WHERE namespace = ? AND key = ?",
                                -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_INTERNAL;
    }
    sqlite3_bind_text(stmt, 1, namespace, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(stmt, 2, key, -1, SQLITE_TRANSIENT);
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        out->id = sqlite3_column_int64(stmt, 0);
        const unsigned char *ns = sqlite3_column_text(stmt, 1);
        const unsigned char *k = sqlite3_column_text(stmt, 2);
        const void *val = sqlite3_column_value(stmt, 3);
        int val_len = sqlite3_column_bytes(stmt, 3);
        out->version = sqlite3_column_int64(stmt, 4);
        out->expires_at = sqlite3_column_int64(stmt, 5);
        out->created_at = sqlite3_column_int64(stmt, 6);
        out->updated_at = sqlite3_column_int64(stmt, 7);
        if (ns != NULL) {
            netd_str_copy(out->namespace, sizeof(out->namespace),
                          (const char *)ns);
        }
        if (k != NULL) {
            netd_str_copy(out->key, sizeof(out->key), (const char *)k);
        }
        if (val != NULL && val_len > 0) {
            out->value = malloc((size_t)val_len);
            if (out->value == NULL) {
                sqlite3_finalize(stmt);
                pthread_mutex_unlock(&s->lock);
                return NETD_STORAGE_ERR_INTERNAL;
            }
            memcpy(out->value, sqlite3_column_blob(stmt, 3), (size_t)val_len);
            out->value_len = (size_t)val_len;
        }
        if (out->expires_at > 0 && now >= out->expires_at) {
            status = NETD_STORAGE_ERR_NOT_FOUND;
            if (out_expired != NULL) {
                *out_expired = true;
            }
        } else {
            status = NETD_STORAGE_OK;
        }
    } else {
        status = NETD_STORAGE_ERR_NOT_FOUND;
    }
    sqlite3_finalize(stmt);
    pthread_mutex_unlock(&s->lock);
    if (status != NETD_STORAGE_OK) {
        free(out->value);
        out->value = NULL;
        out->value_len = 0;
    }
    return status;
}

netd_storage_status_t netd_storage_record_put(netd_storage_t *s,
                                              const char *namespace,
                                              const char *key,
                                              const unsigned char *value,
                                              size_t value_len,
                                              int64_t ttl_seconds,
                                              int64_t now,
                                              int64_t *out_version,
                                              int64_t *out_expires_at)
{
    if (s == NULL || namespace == NULL || key == NULL || value == NULL) {
        return NETD_STORAGE_ERR_INTERNAL;
    }
    pthread_mutex_lock(&s->lock);
    sqlite3_stmt *check = NULL;
    int rc = sqlite3_prepare_v2(s->db,
                                "SELECT 1 FROM records WHERE namespace = ? AND key = ?",
                                -1, &check, NULL);
    if (rc != SQLITE_OK) {
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_INTERNAL;
    }
    sqlite3_bind_text(check, 1, namespace, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(check, 2, key, -1, SQLITE_TRANSIENT);
    int step = sqlite3_step(check);
    sqlite3_finalize(check);
    if (step == SQLITE_ROW) {
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_EXISTS;
    }

    char *err = NULL;
    if (sqlite3_exec(s->db, "BEGIN IMMEDIATE;", NULL, NULL, &err) != SQLITE_OK) {
        if (err != NULL) sqlite3_free(err);
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_INTERNAL;
    }
    int64_t expires_at = (ttl_seconds > 0) ? now + ttl_seconds : 0;
    sqlite3_stmt *ins = NULL;
    rc = sqlite3_prepare_v2(s->db,
                            "INSERT INTO records(namespace, key, value, version, "
                            "  expires_at, created_at, updated_at) "
                            "VALUES(?, ?, ?, 1, ?, ?, ?)",
                            -1, &ins, NULL);
    netd_storage_status_t result = NETD_STORAGE_OK;
    if (rc != SQLITE_OK) {
        result = NETD_STORAGE_ERR_INTERNAL;
        goto fail;
    }
    bind_text_copy(ins, 1, namespace);
    bind_text_copy(ins, 2, key);
    sqlite3_bind_blob(ins, 3, value, (int)value_len, SQLITE_TRANSIENT);
    if (expires_at > 0) {
        sqlite3_bind_int64(ins, 4, expires_at);
    } else {
        sqlite3_bind_null(ins, 4);
    }
    sqlite3_bind_int64(ins, 5, now);
    sqlite3_bind_int64(ins, 6, now);
    if (sqlite3_step(ins) != SQLITE_DONE) {
        sqlite3_finalize(ins);
        result = (sqlite3_errcode(s->db) == SQLITE_CONSTRAINT)
                     ? NETD_STORAGE_ERR_EXISTS : NETD_STORAGE_ERR_INTERNAL;
        goto fail;
    }
    sqlite3_finalize(ins);
    if (sqlite3_exec(s->db, "COMMIT;", NULL, NULL, &err) != SQLITE_OK) {
        if (err != NULL) sqlite3_free(err);
        result = NETD_STORAGE_ERR_INTERNAL;
        goto fail_after_commit_check;
    }
    pthread_mutex_unlock(&s->lock);
    if (out_version != NULL) {
        *out_version = 1;
    }
    if (out_expires_at != NULL) {
        *out_expires_at = expires_at;
    }
    return NETD_STORAGE_OK;

fail:
    sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
fail_after_commit_check:
    pthread_mutex_unlock(&s->lock);
    return result;
}

netd_storage_status_t netd_storage_record_update(netd_storage_t *s,
                                                 const char *namespace,
                                                 const char *key,
                                                 int64_t expected_version,
                                                 const unsigned char *value,
                                                 size_t value_len,
                                                 int64_t ttl_seconds,
                                                 int64_t now,
                                                 int64_t *out_version,
                                                 int64_t *out_expires_at)
{
    if (s == NULL || namespace == NULL || key == NULL || value == NULL) {
        return NETD_STORAGE_ERR_INTERNAL;
    }
    pthread_mutex_lock(&s->lock);
    char *err = NULL;
    if (sqlite3_exec(s->db, "BEGIN IMMEDIATE;", NULL, NULL, &err) != SQLITE_OK) {
        if (err != NULL) sqlite3_free(err);
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_INTERNAL;
    }
    sqlite3_stmt *sel = NULL;
    netd_storage_status_t result = NETD_STORAGE_OK;
    int rc = sqlite3_prepare_v2(s->db,
                                "SELECT version, COALESCE(expires_at, 0) FROM records "
                                "WHERE namespace = ? AND key = ?",
                                -1, &sel, NULL);
    if (rc != SQLITE_OK) {
        result = NETD_STORAGE_ERR_INTERNAL;
        goto rollback;
    }
    sqlite3_bind_text(sel, 1, namespace, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(sel, 2, key, -1, SQLITE_TRANSIENT);
    int step = sqlite3_step(sel);
    if (step != SQLITE_ROW) {
        sqlite3_finalize(sel);
        result = NETD_STORAGE_ERR_NOT_FOUND;
        goto rollback;
    }
    int64_t current_version = sqlite3_column_int64(sel, 0);
    int64_t current_expires = sqlite3_column_int64(sel, 1);
    sqlite3_finalize(sel);
    if (current_expires > 0 && now >= current_expires) {
        result = NETD_STORAGE_ERR_NOT_FOUND;
        goto rollback;
    }
    if (current_version != expected_version) {
        result = NETD_STORAGE_ERR_CONFLICT;
        goto rollback;
    }
    int64_t new_expires = (ttl_seconds > 0) ? now + ttl_seconds : 0;
    sqlite3_stmt *upd = NULL;
    rc = sqlite3_prepare_v2(s->db,
                            "UPDATE records SET value = ?, version = version + 1, "
                            "  expires_at = ?, updated_at = ? "
                            "WHERE namespace = ? AND key = ? AND version = ?",
                            -1, &upd, NULL);
    if (rc != SQLITE_OK) {
        result = NETD_STORAGE_ERR_INTERNAL;
        goto rollback;
    }
    sqlite3_bind_blob(upd, 1, value, (int)value_len, SQLITE_TRANSIENT);
    if (new_expires > 0) {
        sqlite3_bind_int64(upd, 2, new_expires);
    } else {
        sqlite3_bind_null(upd, 2);
    }
    sqlite3_bind_int64(upd, 3, now);
    bind_text_copy(upd, 4, namespace);
    bind_text_copy(upd, 5, key);
    sqlite3_bind_int64(upd, 6, expected_version);
    int s2 = sqlite3_step(upd);
    sqlite3_finalize(upd);
    if (s2 != SQLITE_DONE) {
        result = NETD_STORAGE_ERR_CONFLICT;
        goto rollback;
    }
    if (sqlite3_changes(s->db) != 1) {
        result = NETD_STORAGE_ERR_CONFLICT;
        goto rollback;
    }
    if (sqlite3_exec(s->db, "COMMIT;", NULL, NULL, &err) != SQLITE_OK) {
        if (err != NULL) sqlite3_free(err);
        result = NETD_STORAGE_ERR_INTERNAL;
        goto rollback_after_commit;
    }
    pthread_mutex_unlock(&s->lock);
    if (out_version != NULL) {
        *out_version = expected_version + 1;
    }
    if (out_expires_at != NULL) {
        *out_expires_at = new_expires;
    }
    return NETD_STORAGE_OK;

rollback:
    sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
rollback_after_commit:
    pthread_mutex_unlock(&s->lock);
    return result;
}

netd_storage_status_t netd_storage_record_delete(netd_storage_t *s,
                                                 const char *namespace,
                                                 const char *key,
                                                 int64_t expected_version,
                                                 int64_t now)
{
    if (s == NULL || namespace == NULL || key == NULL) {
        return NETD_STORAGE_ERR_INTERNAL;
    }
    pthread_mutex_lock(&s->lock);
    sqlite3_stmt *sel = NULL;
    int rc = sqlite3_prepare_v2(s->db,
                                "SELECT version, COALESCE(expires_at, 0) FROM records "
                                "WHERE namespace = ? AND key = ?",
                                -1, &sel, NULL);
    if (rc != SQLITE_OK) {
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_INTERNAL;
    }
    sqlite3_bind_text(sel, 1, namespace, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(sel, 2, key, -1, SQLITE_TRANSIENT);
    int step = sqlite3_step(sel);
    if (step != SQLITE_ROW) {
        sqlite3_finalize(sel);
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_NOT_FOUND;
    }
    int64_t version = sqlite3_column_int64(sel, 0);
    int64_t expires_at = sqlite3_column_int64(sel, 1);
    sqlite3_finalize(sel);
    if (expires_at > 0 && now >= expires_at) {
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_NOT_FOUND;
    }
    if (version != expected_version) {
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_CONFLICT;
    }
    sqlite3_stmt *del = NULL;
    rc = sqlite3_prepare_v2(s->db,
                            "DELETE FROM records WHERE namespace = ? AND key = ? AND version = ?",
                            -1, &del, NULL);
    if (rc != SQLITE_OK) {
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_INTERNAL;
    }
    sqlite3_bind_text(del, 1, namespace, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(del, 2, key, -1, SQLITE_TRANSIENT);
    sqlite3_bind_int64(del, 3, expected_version);
    sqlite3_step(del);
    sqlite3_finalize(del);
    int changes = sqlite3_changes(s->db);
    pthread_mutex_unlock(&s->lock);
    if (changes != 1) {
        return NETD_STORAGE_ERR_CONFLICT;
    }
    return NETD_STORAGE_OK;
}

netd_storage_status_t netd_storage_record_list(netd_storage_t *s,
                                               const char *namespace,
                                               const char *prefix,
                                               const char *after_key,
                                               int limit,
                                               int max_limit,
                                               int64_t now,
                                               netd_record_list_entry_t *out,
                                               int *out_count,
                                               bool *out_truncated)
{
    if (s == NULL || namespace == NULL || prefix == NULL || after_key == NULL) {
        return NETD_STORAGE_ERR_INTERNAL;
    }
    if (limit <= 0 || limit > max_limit) {
        return NETD_STORAGE_ERR_RANGE;
    }
    if (out_count != NULL) {
        *out_count = 0;
    }
    if (out_truncated != NULL) {
        *out_truncated = false;
    }

    bool has_after = strcmp(after_key, "-") != 0;
    char prefix_pattern[256];
    (void)has_after;
    int plen = snprintf(prefix_pattern, sizeof(prefix_pattern), "%s%%", prefix);
    if (plen < 0 || (size_t)plen >= sizeof(prefix_pattern)) {
        return NETD_STORAGE_ERR_RANGE;
    }

    pthread_mutex_lock(&s->lock);
    sqlite3_stmt *stmt = NULL;
    const char *sql =
        "SELECT namespace, key, version, COALESCE(expires_at, 0) FROM records "
        "WHERE namespace = ? AND key LIKE ? "
        "  AND (? = '-' OR key > ?) "
        "  AND (expires_at IS NULL OR expires_at = 0 OR expires_at > ?) "
        "ORDER BY key ASC LIMIT ?";
    int rc = sqlite3_prepare_v2(s->db, sql, -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_INTERNAL;
    }
    sqlite3_bind_text(stmt, 1, namespace, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(stmt, 2, prefix_pattern, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(stmt, 3, after_key, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(stmt, 4, after_key, -1, SQLITE_TRANSIENT);
    sqlite3_bind_int64(stmt, 5, now);
    sqlite3_bind_int(stmt, 6, limit);
    int count = 0;
    while (sqlite3_step(stmt) == SQLITE_ROW && count < limit) {
        const unsigned char *ns = sqlite3_column_text(stmt, 0);
        const unsigned char *k = sqlite3_column_text(stmt, 1);
        if (out != NULL) {
            netd_str_copy(out[count].namespace, sizeof(out[count].namespace),
                          ns ? (const char *)ns : "");
            netd_str_copy(out[count].key, sizeof(out[count].key),
                          k ? (const char *)k : "");
            out[count].version = sqlite3_column_int64(stmt, 2);
            out[count].expires_at = sqlite3_column_int64(stmt, 3);
        }
        count++;
    }
    bool truncated = (count == limit);
    sqlite3_finalize(stmt);
    pthread_mutex_unlock(&s->lock);
    if (out_count != NULL) {
        *out_count = count;
    }
    if (out_truncated != NULL) {
        *out_truncated = truncated;
    }
    return NETD_STORAGE_OK;
}

netd_storage_status_t netd_storage_expiry_run(netd_storage_t *s,
                                              int batch_size,
                                              int64_t now,
                                              int *out_deleted)
{
    if (s == NULL || batch_size <= 0) {
        return NETD_STORAGE_ERR_INTERNAL;
    }
    int deleted = 0;
    pthread_mutex_lock(&s->lock);
    char *err = NULL;
    if (sqlite3_exec(s->db, "BEGIN IMMEDIATE;", NULL, NULL, &err) != SQLITE_OK) {
        if (err != NULL) sqlite3_free(err);
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_INTERNAL;
    }
    sqlite3_stmt *sel = NULL;
    int rc = sqlite3_prepare_v2(s->db,
                                "SELECT namespace, key FROM records "
                                "WHERE expires_at IS NOT NULL AND expires_at > 0 "
                                "  AND expires_at <= ? "
                                "ORDER BY expires_at ASC, key ASC LIMIT ?",
                                -1, &sel, NULL);
    if (rc != SQLITE_OK) {
        sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_INTERNAL;
    }
    sqlite3_bind_int64(sel, 1, now);
    sqlite3_bind_int(sel, 2, batch_size);

    typedef struct { char ns[64]; char k[128]; } expired_t;
    expired_t *expired = calloc((size_t)batch_size, sizeof(expired_t));
    int n = 0;
    if (expired != NULL) {
        while (sqlite3_step(sel) == SQLITE_ROW && n < batch_size) {
            const unsigned char *ns = sqlite3_column_text(sel, 0);
            const unsigned char *k = sqlite3_column_text(sel, 1);
            if (ns != NULL) {
                netd_str_copy(expired[n].ns, sizeof(expired[n].ns),
                              (const char *)ns);
            }
            if (k != NULL) {
                netd_str_copy(expired[n].k, sizeof(expired[n].k),
                              (const char *)k);
            }
            n++;
        }
    }
    sqlite3_finalize(sel);

    sqlite3_stmt *del = NULL;
    rc = sqlite3_prepare_v2(s->db,
                            "DELETE FROM records WHERE namespace = ? AND key = ?",
                            -1, &del, NULL);
    if (rc != SQLITE_OK) {
        sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
        free(expired);
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_INTERNAL;
    }
    for (int i = 0; i < n; i++) {
        sqlite3_reset(del);
        sqlite3_clear_bindings(del);
        sqlite3_bind_text(del, 1, expired[i].ns, -1, SQLITE_TRANSIENT);
        sqlite3_bind_text(del, 2, expired[i].k, -1, SQLITE_TRANSIENT);
        sqlite3_step(del);
    }
    sqlite3_finalize(del);
    deleted = n;
    free(expired);

    if (sqlite3_exec(s->db, "COMMIT;", NULL, NULL, &err) != SQLITE_OK) {
        if (err != NULL) sqlite3_free(err);
        sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_INTERNAL;
    }
    pthread_mutex_unlock(&s->lock);
    if (out_deleted != NULL) {
        *out_deleted = deleted;
    }
    return NETD_STORAGE_OK;
}

netd_storage_status_t netd_storage_health_probe(netd_storage_t *s)
{
    if (s == NULL) {
        return NETD_STORAGE_ERR_INTERNAL;
    }
    pthread_mutex_lock(&s->lock);
    sqlite3_stmt *stmt = NULL;
    netd_storage_status_t result = NETD_STORAGE_OK;
    int rc = sqlite3_prepare_v2(s->db, "SELECT 1", -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        pthread_mutex_unlock(&s->lock);
        return NETD_STORAGE_ERR_INTERNAL;
    }
    if (sqlite3_step(stmt) != SQLITE_ROW) {
        result = NETD_STORAGE_ERR_INTERNAL;
    }
    sqlite3_finalize(stmt);
    pthread_mutex_unlock(&s->lock);
    return result;
}

netd_storage_status_t netd_storage_record_config_generation(netd_storage_t *s,
                                                            uint64_t generation,
                                                            const char *source)
{
    if (s == NULL || source == NULL) {
        return NETD_STORAGE_ERR_INTERNAL;
    }
    pthread_mutex_lock(&s->lock);
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(s->db,
                                "INSERT INTO configuration_generations(generation, applied_at, source) VALUES(?, ?, ?)",
                                -1, &stmt, NULL);
    netd_storage_status_t result = NETD_STORAGE_OK;
    if (rc == SQLITE_OK) {
        sqlite3_bind_int64(stmt, 1, (int64_t)generation);
        sqlite3_bind_int64(stmt, 2, netd_now_seconds());
        sqlite3_bind_text(stmt, 3, source, -1, SQLITE_TRANSIENT);
        if (sqlite3_step(stmt) != SQLITE_DONE) {
            result = NETD_STORAGE_ERR_INTERNAL;
        }
        sqlite3_finalize(stmt);
    } else {
        result = NETD_STORAGE_ERR_INTERNAL;
    }
    pthread_mutex_unlock(&s->lock);
    return result;
}

void netd_storage_record_free(netd_record_t *r)
{
    if (r == NULL) {
        return;
    }
    free(r->value);
    r->value = NULL;
    r->value_len = 0;
}

const char *netd_storage_status_string(netd_storage_status_t s)
{
    switch (s) {
    case NETD_STORAGE_OK: return "OK";
    case NETD_STORAGE_ERR_OPEN: return "DB_OPEN";
    case NETD_STORAGE_ERR_MIGRATE: return "DB_MIGRATE";
    case NETD_STORAGE_ERR_BUSY: return "DB_BUSY";
    case NETD_STORAGE_ERR_NOT_FOUND: return "DB_NOT_FOUND";
    case NETD_STORAGE_ERR_CONFLICT: return "DB_CONFLICT";
    case NETD_STORAGE_ERR_EXISTS: return "DB_EXISTS";
    case NETD_STORAGE_ERR_INTERNAL: return "DB_INTERNAL";
    case NETD_STORAGE_ERR_INTEGRITY: return "DB_INTEGRITY";
    case NETD_STORAGE_ERR_RANGE: return "DB_RANGE";
    }
    return "DB_UNKNOWN";
}