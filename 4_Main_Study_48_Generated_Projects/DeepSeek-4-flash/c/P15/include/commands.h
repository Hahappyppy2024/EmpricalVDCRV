/*
 * commands.h -- application protocol command execution on a worker thread.
 */
#ifndef NETD_COMMANDS_H
#define NETD_COMMANDS_H

#include "server.h"

/* Outcome of executing one command. */
typedef enum {
    NETD_CMD_OK = 0,      /* keep the connection open */
    NETD_CMD_CLOSE,       /* send response, then close the connection */
    NETD_CMD_FATAL        /* internal error: close without a useful reply */
} netd_cmd_rc_t;

/*
 * Execute one complete newline-terminated command line for `conn`.
 * `line` is owned by the caller and may be modified in place. `cfg` is the
 * worker's snapshot of the live configuration. Writes at most one response
 * line into out/outlen (always NUL-terminated when outlen>0).
 */
netd_cmd_rc_t cmd_execute(netd_runtime_t *rt, netd_conn_t *conn, char *line,
                          char *out, size_t outlen);

#endif /* NETD_COMMANDS_H */
