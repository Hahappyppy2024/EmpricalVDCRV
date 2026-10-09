#ifndef NETD_WORKER_H
#define NETD_WORKER_H

#include <stdint.h>
#include <stdbool.h>
#include <stddef.h>
#include "protocol.h"
#include "connection.h"

typedef struct netd_server netd_server_t;

typedef struct netd_task {
    netd_command_t cmd;
    netd_connection_t *conn;
} netd_task_t;

typedef struct netd_worker_pool netd_worker_pool_t;

netd_worker_pool_t *netd_worker_pool_create(netd_server_t *server, int worker_count);
void netd_worker_pool_destroy(netd_worker_pool_t *pool);

int netd_worker_pool_start(netd_worker_pool_t *pool);
void netd_worker_pool_stop(netd_worker_pool_t *pool);

int netd_worker_pool_submit(netd_worker_pool_t *pool, netd_task_t *task);
int netd_worker_pool_pending(netd_worker_pool_t *pool);

void netd_worker_pool_set_shutdown(netd_worker_pool_t *pool);

#endif