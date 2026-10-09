#include "netd/common.h"
#include "netd/config.h"
#include "netd/log.h"
#include "netd/pidfile.h"
#include "netd/server.h"
#include "netd/signals.h"
#include "netd/util.h"

#include <errno.h>
#include <fcntl.h>
#include <getopt.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <unistd.h>

static int daemonize(void)
{
    pid_t pid = fork();
    if (pid < 0) {
        return -1;
    }
    if (pid > 0) {
        _exit(0);
    }
    if (setsid() < 0) {
        return -1;
    }
    int fd = open("/dev/null", O_RDONLY);
    if (fd >= 0) {
        dup2(fd, STDIN_FILENO);
        close(fd);
    }
    fd = open("/dev/null", O_WRONLY);
    if (fd >= 0) {
        dup2(fd, STDOUT_FILENO);
        close(fd);
    }
    fd = open("/dev/null", O_WRONLY);
    if (fd >= 0) {
        dup2(fd, STDERR_FILENO);
        close(fd);
    }
    return 0;
}

static void usage(const char *prog)
{
    fprintf(stderr,
            "Usage: %s --config PATH [--foreground|--daemon]\n"
            "  --config PATH       path to netd.conf (required)\n"
            "  --foreground        run in foreground (default)\n"
            "  --daemon            fork into background\n"
            "  --reset             reset database file before start\n"
            "  --print-config      validate and print effective config\n"
            "  --version           print version\n"
            "  --help              this help\n",
            prog);
}

int main(int argc, char **argv)
{
    const char *config_path = NULL;
    int mode = 0; /* 1=foreground 2=daemon */
    int reset_db = 0;
    int print_cfg = 0;

    static struct option long_opts[] = {
        {"config",      required_argument, 0, 'c'},
        {"foreground",  no_argument,       0, 'f'},
        {"daemon",      no_argument,       0, 'd'},
        {"reset",       no_argument,       0, 'r'},
        {"print-config",no_argument,       0, 'p'},
        {"version",     no_argument,       0, 'v'},
        {"help",        no_argument,       0, 'h'},
        {0, 0, 0, 0}
    };
    int idx = 0;
    int opt = 0;
    while ((opt = getopt_long(argc, argv, "c:fdrpvh", long_opts, &idx)) != -1) {
        switch (opt) {
        case 'c': config_path = optarg; break;
        case 'f': mode = 1; break;
        case 'd': mode = 2; break;
        case 'r': reset_db = 1; break;
        case 'p': print_cfg = 1; break;
        case 'v':
            printf("netd %s\n", NETD_VERSION_STRING);
            return 0;
        case 'h':
        default:
            usage(argv[0]);
            return opt == 'h' ? 0 : 2;
        }
    }
    if (config_path == NULL) {
        usage(argv[0]);
        return 2;
    }
    if (mode == 0) {
        mode = 1;
    }

    netd_config_t cfg;
    netd_config_status_t st = NETD_CONFIG_OK;
    char err[256];
    if (netd_config_load(config_path, &cfg, &st, err, sizeof(err)) != 0) {
        fprintf(stderr, "netd: invalid configuration: %s (%s)\n",
                err, netd_config_status_string(st));
        return 1;
    }
    if (print_cfg) {
        printf("bind_address=%s\n", cfg.bind_address);
        printf("port=%d\n", cfg.port);
        printf("database_path=%s\n", cfg.database_path);
        printf("log_path=%s\n", cfg.log_path);
        printf("audit_log_path=%s\n", cfg.audit_log_path);
        printf("pid_file=%s\n", cfg.pid_file);
        printf("worker_count=%d\n", cfg.worker_count);
        printf("idle_timeout_seconds=%d\n", cfg.idle_timeout_seconds);
        printf("max_clients=%d\n", cfg.max_clients);
        printf("max_command_bytes=%d\n", cfg.max_command_bytes);
        printf("expiry_scan_interval_seconds=%d\n",
               cfg.expiry_scan_interval_seconds);
        printf("expiry_batch_size=%d\n", cfg.expiry_batch_size);
        printf("shutdown_timeout_seconds=%d\n", cfg.shutdown_timeout_seconds);
        printf("strict_audit=%s\n", cfg.strict_audit ? "true" : "false");
        printf("database_busy_timeout_ms=%d\n", cfg.database_busy_timeout_ms);
        return 0;
    }

    if (reset_db) {
        if (unlink(cfg.database_path) != 0 && errno != ENOENT) {
            fprintf(stderr, "netd: cannot remove %s: %s\n",
                    cfg.database_path, strerror(errno));
            return 1;
        }
        char wal[512];
        snprintf(wal, sizeof(wal), "%s-wal", cfg.database_path);
        unlink(wal);
        char shm[512];
        snprintf(shm, sizeof(shm), "%s-shm", cfg.database_path);
        unlink(shm);
    }

    int existing_pid = 0;
    netd_pid_status_t pst = NETD_PID_OK;
    int pid_fd = netd_pid_acquire(cfg.pid_file, &existing_pid, &pst);
    if (pid_fd < 0) {
        fprintf(stderr, "netd: cannot acquire pid file %s (%s, existing=%d)\n",
                cfg.pid_file, netd_pid_status_string(pst), existing_pid);
        return 1;
    }
    if (mode == 2) {
        if (daemonize() != 0) {
            fprintf(stderr, "netd: daemonize failed\n");
            netd_pid_release(cfg.pid_file, pid_fd, (int)getpid());
            return 1;
        }
        netd_pid_release(cfg.pid_file, pid_fd, (int)getpid());
        pid_fd = netd_pid_acquire(cfg.pid_file, &existing_pid, &pst);
        if (pid_fd < 0) {
            return 1;
        }
    }

    netd_server_t *server = netd_server_create(&cfg);
    if (server == NULL) {
        netd_pid_release(cfg.pid_file, pid_fd, (int)getpid());
        return 1;
    }
    netd_server_set_config_path(server, config_path);
    server->pid_fd = pid_fd;
    server->stored_pid = (int)getpid();

    if (netd_server_start(server) != 0) {
        fprintf(stderr, "netd: server start failed\n");
        netd_server_destroy(server);
        return 1;
    }
    int rc = netd_server_run(server);

    if (rc == 0 && !netd_server_is_stopping(server)) {
        netd_server_stop(server, cfg.shutdown_timeout_seconds);
    } else {
        netd_server_stop(server, cfg.shutdown_timeout_seconds);
    }

    netd_server_destroy(server);
    netd_signals_uninstall();
    return 0;
}