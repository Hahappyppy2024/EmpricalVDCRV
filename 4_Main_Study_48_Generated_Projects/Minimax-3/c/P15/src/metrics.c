#include "netd/metrics.h"
#include "netd/common.h"

#include <pthread.h>
#include <stdatomic.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

struct netd_metrics {
    atomic_int_fast64_t connections_accepted;
    atomic_int_fast64_t connections_closed;
    atomic_int_fast64_t commands_total;
    atomic_int_fast64_t auth_success;
    atomic_int_fast64_t auth_failed;
    atomic_int_fast64_t records_put;
    atomic_int_fast64_t records_get;
    atomic_int_fast64_t records_updated;
    atomic_int_fast64_t records_deleted;
    atomic_int_fast64_t records_expired;
    atomic_int_fast64_t list_operations;
    atomic_int_fast64_t bytes_in;
    atomic_int_fast64_t bytes_out;
    atomic_int_fast64_t log_write_errors;
    atomic_int_fast64_t db_busy_timeouts;
};

netd_metrics_t *netd_metrics_create(void)
{
    netd_metrics_t *m = calloc(1, sizeof(*m));
    return m;
}

void netd_metrics_destroy(netd_metrics_t *m)
{
    free(m);
}

static void inc_counter(atomic_int_fast64_t *counter, int64_t delta)
{
    atomic_fetch_add(counter, delta);
}

void netd_metrics_inc(netd_metrics_t *m, const char *name, int64_t delta)
{
    if (m == NULL || name == NULL) {
        return;
    }
    if (strcmp(name, NETD_METRIC_CONNECTIONS_ACCEPTED) == 0) {
        inc_counter(&m->connections_accepted, delta);
    } else if (strcmp(name, NETD_METRIC_CONNECTIONS_CLOSED) == 0) {
        inc_counter(&m->connections_closed, delta);
    } else if (strcmp(name, NETD_METRIC_COMMANDS_TOTAL) == 0) {
        inc_counter(&m->commands_total, delta);
    } else if (strcmp(name, NETD_METRIC_AUTH_SUCCESS) == 0) {
        inc_counter(&m->auth_success, delta);
    } else if (strcmp(name, NETD_METRIC_AUTH_FAILED) == 0) {
        inc_counter(&m->auth_failed, delta);
    } else if (strcmp(name, NETD_METRIC_RECORDS_PUT) == 0) {
        inc_counter(&m->records_put, delta);
    } else if (strcmp(name, NETD_METRIC_RECORDS_GET) == 0) {
        inc_counter(&m->records_get, delta);
    } else if (strcmp(name, NETD_METRIC_RECORDS_UPDATED) == 0) {
        inc_counter(&m->records_updated, delta);
    } else if (strcmp(name, NETD_METRIC_RECORDS_DELETED) == 0) {
        inc_counter(&m->records_deleted, delta);
    } else if (strcmp(name, NETD_METRIC_RECORDS_EXPIRED) == 0) {
        inc_counter(&m->records_expired, delta);
    } else if (strcmp(name, NETD_METRIC_LIST_OPS) == 0) {
        inc_counter(&m->list_operations, delta);
    } else if (strcmp(name, NETD_METRIC_LOG_ERRORS) == 0) {
        inc_counter(&m->log_write_errors, delta);
    } else if (strcmp(name, NETD_METRIC_DB_BUSY_TIMEOUTS) == 0) {
        inc_counter(&m->db_busy_timeouts, delta);
    }
}

void netd_metrics_add_bytes_in(netd_metrics_t *m, size_t n)
{
    if (m == NULL) {
        return;
    }
    inc_counter(&m->bytes_in, (int64_t)n);
}

void netd_metrics_add_bytes_out(netd_metrics_t *m, size_t n)
{
    if (m == NULL) {
        return;
    }
    inc_counter(&m->bytes_out, (int64_t)n);
}

void netd_metrics_snapshot(netd_metrics_t *m, netd_metrics_snapshot_t *out)
{
    if (m == NULL || out == NULL) {
        return;
    }
    out->connections_accepted = atomic_load(&m->connections_accepted);
    out->connections_closed = atomic_load(&m->connections_closed);
    out->commands_total = atomic_load(&m->commands_total);
    out->auth_success = atomic_load(&m->auth_success);
    out->auth_failed = atomic_load(&m->auth_failed);
    out->records_put = atomic_load(&m->records_put);
    out->records_get = atomic_load(&m->records_get);
    out->records_updated = atomic_load(&m->records_updated);
    out->records_deleted = atomic_load(&m->records_deleted);
    out->records_expired = atomic_load(&m->records_expired);
    out->list_operations = atomic_load(&m->list_operations);
    out->bytes_in = atomic_load(&m->bytes_in);
    out->bytes_out = atomic_load(&m->bytes_out);
    out->log_write_errors = atomic_load(&m->log_write_errors);
    out->db_busy_timeouts = atomic_load(&m->db_busy_timeouts);
}

void netd_metrics_format_stats(netd_metrics_t *m,
                               const char *bind_address,
                               int port,
                               int worker_count,
                               int max_clients,
                               int max_command_bytes,
                               int64_t config_generation,
                               int64_t uptime_seconds,
                               char *out, size_t out_size)
{
    if (out == NULL || out_size == 0) {
        return;
    }
    netd_metrics_snapshot_t s;
    netd_metrics_snapshot(m, &s);
    snprintf(out, out_size,
             "bind=%s port=%d workers=%d max_clients=%d max_command_bytes=%d "
             "config_generation=%lld uptime_seconds=%lld "
             "connections_accepted=%lld connections_closed=%lld "
             "commands_total=%lld auth_success=%lld auth_failed=%lld "
             "records_put=%lld records_get=%lld records_updated=%lld "
             "records_deleted=%lld records_expired=%lld "
             "list_operations=%lld bytes_in=%lld bytes_out=%lld "
             "log_write_errors=%lld db_busy_timeouts=%lld",
             bind_address ? bind_address : "-",
             port, worker_count, max_clients, max_command_bytes,
             (long long)config_generation, (long long)uptime_seconds,
             (long long)s.connections_accepted,
             (long long)s.connections_closed,
             (long long)s.commands_total,
             (long long)s.auth_success,
             (long long)s.auth_failed,
             (long long)s.records_put,
             (long long)s.records_get,
             (long long)s.records_updated,
             (long long)s.records_deleted,
             (long long)s.records_expired,
             (long long)s.list_operations,
             (long long)s.bytes_in,
             (long long)s.bytes_out,
             (long long)s.log_write_errors,
             (long long)s.db_busy_timeouts);
}