#include "netd/config.h"
#include "netd/util.h"

#include <ctype.h>
#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

void netd_config_set_defaults(netd_config_t *cfg)
{
    memset(cfg, 0, sizeof(*cfg));
    netd_str_copy(cfg->bind_address, sizeof(cfg->bind_address), NETD_DEFAULT_BIND);
    cfg->port = NETD_DEFAULT_PORT;
    netd_str_copy(cfg->database_path, sizeof(cfg->database_path), NETD_DEFAULT_DB_PATH);
    netd_str_copy(cfg->log_path, sizeof(cfg->log_path), NETD_DEFAULT_LOG_PATH);
    netd_str_copy(cfg->audit_log_path, sizeof(cfg->audit_log_path), NETD_DEFAULT_AUDIT_PATH);
    netd_str_copy(cfg->pid_file, sizeof(cfg->pid_file), NETD_DEFAULT_PID_FILE);
    cfg->worker_count = NETD_DEFAULT_WORKERS;
    cfg->idle_timeout_seconds = NETD_DEFAULT_IDLE_TIMEOUT;
    cfg->max_clients = NETD_DEFAULT_MAX_CLIENTS;
    cfg->max_command_bytes = NETD_DEFAULT_MAX_COMMAND_BYTES;
    cfg->expiry_scan_interval_seconds = NETD_DEFAULT_EXPIRY_INTERVAL;
    cfg->expiry_batch_size = NETD_DEFAULT_EXPIRY_BATCH;
    cfg->shutdown_timeout_seconds = NETD_DEFAULT_SHUTDOWN_TIMEOUT;
    cfg->strict_audit = true;
    cfg->database_busy_timeout_ms = 2000;
    cfg->generation = 1;
}

static int set_bool(const char *value, bool *out)
{
    if (netd_str_equal(value, "true") || netd_str_equal(value, "1") ||
        netd_str_equal(value, "yes") || netd_str_equal(value, "on")) {
        *out = true;
        return 0;
    }
    if (netd_str_equal(value, "false") || netd_str_equal(value, "0") ||
        netd_str_equal(value, "no") || netd_str_equal(value, "off")) {
        *out = false;
        return 0;
    }
    return -1;
}

bool netd_config_is_reloadable(const char *key)
{
    if (key == NULL) {
        return false;
    }
    return netd_str_equal(key, "worker_count") ||
           netd_str_equal(key, "idle_timeout_seconds") ||
           netd_str_equal(key, "max_clients") ||
           netd_str_equal(key, "max_command_bytes") ||
           netd_str_equal(key, "expiry_scan_interval_seconds") ||
           netd_str_equal(key, "expiry_batch_size") ||
           netd_str_equal(key, "shutdown_timeout_seconds") ||
           netd_str_equal(key, "strict_audit") ||
           netd_str_equal(key, "audit_log_path") ||
           netd_str_equal(key, "log_path") ||
           netd_str_equal(key, "database_busy_timeout_ms");
}

int netd_config_validate(const netd_config_t *cfg, char *err, size_t err_size)
{
    if (cfg->bind_address[0] == '\0') {
        netd_str_copy(err, err_size, "bind_address must not be empty");
        return -1;
    }
    if (cfg->port <= 0 || cfg->port > 65535) {
        netd_str_copy(err, err_size, "port must be in [1,65535]");
        return -1;
    }
    if (cfg->database_path[0] == '\0') {
        netd_str_copy(err, err_size, "database_path must not be empty");
        return -1;
    }
    if (cfg->log_path[0] == '\0') {
        netd_str_copy(err, err_size, "log_path must not be empty");
        return -1;
    }
    if (cfg->audit_log_path[0] == '\0') {
        netd_str_copy(err, err_size, "audit_log_path must not be empty");
        return -1;
    }
    if (cfg->pid_file[0] == '\0') {
        netd_str_copy(err, err_size, "pid_file must not be empty");
        return -1;
    }
    if (cfg->worker_count < 1 || cfg->worker_count > 256) {
        netd_str_copy(err, err_size, "worker_count must be in [1,256]");
        return -1;
    }
    if (cfg->idle_timeout_seconds < 1 || cfg->idle_timeout_seconds > 86400) {
        netd_str_copy(err, err_size, "idle_timeout_seconds must be in [1,86400]");
        return -1;
    }
    if (cfg->max_clients < 1 || cfg->max_clients > 8192) {
        netd_str_copy(err, err_size, "max_clients must be in [1,8192]");
        return -1;
    }
    if (cfg->max_command_bytes < 64 || cfg->max_command_bytes > 1048576) {
        netd_str_copy(err, err_size, "max_command_bytes must be in [64,1048576]");
        return -1;
    }
    if (cfg->expiry_scan_interval_seconds < 1 ||
        cfg->expiry_scan_interval_seconds > 3600) {
        netd_str_copy(err, err_size, "expiry_scan_interval_seconds must be in [1,3600]");
        return -1;
    }
    if (cfg->expiry_batch_size < 1 || cfg->expiry_batch_size > 100000) {
        netd_str_copy(err, err_size, "expiry_batch_size must be in [1,100000]");
        return -1;
    }
    if (cfg->shutdown_timeout_seconds < 1 || cfg->shutdown_timeout_seconds > 600) {
        netd_str_copy(err, err_size, "shutdown_timeout_seconds must be in [1,600]");
        return -1;
    }
    if (cfg->database_busy_timeout_ms < 0 || cfg->database_busy_timeout_ms > 60000) {
        netd_str_copy(err, err_size, "database_busy_timeout_ms must be in [0,60000]");
        return -1;
    }
    return 0;
}

const char *netd_config_status_string(netd_config_status_t s)
{
    switch (s) {
    case NETD_CONFIG_OK: return "OK";
    case NETD_CONFIG_ERR_OPEN: return "OPEN_FAILED";
    case NETD_CONFIG_ERR_LINE: return "INVALID_LINE";
    case NETD_CONFIG_ERR_UNKNOWN_KEY: return "UNKNOWN_KEY";
    case NETD_CONFIG_ERR_DUPLICATE: return "DUPLICATE_KEY";
    case NETD_CONFIG_ERR_VALUE: return "INVALID_VALUE";
    case NETD_CONFIG_ERR_RANGE: return "OUT_OF_RANGE";
    case NETD_CONFIG_ERR_REQUIRED: return "MISSING_REQUIRED";
    case NETD_CONFIG_ERR_INTERNAL: return "INTERNAL";
    }
    return "UNKNOWN";
}

static int apply_key(netd_config_t *cfg, const char *key, const char *value,
                     netd_config_status_t *status, char *err, size_t err_size,
                     bool *already_set)
{
    *already_set = false;

    if (netd_str_equal(key, "bind_address")) {
        if (cfg->set_bind_address) {
            *already_set = true;
            return 0;
        }
        if (strlen(value) >= sizeof(cfg->bind_address)) {
            *status = NETD_CONFIG_ERR_VALUE;
            netd_str_copy(err, err_size, "bind_address too long");
            return -1;
        }
        netd_str_copy(cfg->bind_address, sizeof(cfg->bind_address), value);
        cfg->set_bind_address = true;
        return 0;
    }
    if (netd_str_equal(key, "port")) {
        if (cfg->set_port) {
            *already_set = true;
            return 0;
        }
        int64_t v;
        if (!netd_parse_int(value, &v)) {
            *status = NETD_CONFIG_ERR_VALUE;
            netd_str_copy(err, err_size, "port must be integer");
            return -1;
        }
        if (v <= 0 || v > 65535) {
            *status = NETD_CONFIG_ERR_RANGE;
            netd_str_copy(err, err_size, "port out of range");
            return -1;
        }
        cfg->port = (int)v;
        cfg->set_port = true;
        return 0;
    }
    if (netd_str_equal(key, "database_path")) {
        if (cfg->set_database_path) {
            *already_set = true;
            return 0;
        }
        netd_str_copy(cfg->database_path, sizeof(cfg->database_path), value);
        cfg->set_database_path = true;
        return 0;
    }
    if (netd_str_equal(key, "log_path")) {
        if (cfg->set_log_path) {
            *already_set = true;
            return 0;
        }
        netd_str_copy(cfg->log_path, sizeof(cfg->log_path), value);
        cfg->set_log_path = true;
        return 0;
    }
    if (netd_str_equal(key, "audit_log_path")) {
        if (cfg->set_audit_log_path) {
            *already_set = true;
            return 0;
        }
        netd_str_copy(cfg->audit_log_path, sizeof(cfg->audit_log_path), value);
        cfg->set_audit_log_path = true;
        return 0;
    }
    if (netd_str_equal(key, "pid_file")) {
        if (cfg->set_pid_file) {
            *already_set = true;
            return 0;
        }
        netd_str_copy(cfg->pid_file, sizeof(cfg->pid_file), value);
        cfg->set_pid_file = true;
        return 0;
    }
    if (netd_str_equal(key, "worker_count")) {
        if (cfg->set_worker_count) {
            *already_set = true;
            return 0;
        }
        int64_t v;
        if (!netd_parse_int(value, &v)) {
            *status = NETD_CONFIG_ERR_VALUE;
            netd_str_copy(err, err_size, "worker_count must be integer");
            return -1;
        }
        cfg->worker_count = (int)v;
        cfg->set_worker_count = true;
        return 0;
    }
    if (netd_str_equal(key, "idle_timeout_seconds")) {
        if (cfg->set_idle_timeout_seconds) {
            *already_set = true;
            return 0;
        }
        int64_t v;
        if (!netd_parse_int(value, &v)) {
            *status = NETD_CONFIG_ERR_VALUE;
            return -1;
        }
        cfg->idle_timeout_seconds = (int)v;
        cfg->set_idle_timeout_seconds = true;
        return 0;
    }
    if (netd_str_equal(key, "max_clients")) {
        if (cfg->set_max_clients) {
            *already_set = true;
            return 0;
        }
        int64_t v;
        if (!netd_parse_int(value, &v)) {
            *status = NETD_CONFIG_ERR_VALUE;
            return -1;
        }
        cfg->max_clients = (int)v;
        cfg->set_max_clients = true;
        return 0;
    }
    if (netd_str_equal(key, "max_command_bytes")) {
        if (cfg->set_max_command_bytes) {
            *already_set = true;
            return 0;
        }
        int64_t v;
        if (!netd_parse_int(value, &v)) {
            *status = NETD_CONFIG_ERR_VALUE;
            return -1;
        }
        cfg->max_command_bytes = (int)v;
        cfg->set_max_command_bytes = true;
        return 0;
    }
    if (netd_str_equal(key, "expiry_scan_interval_seconds")) {
        if (cfg->set_expiry_scan_interval_seconds) {
            *already_set = true;
            return 0;
        }
        int64_t v;
        if (!netd_parse_int(value, &v)) {
            *status = NETD_CONFIG_ERR_VALUE;
            return -1;
        }
        cfg->expiry_scan_interval_seconds = (int)v;
        cfg->set_expiry_scan_interval_seconds = true;
        return 0;
    }
    if (netd_str_equal(key, "expiry_batch_size")) {
        if (cfg->set_expiry_batch_size) {
            *already_set = true;
            return 0;
        }
        int64_t v;
        if (!netd_parse_int(value, &v)) {
            *status = NETD_CONFIG_ERR_VALUE;
            return -1;
        }
        cfg->expiry_batch_size = (int)v;
        cfg->set_expiry_batch_size = true;
        return 0;
    }
    if (netd_str_equal(key, "shutdown_timeout_seconds")) {
        if (cfg->set_shutdown_timeout_seconds) {
            *already_set = true;
            return 0;
        }
        int64_t v;
        if (!netd_parse_int(value, &v)) {
            *status = NETD_CONFIG_ERR_VALUE;
            return -1;
        }
        cfg->shutdown_timeout_seconds = (int)v;
        cfg->set_shutdown_timeout_seconds = true;
        return 0;
    }
    if (netd_str_equal(key, "strict_audit")) {
        if (cfg->set_strict_audit) {
            *already_set = true;
            return 0;
        }
        bool b;
        if (set_bool(value, &b) != 0) {
            *status = NETD_CONFIG_ERR_VALUE;
            netd_str_copy(err, err_size, "strict_audit must be boolean");
            return -1;
        }
        cfg->strict_audit = b;
        cfg->set_strict_audit = true;
        return 0;
    }
    if (netd_str_equal(key, "database_busy_timeout_ms")) {
        if (cfg->set_database_busy_timeout_ms) {
            *already_set = true;
            return 0;
        }
        int64_t v;
        if (!netd_parse_int(value, &v)) {
            *status = NETD_CONFIG_ERR_VALUE;
            return -1;
        }
        cfg->database_busy_timeout_ms = (int)v;
        cfg->set_database_busy_timeout_ms = true;
        return 0;
    }

    *status = NETD_CONFIG_ERR_UNKNOWN_KEY;
    netd_str_copy(err, err_size, "unknown key: ");
    size_t cur = strlen(err);
    if (cur + 1 < err_size) {
        netd_str_copy(err + cur, err_size - cur, key);
    }
    return -1;
}

int netd_config_load(const char *path, netd_config_t *out,
                     netd_config_status_t *status, char *err, size_t err_size)
{
    if (status != NULL) {
        *status = NETD_CONFIG_OK;
    }
    if (err != NULL && err_size > 0) {
        err[0] = '\0';
    }

    netd_config_set_defaults(out);

    FILE *fp = fopen(path, "r");
    if (fp == NULL) {
        if (status != NULL) {
            *status = NETD_CONFIG_ERR_OPEN;
        }
        if (err != NULL && err_size > 0) {
            snprintf(err, err_size, "cannot open %s: %s", path, strerror(errno));
        }
        return -1;
    }

    char line[1024];
    int lineno = 0;
    while (fgets(line, sizeof(line), fp) != NULL) {
        lineno++;
        char *p = line;
        while (*p == ' ' || *p == '\t') {
            p++;
        }
        if (*p == '\0' || *p == '\n' || *p == '\r' || *p == '#') {
            continue;
        }
        size_t len = strlen(p);
        while (len > 0 && (p[len - 1] == '\n' || p[len - 1] == '\r' ||
                           p[len - 1] == ' ' || p[len - 1] == '\t')) {
            p[--len] = '\0';
        }
        char *eq = strchr(p, '=');
        if (eq == NULL) {
            if (status != NULL) {
                *status = NETD_CONFIG_ERR_LINE;
            }
            if (err != NULL && err_size > 0) {
                snprintf(err, err_size, "line %d: missing '='", lineno);
            }
            fclose(fp);
            return -1;
        }
        *eq = '\0';
        char *key = p;
        char *value = eq + 1;
        char *kend = key + strlen(key);
        while (kend > key && (kend[-1] == ' ' || kend[-1] == '\t')) {
            *--kend = '\0';
        }
        char *vstart = value;
        while (*vstart == ' ' || *vstart == '\t') {
            vstart++;
        }
        if (!netd_str_is_valid_token(key, 64)) {
            if (status != NULL) {
                *status = NETD_CONFIG_ERR_LINE;
            }
            if (err != NULL && err_size > 0) {
                snprintf(err, err_size, "line %d: invalid key", lineno);
            }
            fclose(fp);
            return -1;
        }

        netd_config_status_t st = NETD_CONFIG_OK;
        bool already = false;
        if (apply_key(out, key, vstart, &st, err, err_size, &already) != 0) {
            if (status != NULL) {
                *status = st;
            }
            if (err != NULL && err_size > 0 && err[0] == '\0') {
                snprintf(err, err_size, "line %d: invalid value for %s",
                         lineno, key);
            }
            fclose(fp);
            return -1;
        }
        if (already) {
            if (status != NULL) {
                *status = NETD_CONFIG_ERR_DUPLICATE;
            }
            if (err != NULL && err_size > 0) {
                snprintf(err, err_size, "line %d: duplicate key '%s'",
                         lineno, key);
            }
            fclose(fp);
            return -1;
        }
    }
    fclose(fp);

    if (netd_config_validate(out, err, err_size) != 0) {
        if (status != NULL) {
            *status = NETD_CONFIG_ERR_RANGE;
        }
        return -1;
    }
    return 0;
}