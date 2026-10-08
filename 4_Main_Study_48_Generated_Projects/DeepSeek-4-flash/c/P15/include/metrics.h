/*
 * metrics.h -- thread-safe operational counters (in-memory, bounded).
 * Counters never contain tokens, values, or raw command bodies.
 */
#ifndef NETD_METRICS_H
#define NETD_METRICS_H

#include <stdint.h>

typedef struct netd_metrics {
    uint64_t connections_total;
    uint64_t current_connections;
    uint64_t rejected_connections;
    uint64_t commands_total;
    uint64_t auth_ok;
    uint64_t auth_fail;
    uint64_t put_ok;
    uint64_t put_fail;
    uint64_t get_ok;
    uint64_t get_fail;
    uint64_t list_ok;
    uint64_t list_fail;
    uint64_t update_ok;
    uint64_t update_fail;
    uint64_t delete_ok;
    uint64_t delete_fail;
    uint64_t conflict_total;
    uint64_t expiry_runs;
    uint64_t expiry_deleted;
    uint64_t busy_timeouts;
    uint64_t log_errors;
    uint64_t bytes_received;
    uint64_t bytes_sent;
    uint64_t reload_count;
    uint64_t reload_fail;
} netd_metrics_t;

void metrics_init(void);

/* Server start timestamp (monotonic) for STATS uptime reporting. */
void metrics_set_started(int64_t mono_ms);
int64_t metrics_uptime_seconds(void);

#define METRIC_FIELD(m, name) (m).name

/* Generic accessors keyed by enum to keep the call sites compact. */
typedef enum {
    M_CONNECTIONS_TOTAL = 0,
    M_CURRENT_CONNECTIONS,
    M_REJECTED_CONNECTIONS,
    M_COMMANDS_TOTAL,
    M_AUTH_OK,
    M_AUTH_FAIL,
    M_PUT_OK,
    M_PUT_FAIL,
    M_GET_OK,
    M_GET_FAIL,
    M_LIST_OK,
    M_LIST_FAIL,
    M_UPDATE_OK,
    M_UPDATE_FAIL,
    M_DELETE_OK,
    M_DELETE_FAIL,
    M_CONFLICT_TOTAL,
    M_EXPIRY_RUNS,
    M_EXPIRY_DELETED,
    M_BUSY_TIMEOUTS,
    M_LOG_ERRORS,
    M_BYTES_RECEIVED,
    M_BYTES_SENT,
    M_RELOAD_COUNT,
    M_RELOAD_FAIL,
    M_COUNT
} netd_metric_t;

void metric_inc(netd_metric_t m);
void metric_add(netd_metric_t m, uint64_t delta);
void metric_dec(netd_metric_t m);

/* Read a single counter. */
uint64_t metric_get(netd_metric_t m);

/* Consistent snapshot for STATS and shutdown persistence. */
void metrics_snapshot(netd_metrics_t *out);

#endif /* NETD_METRICS_H */
