#ifndef NETD_H
#define NETD_H

#include "sqlite_compat.h"
#include <pthread.h>
#include <signal.h>
#include <stddef.h>
#include <stdint.h>
#include <stdio.h>
#include <time.h>

#define NETD_PATH_MAX 512
#define NETD_ADDR_MAX 64
#define NETD_LINE_MAX 8192
#define NETD_VALUE_MAX 4096
#define NETD_FIELD_MAX 256

typedef struct {
    char bind_address[NETD_ADDR_MAX];
    int port;
    char database_path[NETD_PATH_MAX];
    char log_path[NETD_PATH_MAX];
    char pid_file[NETD_PATH_MAX];
    int worker_count;
    int idle_timeout_seconds;
    int auth_timeout_seconds;
    int shutdown_timeout_seconds;
    int max_clients;
    int max_command_bytes;
    int max_list_limit;
    int max_ttl_seconds;
    int expiry_scan_interval_seconds;
    int expiry_batch_size;
    int sqlite_busy_timeout_ms;
    int strict_audit;
    int log_flush;
} netd_config;

typedef struct {
    sqlite3 *db;
    pthread_mutex_t lock;
} netd_store;

typedef struct {
    uint64_t connections;
    uint64_t commands;
    uint64_t auth_ok;
    uint64_t auth_failed;
    uint64_t mutations;
    uint64_t expired_deleted;
    uint64_t rejected_clients;
    uint64_t log_errors;
    pthread_mutex_t lock;
} netd_metrics;

typedef struct {
    FILE *file;
    char path[NETD_PATH_MAX];
    int flush;
    pthread_mutex_t lock;
    netd_metrics *metrics;
} netd_logger;

typedef enum { ROLE_NONE, ROLE_CLIENT, ROLE_MONITORING, ROLE_ADMIN } netd_role;

typedef struct {
    long principal_id;
    netd_role role;
    char principal[64];
    int authenticated;
} netd_session;

typedef struct {
    netd_config *config;
    pthread_rwlock_t *config_lock;
    netd_store *store;
    netd_logger *logger;
    netd_metrics *metrics;
    volatile sig_atomic_t *stopping;
} netd_context;

void config_defaults(netd_config *cfg);
int config_load(const char *path, netd_config *out, char *err, size_t err_cap);
int config_reload_compatible(const netd_config *old_cfg, const netd_config *new_cfg,
                             char *err, size_t err_cap);

int store_open(netd_store *store, const netd_config *cfg, char *err, size_t err_cap);
void store_close(netd_store *store);
int store_initialize(netd_store *store, int reset, char *err, size_t err_cap);
int store_integrity(netd_store *store);
int store_auth(netd_store *store, const char *token, netd_session *session);
int store_has_grant(netd_store *store, long principal_id, netd_role role, const char *ns, int write);
int store_put(netd_store *store, const char *ns, const char *key, long ttl,
              const unsigned char *value, size_t value_len, long long *expires_at);
int store_get(netd_store *store, const char *ns, const char *key, unsigned char *value,
              size_t cap, size_t *value_len, int *version, long long *expires_at);
int store_list(netd_store *store, const char *ns, const char *prefix, int limit,
               const char *after, char *out, size_t out_cap);
int store_update(netd_store *store, const char *ns, const char *key, int expected,
                 long ttl, const unsigned char *value, size_t value_len, int *new_version);
int store_delete(netd_store *store, const char *ns, const char *key, int expected);
int store_expire(netd_store *store, int batch, int *deleted);

int base64_decode(const char *in, unsigned char *out, size_t cap, size_t *out_len);
int base64_encode(const unsigned char *in, size_t len, char *out, size_t cap);
int valid_identifier(const char *s);
const char *role_name(netd_role role);
void metrics_init(netd_metrics *metrics);
void metrics_add(uint64_t *counter, netd_metrics *metrics, uint64_t amount);

int logger_open(netd_logger *logger, const netd_config *cfg, netd_metrics *metrics);
int logger_reopen(netd_logger *logger);
void logger_close(netd_logger *logger);
void audit_log(netd_logger *logger, const char *event, uint64_t connection_id,
               const netd_session *session, const char *ns, const char *key,
               const char *outcome);

void protocol_handle_connection(int fd, uint64_t connection_id, netd_context *ctx);
int server_run(const char *config_path, netd_config *cfg, int ready_fd);

#endif
