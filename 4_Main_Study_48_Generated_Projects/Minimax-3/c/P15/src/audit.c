#include "netd/audit.h"
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

struct netd_audit {
    char path[256];
    int fd;
    bool strict;
    pthread_mutex_t lock;
    bool valid;
    netd_logger_t *logger;
};

static void close_fd(int *fd)
{
    if (*fd >= 0) {
        close(*fd);
        *fd = -1;
    }
}

netd_audit_t *netd_audit_create(const char *path, bool strict)
{
    if (path == NULL || path[0] == '\0') {
        return NULL;
    }
    netd_audit_t *a = calloc(1, sizeof(*a));
    if (a == NULL) {
        return NULL;
    }
    netd_str_copy(a->path, sizeof(a->path), path);
    a->fd = -1;
    a->strict = strict;
    pthread_mutex_init(&a->lock, NULL);
    a->valid = false;
    a->logger = NULL;

    {
        char tmp[512];
        if (netd_path_dirname(tmp, sizeof(tmp), path) != 0) {
            tmp[0] = '.';
            tmp[1] = '\0';
        }
        netd_ensure_directory(tmp);
    }

    a->fd = open(path, O_WRONLY | O_CREAT | O_APPEND | O_CLOEXEC, 0640);
    if (a->fd < 0 && strict) {
        pthread_mutex_destroy(&a->lock);
        free(a);
        return NULL;
    }
    if (a->fd >= 0) {
        a->valid = true;
    }
    return a;
}

void netd_audit_destroy(netd_audit_t *audit)
{
    if (audit == NULL) {
        return;
    }
    close_fd(&audit->fd);
    pthread_mutex_destroy(&audit->lock);
    free(audit);
}

int netd_audit_reopen(netd_audit_t *audit)
{
    if (audit == NULL) {
        return -1;
    }
    pthread_mutex_lock(&audit->lock);
    close_fd(&audit->fd);
    int new_fd = open(audit->path, O_WRONLY | O_CREAT | O_APPEND | O_CLOEXEC,
                      0640);
    if (new_fd < 0) {
        audit->valid = false;
        pthread_mutex_unlock(&audit->lock);
        return -1;
    }
    audit->fd = new_fd;
    audit->valid = true;
    pthread_mutex_unlock(&audit->lock);
    return 0;
}

void netd_audit_bind_logger(netd_audit_t *audit, netd_logger_t *logger)
{
    if (audit == NULL) {
        return;
    }
    audit->logger = logger;
}

static void sanitize(char *s)
{
    if (s == NULL) {
        return;
    }
    for (char *p = s; *p; p++) {
        if (*p == '\n' || *p == '\r' || *p == '\t') {
            *p = ' ';
        }
        if ((unsigned char)*p < 0x20) {
            *p = ' ';
        }
    }
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

int netd_audit_emit(netd_audit_t *audit,
                    int64_t timestamp,
                    const char *event_type,
                    const char *outcome,
                    const char *connection_id,
                    int64_t principal_id,
                    const char *namespace,
                    const char *key,
                    const char *details)
{
    if (audit == NULL || event_type == NULL || outcome == NULL) {
        return -1;
    }
    if (timestamp <= 0) {
        timestamp = netd_now_seconds();
    }
    struct tm tmv;
    gmtime_r(&timestamp, &tmv);
    char tbuf[32];
    strftime(tbuf, sizeof(tbuf), "%Y-%m-%dT%H:%M:%SZ", &tmv);

    char cid_copy[32] = "";
    if (connection_id != NULL) {
        netd_str_copy(cid_copy, sizeof(cid_copy), connection_id);
        sanitize(cid_copy);
    }
    char ns_copy[80] = "";
    if (namespace != NULL) {
        netd_str_copy(ns_copy, sizeof(ns_copy), namespace);
        sanitize(ns_copy);
    }
    char key_copy[160] = "";
    if (key != NULL) {
        netd_str_copy(key_copy, sizeof(key_copy), key);
        sanitize(key_copy);
    }
    char det_copy[256] = "";
    if (details != NULL) {
        netd_str_copy(det_copy, sizeof(det_copy), details);
        sanitize(det_copy);
    }

    char line[1024];
    int n;
    if (principal_id > 0) {
        n = snprintf(line, sizeof(line),
                     "ts=%s event=%s outcome=%s connection_id=%s "
                     "principal_id=%lld namespace=%s key=%s details=\"%s\"\n",
                     tbuf, event_type, outcome, cid_copy[0] ? cid_copy : "-",
                     (long long)principal_id, ns_copy[0] ? ns_copy : "-",
                     key_copy[0] ? key_copy : "-", det_copy);
    } else {
        n = snprintf(line, sizeof(line),
                     "ts=%s event=%s outcome=%s connection_id=%s "
                     "principal_id=- namespace=%s key=%s details=\"%s\"\n",
                     tbuf, event_type, outcome, cid_copy[0] ? cid_copy : "-",
                     ns_copy[0] ? ns_copy : "-", key_copy[0] ? key_copy : "-",
                     det_copy);
    }
    if (n < 0) {
        return -1;
    }
    if ((size_t)n >= sizeof(line)) {
        n = (int)sizeof(line) - 1;
    }

    pthread_mutex_lock(&audit->lock);
    int err = 0;
    if (audit->fd >= 0) {
        if (write_all(audit->fd, line, (size_t)n) != 0) {
            err = -1;
            audit->valid = false;
        }
    }
    pthread_mutex_unlock(&audit->lock);

    if (err != 0 && audit->logger != NULL) {
        netd_logger_write(audit->logger, NETD_LOG_LEVEL_ERROR,
                          NETD_EVENT_OUTCOME_FAIL, "AUDIT_WRITE",
                          "audit write failed path=%s", audit->path);
    }
    return err;
}