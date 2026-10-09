#ifndef NETD_SERVER_H
#define NETD_SERVER_H

#include <pthread.h>
#include <stdint.h>
#include <stdbool.h>
#include <stddef.h>
#include "netd/config.h"
#include "netd/common.h"
#include "netd/connection.h"
#include "netd/protocol.h"

typedef struct netd_storage netd_storage_t;
typedef struct netd_logger netd_logger_t;
typedef struct netd_audit netd_audit_t;
typedef struct netd_metrics netd_metrics_t;
typedef struct netd_auth netd_auth_t;
typedef struct netd_worker_pool netd_worker_pool_t;
typedef struct netd_expiry_worker netd_expiry_worker_t;

typedef struct netd_server {
    netd_config_t config;
    char config_path[256];
    netd_storage_t *storage;
    netd_logger_t *logger;
    netd_audit_t *audit;
    netd_metrics_t *metrics;
    netd_auth_t *auth;
    netd_worker_pool_t *workers;
    netd_expiry_worker_t *expiry;
    netd_connection_t *connections;
    int max_connections;
    int listen_fd;
    int pid_fd;
    int stored_pid;
    int64_t started_at;
    volatile int stop_flag;
    volatile int reload_flag;
    volatile int log_reopen_flag;
    int64_t current_generation;
    int64_t last_log_flush;
    int64_t uptime_start;
    pthread_t accept_thread;
    bool accept_thread_started;
} netd_server_t;

netd_server_t *netd_server_create(const netd_config_t *cfg);
void netd_server_set_config_path(netd_server_t *server, const char *path);
void netd_server_destroy(netd_server_t *server);
int netd_server_start(netd_server_t *server);
int netd_server_run(netd_server_t *server);
int netd_server_stop(netd_server_t *server, int timeout_seconds);
int netd_server_reload(netd_server_t *server);
int netd_server_reopen_logs(netd_server_t *server);

void netd_server_log_event(netd_server_t *server,
                           const char *event,
                           const char *outcome,
                           const char *details);

void netd_server_audit_event(netd_server_t *server,
                             const char *event_type,
                             const char *outcome,
                             const char *connection_id,
                             int64_t principal_id,
                             const char *namespace,
                             const char *key,
                             const char *details);

bool netd_server_is_stopping(const netd_server_t *server);

int netd_command_execute(netd_server_t *server,
                         netd_connection_t *conn,
                         const netd_command_t *cmd);

#endif