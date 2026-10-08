/*
 * main_netd.c -- netd entry point: configuration validation, storage
 * initialization, PID-file locking, TCP listener, foreground/daemon mode,
 * and the server run loop.
 */
#include <errno.h>
#include <fcntl.h>
#include <netinet/in.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <strings.h>
#include <sys/socket.h>
#include <unistd.h>

#include <arpa/inet.h>

#include "config.h"
#include "daemon.h"
#include "log.h"
#include "metrics.h"
#include "netd.h"
#include "pidfile.h"
#include "server.h"
#include "sigs.h"
#include "storage.h"
#include "util.h"

static void print_version(void) {
    printf("netd %s (P15 Lightweight Network Service / System Daemon)\n",
           NETD_VERSION);
}

static int bind_listener(const netd_config_t *cfg, char *err, size_t errlen) {
    struct in_addr addr;
    if (inet_pton(AF_INET, cfg->bind_address, &addr) != 1) {
        snprintf(err, errlen, "invalid IPv4 bind address '%s'",
                 cfg->bind_address);
        return -1;
    }

    int fd = socket(AF_INET, SOCK_STREAM, 0);
    if (fd < 0) {
        snprintf(err, errlen, "socket: %s", strerror(errno));
        return -1;
    }
    int one = 1;
    setsockopt(fd, SOL_SOCKET, SO_REUSEADDR, &one, sizeof(one));

    struct sockaddr_in sa;
    memset(&sa, 0, sizeof(sa));
    sa.sin_family = AF_INET;
    sa.sin_port = htons((uint16_t)cfg->port);
    sa.sin_addr = addr;

    if (bind(fd, (struct sockaddr *)&sa, sizeof(sa)) != 0) {
        snprintf(err, errlen, "bind %s:%d failed: %s", cfg->bind_address,
                 cfg->port, strerror(errno));
        close(fd);
        return -1;
    }
    if (listen(fd, 128) != 0) {
        snprintf(err, errlen, "listen failed: %s", strerror(errno));
        close(fd);
        return -1;
    }
    /* Non-blocking so the accept loop never stalls on a quiet listener and
     * the main thread always returns to poll() to service signals. */
    int fl = fcntl(fd, F_GETFL, 0);
    fcntl(fd, F_SETFL, fl | O_NONBLOCK);
    return fd;
}

int main(int argc, char **argv) {
    const char *config_path = NULL;
    int want_daemon = 0;
    int check_config = 0;
    int reset_db = 0;
    int show_version = 0;
    int show_help = 0;

    for (int i = 1; i < argc; i++) {
        const char *a = argv[i];
        if (strcmp(a, "--config") == 0) {
            if (i + 1 >= argc) {
                fprintf(stderr, "netd: --config requires a path argument\n");
                return 2;
            }
            config_path = argv[++i];
        } else if (strcmp(a, "--foreground") == 0) {
            want_daemon = 0;
        } else if (strcmp(a, "--daemon") == 0) {
            want_daemon = 1;
        } else if (strcmp(a, "--check-config") == 0) {
            check_config = 1;
        } else if (strcmp(a, "--reset-db") == 0) {
            reset_db = 1;
        } else if (strcmp(a, "--version") == 0) {
            show_version = 1;
        } else if (strcmp(a, "--help") == 0) {
            show_help = 1;
        } else {
            fprintf(stderr, "netd: unknown option '%s'\n", a);
            config_print_help(stderr);
            return 2;
        }
    }

    if (show_help) {
        config_print_help(stdout);
        return 0;
    }
    if (show_version) {
        print_version();
        return 0;
    }
    if (config_path == NULL) {
        fprintf(stderr, "netd: missing required --config PATH\n");
        config_print_help(stderr);
        return 2;
    }

    netd_config_t cfg;
    char err[512];

    if (config_load(config_path, &cfg, err, sizeof(err)) != 0) {
        fprintf(stderr, "netd: %s\n", err);
        return 2;
    }

    if (check_config) {
        char dump[1024];
        config_dump(&cfg, dump, sizeof(dump));
        printf("netd: configuration valid: %s\n", dump);
        return 0;
    }

    metrics_init();

    if (reset_db) {
        if (storage_reset(&cfg, err, sizeof(err)) != 0) {
            fprintf(stderr, "netd: reset failed: %s\n", err);
            return 2;
        }
        printf("netd: database reset and seeded at %s\n", cfg.database_path);
        printf("    client token   : client-token-01   (role client)\n");
        printf("    monitor token  : monitor-token-01  (role monitoring)\n");
        printf("    admin token    : admin-token-01    (role administrator)\n");
        printf("    revoked token  : revoked-token-01  (role client, revoked)\n");
        printf("    expired token  : expired-token-01  (role client, expired)\n");
        return 0;
    }

    int ready_fd = -1;
    if (want_daemon) {
        int rc = daemonize_start(&ready_fd);
        if (rc != 0) return 1; /* parent path never returns normally */
    }

    /* All initialization below runs in the serving process (the daemon
     * child in --daemon mode, or the foreground process otherwise). */
    netd_logger_t *lg = log_open(cfg.log_path, cfg.strict_audit,
                                 cfg.log_flush == NETD_LOG_FLUSH_ALWAYS,
                                 !want_daemon, err, sizeof(err));
    if (lg == NULL) {
        fprintf(stderr, "netd: %s\n", err);
        if (want_daemon) {
            daemon_notify(ready_fd, 0);
        }
        return 2;
    }

#define FATAL_INIT(msg, code)                                              \
    do {                                                                   \
        log_event(lg, EV_STARTUP_ERROR, code, "-", "-", "-", "-",          \
                  "reason=%s", msg);                                       \
        if (!want_daemon) fprintf(stderr, "netd: %s\n", msg);              \
        log_close(lg);                                                     \
        if (want_daemon) daemon_notify(ready_fd, 0);                       \
        return 2;                                                          \
    } while (0)

    sqlite3 *main_db = storage_open(&cfg, err, sizeof(err));
    if (main_db == NULL) FATAL_INIT(err, "ERR");

    int listener = bind_listener(&cfg, err, sizeof(err));
    if (listener < 0) {
        sqlite3_close(main_db);
        FATAL_INIT(err, "ERR");
    }

    int sig_fd = 0;
    if (signal_setup(&sig_fd) != 0) {
        close(listener);
        sqlite3_close(main_db);
        FATAL_INIT("signal setup failed", "ERR");
    }

    int held = 0, stale = 0;
    netd_pidfile_t *pf = pidfile_acquire(cfg.pid_file, &held, &stale, err,
                                         sizeof(err));
    if (pf == NULL) {
        if (held) {
            log_event(lg, EV_SECOND_INST, "ERR", "-", "-", "-", "-",
                      "pid_file=%s", cfg.pid_file);
        }
        close(listener);
        sqlite3_close(main_db);
        FATAL_INIT(err, held ? "LOCKED" : "ERR");
    }
    if (stale) {
        log_event(lg, EV_STALE_PID, "OK", "-", "-", "-", "-",
                  "pid_file=%s", cfg.pid_file);
    }
    log_event(lg, EV_PID_FILE, "OK", "-", "-", "-", "-", "pid=%ld path=%s",
              (long)getpid(), cfg.pid_file);

    storage_instance_start(main_db, (int)getpid(), cfg.generation,
                           util_now_epoch());

    char dump[1024];
    config_dump(&cfg, dump, sizeof(dump));
    log_event(lg, EV_READY, "OK", "-", "-", "-", "-", "pid=%ld %s",
              (long)getpid(), dump);
    if (!want_daemon) {
        printf("netd: ready on %s:%d (pid %ld)\n", cfg.bind_address,
               cfg.port, (long)getpid());
    }

    netd_server_t *srv = server_create(&cfg, config_path, main_db, lg,
                                       listener, sig_fd, err, sizeof(err));
    if (srv == NULL) {
        pidfile_release(pf);
        close(listener);
        sqlite3_close(main_db);
        FATAL_INIT(err, "ERR");
    }

    if (want_daemon) {
        daemon_notify(ready_fd, 1);
    }

    server_run(srv);
    server_destroy(srv);

    storage_instance_stop(main_db, (int)getpid(), util_now_epoch());
    sqlite3_close(main_db);
    log_flush(lg);
    log_close(lg);
    pidfile_release(pf);
    signal_restore();
    return 0;
}
