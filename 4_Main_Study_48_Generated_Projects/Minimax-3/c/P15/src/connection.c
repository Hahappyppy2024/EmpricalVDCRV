#include "netd/connection.h"
#include "netd/server.h"
#include "netd/util.h"

#include <pthread.h>
#include <stdatomic.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

static atomic_uint_fast32_t g_conn_counter = 0;

int netd_connection_init(netd_connection_t *c, int fd, const char *id)
{
    if (c == NULL) {
        return -1;
    }
    memset(c, 0, sizeof(*c));
    c->fd = fd;
    if (id != NULL) {
        netd_str_copy(c->id, sizeof(c->id), id);
    } else {
        uint32_t n = atomic_fetch_add(&g_conn_counter, 1);
        snprintf(c->id, sizeof(c->id), "c%u", n);
    }
    c->state = NETD_CONN_NEW;
    c->opened_at = netd_now_seconds();
    c->last_activity = c->opened_at;
    c->auth_attempts = 0;
    c->principal_id = 0;
    c->out_buf = malloc(NETD_OUT_BUFFER_SIZE);
    if (c->out_buf == NULL) {
        return -1;
    }
    c->out_buf[0] = '\0';
    c->out_cap = NETD_OUT_BUFFER_SIZE;
    c->out_len = 0;
    c->out_pending_close = false;
    c->in_use = true;
    if (netd_parser_init(&c->parser, 1024) != 0) {
        free(c->out_buf);
        c->out_buf = NULL;
        return -1;
    }
    if (pthread_mutex_init(&c->lock, NULL) != 0) {
        free(c->out_buf);
        c->out_buf = NULL;
        return -1;
    }
    c->lock_initialized = true;
    return 0;
}

void netd_connection_destroy(netd_connection_t *c)
{
    if (c == NULL) {
        return;
    }
    if (c->fd >= 0) {
        close(c->fd);
        c->fd = -1;
    }
    free(c->out_buf);
    c->out_buf = NULL;
    c->out_cap = 0;
    c->out_len = 0;
    netd_parser_destroy(&c->parser);
    if (c->lock_initialized) {
        pthread_mutex_destroy(&c->lock);
        c->lock_initialized = false;
    }
    c->in_use = false;
}

void netd_connection_close(netd_connection_t *c)
{
    if (c == NULL) {
        return;
    }
    c->out_pending_close = true;
}

int netd_connection_append_response(netd_connection_t *c, const char *line)
{
    if (c == NULL || line == NULL) {
        return -1;
    }
    size_t len = strlen(line);
    size_t needed = c->out_len + len + 2;
    if (needed > c->out_cap) {
        size_t new_cap = c->out_cap * 2;
        while (new_cap < needed) {
            new_cap *= 2;
        }
        if (new_cap > 1024 * 1024) {
            return -1;
        }
        char *nb = realloc(c->out_buf, new_cap);
        if (nb == NULL) {
            return -1;
        }
        c->out_buf = nb;
        c->out_cap = new_cap;
    }
    memcpy(c->out_buf + c->out_len, line, len);
    c->out_len += len;
    c->out_buf[c->out_len++] = '\n';
    c->out_buf[c->out_len] = '\0';
    return 0;
}

bool netd_connection_is_authenticated(const netd_connection_t *c)
{
    if (c == NULL) {
        return false;
    }
    return c->state == NETD_CONN_AUTH_CLIENT ||
           c->state == NETD_CONN_AUTH_MONITORING ||
           c->state == NETD_CONN_AUTH_ADMIN;
}

const char *netd_connection_role(const netd_connection_t *c)
{
    if (c == NULL) {
        return "";
    }
    switch (c->state) {
    case NETD_CONN_AUTH_CLIENT: return NETD_ROLE_CLIENT;
    case NETD_CONN_AUTH_MONITORING: return NETD_ROLE_MONITORING;
    case NETD_CONN_AUTH_ADMIN: return NETD_ROLE_ADMINISTRATOR;
    default: return "";
    }
}