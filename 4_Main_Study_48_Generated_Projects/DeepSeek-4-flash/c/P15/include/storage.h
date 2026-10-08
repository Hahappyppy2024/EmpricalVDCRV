/*
 * storage.h -- SQLite persistence: schema, versioning, seed fixtures,
 * record repository, authentication repository, sessions, maintenance.
 */
#ifndef NETD_STORAGE_H
#define NETD_STORAGE_H

#include <sqlite3.h>
#include <stddef.h>
#include <stdint.h>

#include "config.h"
#include "netd.h"

/* Storage operation results. */
typedef enum {
    NETD_STORE_OK = 0,
    NETD_STORE_NOT_FOUND,
    NETD_STORE_EXISTS,
    NETD_STORE_CONFLICT,
    NETD_STORE_DB_ERROR,
    NETD_STORE_INTERNAL
} netd_store_rc_t;

typedef struct netd_auth_result {
    int64_t token_id;
    int64_t principal_id;
    netd_role_t role;
    char principal_name[NETD_NAME_MAX];
} netd_auth_result_t;

typedef enum {
    NETD_AUTH_OK = 0,
    NETD_AUTH_UNKNOWN,
    NETD_AUTH_REVOKED,
    NETD_AUTH_EXPIRED,
    NETD_AUTH_DB_ERROR
} netd_auth_rc_t;

typedef struct netd_record {
    int64_t version;
    int64_t expires_at;   /* -1 when the record never expires */
    uint8_t *value;
    size_t value_len;
} netd_record_t;

typedef struct netd_list_row {
    char key[NETD_KEY_MAX + 1];
} netd_list_row_t;

/*
 * Open the primary database. Creates parent directories, initializes or
 * validates the schema (version NETD_SCHEMA_VERSION), applies WAL mode and
 * ensures the deterministic seed fixtures exist. Returns NULL on error
 * (message in err/errlen).
 */
sqlite3 *storage_open(const netd_config_t *cfg, char *err, size_t errlen);

/* Open a per-thread (worker/expiry) connection with WAL/foreign keys and the
 * configured bounded busy timeout. Does not seed. Returns NULL on error. */
sqlite3 *storage_open_thread(const netd_config_t *cfg, char *err, size_t errlen);

/* Wipe the database files and re-initialize with deterministic seeds. */
int storage_reset(const netd_config_t *cfg, char *err, size_t errlen);

/* Insert the deterministic principals, tokens, grants and records using
 * INSERT OR IGNORE so existing user data is never overwritten. */
int storage_seed(sqlite3 *db, char *err, size_t errlen);

/* Fail unless the live schema version equals NETD_SCHEMA_VERSION. */
int storage_validate_schema(sqlite3 *db, char *err, size_t errlen);

/* Authentication ------------------------------------------------------- */
/* Look up a token by its SHA-256 hex digest. Sets *out on success. */
netd_auth_rc_t storage_auth(sqlite3 *db, const char *token_sha256_hex,
                            netd_auth_result_t *out);

int storage_session_open(sqlite3 *db, int64_t conn_id,
                         const netd_auth_result_t *auth, int64_t now);
int storage_session_close(sqlite3 *db, int64_t conn_id,
                          const char *reason, int64_t now);

/* Grants ---------------------------------------------------------------- */
int storage_has_grant(sqlite3 *db, int64_t principal_id, const char *ns,
                      int need_write);

/* Records --------------------------------------------------------------- */
/* ns/key already validated by the caller. ttl>=0; 0 means never expires. */
netd_store_rc_t storage_put(sqlite3 *db, const char *ns, const char *key,
                            int64_t ttl, const uint8_t *value, size_t vlen,
                            int64_t now, int64_t *out_version,
                            int64_t *out_expires);

netd_store_rc_t storage_get(sqlite3 *db, const char *ns, const char *key,
                            int64_t now, netd_record_t *rec);

netd_store_rc_t storage_update(sqlite3 *db, const char *ns, const char *key,
                               int64_t expected_version, int64_t ttl,
                               const uint8_t *value, size_t vlen, int64_t now,
                               int64_t *out_version, int64_t *out_expires,
                               int64_t *current_version);

netd_store_rc_t storage_delete(sqlite3 *db, const char *ns, const char *key,
                               int64_t expected_version, int64_t now,
                               int64_t *out_version, int64_t *current_version);

/*
 * Bounded lexicographic listing. Returns up to limit keys matching prefix,
 * ordered by key. When *more becomes 1, *after holds the last returned key
 * (the continuation token). `after` of "" means start at the beginning.
 */
netd_store_rc_t storage_list(sqlite3 *db, const char *ns, const char *prefix,
                             int64_t limit, const char *after, int64_t now,
                             netd_list_row_t *rows, int *count, int *more,
                             char *after_out);

/* Delete at most batch expired rows in a single transaction. */
netd_store_rc_t storage_delete_expired(sqlite3 *db, int64_t now, int64_t batch,
                                       int64_t *deleted);

/* Bookkeeping ----------------------------------------------------------- */
int storage_config_generation(sqlite3 *db, int generation, const char *path,
                              int64_t now);
int storage_instance_start(sqlite3 *db, int pid, int generation, int64_t now);
int storage_instance_stop(sqlite3 *db, int pid, int64_t now);
int storage_metrics_snapshot(sqlite3 *db, int64_t now);
int storage_maintenance_run(sqlite3 *db, int64_t now, int64_t batch,
                            int64_t deleted, int64_t finished_at,
                            const char *status);

/* Diagnostic helper: quick SQLite probe used by HEALTH. Returns 0 on ok. */
int storage_probe(sqlite3 *db);

#endif /* NETD_STORAGE_H */
