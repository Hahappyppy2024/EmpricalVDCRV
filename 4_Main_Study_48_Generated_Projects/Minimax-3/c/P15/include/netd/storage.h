#ifndef NETD_STORAGE_H
#define NETD_STORAGE_H

#include <pthread.h>
#include <sqlite3.h>
#include <stdint.h>
#include <stdbool.h>
#include <stddef.h>

struct netd_storage {
    sqlite3 *db;
    pthread_mutex_t lock;
    int busy_timeout_ms;
    char path[256];
};

typedef struct netd_storage netd_storage_t;

typedef enum {
    NETD_STORAGE_OK = 0,
    NETD_STORAGE_ERR_OPEN,
    NETD_STORAGE_ERR_MIGRATE,
    NETD_STORAGE_ERR_BUSY,
    NETD_STORAGE_ERR_NOT_FOUND,
    NETD_STORAGE_ERR_CONFLICT,
    NETD_STORAGE_ERR_EXISTS,
    NETD_STORAGE_ERR_INTERNAL,
    NETD_STORAGE_ERR_INTEGRITY,
    NETD_STORAGE_ERR_RANGE
} netd_storage_status_t;

typedef struct netd_principal {
    int64_t id;
    char name[64];
    char role[32];
} netd_principal_t;

typedef struct netd_access_token {
    int64_t id;
    int64_t principal_id;
    char role[32];
    char status[16];
    int64_t expires_at;
    char token_prefix[16];
} netd_access_token_t;

typedef struct netd_namespace_grant {
    int64_t principal_id;
    char namespace[64];
    char permission[16];
} netd_namespace_grant_t;

typedef struct netd_record {
    int64_t id;
    char namespace[64];
    char key[128];
    unsigned char *value;
    size_t value_len;
    int64_t version;
    int64_t expires_at;
    int64_t created_at;
    int64_t updated_at;
} netd_record_t;

typedef struct netd_record_list_entry {
    char namespace[64];
    char key[128];
    int64_t version;
    int64_t expires_at;
} netd_record_list_entry_t;

netd_storage_t *netd_storage_open(const char *path, int busy_timeout_ms);
void netd_storage_close(netd_storage_t *s);
int netd_storage_init_schema(netd_storage_t *s);
int netd_storage_seeded(netd_storage_t *s, bool *out_seeded);
int netd_storage_seed(netd_storage_t *s);
int netd_storage_register_instance(netd_storage_t *s, int pid, int64_t *out_id);

netd_storage_status_t netd_storage_find_token(netd_storage_t *s,
                                              const char *token_hash,
                                              netd_access_token_t *out_token,
                                              netd_principal_t *out_principal);

netd_storage_status_t netd_storage_check_namespace_access(netd_storage_t *s,
                                                          int64_t principal_id,
                                                          const char *role,
                                                          const char *namespace,
                                                          const char *required_permission,
                                                          bool *out_allowed);

netd_storage_status_t netd_storage_record_get(netd_storage_t *s,
                                              const char *namespace,
                                              const char *key,
                                              int64_t now,
                                              netd_record_t *out,
                                              bool *out_expired);

netd_storage_status_t netd_storage_record_put(netd_storage_t *s,
                                              const char *namespace,
                                              const char *key,
                                              const unsigned char *value,
                                              size_t value_len,
                                              int64_t ttl_seconds,
                                              int64_t now,
                                              int64_t *out_version,
                                              int64_t *out_expires_at);

netd_storage_status_t netd_storage_record_update(netd_storage_t *s,
                                                 const char *namespace,
                                                 const char *key,
                                                 int64_t expected_version,
                                                 const unsigned char *value,
                                                 size_t value_len,
                                                 int64_t ttl_seconds,
                                                 int64_t now,
                                                 int64_t *out_version,
                                                 int64_t *out_expires_at);

netd_storage_status_t netd_storage_record_delete(netd_storage_t *s,
                                                 const char *namespace,
                                                 const char *key,
                                                 int64_t expected_version,
                                                 int64_t now);

netd_storage_status_t netd_storage_record_list(netd_storage_t *s,
                                               const char *namespace,
                                               const char *prefix,
                                               const char *after_key,
                                               int limit,
                                               int max_limit,
                                               int64_t now,
                                               netd_record_list_entry_t *out,
                                               int *out_count,
                                               bool *out_truncated);

netd_storage_status_t netd_storage_expiry_run(netd_storage_t *s,
                                              int batch_size,
                                              int64_t now,
                                              int *out_deleted);

netd_storage_status_t netd_storage_health_probe(netd_storage_t *s);
netd_storage_status_t netd_storage_record_config_generation(netd_storage_t *s,
                                                            uint64_t generation,
                                                            const char *source);

void netd_storage_record_free(netd_record_t *r);
const char *netd_storage_status_string(netd_storage_status_t s);

#endif