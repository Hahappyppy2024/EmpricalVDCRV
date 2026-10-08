/*
 * signal.c -- async-signal-safe self-pipe signal bridge.
 * Handlers only write a byte; all real work happens in the control loop.
 */
#include "sigs.h"

#include <errno.h>
#include <fcntl.h>
#include <signal.h>
#include <string.h>
#include <unistd.h>

static int g_sigpipe_w = -1;

static void handler(int sig) {
    unsigned char b;
    switch (sig) {
        case SIGTERM:
        case SIGINT:
            b = (unsigned char)NETD_SIG_STOP;
            break;
        case SIGHUP:
            b = (unsigned char)NETD_SIG_RELOAD;
            break;
        case SIGUSR1:
            b = (unsigned char)NETD_SIG_REOPEN;
            break;
        default:
            return;
    }
    ssize_t n = write(g_sigpipe_w, &b, 1);
    (void)n;
}

int signal_setup(int *out_read_fd) {
    int p[2];
    if (pipe(p) != 0) return -1;
    g_sigpipe_w = p[1];
    *out_read_fd = p[0];

    /* Non-blocking so signal_poll never stalls on an empty pipe and the
     * async-signal-safe handler never blocks on a full one. */
    int fl = fcntl(p[0], F_GETFL, 0);
    fcntl(p[0], F_SETFL, fl | O_NONBLOCK);
    fl = fcntl(p[1], F_GETFL, 0);
    fcntl(p[1], F_SETFL, fl | O_NONBLOCK);

    struct sigaction sa;
    memset(&sa, 0, sizeof(sa));
    sa.sa_handler = handler;
    sigemptyset(&sa.sa_mask);
    sa.sa_flags = 0;

    if (sigaction(SIGTERM, &sa, NULL) != 0 ||
        sigaction(SIGINT, &sa, NULL) != 0 ||
        sigaction(SIGHUP, &sa, NULL) != 0 ||
        sigaction(SIGUSR1, &sa, NULL) != 0) {
        return -1;
    }

    struct sigaction ign;
    memset(&ign, 0, sizeof(ign));
    ign.sa_handler = SIG_IGN;
    sigemptyset(&ign.sa_mask);
    if (sigaction(SIGPIPE, &ign, NULL) != 0) return -1;

    return 0;
}

int signal_poll(int read_fd) {
    int last = NETD_SIG_NONE;
    unsigned char b;
    for (;;) {
        ssize_t n = read(read_fd, &b, 1);
        if (n == 1) {
            last = (int)b;
            continue;
        }
        break;
    }
    return last;
}

void signal_restore(void) {
    struct sigaction sa;
    memset(&sa, 0, sizeof(sa));
    sa.sa_handler = SIG_DFL;
    sigemptyset(&sa.sa_mask);
    sigaction(SIGTERM, &sa, NULL);
    sigaction(SIGINT, &sa, NULL);
    sigaction(SIGHUP, &sa, NULL);
    sigaction(SIGUSR1, &sa, NULL);
    sigaction(SIGPIPE, &sa, NULL);
}
