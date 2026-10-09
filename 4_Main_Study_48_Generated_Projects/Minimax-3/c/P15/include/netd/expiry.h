#ifndef NETD_EXPIRY_H
#define NETD_EXPIRY_H

#include <stdbool.h>
#include <stddef.h>
#include "storage.h"

typedef struct netd_server netd_server_t;

typedef struct netd_expiry_worker netd_expiry_worker_t;

netd_expiry_worker_t *netd_expiry_worker_create(netd_server_t *server);
void netd_expiry_worker_destroy(netd_expiry_worker_t *w);
int netd_expiry_worker_start(netd_expiry_worker_t *w);
void netd_expiry_worker_stop(netd_expiry_worker_t *w);
int netd_expiry_worker_run_once(netd_expiry_worker_t *w);

#endif