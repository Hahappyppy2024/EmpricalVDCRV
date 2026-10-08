/*
 * config.c -- strict key=value configuration parser and validator.
 */
#include "config.h"

#include <ctype.h>
#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

static void trim(char *s) {
    char *p = s;
    while (*p && isspace((unsigned char)*p)) p++;
    if (p != s) memmove(s, p, strlen(p) + 1);
    size_t n = strlen(s);
    while (n > 0 && isspace((unsigned char)s[n - 1])) s[--n] = '\0';
}

static int parse_int(const char *val, long *out, char *err, size_t errlen) {
    if (*val == '\0') {
        snprintf(err, errlen, "empty integer value");
        return -1;
    }
    errno = 0;
    char *end = NULL;
    long v = strtol(val, &end, 10);
    if (errno != 0 || end == NULL || *end != '\0') {
        snprintf(err, errlen, "malformed integer value '%s'", val);
        return -1;
    }
    *out = v;
    return 0;
}

void config_defaults(netd_config_t *cfg) {
    memset(cfg, 0, sizeof(*cfg));
    snprintf(cfg->bind_address, sizeof(cfg->bind_address), "127.0.0.1");
    cfg->port = 9090;
    snprintf(cfg->database_path, sizeof(cfg->database_path), "data/netd.db");
    snprintf(cfg->log_path, sizeof(cfg->log_path), "logs/netd.log");
    snprintf(cfg->pid_file, sizeof(cfg->pid_file), "run/netd.pid");
    cfg->worker_count = 4;
    cfg->max_clients = 64;
    cfg->max_command_bytes = 4096;
    cfg->idle_timeout_seconds = 60;
    cfg->auth_timeout_seconds = 10;
    cfg->shutdown_timeout_seconds = 5;
    cfg->expiry_scan_interval_seconds = 10;
    cfg->expiry_batch_size = 100;
    cfg->database_busy_timeout_ms = 5000;
    cfg->list_max_limit = 100;
    cfg->strict_audit = 0;
    cfg->log_flush = NETD_LOG_FLUSH_AUTO;
    cfg->generation = 1;
}

/* Validates one already-bounded value; returns -1 with a message on error. */
static int validate_value(const char *key, const netd_config_t *c,
                          char *err, size_t errlen) {
    if (strcmp(key, "bind_address") == 0) {
        if (c->bind_address[0] == '\0') {
            snprintf(err, errlen, "bind_address must not be empty");
            return -1;
        }
    } else if (strcmp(key, "port") == 0) {
        if (c->port < 1 || c->port > 65535) {
            snprintf(err, errlen, "port out of range (1..65535): %d", c->port);
            return -1;
        }
    } else if (strcmp(key, "database_path") == 0 ||
               strcmp(key, "log_path") == 0 ||
               strcmp(key, "pid_file") == 0) {
        if (c->database_path[0] == '\0' || c->log_path[0] == '\0' ||
            c->pid_file[0] == '\0') {
            snprintf(err, errlen, "%s must not be empty", key);
            return -1;
        }
    } else if (strcmp(key, "worker_count") == 0) {
        if (c->worker_count < 1 || c->worker_count > 64) {
            snprintf(err, errlen, "worker_count out of range (1..64): %d",
                     c->worker_count);
            return -1;
        }
    } else if (strcmp(key, "max_clients") == 0) {
        if (c->max_clients < 1 || c->max_clients > 65535) {
            snprintf(err, errlen, "max_clients out of range (1..65535): %d",
                     c->max_clients);
            return -1;
        }
    } else if (strcmp(key, "max_command_bytes") == 0) {
        if (c->max_command_bytes < 64 || c->max_command_bytes > NETD_LINE_ABS_MAX) {
            snprintf(err, errlen, "max_command_bytes out of range (64..%d): %d",
                     NETD_LINE_ABS_MAX, c->max_command_bytes);
            return -1;
        }
    } else if (strcmp(key, "idle_timeout_seconds") == 0) {
        if (c->idle_timeout_seconds < 0 || c->idle_timeout_seconds > 86400) {
            snprintf(err, errlen, "idle_timeout_seconds out of range (0..86400): %d",
                     c->idle_timeout_seconds);
            return -1;
        }
    } else if (strcmp(key, "auth_timeout_seconds") == 0) {
        if (c->auth_timeout_seconds < 1 || c->auth_timeout_seconds > 86400) {
            snprintf(err, errlen, "auth_timeout_seconds out of range (1..86400): %d",
                     c->auth_timeout_seconds);
            return -1;
        }
    } else if (strcmp(key, "shutdown_timeout_seconds") == 0) {
        if (c->shutdown_timeout_seconds < 1 || c->shutdown_timeout_seconds > 3600) {
            snprintf(err, errlen, "shutdown_timeout_seconds out of range (1..3600): %d",
                     c->shutdown_timeout_seconds);
            return -1;
        }
    } else if (strcmp(key, "expiry_scan_interval_seconds") == 0) {
        if (c->expiry_scan_interval_seconds < 1 ||
            c->expiry_scan_interval_seconds > 86400) {
            snprintf(err, errlen,
                     "expiry_scan_interval_seconds out of range (1..86400): %d",
                     c->expiry_scan_interval_seconds);
            return -1;
        }
    } else if (strcmp(key, "expiry_batch_size") == 0) {
        if (c->expiry_batch_size < 1 || c->expiry_batch_size > 10000) {
            snprintf(err, errlen, "expiry_batch_size out of range (1..10000): %d",
                     c->expiry_batch_size);
            return -1;
        }
    } else if (strcmp(key, "database_busy_timeout_ms") == 0) {
        if (c->database_busy_timeout_ms < 0 || c->database_busy_timeout_ms > 120000) {
            snprintf(err, errlen,
                     "database_busy_timeout_ms out of range (0..120000): %d",
                     c->database_busy_timeout_ms);
            return -1;
        }
    } else if (strcmp(key, "list_max_limit") == 0) {
        if (c->list_max_limit < 1 || c->list_max_limit > 10000) {
            snprintf(err, errlen, "list_max_limit out of range (1..10000): %d",
                     c->list_max_limit);
            return -1;
        }
    } else if (strcmp(key, "strict_audit") == 0) {
        if (c->strict_audit != 0 && c->strict_audit != 1) {
            snprintf(err, errlen, "strict_audit must be 0 or 1 (got %d)",
                     c->strict_audit);
            return -1;
        }
    } else if (strcmp(key, "log_flush") == 0) {
        if (c->log_flush != NETD_LOG_FLUSH_AUTO &&
            c->log_flush != NETD_LOG_FLUSH_ALWAYS) {
            snprintf(err, errlen, "log_flush must be auto or always");
            return -1;
        }
    }
    return 0;
}

int config_validate(const netd_config_t *cfg, char *err, size_t errlen) {
    return validate_value("bind_address", cfg, err, errlen) ||
           validate_value("port", cfg, err, errlen) ||
           validate_value("database_path", cfg, err, errlen) ||
           validate_value("log_path", cfg, err, errlen) ||
           validate_value("pid_file", cfg, err, errlen) ||
           validate_value("worker_count", cfg, err, errlen) ||
           validate_value("max_clients", cfg, err, errlen) ||
           validate_value("max_command_bytes", cfg, err, errlen) ||
           validate_value("idle_timeout_seconds", cfg, err, errlen) ||
           validate_value("auth_timeout_seconds", cfg, err, errlen) ||
           validate_value("shutdown_timeout_seconds", cfg, err, errlen) ||
           validate_value("expiry_scan_interval_seconds", cfg, err, errlen) ||
           validate_value("expiry_batch_size", cfg, err, errlen) ||
           validate_value("database_busy_timeout_ms", cfg, err, errlen) ||
           validate_value("list_max_limit", cfg, err, errlen) ||
           validate_value("strict_audit", cfg, err, errlen) ||
           validate_value("log_flush", cfg, err, errlen);
}

int config_load(const char *path, netd_config_t *cfg, char *err, size_t errlen) {
    config_defaults(cfg);

    FILE *f = fopen(path, "r");
    if (f == NULL) {
        snprintf(err, errlen, "cannot open config '%s': %s", path,
                 strerror(errno));
        return -1;
    }

    char line[8192];
    char tmperr[512];
    char seen_keys[64][64];
    size_t nkeys = 0;

    unsigned long lineno = 0;
    while (fgets(line, sizeof(line), f) != NULL) {
        lineno++;
        char *p = line;
        /* strip leading whitespace */
        while (*p && isspace((unsigned char)*p)) p++;
        if (*p == '\0' || *p == '#') continue;
        /* strip trailing newline and comment */
        size_t n = strlen(p);
        while (n > 0 && (p[n - 1] == '\n' || p[n - 1] == '\r')) p[--n] = '\0';
        char *hash = strchr(p, '#');
        if (hash != NULL) *hash = '\0';
        trim(p);
        if (*p == '\0') continue;

        char *eq = strchr(p, '=');
        if (eq == NULL) {
            snprintf(err, errlen, "%s:%lu: malformed line (expected key=value)",
                     path, lineno);
            fclose(f);
            return -1;
        }
        if (strchr(eq + 1, '=') != NULL) {
            snprintf(err, errlen, "%s:%lu: value must not contain '='",
                     path, lineno);
            fclose(f);
            return -1;
        }
        *eq = '\0';
        trim(p);
        char *val = eq + 1;
        trim(val);

        /* duplicate key check */
        for (size_t i = 0; i < nkeys; i++) {
            if (strcmp(seen_keys[i], p) == 0) {
                snprintf(err, errlen, "%s:%lu: duplicate key '%s'", path,
                         lineno, p);
                fclose(f);
                return -1;
            }
        }
        if (nkeys < 64) {
            size_t klen = strlen(p);
            if (klen > 63) klen = 63;
            memcpy(seen_keys[nkeys], p, klen);
            seen_keys[nkeys][klen] = '\0';
            nkeys++;
        }

        long iv;
        if (strcmp(p, "bind_address") == 0) {
            snprintf(cfg->bind_address, sizeof(cfg->bind_address), "%s", val);
        } else if (strcmp(p, "port") == 0) {
            if (parse_int(val, &iv, err, errlen) != 0) {
            snprintf(tmperr, sizeof(tmperr), "%s", err);
            snprintf(err, errlen, "%s:%lu: %s", path, lineno, tmperr);
                fclose(f);
                return -1;
            }
            cfg->port = (int)iv;
        } else if (strcmp(p, "database_path") == 0) {
            snprintf(cfg->database_path, sizeof(cfg->database_path), "%s", val);
        } else if (strcmp(p, "log_path") == 0) {
            snprintf(cfg->log_path, sizeof(cfg->log_path), "%s", val);
        } else if (strcmp(p, "pid_file") == 0) {
            snprintf(cfg->pid_file, sizeof(cfg->pid_file), "%s", val);
        } else if (strcmp(p, "worker_count") == 0) {
            if (parse_int(val, &iv, err, errlen) != 0) {
            snprintf(tmperr, sizeof(tmperr), "%s", err);
            snprintf(err, errlen, "%s:%lu: %s", path, lineno, tmperr);
                fclose(f);
                return -1;
            }
            cfg->worker_count = (int)iv;
        } else if (strcmp(p, "max_clients") == 0) {
            if (parse_int(val, &iv, err, errlen) != 0) {
            snprintf(tmperr, sizeof(tmperr), "%s", err);
            snprintf(err, errlen, "%s:%lu: %s", path, lineno, tmperr);
                fclose(f);
                return -1;
            }
            cfg->max_clients = (int)iv;
        } else if (strcmp(p, "max_command_bytes") == 0) {
            if (parse_int(val, &iv, err, errlen) != 0) {
            snprintf(tmperr, sizeof(tmperr), "%s", err);
            snprintf(err, errlen, "%s:%lu: %s", path, lineno, tmperr);
                fclose(f);
                return -1;
            }
            cfg->max_command_bytes = (int)iv;
        } else if (strcmp(p, "idle_timeout_seconds") == 0) {
            if (parse_int(val, &iv, err, errlen) != 0) {
            snprintf(tmperr, sizeof(tmperr), "%s", err);
            snprintf(err, errlen, "%s:%lu: %s", path, lineno, tmperr);
                fclose(f);
                return -1;
            }
            cfg->idle_timeout_seconds = (int)iv;
        } else if (strcmp(p, "auth_timeout_seconds") == 0) {
            if (parse_int(val, &iv, err, errlen) != 0) {
            snprintf(tmperr, sizeof(tmperr), "%s", err);
            snprintf(err, errlen, "%s:%lu: %s", path, lineno, tmperr);
                fclose(f);
                return -1;
            }
            cfg->auth_timeout_seconds = (int)iv;
        } else if (strcmp(p, "shutdown_timeout_seconds") == 0) {
            if (parse_int(val, &iv, err, errlen) != 0) {
            snprintf(tmperr, sizeof(tmperr), "%s", err);
            snprintf(err, errlen, "%s:%lu: %s", path, lineno, tmperr);
                fclose(f);
                return -1;
            }
            cfg->shutdown_timeout_seconds = (int)iv;
        } else if (strcmp(p, "expiry_scan_interval_seconds") == 0) {
            if (parse_int(val, &iv, err, errlen) != 0) {
            snprintf(tmperr, sizeof(tmperr), "%s", err);
            snprintf(err, errlen, "%s:%lu: %s", path, lineno, tmperr);
                fclose(f);
                return -1;
            }
            cfg->expiry_scan_interval_seconds = (int)iv;
        } else if (strcmp(p, "expiry_batch_size") == 0) {
            if (parse_int(val, &iv, err, errlen) != 0) {
            snprintf(tmperr, sizeof(tmperr), "%s", err);
            snprintf(err, errlen, "%s:%lu: %s", path, lineno, tmperr);
                fclose(f);
                return -1;
            }
            cfg->expiry_batch_size = (int)iv;
        } else if (strcmp(p, "database_busy_timeout_ms") == 0) {
            if (parse_int(val, &iv, err, errlen) != 0) {
            snprintf(tmperr, sizeof(tmperr), "%s", err);
            snprintf(err, errlen, "%s:%lu: %s", path, lineno, tmperr);
                fclose(f);
                return -1;
            }
            cfg->database_busy_timeout_ms = (int)iv;
        } else if (strcmp(p, "list_max_limit") == 0) {
            if (parse_int(val, &iv, err, errlen) != 0) {
            snprintf(tmperr, sizeof(tmperr), "%s", err);
            snprintf(err, errlen, "%s:%lu: %s", path, lineno, tmperr);
                fclose(f);
                return -1;
            }
            cfg->list_max_limit = (int)iv;
        } else if (strcmp(p, "strict_audit") == 0) {
            if (parse_int(val, &iv, err, errlen) != 0) {
            snprintf(tmperr, sizeof(tmperr), "%s", err);
            snprintf(err, errlen, "%s:%lu: %s", path, lineno, tmperr);
                fclose(f);
                return -1;
            }
            cfg->strict_audit = (int)iv;
        } else if (strcmp(p, "log_flush") == 0) {
            if (strcmp(val, "auto") == 0) {
                cfg->log_flush = NETD_LOG_FLUSH_AUTO;
            } else if (strcmp(val, "always") == 0) {
                cfg->log_flush = NETD_LOG_FLUSH_ALWAYS;
            } else {
                snprintf(err, errlen, "%s:%lu: log_flush must be auto or always",
                         path, lineno);
                fclose(f);
                return -1;
            }
        } else {
            snprintf(err, errlen, "%s:%lu: unknown configuration key '%s'",
                     path, lineno, p);
            fclose(f);
            return -1;
        }
    }
    fclose(f);

    if (config_validate(cfg, err, errlen) != 0) {
        snprintf(tmperr, sizeof(tmperr), "%s", err);
        snprintf(err, errlen, "%s: %s", path, tmperr);
        return -1;
    }
    return 0;
}

void config_dump(const netd_config_t *cfg, char *out, size_t outlen) {
    snprintf(out, outlen,
             "bind_address=%s port=%d database_path=%s log_path=%s "
             "pid_file=%s worker_count=%d max_clients=%d max_command_bytes=%d "
             "idle_timeout_seconds=%d auth_timeout_seconds=%d "
             "shutdown_timeout_seconds=%d expiry_scan_interval_seconds=%d "
             "expiry_batch_size=%d database_busy_timeout_ms=%d "
             "list_max_limit=%d strict_audit=%d log_flush=%s generation=%d",
             cfg->bind_address, cfg->port, cfg->database_path, cfg->log_path,
             cfg->pid_file, cfg->worker_count, cfg->max_clients,
             cfg->max_command_bytes, cfg->idle_timeout_seconds,
             cfg->auth_timeout_seconds, cfg->shutdown_timeout_seconds,
             cfg->expiry_scan_interval_seconds, cfg->expiry_batch_size,
             cfg->database_busy_timeout_ms, cfg->list_max_limit,
             cfg->strict_audit, cfg->log_flush == NETD_LOG_FLUSH_ALWAYS
                                   ? "always" : "auto",
             cfg->generation);
}

int config_nonreloadable_changed(const netd_config_t *old,
                                 const netd_config_t *nu,
                                 char *changed, size_t changedlen) {
    changed[0] = '\0';
    size_t used = 0;
#define APPEND_CHANGED(name)                                                  \
    do {                                                                      \
        size_t need = strlen(name) + (used ? 1 : 0) + 1;                      \
        if (used + need <= changedlen) {                                      \
            if (used) {                                                       \
                changed[used++] = ',';                                        \
            }                                                                 \
            memcpy(changed + used, name, strlen(name));                       \
            used += strlen(name);                                             \
            changed[used] = '\0';                                             \
        }                                                                     \
    } while (0)
    if (strcmp(old->bind_address, nu->bind_address) != 0) APPEND_CHANGED("bind_address");
    if (old->port != nu->port) APPEND_CHANGED("port");
    if (strcmp(old->database_path, nu->database_path) != 0) APPEND_CHANGED("database_path");
    if (strcmp(old->log_path, nu->log_path) != 0) APPEND_CHANGED("log_path");
    if (strcmp(old->pid_file, nu->pid_file) != 0) APPEND_CHANGED("pid_file");
    if (old->worker_count != nu->worker_count) APPEND_CHANGED("worker_count");
#undef APPEND_CHANGED
    return changed[0] != '\0';
}

void config_print_help(FILE *out) {
    fprintf(out,
            "netd %s - P15 Lightweight Network Service / System Daemon\n"
            "\n"
            "Usage:\n"
            "  netd --config PATH [--foreground|--daemon]\n"
            "  netd --config PATH --check-config\n"
            "  netd --config PATH --reset-db\n"
            "  netd --version\n"
            "  netd --help\n"
            "\n"
            "Options:\n"
            "  --config PATH     Path to the strict netd.conf file (required)\n"
            "  --foreground      Run in the foreground (default)\n"
            "  --daemon          Detach and run as a daemon\n"
            "  --check-config    Parse and validate the configuration, then exit\n"
            "  --reset-db        Wipe and re-seed the SQLite database, then exit\n"
            "  --version         Print the version and exit\n"
            "  --help            Show this help and exit\n",
            NETD_VERSION);
}
