#ifndef NETD_AUDIT_H
#define NETD_AUDIT_H

#include <stdint.h>
#include <stdbool.h>
#include "log.h"

typedef struct netd_audit netd_audit_t;

netd_audit_t *netd_audit_create(const char *path, bool strict);
void netd_audit_destroy(netd_audit_t *audit);
int netd_audit_reopen(netd_audit_t *audit);
int netd_audit_emit(netd_audit_t *audit,
                    int64_t timestamp,
                    const char *event_type,
                    const char *outcome,
                    const char *connection_id,
                    int64_t principal_id,
                    const char *namespace,
                    const char *key,
                    const char *details);
void netd_audit_bind_logger(netd_audit_t *audit, netd_logger_t *logger);

#endif