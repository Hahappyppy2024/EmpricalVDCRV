/*
 * config.h -- strict key=value configuration file parsing and validation.
 */
#ifndef NETD_CONFIG_H
#define NETD_CONFIG_H

#include <limits.h>
#include <stddef.h>
#include <stdio.h>

#include "netd.h"

/* Flush policy for the structured logger. */
#define NETD_LOG_FLUSH_AUTO 0
#define NETD_LOG_FLUSH_ALWAYS 1

typedef struct netd_config {
    /* Non-reloadable settings (change requires restart). */
    char bind_address[64];
    int  port;
    char database_path[PATH_MAX];
    char log_path[PATH_MAX];
    char pid_file[PATH_MAX];
    int  worker_count;

    /* Reloadable settings. */
    int max_clients;
    int max_command_bytes;
    int idle_timeout_seconds;         /* 0 disables idle reaping */
    int auth_timeout_seconds;
    int shutdown_timeout_seconds;
    int expiry_scan_interval_seconds;
    int expiry_batch_size;
    int database_busy_timeout_ms;
    int list_max_limit;
    int strict_audit;
    int log_flush;                    /* NETD_LOG_FLUSH_* */

    /* Derived runtime fields. */
    int generation;
} netd_config_t;

/* Fill cfg with the documented defaults. */
void config_defaults(netd_config_t *cfg);

/*
 * Parse and validate a configuration file.
 * Returns 0 on success, -1 on error (message in err/errlen).
 * Unknown keys, duplicate keys, malformed values, and out-of-range values
 * are all startup/reload errors.
 */
int config_load(const char *path, netd_config_t *cfg, char *err, size_t errlen);

/* Validate field ranges without parsing. Returns 0 on success. */
int config_validate(const netd_config_t *cfg, char *err, size_t errlen);

/* Render the active configuration as a single bounded line (no secrets). */
void config_dump(const netd_config_t *cfg, char *out, size_t outlen);

/* Returns 1 if any non-reloadable setting differs; fills `changed` with the
 * comma-separated list of changed key names. */
int config_nonreloadable_changed(const netd_config_t *old,
                                 const netd_config_t *nu,
                                 char *changed, size_t changedlen);

void config_print_help(FILE *out);

#endif /* NETD_CONFIG_H */
