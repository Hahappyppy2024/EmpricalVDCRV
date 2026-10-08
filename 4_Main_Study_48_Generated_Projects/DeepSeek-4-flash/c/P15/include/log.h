/*
 * log.h -- bounded line-oriented structured audit/event logger.
 * Credentials and stored record values are never written.
 */
#ifndef NETD_LOG_H
#define NETD_LOG_H

#include <stddef.h>

typedef struct netd_logger netd_logger_t;

/*
 * Open the structured logger at `path`. strict_audit=1 turns unwritable
 * destinations into hard failures at startup. flush_always fsyncs every line.
 * to_stderr mirrors every line to stderr (used in foreground mode).
 * Returns NULL on failure (message in err/errlen when err != NULL).
 */
netd_logger_t *log_open(const char *path, int strict_audit, int flush_always,
                        int to_stderr, char *err, size_t errlen);

void log_close(netd_logger_t *lg);

/* Close and reopen the configured path between complete records (SIGUSR1). */
int log_reopen(netd_logger_t *lg);

/*
 * Emit one structured line:
 *   ts=<epoch> ev=<ev> code=<code> conn=<conn> principal=<principal>
 *   ns=<ns> key=<key> <extra>
 * `conn`, `principal`, `ns`, `key` may be NULL or "-". `extra` is a printf
 * format producing further space-separated key=value pairs (may be NULL).
 * Each event is one bounded line.
 */
void log_event(netd_logger_t *lg, const char *ev, const char *code,
               const char *conn, const char *principal,
               const char *ns, const char *key,
               const char *extra_fmt, ...);

/* Free-text message to stderr (foreground mode) and to the log as ev=STARTUP/
 * ev=... informational line. */
void log_stderr(netd_logger_t *lg, const char *fmt, ...);

/* Flush buffered records and fsync (used before exit). */
void log_flush(netd_logger_t *lg);

/* Running total of log write failures (fed into the metrics registry). */
unsigned long long log_error_count(const netd_logger_t *lg);

#endif /* NETD_LOG_H */
