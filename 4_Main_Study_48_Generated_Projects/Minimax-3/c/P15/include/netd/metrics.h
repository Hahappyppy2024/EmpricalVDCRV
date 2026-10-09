#ifndef NETD_METRICS_H
#define NETD_METRICS_H

#include <stdint.h>
#include <stddef.h>

typedef struct netd_metrics netd_metrics_t;

typedef struct netd_metrics_snapshot {
    int64_t connections_accepted;
    int64_t connections_closed;
    int64_t commands_total;
    int64_t auth_success;
    int64_t auth_failed;
    int64_t records_put;
    int64_t records_get;
    int64_t records_updated;
    int64_t records_deleted;
    int64_t records_expired;
    int64_t list_operations;
    int64_t bytes_in;
    int64_t bytes_out;
    int64_t log_write_errors;
    int64_t db_busy_timeouts;
} netd_metrics_snapshot_t;

netd_metrics_t *netd_metrics_create(void);
void netd_metrics_destroy(netd_metrics_t *m);
void netd_metrics_inc(netd_metrics_t *m, const char *name, int64_t delta);
void netd_metrics_add_bytes_in(netd_metrics_t *m, size_t n);
void netd_metrics_add_bytes_out(netd_metrics_t *m, size_t n);
void netd_metrics_snapshot(netd_metrics_t *m, netd_metrics_snapshot_t *out);
void netd_metrics_format_stats(netd_metrics_t *m,
                               const char *bind_address,
                               int port,
                               int worker_count,
                               int max_clients,
                               int max_command_bytes,
                               int64_t config_generation,
                               int64_t uptime_seconds,
                               char *out, size_t out_size);

#endif