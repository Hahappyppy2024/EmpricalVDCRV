/*
 * metrics.c -- thread-safe atomic operational counters.
 */
#include "metrics.h"

#include <stdatomic.h>
#include <string.h>

#include "util.h"

static atomic_uint_fast64_t g_metrics[M_COUNT];
static atomic_int_fast64_t g_started_mono = 0;

void metrics_init(void) {
    for (int i = 0; i < M_COUNT; i++) {
        atomic_store_explicit(&g_metrics[i], 0, memory_order_relaxed);
    }
    atomic_store_explicit(&g_started_mono, 0, memory_order_relaxed);
}

void metrics_set_started(int64_t mono_ms) {
    atomic_store_explicit(&g_started_mono, mono_ms, memory_order_relaxed);
}

int64_t metrics_uptime_seconds(void) {
    int64_t start = atomic_load_explicit(&g_started_mono, memory_order_relaxed);
    if (start <= 0) return 0;
    return (util_mono_ms() - start) / 1000;
}

uint64_t metric_get(netd_metric_t m) {
    if (m < 0 || m >= M_COUNT) return 0;
    return atomic_load_explicit(&g_metrics[m], memory_order_relaxed);
}

void metric_inc(netd_metric_t m) {
    if (m >= 0 && m < M_COUNT) {
        atomic_fetch_add_explicit(&g_metrics[m], 1, memory_order_relaxed);
    }
}

void metric_add(netd_metric_t m, uint64_t delta) {
    if (m >= 0 && m < M_COUNT) {
        atomic_fetch_add_explicit(&g_metrics[m], delta, memory_order_relaxed);
    }
}

void metric_dec(netd_metric_t m) {
    if (m >= 0 && m < M_COUNT) {
        atomic_fetch_sub_explicit(&g_metrics[m], 1, memory_order_relaxed);
    }
}

void metrics_snapshot(netd_metrics_t *out) {
    memset(out, 0, sizeof(*out));
    out->connections_total =
        atomic_load_explicit(&g_metrics[M_CONNECTIONS_TOTAL], memory_order_relaxed);
    out->current_connections =
        atomic_load_explicit(&g_metrics[M_CURRENT_CONNECTIONS], memory_order_relaxed);
    out->rejected_connections =
        atomic_load_explicit(&g_metrics[M_REJECTED_CONNECTIONS], memory_order_relaxed);
    out->commands_total =
        atomic_load_explicit(&g_metrics[M_COMMANDS_TOTAL], memory_order_relaxed);
    out->auth_ok = atomic_load_explicit(&g_metrics[M_AUTH_OK], memory_order_relaxed);
    out->auth_fail = atomic_load_explicit(&g_metrics[M_AUTH_FAIL], memory_order_relaxed);
    out->put_ok = atomic_load_explicit(&g_metrics[M_PUT_OK], memory_order_relaxed);
    out->put_fail = atomic_load_explicit(&g_metrics[M_PUT_FAIL], memory_order_relaxed);
    out->get_ok = atomic_load_explicit(&g_metrics[M_GET_OK], memory_order_relaxed);
    out->get_fail = atomic_load_explicit(&g_metrics[M_GET_FAIL], memory_order_relaxed);
    out->list_ok = atomic_load_explicit(&g_metrics[M_LIST_OK], memory_order_relaxed);
    out->list_fail = atomic_load_explicit(&g_metrics[M_LIST_FAIL], memory_order_relaxed);
    out->update_ok = atomic_load_explicit(&g_metrics[M_UPDATE_OK], memory_order_relaxed);
    out->update_fail = atomic_load_explicit(&g_metrics[M_UPDATE_FAIL], memory_order_relaxed);
    out->delete_ok = atomic_load_explicit(&g_metrics[M_DELETE_OK], memory_order_relaxed);
    out->delete_fail = atomic_load_explicit(&g_metrics[M_DELETE_FAIL], memory_order_relaxed);
    out->conflict_total = atomic_load_explicit(&g_metrics[M_CONFLICT_TOTAL], memory_order_relaxed);
    out->expiry_runs = atomic_load_explicit(&g_metrics[M_EXPIRY_RUNS], memory_order_relaxed);
    out->expiry_deleted = atomic_load_explicit(&g_metrics[M_EXPIRY_DELETED], memory_order_relaxed);
    out->busy_timeouts = atomic_load_explicit(&g_metrics[M_BUSY_TIMEOUTS], memory_order_relaxed);
    out->log_errors = atomic_load_explicit(&g_metrics[M_LOG_ERRORS], memory_order_relaxed);
    out->bytes_received = atomic_load_explicit(&g_metrics[M_BYTES_RECEIVED], memory_order_relaxed);
    out->bytes_sent = atomic_load_explicit(&g_metrics[M_BYTES_SENT], memory_order_relaxed);
    out->reload_count = atomic_load_explicit(&g_metrics[M_RELOAD_COUNT], memory_order_relaxed);
    out->reload_fail = atomic_load_explicit(&g_metrics[M_RELOAD_FAIL], memory_order_relaxed);
}
