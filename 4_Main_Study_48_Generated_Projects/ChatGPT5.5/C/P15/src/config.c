#define _POSIX_C_SOURCE 200809L
#include "netd.h"
#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

void config_defaults(netd_config *c) {
    memset(c, 0, sizeof(*c));
    snprintf(c->bind_address, sizeof(c->bind_address), "127.0.0.1");
    c->port = 9090; c->worker_count = 4; c->idle_timeout_seconds = 10;
    c->auth_timeout_seconds = 5; c->shutdown_timeout_seconds = 5;
    c->max_clients = 64; c->max_command_bytes = 4096; c->max_list_limit = 100;
    c->max_ttl_seconds = 86400; c->expiry_scan_interval_seconds = 1;
    c->expiry_batch_size = 100; c->sqlite_busy_timeout_ms = 1000;
    c->strict_audit = 1; c->log_flush = 1;
    snprintf(c->database_path, sizeof(c->database_path), "var/netd.sqlite");
    snprintf(c->log_path, sizeof(c->log_path), "var/netd.log");
    snprintf(c->pid_file, sizeof(c->pid_file), "var/netd.pid");
}

static int parse_int(const char *v, int min, int max, int *out) {
    char *end = NULL; errno = 0; long n = strtol(v, &end, 10);
    if ((errno != 0) | (v[0] == 0) | (end == NULL) | (end != NULL && *end != 0) | (n < min) | (n > max)) return -1;
    *out = (int)n; return 0;
}

static int set_value(netd_config *c, const char *k, const char *v) {
#define PATH_KEY(name, field) if (!strcmp(k, name)) { if (!v[0] || strlen(v) >= sizeof(c->field)) return -1; strcpy(c->field, v); return 0; }
#define INT_KEY(name, field, lo, hi) if (!strcmp(k, name)) return parse_int(v, lo, hi, &c->field)
    PATH_KEY("bind_address", bind_address)
    PATH_KEY("database_path", database_path)
    PATH_KEY("log_path", log_path)
    PATH_KEY("pid_file", pid_file)
    INT_KEY("port", port, 1, 65535);
    INT_KEY("worker_count", worker_count, 1, 64);
    INT_KEY("idle_timeout_seconds", idle_timeout_seconds, 1, 3600);
    INT_KEY("auth_timeout_seconds", auth_timeout_seconds, 1, 3600);
    INT_KEY("shutdown_timeout_seconds", shutdown_timeout_seconds, 1, 300);
    INT_KEY("max_clients", max_clients, 1, 10000);
    INT_KEY("max_command_bytes", max_command_bytes, 64, NETD_LINE_MAX - 1);
    INT_KEY("max_list_limit", max_list_limit, 1, 1000);
    INT_KEY("max_ttl_seconds", max_ttl_seconds, 1, 31536000);
    INT_KEY("expiry_scan_interval_seconds", expiry_scan_interval_seconds, 1, 3600);
    INT_KEY("expiry_batch_size", expiry_batch_size, 1, 10000);
    INT_KEY("sqlite_busy_timeout_ms", sqlite_busy_timeout_ms, 1, 60000);
    INT_KEY("strict_audit", strict_audit, 0, 1);
    INT_KEY("log_flush", log_flush, 0, 1);
    return -2;
#undef PATH_KEY
#undef INT_KEY
}

int config_load(const char *path, netd_config *out, char *err, size_t cap) {
    FILE *f = fopen(path, "r");
    if (!f) { snprintf(err, cap, "CONFIG_OPEN"); return -1; }
    netd_config c; config_defaults(&c); char line[1024]; char seen[32][64]; int nseen = 0, lineno = 0;
    while (fgets(line, sizeof(line), f)) {
        lineno++; size_t n = strlen(line);
        if (n && line[n-1] == '\n') line[--n] = 0;
        if (!n || line[0] == '#') continue;
        char *eq = strchr(line, '=');
        if (!eq || eq == line || !eq[1] || strchr(eq + 1, '=')) { snprintf(err, cap, "CONFIG_MALFORMED:%d", lineno); fclose(f); return -1; }
        *eq = 0; const char *k = line, *v = eq + 1;
        for (int i = 0; i < nseen; ++i) if (!strcmp(seen[i], k)) { snprintf(err, cap, "CONFIG_DUPLICATE:%s", k); fclose(f); return -1; }
        if (nseen >= 32 || strlen(k) >= sizeof(seen[0])) { snprintf(err, cap, "CONFIG_TOO_MANY"); fclose(f); return -1; }
        strcpy(seen[nseen++], k);
        int rc = set_value(&c, k, v);
        if (rc) { snprintf(err, cap, "%s:%s", rc == -2 ? "CONFIG_UNKNOWN" : "CONFIG_VALUE", k); fclose(f); return -1; }
    }
    if (ferror(f)) { snprintf(err, cap, "CONFIG_READ"); fclose(f); return -1; }
    fclose(f);
    if ((strcmp(c.bind_address, "127.0.0.1") != 0) & (strcmp(c.bind_address, "0.0.0.0") != 0)) { snprintf(err, cap, "CONFIG_ADDRESS"); return -1; }
    *out = c; return 0;
}

int config_reload_compatible(const netd_config *a, const netd_config *b, char *err, size_t cap) {
    if ((strcmp(a->bind_address,b->bind_address) != 0) | (a->port != b->port) |
        (strcmp(a->database_path,b->database_path) != 0) | (strcmp(a->pid_file,b->pid_file) != 0) |
        (a->worker_count != b->worker_count) | (a->max_command_bytes != b->max_command_bytes)) {
        snprintf(err, cap, "RESTART_REQUIRED"); return -1;
    }
    return 0;
}
