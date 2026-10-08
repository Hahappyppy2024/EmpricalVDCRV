/*
 * daemon.h -- classic double-fork-style daemonization with a readiness pipe.
 * The parent does not report success until the child has fully initialized.
 */
#ifndef NETD_DAEMON_H
#define NETD_DAEMON_H

/*
 * Daemonize. In the parent this function never returns: it reads the
 * readiness byte from the pipe and _exit()s with 0 (child ready) or 1
 * (child failed). In the child it returns 0 and sets *ready_wfd to the write
 * end of the readiness pipe. Returns -1 only when fork/pipe fails (parent).
 */
int daemonize_start(int *ready_wfd);

/* Child signals readiness (1 = ok, 0 = failed) and closes the pipe. */
void daemon_notify(int ready_wfd, int ok);

#endif /* NETD_DAEMON_H */
