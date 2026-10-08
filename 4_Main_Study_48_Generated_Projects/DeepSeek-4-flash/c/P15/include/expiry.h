/*
 * expiry.h -- bounded maintenance worker for record expiry processing.
 */
#ifndef NETD_EXPIRY_H
#define NETD_EXPIRY_H

#include <pthread.h>
#include <sqlite3.h>
#include <stdatomic.h>

#include "config.h"
#include "log.h"

typedef struct netd_expiry_ctx {
    sqlite3 *db;
    netd_logger_t *lg;
    pthread_mutex_t *mutex;
    pthread_cond_t *cond;
    atomic_int *stopping;
    void (*cfg_snapshot)(netd_config_t *out);
} netd_expiry_ctx_t;

/* Runs until *stopping is set (uses cond timed waits). */
void expiry_main(netd_expiry_ctx_t *ctx);

#endif /* NETD_EXPIRY_H */
