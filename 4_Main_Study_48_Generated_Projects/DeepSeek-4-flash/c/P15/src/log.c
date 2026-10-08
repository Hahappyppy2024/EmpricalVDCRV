/*
 * log.c -- bounded line-oriented structured event logger.
 * Tokens, Base64 values, and decoded values are never logged.
 */
#include "log.h"

#include <errno.h>
#include <limits.h>
#include <pthread.h>
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

#include "metrics.h"
#include "netd.h"
#include "util.h"

#define LOG_LINE_MAX 4096

struct netd_logger {
    char path[PATH_MAX];
    FILE *fp;
    pthread_mutex_t lock;
    int strict_audit;
    int flush_always;
    int to_stderr;
    unsigned long long errors;
    int open_ok;
};

netd_logger_t *log_open(const char *path, int strict_audit, int flush_always,
                        int to_stderr, char *err, size_t errlen) {
    netd_logger_t *lg = calloc(1, sizeof(*lg));
    if (lg == NULL) {
        snprintf(err, errlen, "out of memory");
        return NULL;
    }
    snprintf(lg->path, sizeof(lg->path), "%s", path);
    lg->strict_audit = strict_audit;
    lg->flush_always = flush_always;
    lg->to_stderr = to_stderr;
    pthread_mutex_init(&lg->lock, NULL);

    FILE *fp = fopen(path, "a");
    if (fp == NULL) {
        snprintf(err, errlen, "cannot open log '%s': %s", path,
                 strerror(errno));
        if (strict_audit) {
            pthread_mutex_destroy(&lg->lock);
            free(lg);
            return NULL;
        }
        lg->open_ok = 0;
    } else {
        lg->open_ok = 1;
        lg->fp = fp;
        setvbuf(fp, NULL, _IOLBF, 0);
    }
    return lg;
}

void log_flush(netd_logger_t *lg) {
    if (lg == NULL) return;
    pthread_mutex_lock(&lg->lock);
    if (lg->fp != NULL) {
        fflush(lg->fp);
        fsync(fileno(lg->fp));
    }
    pthread_mutex_unlock(&lg->lock);
}

void log_close(netd_logger_t *lg) {
    if (lg == NULL) return;
    log_flush(lg);
    pthread_mutex_lock(&lg->lock);
    if (lg->fp != NULL) {
        fclose(lg->fp);
        lg->fp = NULL;
    }
    pthread_mutex_unlock(&lg->lock);
    pthread_mutex_destroy(&lg->lock);
    free(lg);
}

int log_reopen(netd_logger_t *lg) {
    if (lg == NULL) return -1;
    pthread_mutex_lock(&lg->lock);
    if (lg->fp != NULL) {
        fflush(lg->fp);
        if (fclose(lg->fp) != 0) {
            pthread_mutex_unlock(&lg->lock);
            return -1;
        }
        lg->fp = NULL;
    }
    FILE *fp = fopen(lg->path, "a");
    if (fp == NULL) {
        lg->open_ok = 0;
        pthread_mutex_unlock(&lg->lock);
        return -1;
    }
    lg->open_ok = 1;
    lg->fp = fp;
    setvbuf(fp, NULL, _IOLBF, 0);
    pthread_mutex_unlock(&lg->lock);
    return 0;
}

static void record_write_fail(netd_logger_t *lg) {
    lg->errors++;
    metric_inc(M_LOG_ERRORS);
}

void log_event(netd_logger_t *lg, const char *ev, const char *code,
               const char *conn, const char *principal,
               const char *ns, const char *key,
               const char *extra_fmt, ...) {
    if (lg == NULL) return;
    char buf[LOG_LINE_MAX];
    char extra[LOG_LINE_MAX / 2];
    extra[0] = '\0';

    if (extra_fmt != NULL) {
        va_list ap;
        va_start(ap, extra_fmt);
        vsnprintf(extra, sizeof(extra), extra_fmt, ap);
        va_end(ap);
    }

    snprintf(buf, sizeof(buf),
             "ts=%lld ev=%s code=%s conn=%s principal=%s ns=%s key=%s%s%s\n",
             (long long)util_now_epoch(),
             ev ? ev : "-", code ? code : "-",
             conn ? conn : "-", principal ? principal : "-",
             ns ? ns : "-", key ? key : "-",
             extra[0] ? " " : "", extra);

    pthread_mutex_lock(&lg->lock);
    if (lg->fp != NULL) {
        if (fputs(buf, lg->fp) == EOF || fflush(lg->fp) == EOF) {
            record_write_fail(lg);
        } else if (lg->flush_always) {
            if (fsync(fileno(lg->fp)) != 0) record_write_fail(lg);
        }
    } else if (lg->strict_audit) {
        record_write_fail(lg);
    }
    pthread_mutex_unlock(&lg->lock);

    if (lg->to_stderr) {
        fputs(buf, stderr);
        fflush(stderr);
    }
}

void log_stderr(netd_logger_t *lg, const char *fmt, ...) {
    if (lg == NULL) return;
    char buf[LOG_LINE_MAX];
    va_list ap;
    va_start(ap, fmt);
    vsnprintf(buf, sizeof(buf), fmt, ap);
    va_end(ap);

    pthread_mutex_lock(&lg->lock);
    if (lg->fp != NULL) {
        if (fprintf(lg->fp, "ts=%lld ev=STDERR code=- conn=- principal=- "
                     "ns=- key=- msg=%s\n",
                     (long long)util_now_epoch(), buf) < 0 ||
            fflush(lg->fp) == EOF) {
            record_write_fail(lg);
        } else if (lg->flush_always) {
            if (fsync(fileno(lg->fp)) != 0) record_write_fail(lg);
        }
    }
    pthread_mutex_unlock(&lg->lock);

    if (lg->to_stderr) {
        fprintf(stderr, "%s\n", buf);
        fflush(stderr);
    }
}

unsigned long long log_error_count(const netd_logger_t *lg) {
    return lg ? lg->errors : 0;
}
