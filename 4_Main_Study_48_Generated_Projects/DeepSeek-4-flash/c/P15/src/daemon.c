/*
 * daemon.c -- daemonization with a readiness pipe: the parent exits 0 only
 * after the child has fully initialized (config parsed, storage ready,
 * listener bound, PID locked).
 */
#include "daemon.h"

#include <fcntl.h>
#include <stdlib.h>
#include <sys/stat.h>
#include <unistd.h>

int daemonize_start(int *ready_wfd) {
    int p[2];
    if (pipe(p) != 0) return -1;

    pid_t pid = fork();
    if (pid < 0) {
        close(p[0]);
        close(p[1]);
        return -1;
    }

    if (pid > 0) {
        /* Parent: wait for the child's readiness byte. */
        close(p[1]);
        char c = '1';
        ssize_t n = read(p[0], &c, 1);
        close(p[0]);
        if (n == 1 && c == '0') _exit(0);
        _exit(1);
    }

    /* Child. */
    close(p[0]);
    if (setsid() < 0) _exit(1);

    int devnull = open("/dev/null", O_RDWR);
    if (devnull >= 0) {
        dup2(devnull, STDIN_FILENO);
        dup2(devnull, STDOUT_FILENO);
        dup2(devnull, STDERR_FILENO);
        if (devnull > 2) close(devnull);
    }

    *ready_wfd = p[1];
    return 0;
}

void daemon_notify(int ready_wfd, int ok) {
    char c = ok ? '0' : '1';
    ssize_t n = write(ready_wfd, &c, 1);
    (void)n;
    close(ready_wfd);
}
