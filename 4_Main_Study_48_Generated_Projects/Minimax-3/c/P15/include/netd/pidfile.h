#ifndef NETD_PIDFILE_H
#define NETD_PIDFILE_H

#include <stdbool.h>
#include <stddef.h>
#include <stdint.h>

typedef enum {
    NETD_PID_OK = 0,
    NETD_PID_ERR_OPEN,
    NETD_PID_ERR_LOCK,
    NETD_PID_ERR_STALE,
    NETD_PID_ERR_LIVE
} netd_pid_status_t;

int netd_pid_acquire(const char *path, int *out_existing_pid,
                     netd_pid_status_t *out_status);
int netd_pid_release(const char *path, int acquired_fd, int stored_pid);
int netd_pid_is_live(int pid);
int netd_pid_remove_file(const char *path);

const char *netd_pid_status_string(netd_pid_status_t s);

#endif