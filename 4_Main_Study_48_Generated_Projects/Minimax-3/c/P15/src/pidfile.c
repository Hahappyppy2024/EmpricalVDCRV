#include "netd/pidfile.h"
#include "netd/util.h"

#include <ctype.h>
#include <errno.h>
#include <fcntl.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <unistd.h>

#ifdef _WIN32
#include <windows.h>
#include <io.h>
#else
#include <sys/file.h>
#include <signal.h>
#endif

#ifndef O_CLOEXEC
#define O_CLOEXEC 0
#endif

static int read_existing_pid(const char *path, int *out_pid)
{
    FILE *fp = fopen(path, "r");
    if (fp == NULL) {
        return -1;
    }
    char buf[32];
    size_t nread = fread(buf, 1, sizeof(buf) - 1, fp);
    fclose(fp);
    if (nread == 0) {
        return -1;
    }
    buf[nread] = '\0';
    char *end = NULL;
    long v = strtol(buf, &end, 10);
    if (end == buf || v <= 0) {
        return -1;
    }
    *out_pid = (int)v;
    return 0;
}

int netd_pid_is_live(int pid)
{
    if (pid <= 0) {
        return 0;
    }
#ifdef _WIN32
    HANDLE h = OpenProcess(PROCESS_QUERY_LIMITED_INFORMATION, FALSE, (DWORD)pid);
    if (h != NULL) {
        DWORD code = 0;
        GetExitCodeProcess(h, &code);
        CloseHandle(h);
        if (code == STILL_ACTIVE) {
            return 1;
        }
    }
    return 0;
#else
    if (kill((pid_t)pid, 0) == 0) {
        return 1;
    }
    if (errno == EPERM) {
        return 1;
    }
    return 0;
#endif
}

int netd_pid_acquire(const char *path, int *out_existing_pid,
                     netd_pid_status_t *out_status)
{
    if (path == NULL) {
        if (out_status != NULL) {
            *out_status = NETD_PID_ERR_OPEN;
        }
        return -1;
    }
    char dir[512];
    if (netd_path_dirname(dir, sizeof(dir), path) != 0) {
        if (out_status != NULL) {
            *out_status = NETD_PID_ERR_OPEN;
        }
        return -1;
    }
    netd_ensure_directory(dir);

    int fd = open(path, O_RDWR | O_CREAT | O_CLOEXEC, 0640);
    if (fd < 0) {
        if (out_status != NULL) {
            *out_status = NETD_PID_ERR_OPEN;
        }
        return -1;
    }

#ifdef _WIN32
    /* Windows: use LockFileEx for cross-process advisory locking.
     * We attempt a non-blocking lock; if it fails because another
     * process holds it, we report ALREADY_RUNNING. */
    OVERLAPPED ov;
    memset(&ov, 0, sizeof(ov));
    if (!LockFileEx((HANDLE)_get_osfhandle(fd), LOCKFILE_FAIL_IMMEDIATELY,
                    0, 0xFFFFFFFF, 0, &ov)) {
        DWORD err = GetLastError();
        if (err == ERROR_LOCK_VIOLATION || err == ERROR_IO_PENDING) {
            int existing = 0;
            if (read_existing_pid(path, &existing) == 0 &&
                netd_pid_is_live(existing)) {
                if (out_status != NULL) {
                    *out_status = NETD_PID_ERR_LIVE;
                }
                if (out_existing_pid != NULL) {
                    *out_existing_pid = existing;
                }
                close(fd);
                return -1;
            }
            /* stale lock holder — overwrite */
            ftruncate(fd, 0);
            lseek(fd, 0, SEEK_SET);
        } else {
            close(fd);
            if (out_status != NULL) {
                *out_status = NETD_PID_ERR_LOCK;
            }
            return -1;
        }
    }
#else
    struct flock fl;
    memset(&fl, 0, sizeof(fl));
    fl.l_type = F_WRLCK;
    fl.l_whence = SEEK_SET;
    fl.l_start = 0;
    fl.l_len = 0;
    if (fcntl(fd, F_SETLK, &fl) != 0) {
        if (errno == EAGAIN || errno == EACCES) {
            int existing = 0;
            if (read_existing_pid(path, &existing) == 0 &&
                netd_pid_is_live(existing)) {
                if (out_status != NULL) {
                    *out_status = NETD_PID_ERR_LIVE;
                }
                if (out_existing_pid != NULL) {
                    *out_existing_pid = existing;
                }
                close(fd);
                return -1;
            }
            ftruncate(fd, 0);
            lseek(fd, 0, SEEK_SET);
        } else {
            close(fd);
            if (out_status != NULL) {
                *out_status = NETD_PID_ERR_LOCK;
            }
            return -1;
        }
    }
#endif

    int existing = 0;
    if (read_existing_pid(path, &existing) == 0 && netd_pid_is_live(existing) &&
        existing != getpid()) {
#ifdef _WIN32
        OVERLAPPED ovu;
        memset(&ovu, 0, sizeof(ovu));
        UnlockFileEx((HANDLE)_get_osfhandle(fd), 0, 0xFFFFFFFF, 0, &ovu);
#else
        struct flock flu;
        memset(&flu, 0, sizeof(flu));
        flu.l_type = F_UNLCK;
        flu.l_whence = SEEK_SET;
        flu.l_start = 0;
        flu.l_len = 0;
        fcntl(fd, F_SETLK, &flu);
#endif
        close(fd);
        if (out_status != NULL) {
            *out_status = NETD_PID_ERR_LIVE;
        }
        if (out_existing_pid != NULL) {
            *out_existing_pid = existing;
        }
        return -1;
    }

    ftruncate(fd, 0);
    lseek(fd, 0, SEEK_SET);
    char pidbuf[32];
    int n = snprintf(pidbuf, sizeof(pidbuf), "%d\n", (int)getpid());
    if (n > 0) {
        ssize_t w = write(fd, pidbuf, (size_t)n);
        (void)w;
    }
#ifdef _WIN32
    _commit(fd);
#else
    fsync(fd);
#endif
    if (out_status != NULL) {
        *out_status = NETD_PID_OK;
    }
    return fd;
}

int netd_pid_release(const char *path, int acquired_fd, int stored_pid)
{
    if (acquired_fd >= 0) {
#ifdef _WIN32
        OVERLAPPED ovu;
        memset(&ovu, 0, sizeof(ovu));
        UnlockFileEx((HANDLE)_get_osfhandle(acquired_fd), 0, 0xFFFFFFFF, 0,
                     &ovu);
#else
        struct flock fl;
        memset(&fl, 0, sizeof(fl));
        fl.l_type = F_UNLCK;
        fl.l_whence = SEEK_SET;
        fl.l_start = 0;
        fl.l_len = 0;
        fcntl(acquired_fd, F_SETLK, &fl);
#endif
        close(acquired_fd);
    }
    if (path != NULL && stored_pid == getpid()) {
        unlink(path);
    }
    return 0;
}

int netd_pid_remove_file(const char *path)
{
    if (path == NULL) {
        return -1;
    }
    return unlink(path);
}

const char *netd_pid_status_string(netd_pid_status_t s)
{
    switch (s) {
    case NETD_PID_OK: return "OK";
    case NETD_PID_ERR_OPEN: return "OPEN_FAILED";
    case NETD_PID_ERR_LOCK: return "LOCK_FAILED";
    case NETD_PID_ERR_STALE: return "STALE";
    case NETD_PID_ERR_LIVE: return "ALREADY_RUNNING";
    }
    return "UNKNOWN";
}