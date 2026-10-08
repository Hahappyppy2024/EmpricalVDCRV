/*
 * pidfile.c -- PID-file locking (fcntl) with stale-PID recovery.
 */
#include "pidfile.h"

#include <errno.h>
#include <fcntl.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/file.h>
#include <unistd.h>

#include "util.h"

int pidfile_read_pid(const char *path, long *pid) {
    FILE *f = fopen(path, "r");
    if (f == NULL) return -1;
    long p = 0;
    if (fscanf(f, "%ld", &p) != 1) p = 0;
    fclose(f);
    if (p <= 0) return -1;
    *pid = p;
    return 0;
}

static int proc_is_netd(long pid) {
    char name[64];
    if (util_proc_name(pid, name, sizeof(name)) != 0) return 0;
    return strncmp(name, "netd", 4) == 0;
}

netd_pidfile_t *pidfile_acquire(const char *path, int *held, int *stale,
                                char *err, size_t errlen) {
    *held = 0;
    *stale = 0;

    if (util_mkdirs_file(path) != 0) {
        snprintf(err, errlen, "cannot create runtime directory for '%s': %s",
                 path, strerror(errno));
        return NULL;
    }

    int fd = open(path, O_RDWR | O_CREAT | O_CLOEXEC, 0644);
    if (fd < 0) {
        snprintf(err, errlen, "cannot open pid file '%s': %s", path,
                 strerror(errno));
        return NULL;
    }

    struct flock fl;
    memset(&fl, 0, sizeof(fl));
    fl.l_type = F_WRLCK;
    fl.l_whence = SEEK_SET;

    if (fcntl(fd, F_SETLK, &fl) != 0) {
        /* Lock is held by a live instance. */
        close(fd);
        *held = 1;
        long pid = 0;
        if (pidfile_read_pid(path, &pid) == 0 && util_pid_alive(pid)) {
            snprintf(err, errlen,
                     "netd is already running (pid %ld); second instance "
                     "rejected", pid);
        } else {
            snprintf(err, errlen,
                     "netd pid file '%s' is locked by another process", path);
        }
        return NULL;
    }

    /* We hold the lock. Confirm any pre-existing PID is absent before
     * replacing the file contents. */
    long old = 0;
    if (pidfile_read_pid(path, &old) == 0 && old > 0 && old != (long)getpid()) {
        if (util_pid_alive(old)) {
            if (proc_is_netd(old)) {
                close(fd);
                *held = 1;
                snprintf(err, errlen,
                         "netd (pid %ld) appears to be running; refusing to "
                         "replace its pid file", old);
                return NULL;
            }
            /* A live but unrelated process: stale from a recycled PID. */
        }
        *stale = 1;
    }

    if (ftruncate(fd, 0) != 0 || lseek(fd, 0, SEEK_SET) != 0) {
        snprintf(err, errlen, "cannot reset pid file '%s': %s", path,
                 strerror(errno));
        close(fd);
        return NULL;
    }

    char buf[32];
    int n = snprintf(buf, sizeof(buf), "%ld\n", (long)getpid());
    if (write(fd, buf, (size_t)n) != n) {
        snprintf(err, errlen, "cannot write pid file '%s': %s", path,
                 strerror(errno));
        close(fd);
        return NULL;
    }

    netd_pidfile_t *pf = malloc(sizeof(*pf));
    if (pf == NULL) {
        close(fd);
        snprintf(err, errlen, "out of memory");
        return NULL;
    }
    pf->fd = fd;
    pf->pid = (long)getpid();
    snprintf(pf->path, sizeof(pf->path), "%s", path);
    return pf;
}

void pidfile_release(netd_pidfile_t *pf) {
    if (pf == NULL) return;
    long now = 0;
    if (pidfile_read_pid(pf->path, &now) == 0 && now == pf->pid) {
        unlink(pf->path);
    }
    close(pf->fd);
    free(pf);
}
