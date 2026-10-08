/*
 * expiry.c -- the maintenance worker deletes expired records in bounded
 * batches (one transaction per batch) and records aggregate metrics.
 * Read-time visibility uses the same expiry comparison in storage.c.
 */
#include "expiry.h"

#include <errno.h>
#include <stdio.h>
#include <string.h>
#include <time.h>

#include "metrics.h"
#include "netd.h"
#include "storage.h"
#include "util.h"

void expiry_main(netd_expiry_ctx_t *ctx) {
    for (;;) {
        netd_config_t cfg;
        ctx->cfg_snapshot(&cfg);
        int interval = cfg.expiry_scan_interval_seconds;
        int64_t batch = cfg.expiry_batch_size;

        struct timespec ts;
        clock_gettime(CLOCK_REALTIME, &ts);
        ts.tv_sec += interval;

        pthread_mutex_lock(ctx->mutex);
        int rc = pthread_cond_timedwait(ctx->cond, ctx->mutex, &ts);
        pthread_mutex_unlock(ctx->mutex);

        if (atomic_load_explicit(ctx->stopping, memory_order_relaxed)) break;
        if (rc == 0) continue; /* woken but not stopping: skip cycle */

        int64_t started_at = util_now_epoch();
        int64_t deleted = 0;
        netd_store_rc_t s = storage_delete_expired(ctx->db, started_at, batch,
                                                   &deleted);
        int64_t finished_at = util_now_epoch();

        if (s != NETD_STORE_OK) {
            metric_inc(M_BUSY_TIMEOUTS);
            log_event(ctx->lg, EV_DB_ERROR, "ERR", "-", "-", "-", "-",
                      "op=expiry rc=%d", (int)s);
            storage_maintenance_run(ctx->db, started_at, batch, 0,
                                    finished_at, "error");
            continue;
        }

        metric_inc(M_EXPIRY_RUNS);
        metric_add(M_EXPIRY_DELETED, (uint64_t)deleted);
        log_event(ctx->lg, EV_EXPIRY_RUN, "OK", "-", "-", "-", "-",
                  "deleted=%lld batch=%lld", (long long)deleted,
                  (long long)batch);
        storage_maintenance_run(ctx->db, started_at, batch, deleted,
                                finished_at, "ok");
    }
}
