#ifndef NETD_CONNECTION_H
#define NETD_CONNECTION_H

#include <pthread.h>
#include <stdint.h>
#include <stdbool.h>
#include <stddef.h>
#include "protocol.h"

typedef enum {
    NETD_CONN_NEW = 0,
    NETD_CONN_UNAUTH,
    NETD_CONN_AUTH_CLIENT,
    NETD_CONN_AUTH_MONITORING,
    NETD_CONN_AUTH_ADMIN,
    NETD_CONN_CLOSING
} netd_conn_state_t;

typedef struct netd_connection {
    int fd;
    char id[24];
    int64_t opened_at;
    int64_t last_activity;
    int64_t auth_attempts;
    netd_conn_state_t state;
    int64_t principal_id;
    char role[32];
    netd_parser_t parser;
    char *out_buf;
    size_t out_len;
    size_t out_cap;
    bool out_pending_close;
    bool in_use;
    struct netd_server *server;
    pthread_mutex_t lock;
    bool lock_initialized;
} netd_connection_t;

int netd_connection_init(netd_connection_t *c, int fd, const char *id);
void netd_connection_destroy(netd_connection_t *c);
void netd_connection_close(netd_connection_t *c);

int netd_connection_append_response(netd_connection_t *c, const char *line);

bool netd_connection_is_authenticated(const netd_connection_t *c);
const char *netd_connection_role(const netd_connection_t *c);

#endif