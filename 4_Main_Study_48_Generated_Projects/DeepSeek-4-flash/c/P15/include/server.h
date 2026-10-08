/*
 * server.h -- accept loop, bounded pthread worker pool, per-connection
 * parser/authentication state, and graceful shutdown coordinator.
 */
#ifndef NETD_SERVER_H
#define NETD_SERVER_H

#include <pthread.h>
#include <sqlite3.h>
#include <stdint.h>

#include "config.h"
#include "log.h"

typedef struct netd_server netd_server_t;

typedef struct netd_conn {
    int fd;
    uint64_t id;
    int worker_idx;
    char *inbuf;        /* owned buffer, bounded by max_command_bytes+2 */
    size_t inlen;
    size_t incap;
    int overlong;
    int authenticated;
    int64_t principal_id;
    int64_t token_id;
    netd_role_t role;
    char principal_name[NETD_NAME_MAX];
    int64_t opened_at;
    int64_t last_activity;
    int closing;
    int quit_seen;
} netd_conn_t;

/* Runtime configuration that command handlers read. */
typedef struct netd_runtime {
    netd_config_t cfg;
    sqlite3 *db;
    netd_logger_t *lg;
    uint64_t conn_id;
    const char *principal;   /* valid when authenticated */
    netd_role_t role;
} netd_runtime_t;

/*
 * Start serving on an already-bound listener. Blocks until graceful
 * shutdown completes. `main_db` is owned by the caller; the server uses it
 * for bookkeeping (config generations, metrics snapshots) on the main thread
 * only. Returns 0 on clean exit.
 */
int server_run(netd_server_t *srv);

netd_server_t *server_create(const netd_config_t *cfg, const char *config_path,
                             sqlite3 *main_db, netd_logger_t *lg,
                             int listener_fd, int signal_fd,
                             char *err, size_t errlen);
void server_destroy(netd_server_t *srv);

/* Snapshot of the live reloadable configuration (main thread / workers). */
void server_cfg_snapshot(netd_config_t *out);
void server_cfg_apply(const netd_config_t *nu);

/* True once graceful shutdown has been requested. */
int server_is_stopping(void);

/* Request graceful shutdown (used by the signal bridge). */
void server_request_shutdown(void);

#endif /* NETD_SERVER_H */
