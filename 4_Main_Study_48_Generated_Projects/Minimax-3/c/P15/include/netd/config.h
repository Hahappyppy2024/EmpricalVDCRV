#ifndef NETD_CONFIG_H
#define NETD_CONFIG_H

#include <stdint.h>
#include <stdbool.h>
#include "common.h"

typedef struct netd_config {
    char bind_address[64];
    int port;
    char database_path[256];
    char log_path[256];
    char audit_log_path[256];
    char pid_file[256];
    int worker_count;
    int idle_timeout_seconds;
    int max_clients;
    int max_command_bytes;
    int expiry_scan_interval_seconds;
    int expiry_batch_size;
    int shutdown_timeout_seconds;
    bool strict_audit;
    int database_busy_timeout_ms;
    uint64_t generation;
    bool set_bind_address;
    bool set_port;
    bool set_database_path;
    bool set_log_path;
    bool set_audit_log_path;
    bool set_pid_file;
    bool set_worker_count;
    bool set_idle_timeout_seconds;
    bool set_max_clients;
    bool set_max_command_bytes;
    bool set_expiry_scan_interval_seconds;
    bool set_expiry_batch_size;
    bool set_shutdown_timeout_seconds;
    bool set_strict_audit;
    bool set_database_busy_timeout_ms;
} netd_config_t;

typedef enum {
    NETD_CONFIG_OK = 0,
    NETD_CONFIG_ERR_OPEN,
    NETD_CONFIG_ERR_LINE,
    NETD_CONFIG_ERR_UNKNOWN_KEY,
    NETD_CONFIG_ERR_DUPLICATE,
    NETD_CONFIG_ERR_VALUE,
    NETD_CONFIG_ERR_RANGE,
    NETD_CONFIG_ERR_REQUIRED,
    NETD_CONFIG_ERR_INTERNAL
} netd_config_status_t;

int netd_config_load(const char *path, netd_config_t *out,
                     netd_config_status_t *status, char *err, size_t err_size);

void netd_config_set_defaults(netd_config_t *cfg);
int netd_config_validate(const netd_config_t *cfg, char *err, size_t err_size);
const char *netd_config_status_string(netd_config_status_t s);

bool netd_config_is_reloadable(const char *key);

#endif