#include "netd/storage.h"
#include "netd/common.h"
#include "netd/auth.h"
#include "netd/util.h"

#include <pthread.h>
#include <sqlite3.h>
#include <stdlib.h>
#include <string.h>

int netd_storage_seed(netd_storage_t *s)
{
    if (s == NULL) {
        return -1;
    }
    bool already = false;
    if (netd_storage_seeded(s, &already) != 0) {
        return -1;
    }
    if (already) {
        return 0;
    }

    struct {
        const char *name;
        const char *role;
    } principals[] = {
        {"client-alpha", NETD_ROLE_CLIENT},
        {"monitoring-01", NETD_ROLE_MONITORING},
        {"admin-root", NETD_ROLE_ADMINISTRATOR}
    };
    int64_t principal_ids[3] = {0, 0, 0};

    pthread_mutex_lock(&s->lock);
    char *err = NULL;
    if (sqlite3_exec(s->db, "BEGIN IMMEDIATE;", NULL, NULL, &err) != SQLITE_OK) {
        if (err != NULL) {
            sqlite3_free(err);
        }
        pthread_mutex_unlock(&s->lock);
        return -1;
    }

    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(s->db,
                                "INSERT INTO principals(name, role, created_at) VALUES(?, ?, ?)",
                                -1, &stmt, NULL);
    int result = 0;
    if (rc != SQLITE_OK) {
        sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
        pthread_mutex_unlock(&s->lock);
        return -1;
    }
    for (int i = 0; i < 3; i++) {
        sqlite3_reset(stmt);
        sqlite3_clear_bindings(stmt);
        sqlite3_bind_text(stmt, 1, principals[i].name, -1, SQLITE_TRANSIENT);
        sqlite3_bind_text(stmt, 2, principals[i].role, -1, SQLITE_TRANSIENT);
        sqlite3_bind_int64(stmt, 3, (int64_t)NETD_SEED_EPOCH);
        if (sqlite3_step(stmt) != SQLITE_DONE) {
            result = -1;
            break;
        }
        principal_ids[i] = sqlite3_last_insert_rowid(s->db);
    }
    sqlite3_finalize(stmt);
    if (result != 0) {
        sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
        pthread_mutex_unlock(&s->lock);
        return -1;
    }

    struct {
        const char *plaintext;
        const char *role;
        const char *status;
    } tokens[] = {
        {"client-token-001", NETD_ROLE_CLIENT, NETD_TOKEN_STATUS_ACTIVE},
        {"monitor-token-001", NETD_ROLE_MONITORING, NETD_TOKEN_STATUS_ACTIVE},
        {"admin-token-001", NETD_ROLE_ADMINISTRATOR, NETD_TOKEN_STATUS_ACTIVE}
    };
    rc = sqlite3_prepare_v2(s->db,
                            "INSERT INTO access_tokens(principal_id, token_hash, "
                            "  token_prefix, role, status, expires_at, created_at) "
                            "VALUES(?, ?, ?, ?, ?, NULL, ?)",
                            -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
        pthread_mutex_unlock(&s->lock);
        return -1;
    }
    char hash_hex[65];
    char prefix_buf[16];
    for (int i = 0; i < 3; i++) {
        netd_auth_t *auth = netd_auth_create();
        if (netd_auth_hash_token(auth, tokens[i].plaintext, hash_hex,
                                 sizeof(hash_hex)) != 0) {
            netd_auth_destroy(auth);
            sqlite3_finalize(stmt);
            sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
            pthread_mutex_unlock(&s->lock);
            return -1;
        }
        netd_auth_destroy(auth);
        size_t plen = strlen(tokens[i].plaintext);
        size_t copy_len = plen < 6 ? plen : 6;
        memcpy(prefix_buf, tokens[i].plaintext, copy_len);
        prefix_buf[copy_len] = '\0';
        sqlite3_reset(stmt);
        sqlite3_clear_bindings(stmt);
        sqlite3_bind_int64(stmt, 1, principal_ids[i]);
        sqlite3_bind_text(stmt, 2, hash_hex, -1, SQLITE_TRANSIENT);
        sqlite3_bind_text(stmt, 3, prefix_buf, -1, SQLITE_TRANSIENT);
        sqlite3_bind_text(stmt, 4, tokens[i].role, -1, SQLITE_TRANSIENT);
        sqlite3_bind_text(stmt, 5, tokens[i].status, -1, SQLITE_TRANSIENT);
        sqlite3_bind_int64(stmt, 6, (int64_t)NETD_SEED_EPOCH);
        if (sqlite3_step(stmt) != SQLITE_DONE) {
            result = -1;
            break;
        }
    }
    sqlite3_finalize(stmt);
    if (result != 0) {
        sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
        pthread_mutex_unlock(&s->lock);
        return -1;
    }

    struct {
        int principal_idx;
        const char *namespace;
        const char *permission;
    } grants[] = {
        {0, "app", NETD_PERM_WRITE},
        {0, "public", NETD_PERM_READ},
        {1, "metrics", NETD_PERM_ADMIN},
        {2, "*", NETD_PERM_ADMIN}
    };
    rc = sqlite3_prepare_v2(s->db,
                            "INSERT INTO namespace_grants(principal_id, namespace, permission) "
                            "VALUES(?, ?, ?)",
                            -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
        pthread_mutex_unlock(&s->lock);
        return -1;
    }
    for (size_t i = 0; i < sizeof(grants) / sizeof(grants[0]); i++) {
        sqlite3_reset(stmt);
        sqlite3_clear_bindings(stmt);
        sqlite3_bind_int64(stmt, 1, principal_ids[grants[i].principal_idx]);
        sqlite3_bind_text(stmt, 2, grants[i].namespace, -1, SQLITE_TRANSIENT);
        sqlite3_bind_text(stmt, 3, grants[i].permission, -1, SQLITE_TRANSIENT);
        if (sqlite3_step(stmt) != SQLITE_DONE) {
            result = -1;
            break;
        }
    }
    sqlite3_finalize(stmt);
    if (result != 0) {
        sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
        pthread_mutex_unlock(&s->lock);
        return -1;
    }

    struct {
        const char *namespace;
        const char *key;
        const char *value;
        int64_t ttl;
    } records[] = {
        {"app", "greeting", "Hello, World!", 0},
        {"app", "config", "k=v;ttl=60", 0},
        {"public", "info", "Welcome", 0},
        {"metrics", "today", "100", 60},
        {"metrics", "yesterday", "80", 0}
    };
    rc = sqlite3_prepare_v2(s->db,
                            "INSERT INTO records(namespace, key, value, version, "
                            "  expires_at, created_at, updated_at) "
                            "VALUES(?, ?, ?, 1, ?, ?, ?)",
                            -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
        pthread_mutex_unlock(&s->lock);
        return -1;
    }
    int64_t now = (int64_t)NETD_SEED_EPOCH;
    for (size_t i = 0; i < sizeof(records) / sizeof(records[0]); i++) {
        sqlite3_reset(stmt);
        sqlite3_clear_bindings(stmt);
        sqlite3_bind_text(stmt, 1, records[i].namespace, -1, SQLITE_TRANSIENT);
        sqlite3_bind_text(stmt, 2, records[i].key, -1, SQLITE_TRANSIENT);
        sqlite3_bind_text(stmt, 3, records[i].value, -1, SQLITE_TRANSIENT);
        if (records[i].ttl > 0) {
            sqlite3_bind_int64(stmt, 4, now + records[i].ttl);
        } else {
            sqlite3_bind_null(stmt, 4);
        }
        sqlite3_bind_int64(stmt, 5, now);
        sqlite3_bind_int64(stmt, 6, now);
        if (sqlite3_step(stmt) != SQLITE_DONE) {
            result = -1;
            break;
        }
    }
    sqlite3_finalize(stmt);
    if (result != 0) {
        sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
        pthread_mutex_unlock(&s->lock);
        return -1;
    }

    if (sqlite3_exec(s->db, "COMMIT;", NULL, NULL, &err) != SQLITE_OK) {
        if (err != NULL) {
            sqlite3_free(err);
        }
        sqlite3_exec(s->db, "ROLLBACK;", NULL, NULL, NULL);
        pthread_mutex_unlock(&s->lock);
        return -1;
    }
    pthread_mutex_unlock(&s->lock);
    return result;
}