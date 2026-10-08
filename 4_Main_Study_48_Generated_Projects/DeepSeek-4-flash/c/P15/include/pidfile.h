/*
 * pidfile.h -- PID-file locking with stale-PID recovery.
 */
#ifndef NETD_PIDFILE_H
#define NETD_PIDFILE_H

#include <limits.h>
#include <stddef.h>

typedef struct netd_pidfile {
    int fd;
    long pid;
    char path[PATH_MAX];
} netd_pidfile_t;

/*
 * Acquire an exclusive lock on `path`. 
 *   - On success returns a handle (*out). *held=0, *stale=1 if a stale PID
 *     was confirmed absent and replaced; *stale=0 otherwise.
 *   - If a live instance holds the lock, *held=1, returns NULL and fills err.
 *   - On other errors returns NULL and fills err.
 */
netd_pidfile_t *pidfile_acquire(const char *path, int *held, int *stale,
                                char *err, size_t errlen);

/* Release the lock and remove the file (only if it still holds our PID). */
void pidfile_release(netd_pidfile_t *pf);

/* Read the PID stored in the file. Returns 0 and sets *pid on success. */
int pidfile_read_pid(const char *path, long *pid);

#endif /* NETD_PIDFILE_H */
