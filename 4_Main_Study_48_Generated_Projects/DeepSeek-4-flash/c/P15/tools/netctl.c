/*
 * netctl.c -- operator / client / status executable for the netd daemon.
 *
 * Subcommands:
 *   status   --config PATH                    show running/not-running + PID
 *   shutdown --config PATH                    SIGTERM
 *   reload   --config PATH                    SIGHUP  (live config reload)
 *   reopen   --config PATH                    SIGUSR1 (log reopen)
 *   ping     --config PATH --token T          PING
 *   health   --config PATH --token T          HEALTH
 *   stats    --config PATH --token T          STATS
 *   put      --config PATH --token T --namespace N --key K [--ttl S] --value V
 *   get      --config PATH --token T --namespace N --key K
 *   list     --config PATH --token T --namespace N [--prefix P] [--limit L]
 *                                                 [--after K]
 *   update   --config PATH --token T --namespace N --key K
 *                 --expected-version N [--ttl S] --value V
 *   delete   --config PATH --token T --namespace N --key K --expected-version N
 *   token-hash TOKEN                          print the SHA-256 hex digest
 */
#include <errno.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

#include "auth.h"
#include "config.h"
#include "pidfile.h"
#include "proto.h"
#include "util.h"

/* When the daemon listens on 0.0.0.0, clients connect to loopback. */
static const char *host_for_connect(const netd_config_t *cfg) {
    if (strcmp(cfg->bind_address, "0.0.0.0") == 0) return "127.0.0.1";
    return cfg->bind_address;
}

static void usage(FILE *out) {
    fprintf(out,
            "netctl %s - operator/client for netd\n"
            "Usage: netctl <subcommand> [options]\n"
            "Options: --config PATH --token T --namespace N --key K\n"
            "         --value V --value-b64 B --ttl S --expected-version N\n"
            "         --limit L --prefix P --after K\n",
            NETD_VERSION);
}

static int load_cfg(const char *path, netd_config_t *cfg) {
    char err[512];
    if (config_load(path, cfg, err, sizeof(err)) != 0) {
        fprintf(stderr, "netctl: %s\n", err);
        return -1;
    }
    return 0;
}

static int signal_by_pid(const netd_config_t *cfg, int sig) {
    long pid = 0;
    if (pidfile_read_pid(cfg->pid_file, &pid) != 0) {
        fprintf(stderr, "netctl: not running (no pid file %s)\n",
                cfg->pid_file);
        return 1;
    }
    if (!util_pid_alive(pid)) {
        fprintf(stderr, "netctl: not running (stale pid %ld in %s)\n", pid,
                cfg->pid_file);
        return 1;
    }
    if (kill((pid_t)pid, sig) != 0) {
        fprintf(stderr, "netctl: cannot signal pid %ld: %s\n", pid,
                strerror(errno));
        return 1;
    }
    return 0;
}

static int cmd_status(const netd_config_t *cfg) {
    long pid = 0;
    if (pidfile_read_pid(cfg->pid_file, &pid) != 0) {
        printf("netd: not running\n");
        return 1;
    }
    if (!util_pid_alive(pid)) {
        printf("netd: not running (stale pid file %s)\n", cfg->pid_file);
        return 1;
    }
    int fd = util_tcp_connect(host_for_connect(cfg), cfg->port, 2000);
    if (fd >= 0) {
        close(fd);
        printf("netd: running pid=%ld listener=%s:%d ready\n", pid,
               cfg->bind_address, cfg->port);
        return 0;
    }    printf("netd: running pid=%ld listener=%s:%d not-ready\n", pid,
           cfg->bind_address, cfg->port);
    return 1;
}

static int protocol_call(const netd_config_t *cfg, const char *token,
                         const char *command) {
    int fd = util_tcp_connect(host_for_connect(cfg), cfg->port, 5000);
    if (fd < 0) {
        fprintf(stderr, "netctl: cannot connect to %s:%d\n", cfg->bind_address,
                cfg->port);
        return 1;
    }
    char line[16384];

    if (token != NULL) {
        char auth[256];
        snprintf(auth, sizeof(auth), "AUTH %s\n", token);
        if (util_write_all(fd, auth, strlen(auth), 5000) < 0) {
            fprintf(stderr, "netctl: write failed\n");
            close(fd);
            return 1;
        }
        int n = util_read_line(fd, line, sizeof(line), 5000);
        if (n < 0) {
            fprintf(stderr, "netctl: no AUTH response\n");
            close(fd);
            return 1;
        }
        printf("%s\n", line);
        if (strncmp(line, "OK AUTH ", 8) != 0) {
            close(fd);
            return 1;
        }
    }

    size_t cmdlen = strlen(command);
    char *cmdline = malloc(cmdlen + 2);
    if (cmdline == NULL) {
        fprintf(stderr, "netctl: out of memory\n");
        close(fd);
        return 1;
    }
    memcpy(cmdline, command, cmdlen);
    cmdline[cmdlen] = '\n';
    cmdline[cmdlen + 1] = '\0';

    if (util_write_all(fd, cmdline, cmdlen + 1, 5000) < 0) {
        free(cmdline);
        fprintf(stderr, "netctl: write failed\n");
        close(fd);
        return 1;
    }
    free(cmdline);
    int n = util_read_line(fd, line, sizeof(line), 10000);
    if (n < 0) {
        fprintf(stderr, "netctl: no command response\n");
        close(fd);
        return 1;
    }
    printf("%s\n", line);

    int ok = strncmp(line, "OK ", 3) == 0 || strncmp(line, "PONG", 4) == 0;

    (void)util_write_all(fd, "QUIT\n", 5, 5000);
    (void)util_read_line(fd, line, sizeof(line), 5000);
    close(fd);
    return ok ? 0 : 1;
}

int main(int argc, char **argv) {
    const char *sub = NULL;
    const char *config_path = NULL;
    const char *token = NULL;
    const char *ns = NULL;
    const char *key = NULL;
    const char *value = NULL;
    const char *value_b64 = NULL;
    const char *ttl = NULL;
    const char *expected = NULL;
    const char *limit = NULL;
    const char *prefix = NULL;
    const char *after = NULL;

    for (int i = 1; i < argc; i++) {
        const char *a = argv[i];
        if (strcmp(a, "--config") == 0 && i + 1 < argc) {
            config_path = argv[++i];
        } else if (strcmp(a, "--token") == 0 && i + 1 < argc) {
            token = argv[++i];
        } else if (strcmp(a, "--namespace") == 0 && i + 1 < argc) {
            ns = argv[++i];
        } else if (strcmp(a, "--key") == 0 && i + 1 < argc) {
            key = argv[++i];
        } else if (strcmp(a, "--value") == 0 && i + 1 < argc) {
            value = argv[++i];
        } else if (strcmp(a, "--value-b64") == 0 && i + 1 < argc) {
            value_b64 = argv[++i];
        } else if (strcmp(a, "--ttl") == 0 && i + 1 < argc) {
            ttl = argv[++i];
        } else if (strcmp(a, "--expected-version") == 0 && i + 1 < argc) {
            expected = argv[++i];
        } else if (strcmp(a, "--limit") == 0 && i + 1 < argc) {
            limit = argv[++i];
        } else if (strcmp(a, "--prefix") == 0 && i + 1 < argc) {
            prefix = argv[++i];
        } else if (strcmp(a, "--after") == 0 && i + 1 < argc) {
            after = argv[++i];
        } else if (strcmp(a, "--help") == 0 || strcmp(a, "-h") == 0) {
            usage(stdout);
            return 0;
        } else if (a[0] == '-') {
            fprintf(stderr, "netctl: unknown option '%s'\n", a);
            usage(stderr);
            return 2;
        } else if (sub == NULL) {
            sub = a;
        } else {
            fprintf(stderr, "netctl: unexpected argument '%s'\n", a);
            usage(stderr);
            return 2;
        }
    }

    if (sub == NULL) {
        usage(stderr);
        return 2;
    }

    if (strcmp(sub, "token-hash") == 0) {
        const char *t = token ? token : (value ? value : NULL);
        if (t == NULL) {
            fprintf(stderr, "netctl: token-hash requires TOKEN argument\n");
            return 2;
        }
        char hex[NETD_SHA256_HEX_LEN];
        if (auth_hash_token(t, hex, sizeof(hex)) != 0) {
            fprintf(stderr, "netctl: hash failed\n");
            return 1;
        }
        printf("%s\n", hex);
        return 0;
    }

    if (config_path == NULL) {
        fprintf(stderr, "netctl: %s requires --config PATH\n", sub);
        return 2;
    }

    netd_config_t cfg;
    if (load_cfg(config_path, &cfg) != 0) return 2;

    if (strcmp(sub, "status") == 0) return cmd_status(&cfg);
    if (strcmp(sub, "shutdown") == 0) {
        int rc = signal_by_pid(&cfg, SIGTERM);
        if (rc == 0) printf("netctl: SIGTERM sent\n");
        return rc;
    }
    if (strcmp(sub, "reload") == 0) {
        int rc = signal_by_pid(&cfg, SIGHUP);
        if (rc == 0) printf("netctl: SIGHUP sent\n");
        return rc;
    }
    if (strcmp(sub, "reopen") == 0) {
        int rc = signal_by_pid(&cfg, SIGUSR1);
        if (rc == 0) printf("netctl: SIGUSR1 sent\n");
        return rc;
    }

    if (token == NULL) {
        fprintf(stderr, "netctl: %s requires --token T\n", sub);
        return 2;
    }

    char cmd[4096];

    if (strcmp(sub, "ping") == 0) return protocol_call(&cfg, token, "PING");
    if (strcmp(sub, "health") == 0) return protocol_call(&cfg, token, "HEALTH");
    if (strcmp(sub, "stats") == 0) return protocol_call(&cfg, token, "STATS");

    if (strcmp(sub, "put") == 0 || strcmp(sub, "update") == 0) {
        if (ns == NULL || key == NULL) {
            fprintf(stderr, "netctl: %s requires --namespace and --key\n", sub);
            return 2;
        }
        const char *b64 = value_b64;
        char *encoded = NULL;
        if (b64 == NULL) {
            if (value == NULL) {
                fprintf(stderr, "netctl: %s requires --value or --value-b64\n", sub);
                return 2;
            }
            size_t encap = strlen(value) / 3 * 4 + 8;
            encoded = malloc(encap);
            if (encoded == NULL ||
                proto_b64_encode((const uint8_t *)value, strlen(value),
                                 encoded, encap) != 0) {
                free(encoded);
                fprintf(stderr, "netctl: cannot encode value\n");
                return 2;
            }
            b64 = encoded;
        }
        const char *t = ttl ? ttl : "0";
        size_t need = strlen(ns) + strlen(key) + strlen(t) + strlen(b64) + 64;
        if (strcmp(sub, "update") == 0 && expected != NULL) {
            need += strlen(expected);
        }
        char *cmd = malloc(need);
        if (cmd == NULL) {
            free(encoded);
            fprintf(stderr, "netctl: out of memory\n");
            return 2;
        }
        if (strcmp(sub, "put") == 0) {
            snprintf(cmd, need, "PUT %s %s %s %s", ns, key, t, b64);
        } else {
            if (expected == NULL) {
                free(cmd);
                free(encoded);
                fprintf(stderr, "netctl: update requires --expected-version\n");
                return 2;
            }
            snprintf(cmd, need, "UPDATE %s %s %s %s %s", ns, key, expected, t,
                     b64);
        }
        int rc = protocol_call(&cfg, token, cmd);
        free(cmd);
        free(encoded);
        return rc;
    }

    if (strcmp(sub, "get") == 0) {
        if (ns == NULL || key == NULL) {
            fprintf(stderr, "netctl: get requires --namespace and --key\n");
            return 2;
        }
        snprintf(cmd, sizeof(cmd), "GET %s %s", ns, key);
        return protocol_call(&cfg, token, cmd);
    }

    if (strcmp(sub, "list") == 0) {
        if (ns == NULL) {
            fprintf(stderr, "netctl: list requires --namespace\n");
            return 2;
        }
        const char *p = prefix ? prefix : "-";
        const char *l = limit ? limit : "10";
        const char *a = after ? after : "-";
        snprintf(cmd, sizeof(cmd), "LIST %s %s %s %s", ns, p, l, a);
        return protocol_call(&cfg, token, cmd);
    }

    if (strcmp(sub, "delete") == 0) {
        if (ns == NULL || key == NULL || expected == NULL) {
            fprintf(stderr,
                    "netctl: delete requires --namespace --key "
                    "--expected-version\n");
            return 2;
        }
        snprintf(cmd, sizeof(cmd), "DELETE %s %s %s", ns, key, expected);
        return protocol_call(&cfg, token, cmd);
    }

    fprintf(stderr, "netctl: unknown subcommand '%s'\n", sub);
    usage(stderr);
    return 2;
}
