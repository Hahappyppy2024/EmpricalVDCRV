#include "netd/expiry.h"
#include "netd/server.h"
#include "netd/util.h"
#include "netd/common.h"
#include "netd/log.h"
#include "netd/metrics.h"

#include <errno.h>
#include <pthread.h>
#include <stdatomic.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>

struct netd_expiry_worker {
    netd_server_t *server;
    pthread_t thread;
    bool started;
    atomic_int stop_flag;
};

static int do_run(netd_expiry_worker_t *w)
{
    if (w == NULL || w->server == NULL || w->server->storage == NULL) {
        return -1;
    }
    int total = 0;
    int batch = w->server->config.expiry_batch_size;
    if (batch <= 0) {
        batch = 100;
    }
    while (1) {
        int deleted = 0;
        int64_t now = netd_now_seconds();
        netd_storage_status_t st = netd_storage_expiry_run(w->server->storage,
                                                           batch, now, &deleted);
        if (st != NETD_STORAGE_OK) {
            netd_logger_write(w->server->logger, NETD_LOG_LEVEL_ERROR,
                              NETD_EVENT_EXPIRY_RUN, NETD_EVENT_OUTCOME_FAIL,
                              "expiry run failed status=%s",
                              netd_storage_status_string(st));
            return -1;
        }
        if (deleted <= 0) {
            break;
        }
        total += deleted;
        netd_metrics_inc(w->server->metrics, NETD_METRIC_RECORDS_EXPIRED,
                         (int64_t)deleted);
        if (deleted < batch) {
            break;
        }
    }
    if (total > 0) {
        netd_logger_write(w->server->logger, NETD_LOG_LEVEL_INFO,
                          NETD_EVENT_EXPIRY_RUN, NETD_EVENT_OUTCOME_OK,
                          "expiry batch deleted=%d", total);
        netd_server_audit_event(w->server, NETD_EVENT_EXPIRY_RUN,
                                NETD_EVENT_OUTCOME_OK,
                                NULL, 0, NULL, NULL,
                                "deleted_records");
    }
    return total;
}

int netd_expiry_worker_run_once(netd_expiry_worker_t *w)
{
    return do_run(w);
}

static void *expiry_main(void *arg)
{
    netd_expiry_worker_t *w = arg;
    int interval = w->server->config.expiry_scan_interval_seconds;
    if (interval < 1) {
        interval = 1;
    }
    while (!atomic_load(&w->stop_flag)) {
        do_run(w);
        for (int i = 0; i < interval * 10 && !atomic_load(&w->stop_flag); i++) {
            netd_sleep_ms(100);
        }
    }
    return NULL;
}

netd_expiry_worker_t *netd_expiry_worker_create(netd_server_t *server)
{
    if (server == NULL) {
        return NULL;
    }
    netd_expiry_worker_t *w = calloc(1, sizeof(*w));
    if (w == NULL) {
        return NULL;
    }
    w->server = server;
    atomic_store(&w->stop_flag, 0);
    return w;
}

void netd_expiry_worker_destroy(netd_expiry_worker_t *w)
{
    free(w);
}

int netd_expiry_worker_start(netd_expiry_worker_t *w)
{
    if (w == NULL || w->started) {
        return -1;
    }
    if (pthread_create(&w->thread, NULL, expiry_main, w) != 0) {
        return -1;
    }
    w->started = true;
    return 0;
}

void netd_expiry_worker_stop(netd_expiry_worker_t *w)
{
    if (w == NULL || !w->started) {
        return;
    }
    atomic_store(&w->stop_flag, 1);
    pthread_join(w->thread, NULL);
    w->started = false;
}