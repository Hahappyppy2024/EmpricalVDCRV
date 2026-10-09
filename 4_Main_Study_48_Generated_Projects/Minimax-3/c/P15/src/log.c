#include "netd/log.h"
#include "netd/util.h"

#include <ctype.h>
#include <errno.h>
#include <fcntl.h>
#include <pthread.h>
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <time.h>
#include <unistd.h>

#ifndef O_CLOEXEC
#define O_CLOEXEC 0
#endif

struct netd_logger {
    char path[256];
    int fd;
    bool strict;
    bool console;
    pthread_mutex_t lock;
    bool valid;
};

static void close_fd(int *fd)
{
    if (*fd >= 0) {
        close(*fd);
        *fd = -1;
    }
}

netd_logger_t *netd_logger_create(const char *path, bool strict)
{
    if (path == NULL || path[0] == '\0') {
        return NULL;
    }
    netd_logger_t *l = calloc(1, sizeof(*l));
    if (l == NULL) {
        return NULL;
    }
    netd_str_copy(l->path, sizeof(l->path), path);
    l->fd = -1;
    l->strict = strict;
    l->console = false;
    pthread_mutex_init(&l->lock, NULL);
    l->valid = false;
    {
        char tmp[512];
        if (netd_path_dirname(tmp, sizeof(tmp), path) != 0) {
            tmp[0] = '.';
            tmp[1] = '\0';
        }
        netd_ensure_directory(tmp);
    }
    l->fd = open(path, O_WRONLY | O_CREAT | O_APPEND | O_CLOEXEC, 0640);
    if (l->fd < 0) {
        if (strict) {
            free(l);
            return NULL;
        }
    } else {
        l->valid = true;
    }
    return l;
}

void netd_logger_destroy(netd_logger_t *logger)
{
    if (logger == NULL) {
        return;
    }
    if (logger->fd >= 0) {
        close(logger->fd);
    }
    pthread_mutex_destroy(&logger->lock);
    free(logger);
}

int netd_logger_reopen(netd_logger_t *logger)
{
    if (logger == NULL) {
        return -1;
    }
    pthread_mutex_lock(&logger->lock);
    close_fd(&logger->fd);
    int new_fd = open(logger->path, O_WRONLY | O_CREAT | O_APPEND | O_CLOEXEC,
                      0640);
    if (new_fd < 0) {
        pthread_mutex_unlock(&logger->lock);
        return -1;
    }
    logger->fd = new_fd;
    logger->valid = true;
    pthread_mutex_unlock(&logger->lock);
    return 0;
}

void netd_logger_set_console(netd_logger_t *logger, bool enabled)
{
    if (logger == NULL) {
        return;
    }
    pthread_mutex_lock(&logger->lock);
    logger->console = enabled;
    pthread_mutex_unlock(&logger->lock);
}

const char *netd_logger_path(const netd_logger_t *logger)
{
    return logger ? logger->path : "";
}

static const char *level_name(netd_log_level_t l)
{
    switch (l) {
    case NETD_LOG_LEVEL_ERROR: return "ERROR";
    case NETD_LOG_LEVEL_WARN: return "WARN";
    case NETD_LOG_LEVEL_INFO: return "INFO";
    case NETD_LOG_LEVEL_DEBUG: return "DEBUG";
    }
    return "INFO";
}

static int write_all(int fd, const char *buf, size_t len)
{
    while (len > 0) {
        ssize_t n = write(fd, buf, len);
        if (n < 0) {
            if (errno == EINTR) {
                continue;
            }
            return -1;
        }
        buf += n;
        len -= (size_t)n;
    }
    return 0;
}

int netd_logger_write(netd_logger_t *logger,
                      netd_log_level_t level,
                      const char *event,
                      const char *outcome,
                      const char *fmt, ...)
{
    if (logger == NULL || event == NULL || outcome == NULL) {
        return -1;
    }
    int64_t ts = netd_now_seconds();
    struct tm tmv;
    gmtime_r(&ts, &tmv) ? (void)0 : (void)0;
    char tbuf[32];
    strftime(tbuf, sizeof(tbuf), "%Y-%m-%dT%H:%M:%SZ", &tmv);

    char msg[1024];
    va_list ap;
    va_start(ap, fmt);
    int msg_len = vsnprintf(msg, sizeof(msg), fmt ? fmt : "%s", ap);
    va_end(ap);
    if (msg_len < 0) {
        msg[0] = '\0';
        msg_len = 0;
    }
    if ((size_t)msg_len >= sizeof(msg)) {
        msg[sizeof(msg) - 1] = '\0';
        msg_len = (int)strlen(msg);
    }
    for (int i = 0; i < msg_len; i++) {
        if (msg[i] == '\n' || msg[i] == '\r') {
            msg[i] = ' ';
        }
    }

    char line[1280];
    int n = snprintf(line, sizeof(line),
                     "ts=%s level=%s event=%s outcome=%s msg=\"%s\"\n",
                     tbuf, level_name(level), event, outcome, msg);
    if (n < 0) {
        return -1;
    }
    if ((size_t)n >= sizeof(line)) {
        n = (int)sizeof(line) - 1;
        line[n] = '\0';
    }

    pthread_mutex_lock(&logger->lock);
    int err = 0;
    if (logger->fd >= 0) {
        if (write_all(logger->fd, line, (size_t)n) != 0) {
            err = -1;
            logger->valid = false;
        }
    }
    if (logger->console || logger->fd < 0) {
        fputs(line, stderr);
        fflush(stderr);
    }
    pthread_mutex_unlock(&logger->lock);
    return err;
}

void netd_logger_record_error(netd_logger_t *logger)
{
    (void)logger;
}