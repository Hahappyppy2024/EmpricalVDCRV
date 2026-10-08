/*
 * sigs.h -- async-signal-safe signal bridge (self-pipe trick).
 * No parsing, allocation, logging, or SQLite work happens in handlers.
 *
 * Named `sigs.h` (not `signal.h`) so that <signal.h> always resolves to the
 * system header even with -I include on the search path.
 */
#ifndef NETD_SIGS_H
#define NETD_SIGS_H

#define NETD_SIG_NONE 0
#define NETD_SIG_STOP 1   /* SIGTERM / SIGINT */
#define NETD_SIG_RELOAD 2 /* SIGHUP */
#define NETD_SIG_REOPEN 3 /* SIGUSR1 */

/* Install handlers and create the self-pipe. Returns the read fd or -1. */
int signal_setup(int *out_read_fd);

/* Read all pending signal numbers; returns the last one or NETD_SIG_NONE. */
int signal_poll(int read_fd);

/* Restore default handlers (before exit). */
void signal_restore(void);

#endif /* NETD_SIGS_H */
