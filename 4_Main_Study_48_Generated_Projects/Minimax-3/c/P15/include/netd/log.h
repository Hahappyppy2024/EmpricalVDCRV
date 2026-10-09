#ifndef NETD_LOG_H
#define NETD_LOG_H

#include <stdint.h>
#include <stdbool.h>
#include "common.h"

typedef enum {
    NETD_LOG_LEVEL_ERROR = 0,
    NETD_LOG_LEVEL_WARN = 1,
    NETD_LOG_LEVEL_INFO = 2,
    NETD_LOG_LEVEL_DEBUG = 3
} netd_log_level_t;

typedef struct netd_logger netd_logger_t;

netd_logger_t *netd_logger_create(const char *path, bool strict);
void netd_logger_destroy(netd_logger_t *logger);
int netd_logger_reopen(netd_logger_t *logger);
int netd_logger_write(netd_logger_t *logger,
                      netd_log_level_t level,
                      const char *event,
                      const char *outcome,
                      const char *fmt, ...);
void netd_logger_set_console(netd_logger_t *logger, bool enabled);
const char *netd_logger_path(const netd_logger_t *logger);
void netd_logger_record_error(netd_logger_t *logger);

#endif