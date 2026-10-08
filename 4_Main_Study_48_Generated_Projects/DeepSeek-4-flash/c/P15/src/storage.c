/*
 * storage.c -- SQLite persistence via the native C API.
 * Prepared statements, transactions, schema versioning, WAL mode, foreign
 * keys, and a bounded busy timeout are all configured here.
 */
#include "storage.h"

#include <errno.h>
#include <inttypes.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <unistd.h>

#include "auth.h"
#include "metrics.h"
#include "util.h"

#define STORAGE_DDL                                                        \
    "CREATE TABLE IF NOT EXISTS schema_version ("                          \
    "  version INTEGER NOT NULL PRIMARY KEY, applied_at INTEGER NOT NULL);"\
    "CREATE TABLE IF NOT EXISTS principals ("                              \
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"                              \
    "  name TEXT NOT NULL UNIQUE,"                                         \
    "  role TEXT NOT NULL CHECK(role IN ('client','monitoring','administrator'))," \
    "  created_at INTEGER NOT NULL);"                                      \
    "CREATE TABLE IF NOT EXISTS access_tokens ("                           \
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"                              \
    "  principal_id INTEGER NOT NULL REFERENCES principals(id),"           \
    "  token_hash TEXT NOT NULL UNIQUE,"                                   \
    "  role TEXT NOT NULL CHECK(role IN ('client','monitoring','administrator'))," \
    "  status TEXT NOT NULL CHECK(status IN ('active','revoked','expired'))," \
    "  expires_at INTEGER,"                                                \
    "  created_at INTEGER NOT NULL,"                                       \
    "  revoked_at INTEGER);"                                               \
    "CREATE TABLE IF NOT EXISTS namespace_grants ("                        \
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"                              \
    "  principal_id INTEGER NOT NULL REFERENCES principals(id),"           \
    "  namespace TEXT NOT NULL,"                                           \
    "  permission TEXT NOT NULL CHECK(permission IN ('read','write','admin'))," \
    "  created_at INTEGER NOT NULL,"                                       \
    "  UNIQUE(principal_id, namespace));"                                  \
    "CREATE TABLE IF NOT EXISTS records ("                                 \
    "  namespace TEXT NOT NULL,"                                           \
    "  key TEXT NOT NULL,"                                                 \
    "  value BLOB NOT NULL,"                                               \
    "  version INTEGER NOT NULL,"                                          \
    "  expires_at INTEGER,"                                                \
    "  created_at INTEGER NOT NULL,"                                       \
    "  updated_at INTEGER NOT NULL,"                                       \
    "  PRIMARY KEY (namespace, key));"                                     \
    "CREATE INDEX IF NOT EXISTS idx_records_ns_key ON records(namespace, key);" \
    "CREATE INDEX IF NOT EXISTS idx_records_expires ON records(expires_at);" \
    "CREATE TABLE IF NOT EXISTS client_sessions ("                         \
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"                              \
    "  connection_id INTEGER NOT NULL,"                                    \
    "  principal_id INTEGER REFERENCES principals(id),"                    \
    "  token_id INTEGER REFERENCES access_tokens(id),"                     \
    "  established_at INTEGER NOT NULL,"                                   \
    "  closed_at INTEGER,"                                                 \
    "  close_reason TEXT);"                                                \
    "CREATE TABLE IF NOT EXISTS service_instances ("                       \
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"                              \
    "  pid INTEGER NOT NULL,"                                              \
    "  started_at INTEGER NOT NULL,"                                       \
    "  config_generation INTEGER NOT NULL DEFAULT 1,"                      \
    "  stopped_at INTEGER);"                                               \
    "CREATE TABLE IF NOT EXISTS maintenance_runs ("                        \
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"                              \
    "  started_at INTEGER NOT NULL,"                                       \
    "  finished_at INTEGER,"                                               \
    "  batch_limit INTEGER NOT NULL,"                                      \
    "  deleted_count INTEGER NOT NULL DEFAULT 0,"                          \
    "  status TEXT NOT NULL);"                                             \
    "CREATE TABLE IF NOT EXISTS configuration_generations ("               \
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"                              \
    "  generation INTEGER NOT NULL,"                                       \
    "  applied_at INTEGER NOT NULL,"                                       \
    "  config_path TEXT NOT NULL);"                                        \
    "CREATE TABLE IF NOT EXISTS service_metrics ("                         \
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"                              \
    "  recorded_at INTEGER NOT NULL,"                                      \
    "  connections_total INTEGER NOT NULL,"                                \
    "  current_connections INTEGER NOT NULL,"                              \
    "  commands_total INTEGER NOT NULL,"                                   \
    "  auth_ok INTEGER NOT NULL,"                                          \
    "  auth_fail INTEGER NOT NULL,"                                        \
    "  put_ok INTEGER NOT NULL,"                                           \
    "  get_ok INTEGER NOT NULL,"                                           \
    "  list_ok INTEGER NOT NULL,"                                          \
    "  update_ok INTEGER NOT NULL,"                                        \
    "  delete_ok INTEGER NOT NULL,"                                        \
    "  conflict_total INTEGER NOT NULL,"                                   \
    "  expiry_runs INTEGER NOT NULL,"                                      \
    "  expiry_deleted INTEGER NOT NULL,"                                   \
    "  busy_timeouts INTEGER NOT NULL,"                                    \
    "  log_errors INTEGER NOT NULL,"                                       \
    "  rejected_connections INTEGER NOT NULL);"

static void set_pragmas(sqlite3 *db, const netd_config_t *cfg) {
    char sql[256];
    sqlite3_busy_timeout(db, cfg->database_busy_timeout_ms);
    sqlite3_exec(db, "PRAGMA foreign_keys=ON;", NULL, NULL, NULL);
    sqlite3_exec(db, "PRAGMA synchronous=NORMAL;", NULL, NULL, NULL);
    snprintf(sql, sizeof(sql), "PRAGMA journal_mode=WAL;");
    sqlite3_exec(db, sql, NULL, NULL, NULL);
}

static int table_exists(sqlite3 *db, const char *name) {
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
                                "SELECT 1 FROM sqlite_master "
                                "WHERE type='table' AND name=?",
                                -1, &stmt, NULL);
    int exists = 0;
    if (rc == SQLITE_OK) {
        sqlite3_bind_text(stmt, 1, name, -1, SQLITE_STATIC);
        if (sqlite3_step(stmt) == SQLITE_ROW) exists = 1;
    }
    sqlite3_finalize(stmt);
    return exists;
}

static int schema_version(sqlite3 *db, int *ver) {
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
                                "SELECT version FROM schema_version "
                                "ORDER BY version DESC LIMIT 1",
                                -1, &stmt, NULL);
    if (rc != SQLITE_OK) return -1;
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        *ver = sqlite3_column_int(stmt, 0);
        sqlite3_finalize(stmt);
        return 0;
    }
    sqlite3_finalize(stmt);
    return -1;
}

static int create_schema(sqlite3 *db, int64_t now, char *err, size_t errlen) {
    char *emsg = NULL;
    if (sqlite3_exec(db, STORAGE_DDL, NULL, NULL, &emsg) != SQLITE_OK) {
        snprintf(err, errlen, "schema creation failed: %s",
                 emsg ? emsg : sqlite3_errmsg(db));
        sqlite3_free(emsg);
        return -1;
    }
    if (!table_exists(db, "schema_version")) {
        snprintf(err, errlen, "schema_version table missing after creation");
        return -1;
    }
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
                                "INSERT OR IGNORE INTO schema_version"
                                "(version, applied_at) VALUES(?, ?)",
                                -1, &stmt, NULL);
    if (rc == SQLITE_OK) {
        sqlite3_bind_int(stmt, 1, NETD_SCHEMA_VERSION);
        sqlite3_bind_int64(stmt, 2, now);
        rc = sqlite3_step(stmt);
    }
    sqlite3_finalize(stmt);
    if (rc != SQLITE_DONE) {
        snprintf(err, errlen, "cannot record schema version: %s",
                 sqlite3_errmsg(db));
        return -1;
    }
    return 0;
}

static sqlite3 *open_db(const netd_config_t *cfg, char *err, size_t errlen) {
    if (util_mkdirs_file(cfg->database_path) != 0) {
        snprintf(err, errlen, "cannot create database directory for '%s': %s",
                 cfg->database_path, strerror(errno));
        return NULL;
    }
    sqlite3 *db = NULL;
    int rc = sqlite3_open_v2(cfg->database_path, &db,
                             SQLITE_OPEN_READWRITE | SQLITE_OPEN_CREATE |
                                 SQLITE_OPEN_NOMUTEX,
                             NULL);
    if (rc != SQLITE_OK) {
        if (db != NULL) {
            snprintf(err, errlen, "cannot open database '%s': %s",
                     cfg->database_path, sqlite3_errmsg(db));
            sqlite3_close(db);
        } else {
            snprintf(err, errlen, "cannot open database '%s'", cfg->database_path);
        }
        return NULL;
    }
    set_pragmas(db, cfg);
    return db;
}

sqlite3 *storage_open(const netd_config_t *cfg, char *err, size_t errlen) {
    sqlite3 *db = open_db(cfg, err, errlen);
    if (db == NULL) return NULL;

    if (!table_exists(db, "schema_version")) {
        if (create_schema(db, util_now_epoch(), err, errlen) != 0) {
            sqlite3_close(db);
            return NULL;
        }
        if (storage_seed(db, err, errlen) != 0) {
            sqlite3_close(db);
            return NULL;
        }
    } else if (storage_validate_schema(db, err, errlen) != 0) {
        sqlite3_close(db);
        return NULL;
    }

    /* Re-seed in place: deterministic fixtures are ensured, user records
     * are never overwritten (INSERT OR IGNORE). */
    if (storage_seed(db, err, errlen) != 0) {
        sqlite3_close(db);
        return NULL;
    }
    return db;
}

sqlite3 *storage_open_thread(const netd_config_t *cfg, char *err, size_t errlen) {
    sqlite3 *db = open_db(cfg, err, errlen);
    return db;
}

int storage_validate_schema(sqlite3 *db, char *err, size_t errlen) {
    int ver = 0;
    if (schema_version(db, &ver) != 0) {
        snprintf(err, errlen, "database has no readable schema version");
        return -1;
    }
    if (ver != NETD_SCHEMA_VERSION) {
        snprintf(err, errlen,
                 "incompatible database schema version %d (expected %d); "
                 "refusing to start", ver, NETD_SCHEMA_VERSION);
        return -1;
    }
    return 0;
}

int storage_reset(const netd_config_t *cfg, char *err, size_t errlen) {
    char paths[3][PATH_MAX + 8];
    snprintf(paths[0], sizeof(paths[0]), "%s", cfg->database_path);
    snprintf(paths[1], sizeof(paths[1]), "%s-wal", cfg->database_path);
    snprintf(paths[2], sizeof(paths[2]), "%s-shm", cfg->database_path);
    for (int i = 0; i < 3; i++) {
        if (unlink(paths[i]) != 0 && errno != ENOENT) {
            snprintf(err, errlen, "cannot remove '%s': %s", paths[i],
                     strerror(errno));
            return -1;
        }
    }
    sqlite3 *db = storage_open(cfg, err, errlen);
    if (db == NULL) return -1;
    sqlite3_close(db);
    return 0;
}

/* ------------------------------------------------------------------ */
/* Seed fixtures (deterministic; INSERT OR IGNORE).                    */
/* ------------------------------------------------------------------ */

static int64_t bind_principal_id(sqlite3 *db, const char *name) {
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db, "SELECT id FROM principals WHERE name=?",
                                -1, &stmt, NULL);
    int64_t id = -1;
    if (rc == SQLITE_OK) {
        sqlite3_bind_text(stmt, 1, name, -1, SQLITE_STATIC);
        if (sqlite3_step(stmt) == SQLITE_ROW) {
            id = sqlite3_column_int64(stmt, 0);
        }
    }
    sqlite3_finalize(stmt);
    return id;
}

static int seed_principal(sqlite3 *db, const char *name, const char *role,
                          int64_t now) {
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
                                "INSERT OR IGNORE INTO principals(name, role,"
                                " created_at) VALUES(?,?,?)",
                                -1, &stmt, NULL);
    if (rc != SQLITE_OK) return -1;
    sqlite3_bind_text(stmt, 1, name, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 2, role, -1, SQLITE_STATIC);
    sqlite3_bind_int64(stmt, 3, now);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    return rc == SQLITE_DONE ? 0 : -1;
}

static int seed_token(sqlite3 *db, int64_t principal_id, const char *plaintext,
                      const char *role, const char *status, int64_t expires_at,
                      int64_t created_at, int64_t revoked_at) {
    char hex[NETD_SHA256_HEX_LEN];
    if (auth_hash_token(plaintext, hex, sizeof(hex)) != 0) return -1;

    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
                                "INSERT OR IGNORE INTO access_tokens"
                                "(principal_id, token_hash, role, status,"
                                " expires_at, created_at, revoked_at)"
                                " VALUES(?,?,?,?,?,?,?)",
                                -1, &stmt, NULL);
    if (rc != SQLITE_OK) return -1;
    sqlite3_bind_int64(stmt, 1, principal_id);
    sqlite3_bind_text(stmt, 2, hex, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 3, role, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 4, status, -1, SQLITE_STATIC);
    if (expires_at > 0) sqlite3_bind_int64(stmt, 5, expires_at);
    sqlite3_bind_int64(stmt, 6, created_at);
    if (revoked_at > 0) sqlite3_bind_int64(stmt, 7, revoked_at);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    return rc == SQLITE_DONE ? 0 : -1;
}

static int seed_grant(sqlite3 *db, int64_t principal_id, const char *ns,
                      const char *permission, int64_t now) {
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
                                "INSERT OR IGNORE INTO namespace_grants"
                                "(principal_id, namespace, permission,"
                                " created_at) VALUES(?,?,?,?)",
                                -1, &stmt, NULL);
    if (rc != SQLITE_OK) return -1;
    sqlite3_bind_int64(stmt, 1, principal_id);
    sqlite3_bind_text(stmt, 2, ns, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 3, permission, -1, SQLITE_STATIC);
    sqlite3_bind_int64(stmt, 4, now);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    return rc == SQLITE_DONE ? 0 : -1;
}

static int seed_record(sqlite3 *db, const char *ns, const char *key,
                       const void *value, size_t vlen, int64_t ttl, int64_t now) {
    int64_t expires = ttl > 0 ? now + ttl : -1;
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
                                "INSERT OR IGNORE INTO records"
                                "(namespace, key, value, version, expires_at,"
                                " created_at, updated_at)"
                                " VALUES(?,?,?,1,?,?,?)",
                                -1, &stmt, NULL);
    if (rc != SQLITE_OK) return -1;
    sqlite3_bind_text(stmt, 1, ns, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 2, key, -1, SQLITE_STATIC);
    sqlite3_bind_blob(stmt, 3, value, (int)vlen, SQLITE_STATIC);
    if (expires > 0) sqlite3_bind_int64(stmt, 4, expires);
    sqlite3_bind_int64(stmt, 5, now);
    sqlite3_bind_int64(stmt, 6, now);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    return rc == SQLITE_DONE ? 0 : -1;
}

int storage_seed(sqlite3 *db, char *err, size_t errlen) {
    int64_t now = util_now_epoch();
    if (sqlite3_exec(db, "BEGIN IMMEDIATE;", NULL, NULL, NULL) != SQLITE_OK) {
        snprintf(err, errlen, "seed begin failed: %s", sqlite3_errmsg(db));
        return -1;
    }

    if (seed_principal(db, "client01", "client", now) != 0 ||
        seed_principal(db, "monitor01", "monitoring", now) != 0 ||
        seed_principal(db, "admin01", "administrator", now) != 0 ||
        seed_principal(db, "revoked01", "client", now) != 0 ||
        seed_principal(db, "expired01", "client", now) != 0) {
        sqlite3_exec(db, "ROLLBACK;", NULL, NULL, NULL);
        snprintf(err, errlen, "seed principals failed: %s", sqlite3_errmsg(db));
        return -1;
    }

    int64_t cid = bind_principal_id(db, "client01");
    int64_t mid = bind_principal_id(db, "monitor01");
    int64_t aid = bind_principal_id(db, "admin01");
    int64_t rid = bind_principal_id(db, "revoked01");
    int64_t eid = bind_principal_id(db, "expired01");

    if (cid < 0 || mid < 0 || aid < 0 || rid < 0 || eid < 0) {
        sqlite3_exec(db, "ROLLBACK;", NULL, NULL, NULL);
        snprintf(err, errlen, "seed principal lookup failed");
        return -1;
    }

    if (seed_token(db, cid, "client-token-01", "client", "active", 0, now, 0) != 0 ||
        seed_token(db, mid, "monitor-token-01", "monitoring", "active", 0, now, 0) != 0 ||
        seed_token(db, aid, "admin-token-01", "administrator", "active", 0, now, 0) != 0 ||
        seed_token(db, rid, "revoked-token-01", "client", "revoked", 0, now, now) != 0 ||
        seed_token(db, eid, "expired-token-01", "client", "expired", now - 100, now, 0) != 0) {
        sqlite3_exec(db, "ROLLBACK;", NULL, NULL, NULL);
        snprintf(err, errlen, "seed tokens failed: %s", sqlite3_errmsg(db));
        return -1;
    }

    if (seed_grant(db, cid, "app", "write", now) != 0 ||
        seed_grant(db, cid, "shared", "write", now) != 0 ||
        seed_grant(db, aid, "*", "write", now) != 0) {
        sqlite3_exec(db, "ROLLBACK;", NULL, NULL, NULL);
        snprintf(err, errlen, "seed grants failed: %s", sqlite3_errmsg(db));
        return -1;
    }

    if (seed_record(db, "app", "greeting", "hello world", 11, 0, now) != 0 ||
        seed_record(db, "app", "counter", "42", 2, 0, now) != 0 ||
        seed_record(db, "app", "temp", "ephemeral", 9, 5, now) != 0 ||
        seed_record(db, "shared", "note", "shared note", 11, 0, now) != 0) {
        sqlite3_exec(db, "ROLLBACK;", NULL, NULL, NULL);
        snprintf(err, errlen, "seed records failed: %s", sqlite3_errmsg(db));
        return -1;
    }

    if (sqlite3_exec(db, "COMMIT;", NULL, NULL, NULL) != SQLITE_OK) {
        sqlite3_exec(db, "ROLLBACK;", NULL, NULL, NULL);
        snprintf(err, errlen, "seed commit failed: %s", sqlite3_errmsg(db));
        return -1;
    }
    return 0;
}

/* ------------------------------------------------------------------ */
/* Authentication.                                                     */
/* ------------------------------------------------------------------ */

netd_auth_rc_t storage_auth(sqlite3 *db, const char *token_sha256_hex,
                            netd_auth_result_t *out) {
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(
        db,
        "SELECT t.id, t.role, t.status, t.expires_at, p.id, p.name "
        "FROM access_tokens t JOIN principals p ON p.id = t.principal_id "
        "WHERE t.token_hash = ?",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) return NETD_AUTH_DB_ERROR;

    sqlite3_bind_text(stmt, 1, token_sha256_hex, -1, SQLITE_STATIC);
    netd_auth_rc_t result = NETD_AUTH_UNKNOWN;
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        const char *status = (const char *)sqlite3_column_text(stmt, 2);
        int64_t expires_at = sqlite3_column_type(stmt, 3) == SQLITE_NULL
                                 ? 0
                                 : sqlite3_column_int64(stmt, 3);
        int64_t now = util_now_epoch();
        const char *role = (const char *)sqlite3_column_text(stmt, 1);

        if (status == NULL || strcmp(status, "revoked") == 0) {
            result = NETD_AUTH_REVOKED;
        } else if (strcmp(status, "expired") == 0 ||
                   (expires_at > 0 && expires_at <= now)) {
            result = NETD_AUTH_EXPIRED;
        } else {
            out->token_id = sqlite3_column_int64(stmt, 0);
            out->principal_id = sqlite3_column_int64(stmt, 4);
            out->role = NETD_ROLE_CLIENT;
            if (role != NULL && strcmp(role, "monitoring") == 0) {
                out->role = NETD_ROLE_MONITORING;
            } else if (role != NULL && strcmp(role, "administrator") == 0) {
                out->role = NETD_ROLE_ADMIN;
            }
            const char *name = (const char *)sqlite3_column_text(stmt, 5);
            snprintf(out->principal_name, sizeof(out->principal_name), "%s",
                     name ? name : "?");
            result = NETD_AUTH_OK;
        }
    }
    sqlite3_finalize(stmt);
    return result;
}

int storage_session_open(sqlite3 *db, int64_t conn_id,
                         const netd_auth_result_t *auth, int64_t now) {
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(
        db,
        "INSERT INTO client_sessions(connection_id, principal_id, token_id,"
        " established_at) VALUES(?,?,?,?)",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) return -1;
    sqlite3_bind_int64(stmt, 1, conn_id);
    sqlite3_bind_int64(stmt, 2, auth->principal_id);
    sqlite3_bind_int64(stmt, 3, auth->token_id);
    sqlite3_bind_int64(stmt, 4, now);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    return rc == SQLITE_DONE ? 0 : -1;
}

int storage_session_close(sqlite3 *db, int64_t conn_id, const char *reason,
                          int64_t now) {
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(
        db, "UPDATE client_sessions SET closed_at=?, close_reason=? "
            "WHERE connection_id=? AND closed_at IS NULL",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) return -1;
    sqlite3_bind_int64(stmt, 1, now);
    sqlite3_bind_text(stmt, 2, reason, -1, SQLITE_STATIC);
    sqlite3_bind_int64(stmt, 3, conn_id);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    return rc == SQLITE_DONE ? 0 : -1;
}

int storage_has_grant(sqlite3 *db, int64_t principal_id, const char *ns,
                      int need_write) {
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(
        db,
        "SELECT permission FROM namespace_grants "
        "WHERE principal_id=? AND (namespace=? OR namespace='*') LIMIT 1",
        -1, &stmt, NULL);
    int ok = 0;
    if (rc == SQLITE_OK) {
        sqlite3_bind_int64(stmt, 1, principal_id);
        sqlite3_bind_text(stmt, 2, ns, -1, SQLITE_STATIC);
        if (sqlite3_step(stmt) == SQLITE_ROW) {
            const char *perm = (const char *)sqlite3_column_text(stmt, 0);
            if (perm != NULL) {
                if (strcmp(perm, "admin") == 0) {
                    ok = 1;
                } else if (strcmp(perm, "write") == 0) {
                    ok = need_write ? 1 : 1;
                } else if (strcmp(perm, "read") == 0) {
                    ok = need_write ? 0 : 1;
                }
            }
        }
    }
    sqlite3_finalize(stmt);
    return ok;
}

/* ------------------------------------------------------------------ */
/* Records.                                                            */
/* ------------------------------------------------------------------ */

static int exec_txn(sqlite3 *db, const char *sql) {
    char *emsg = NULL;
    int rc = sqlite3_exec(db, sql, NULL, NULL, &emsg);
    sqlite3_free(emsg);
    return rc == SQLITE_OK ? 0 : -1;
}

netd_store_rc_t storage_put(sqlite3 *db, const char *ns, const char *key,
                            int64_t ttl, const uint8_t *value, size_t vlen,
                            int64_t now, int64_t *out_version,
                            int64_t *out_expires) {
    if (exec_txn(db, "BEGIN IMMEDIATE;") != 0) return NETD_STORE_DB_ERROR;

    sqlite3_stmt *stmt = NULL;
    netd_store_rc_t rc = NETD_STORE_DB_ERROR;
    if (sqlite3_prepare_v2(db,
                           "SELECT 1 FROM records WHERE namespace=? AND key=?",
                           -1, &stmt, NULL) != SQLITE_OK) {
        goto done;
    }
    sqlite3_bind_text(stmt, 1, ns, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 2, key, -1, SQLITE_STATIC);
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        rc = NETD_STORE_EXISTS;
        goto done;
    }
    sqlite3_finalize(stmt);
    stmt = NULL;

    int64_t expires = ttl > 0 ? now + ttl : -1;
    if (sqlite3_prepare_v2(
            db,
            "INSERT INTO records(namespace, key, value, version, expires_at,"
            " created_at, updated_at) VALUES(?,?,?,1,?,?,?)",
            -1, &stmt, NULL) != SQLITE_OK) {
        goto done;
    }
    sqlite3_bind_text(stmt, 1, ns, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 2, key, -1, SQLITE_STATIC);
    sqlite3_bind_blob(stmt, 3, value, (int)vlen, SQLITE_STATIC);
    if (expires > 0) sqlite3_bind_int64(stmt, 4, expires);
    sqlite3_bind_int64(stmt, 5, now);
    sqlite3_bind_int64(stmt, 6, now);
    if (sqlite3_step(stmt) != SQLITE_DONE) goto done;
    sqlite3_finalize(stmt);

    if (exec_txn(db, "COMMIT;") != 0) goto done;
    *out_version = 1;
    *out_expires = expires;
    return NETD_STORE_OK;

done:
    sqlite3_finalize(stmt);
    exec_txn(db, "ROLLBACK;");
    return rc == NETD_STORE_DB_ERROR ? NETD_STORE_DB_ERROR : rc;
}

netd_store_rc_t storage_get(sqlite3 *db, const char *ns, const char *key,
                            int64_t now, netd_record_t *rec) {
    memset(rec, 0, sizeof(*rec));
    rec->expires_at = -1;
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(
        db,
        "SELECT version, expires_at, value FROM records "
        "WHERE namespace=? AND key=? AND (expires_at IS NULL OR expires_at>?)",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) return NETD_STORE_DB_ERROR;
    sqlite3_bind_text(stmt, 1, ns, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 2, key, -1, SQLITE_STATIC);
    sqlite3_bind_int64(stmt, 3, now);

    netd_store_rc_t result = NETD_STORE_NOT_FOUND;
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        rec->version = sqlite3_column_int64(stmt, 0);
        if (sqlite3_column_type(stmt, 1) == SQLITE_NULL) {
            rec->expires_at = -1;
        } else {
            rec->expires_at = sqlite3_column_int64(stmt, 1);
        }
        const void *blob = sqlite3_column_blob(stmt, 2);
        int blen = sqlite3_column_bytes(stmt, 2);
        if (blob != NULL && blen > 0) {
            rec->value = malloc((size_t)blen);
            if (rec->value == NULL) {
                sqlite3_finalize(stmt);
                return NETD_STORE_INTERNAL;
            }
            memcpy(rec->value, blob, (size_t)blen);
            rec->value_len = (size_t)blen;
        } else {
            rec->value = malloc(1);
            if (rec->value == NULL) {
                sqlite3_finalize(stmt);
                return NETD_STORE_INTERNAL;
            }
            rec->value_len = 0;
        }
        result = NETD_STORE_OK;
    }
    sqlite3_finalize(stmt);
    return result;
}

netd_store_rc_t storage_update(sqlite3 *db, const char *ns, const char *key,
                               int64_t expected_version, int64_t ttl,
                               const uint8_t *value, size_t vlen, int64_t now,
                               int64_t *out_version, int64_t *out_expires,
                               int64_t *current_version) {
    if (exec_txn(db, "BEGIN IMMEDIATE;") != 0) return NETD_STORE_DB_ERROR;

    int64_t expires = ttl > 0 ? now + ttl : -1;
    sqlite3_stmt *stmt = NULL;
    netd_store_rc_t rc = NETD_STORE_DB_ERROR;
    if (sqlite3_prepare_v2(
            db,
            "UPDATE records SET value=?, version=version+1, expires_at=?,"
            " updated_at=? WHERE namespace=? AND key=? AND version=? AND"
            " (expires_at IS NULL OR expires_at>?)",
            -1, &stmt, NULL) != SQLITE_OK) {
        goto done;
    }
    sqlite3_bind_blob(stmt, 1, value, (int)vlen, SQLITE_STATIC);
    if (expires > 0) sqlite3_bind_int64(stmt, 2, expires);
    sqlite3_bind_int64(stmt, 3, now);
    sqlite3_bind_text(stmt, 4, ns, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 5, key, -1, SQLITE_STATIC);
    sqlite3_bind_int64(stmt, 6, expected_version);
    sqlite3_bind_int64(stmt, 7, now);

    int changes = 0;
    if (sqlite3_step(stmt) == SQLITE_DONE) {
        changes = sqlite3_changes(db);
    }
    sqlite3_finalize(stmt);
    stmt = NULL;

    if (changes > 0) {
        if (exec_txn(db, "COMMIT;") != 0) goto done;
        *out_version = expected_version + 1;
        *out_expires = expires;
        return NETD_STORE_OK;
    }

    if (sqlite3_prepare_v2(db,
                           "SELECT version, expires_at FROM records "
                           "WHERE namespace=? AND key=?",
                           -1, &stmt, NULL) != SQLITE_OK) {
        goto done;
    }
    sqlite3_bind_text(stmt, 1, ns, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 2, key, -1, SQLITE_STATIC);
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        int64_t ver = sqlite3_column_int64(stmt, 0);
        int64_t exp = sqlite3_column_type(stmt, 1) == SQLITE_NULL
                          ? 0
                          : sqlite3_column_int64(stmt, 1);
        if (exp > 0 && exp <= now) {
            rc = NETD_STORE_NOT_FOUND;
        } else {
            *current_version = ver;
            rc = NETD_STORE_CONFLICT;
        }
    } else {
        rc = NETD_STORE_NOT_FOUND;
    }
    sqlite3_finalize(stmt);
    exec_txn(db, "ROLLBACK;");
    return rc;

done:
    sqlite3_finalize(stmt);
    exec_txn(db, "ROLLBACK;");
    return rc;
}

netd_store_rc_t storage_delete(sqlite3 *db, const char *ns, const char *key,
                               int64_t expected_version, int64_t now,
                               int64_t *out_version, int64_t *current_version) {
    if (exec_txn(db, "BEGIN IMMEDIATE;") != 0) return NETD_STORE_DB_ERROR;

    sqlite3_stmt *stmt = NULL;
    netd_store_rc_t rc = NETD_STORE_DB_ERROR;
    if (sqlite3_prepare_v2(
            db,
            "DELETE FROM records WHERE namespace=? AND key=? AND version=? "
            "AND (expires_at IS NULL OR expires_at>?)",
            -1, &stmt, NULL) != SQLITE_OK) {
        goto done;
    }
    sqlite3_bind_text(stmt, 1, ns, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 2, key, -1, SQLITE_STATIC);
    sqlite3_bind_int64(stmt, 3, expected_version);
    sqlite3_bind_int64(stmt, 4, now);

    int changes = 0;
    if (sqlite3_step(stmt) == SQLITE_DONE) {
        changes = sqlite3_changes(db);
    }
    sqlite3_finalize(stmt);
    stmt = NULL;

    if (changes > 0) {
        if (exec_txn(db, "COMMIT;") != 0) goto done;
        *out_version = expected_version;
        return NETD_STORE_OK;
    }

    if (sqlite3_prepare_v2(db,
                           "SELECT version, expires_at FROM records "
                           "WHERE namespace=? AND key=?",
                           -1, &stmt, NULL) != SQLITE_OK) {
        goto done;
    }
    sqlite3_bind_text(stmt, 1, ns, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 2, key, -1, SQLITE_STATIC);
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        int64_t ver = sqlite3_column_int64(stmt, 0);
        int64_t exp = sqlite3_column_type(stmt, 1) == SQLITE_NULL
                          ? 0
                          : sqlite3_column_int64(stmt, 1);
        if (exp > 0 && exp <= now) {
            rc = NETD_STORE_NOT_FOUND;
        } else {
            *current_version = ver;
            rc = NETD_STORE_CONFLICT;
        }
    } else {
        rc = NETD_STORE_NOT_FOUND;
    }
    sqlite3_finalize(stmt);
    exec_txn(db, "ROLLBACK;");
    return rc;

done:
    sqlite3_finalize(stmt);
    exec_txn(db, "ROLLBACK;");
    return rc;
}

netd_store_rc_t storage_list(sqlite3 *db, const char *ns, const char *prefix,
                             int64_t limit, const char *after, int64_t now,
                             netd_list_row_t *rows, int *count, int *more,
                             char *after_out) {
    *count = 0;
    *more = 0;
    after_out[0] = '\0';

    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(
        db,
        "SELECT key FROM records WHERE namespace=? AND key LIKE ? "
        "AND (?='' OR key>?) AND (expires_at IS NULL OR expires_at>?) "
        "ORDER BY key LIMIT ?",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) return NETD_STORE_DB_ERROR;

    char pat[NETD_KEY_MAX + 4];
    snprintf(pat, sizeof(pat), "%s%%", prefix);
    sqlite3_bind_text(stmt, 1, ns, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 2, pat, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 3, after, -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 4, after, -1, SQLITE_STATIC);
    sqlite3_bind_int64(stmt, 5, now);
    sqlite3_bind_int64(stmt, 6, limit + 1);

    int n = 0;
    while (sqlite3_step(stmt) == SQLITE_ROW && n < limit + 1) {
        const char *k = (const char *)sqlite3_column_text(stmt, 0);
        snprintf(rows[n].key, sizeof(rows[n].key), "%s", k ? k : "");
        n++;
    }
    sqlite3_finalize(stmt);

    if (n > limit) {
        *more = 1;
        *count = (int)limit;
        snprintf(after_out, NETD_KEY_MAX + 1, "%s", rows[limit - 1].key);
    } else {
        *more = 0;
        *count = n;
    }
    return NETD_STORE_OK;
}

netd_store_rc_t storage_delete_expired(sqlite3 *db, int64_t now, int64_t batch,
                                       int64_t *deleted) {
    *deleted = 0;
    if (exec_txn(db, "BEGIN IMMEDIATE;") != 0) return NETD_STORE_DB_ERROR;

    sqlite3_stmt *sel = NULL;
    sqlite3_stmt *del = NULL;
    netd_store_rc_t rc = NETD_STORE_DB_ERROR;

    if (sqlite3_prepare_v2(db,
                           "SELECT namespace, key FROM records WHERE "
                           "expires_at IS NOT NULL AND expires_at<=? LIMIT ?",
                           -1, &sel, NULL) != SQLITE_OK) {
        goto done;
    }
    sqlite3_bind_int64(sel, 1, now);
    sqlite3_bind_int64(sel, 2, batch);

    if (sqlite3_prepare_v2(db,
                           "DELETE FROM records WHERE namespace=? AND key=?",
                           -1, &del, NULL) != SQLITE_OK) {
        goto done;
    }

    int64_t count = 0;
    int ok = 1;
    while (sqlite3_step(sel) == SQLITE_ROW) {
        const char *ns = (const char *)sqlite3_column_text(sel, 0);
        const char *key = (const char *)sqlite3_column_text(sel, 1);
        sqlite3_bind_text(del, 1, ns, -1, SQLITE_STATIC);
        sqlite3_bind_text(del, 2, key, -1, SQLITE_STATIC);
        if (sqlite3_step(del) != SQLITE_DONE) {
            ok = 0;
            break;
        }
        sqlite3_reset(del);
        sqlite3_clear_bindings(del);
        count++;
    }

    if (!ok) goto done;
    if (exec_txn(db, "COMMIT;") != 0) goto done;
    *deleted = count;
    rc = NETD_STORE_OK;

done:
    sqlite3_finalize(sel);
    sqlite3_finalize(del);
    if (rc != NETD_STORE_OK) {
        exec_txn(db, "ROLLBACK;");
        *deleted = 0;
    }
    return rc;
}

/* ------------------------------------------------------------------ */
/* Bookkeeping.                                                        */
/* ------------------------------------------------------------------ */

int storage_config_generation(sqlite3 *db, int generation, const char *path,
                              int64_t now) {
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
                                "INSERT INTO configuration_generations"
                                "(generation, applied_at, config_path)"
                                " VALUES(?,?,?)",
                                -1, &stmt, NULL);
    if (rc != SQLITE_OK) return -1;
    sqlite3_bind_int(stmt, 1, generation);
    sqlite3_bind_int64(stmt, 2, now);
    sqlite3_bind_text(stmt, 3, path, -1, SQLITE_STATIC);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    return rc == SQLITE_DONE ? 0 : -1;
}

int storage_instance_start(sqlite3 *db, int pid, int generation, int64_t now) {
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
                                "INSERT INTO service_instances"
                                "(pid, started_at, config_generation)"
                                " VALUES(?,?,?)",
                                -1, &stmt, NULL);
    if (rc != SQLITE_OK) return -1;
    sqlite3_bind_int(stmt, 1, pid);
    sqlite3_bind_int64(stmt, 2, now);
    sqlite3_bind_int(stmt, 3, generation);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    return rc == SQLITE_DONE ? 0 : -1;
}

int storage_instance_stop(sqlite3 *db, int pid, int64_t now) {
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
                                "UPDATE service_instances SET stopped_at=?"
                                " WHERE pid=? AND stopped_at IS NULL",
                                -1, &stmt, NULL);
    if (rc != SQLITE_OK) return -1;
    sqlite3_bind_int64(stmt, 1, now);
    sqlite3_bind_int(stmt, 2, pid);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    return rc == SQLITE_DONE ? 0 : -1;
}

int storage_metrics_snapshot(sqlite3 *db, int64_t now) {
    netd_metrics_t m;
    metrics_snapshot(&m);
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(
        db,
        "INSERT INTO service_metrics(recorded_at, connections_total,"
        " current_connections, commands_total, auth_ok, auth_fail, put_ok,"
        " get_ok, list_ok, update_ok, delete_ok, conflict_total, expiry_runs,"
        " expiry_deleted, busy_timeouts, log_errors, rejected_connections)"
        " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) return -1;
    sqlite3_bind_int64(stmt, 1, now);
    sqlite3_bind_int64(stmt, 2, (int64_t)m.connections_total);
    sqlite3_bind_int64(stmt, 3, (int64_t)m.current_connections);
    sqlite3_bind_int64(stmt, 4, (int64_t)m.commands_total);
    sqlite3_bind_int64(stmt, 5, (int64_t)m.auth_ok);
    sqlite3_bind_int64(stmt, 6, (int64_t)m.auth_fail);
    sqlite3_bind_int64(stmt, 7, (int64_t)m.put_ok);
    sqlite3_bind_int64(stmt, 8, (int64_t)m.get_ok);
    sqlite3_bind_int64(stmt, 9, (int64_t)m.list_ok);
    sqlite3_bind_int64(stmt, 10, (int64_t)m.update_ok);
    sqlite3_bind_int64(stmt, 11, (int64_t)m.delete_ok);
    sqlite3_bind_int64(stmt, 12, (int64_t)m.conflict_total);
    sqlite3_bind_int64(stmt, 13, (int64_t)m.expiry_runs);
    sqlite3_bind_int64(stmt, 14, (int64_t)m.expiry_deleted);
    sqlite3_bind_int64(stmt, 15, (int64_t)m.busy_timeouts);
    sqlite3_bind_int64(stmt, 16, (int64_t)m.log_errors);
    sqlite3_bind_int64(stmt, 17, (int64_t)m.rejected_connections);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    return rc == SQLITE_DONE ? 0 : -1;
}

int storage_maintenance_run(sqlite3 *db, int64_t now, int64_t batch,
                            int64_t deleted, int64_t finished_at,
                            const char *status) {
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
                                "INSERT INTO maintenance_runs"
                                "(started_at, finished_at, batch_limit,"
                                " deleted_count, status) VALUES(?,?,?,?,?)",
                                -1, &stmt, NULL);
    if (rc != SQLITE_OK) return -1;
    sqlite3_bind_int64(stmt, 1, now);
    sqlite3_bind_int64(stmt, 2, finished_at);
    sqlite3_bind_int64(stmt, 3, batch);
    sqlite3_bind_int64(stmt, 4, deleted);
    sqlite3_bind_text(stmt, 5, status, -1, SQLITE_STATIC);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    return rc == SQLITE_DONE ? 0 : -1;
}

int storage_probe(sqlite3 *db) {
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db, "SELECT 1;", -1, &stmt, NULL);
    if (rc != SQLITE_OK) return -1;
    int ok = sqlite3_step(stmt) == SQLITE_ROW ? 0 : -1;
    sqlite3_finalize(stmt);
    return ok;
}
