#define _POSIX_C_SOURCE 200809L

#include "netd/common.h"
#include "netd/config.h"
#include "netd/pidfile.h"
#include "netd/util.h"

#include <arpa/inet.h>
#include <ctype.h>
#include <errno.h>
#include <fcntl.h>
#include <getopt.h>
#include <netinet/in.h>
#include <netinet/tcp.h>
#include <signal.h>
#include <stdarg.h>
#include <stdbool.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <unistd.h>

static int read_line(int fd, char *buf, size_t cap, int timeout_ms)
{
    size_t off = 0;
    int64_t deadline_ms = netd_now_monotonic_ms() + timeout_ms;
    while (off + 1 < cap) {
        int64_t now = netd_now_monotonic_ms();
        if (now >= deadline_ms) {
            return -1;
        }
        struct timeval tv;
        tv.tv_sec = (deadline_ms - now) / 1000;
        tv.tv_usec = ((deadline_ms - now) % 1000) * 1000;
        fd_set rfds;
        FD_ZERO(&rfds);
        FD_SET(fd, &rfds);
        int rc = select(fd + 1, &rfds, NULL, NULL, &tv);
        if (rc <= 0) {
            return -1;
        }
        char c;
        ssize_t n = read(fd, &c, 1);
        if (n <= 0) {
            return -1;
        }
        if (c == '\n') {
            buf[off] = '\0';
            return (int)off;
        }
        if (c == '\r') {
            continue;
        }
        buf[off++] = c;
    }
    buf[off] = '\0';
    return (int)off;
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

static int tcp_connect(const char *host, int port, int timeout_ms)
{
    int fd = socket(AF_INET, SOCK_STREAM, 0);
    if (fd < 0) {
        return -1;
    }
    int one = 1;
    setsockopt(fd, IPPROTO_TCP, TCP_NODELAY, &one, sizeof(one));
    struct sockaddr_in addr;
    memset(&addr, 0, sizeof(addr));
    addr.sin_family = AF_INET;
    addr.sin_port = htons((uint16_t)port);
    if (inet_pton(AF_INET, host, &addr.sin_addr) != 1) {
        close(fd);
        return -1;
    }
    struct timeval tv;
    tv.tv_sec = timeout_ms / 1000;
    tv.tv_usec = (timeout_ms % 1000) * 1000;
    setsockopt(fd, SOL_SOCKET, SO_RCVTIMEO, &tv, sizeof(tv));
    setsockopt(fd, SOL_SOCKET, SO_SNDTIMEO, &tv, sizeof(tv));
    if (connect(fd, (struct sockaddr *)&addr, sizeof(addr)) != 0) {
        close(fd);
        return -1;
    }
    return fd;
}

static void load_target(const char *config_path, const char **host, int *port,
                        const char **pid_file)
{
    static char host_buf[64];
    static char pid_buf[256];
    netd_config_t cfg;
    netd_config_status_t st = NETD_CONFIG_OK;
    char err[256];
    if (netd_config_load(config_path, &cfg, &st, err, sizeof(err)) == 0) {
        netd_str_copy(host_buf, sizeof(host_buf), cfg.bind_address);
        netd_str_copy(pid_buf, sizeof(pid_buf), cfg.pid_file);
        *host = host_buf;
        *port = cfg.port;
        *pid_file = pid_buf;
    } else {
        *host = NETD_DEFAULT_BIND;
        *port = NETD_DEFAULT_PORT;
        *pid_file = NETD_DEFAULT_PID_FILE;
    }
}

static int do_status(const char *pid_file)
{
    int pid = 0;
    FILE *fp = fopen(pid_file, "r");
    if (fp == NULL) {
        printf("status=not-running reason=no_pid_file\n");
        return 3;
    }
    char buf[64];
    if (fgets(buf, sizeof(buf), fp) == NULL) {
        fclose(fp);
        printf("status=not-running reason=empty_pid_file\n");
        return 3;
    }
    fclose(fp);
    char *end = NULL;
    long v = strtol(buf, &end, 10);
    if (end == buf || v <= 0) {
        printf("status=not-running reason=invalid_pid_file\n");
        return 3;
    }
    pid = (int)v;
    if (kill((pid_t)pid, 0) == 0) {
        printf("status=running pid=%d\n", pid);
        return 0;
    }
    if (errno == EPERM) {
        printf("status=running pid=%d note=permission\n", pid);
        return 0;
    }
    printf("status=not-running pid=%d reason=process_absent\n", pid);
    return 3;
}

static int send_and_print(const char *host, int port, const char *line)
{
    int fd = tcp_connect(host, port, 3000);
    if (fd < 0) {
        fprintf(stderr, "netctl: connect failed host=%s port=%d\n", host, port);
        return 1;
    }
    if (write_all(fd, line, strlen(line)) != 0 ||
        write_all(fd, "\n", 1) != 0) {
        fprintf(stderr, "netctl: write failed\n");
        close(fd);
        return 1;
    }
    char resp[8192];
    int n = read_line(fd, resp, sizeof(resp), 5000);
    if (n < 0) {
        fprintf(stderr, "netctl: read failed\n");
        close(fd);
        return 1;
    }
    printf("%s\n", resp);
    close(fd);
    if (resp[0] == 'O' && resp[1] == 'K') {
        return 0;
    }
    return 2;
}

static int send_session(const char *host, int port,
                        const char **lines, int n_lines)
{
    int fd = tcp_connect(host, port, 3000);
    if (fd < 0) {
        fprintf(stderr, "netctl: connect failed host=%s port=%d\n", host, port);
        return 1;
    }
    for (int i = 0; i < n_lines; i++) {
        if (write_all(fd, lines[i], strlen(lines[i])) != 0 ||
            write_all(fd, "\n", 1) != 0) {
            fprintf(stderr, "netctl: write failed\n");
            close(fd);
            return 1;
        }
        char resp[8192];
        int n = read_line(fd, resp, sizeof(resp), 5000);
        if (n < 0) {
            fprintf(stderr, "netctl: read failed\n");
            close(fd);
            return 1;
        }
        printf("%s\n", resp);
    }
    close(fd);
    return 0;
}

static void usage(void)
{
    fprintf(stderr,
            "Usage:\n"
            "  netctl --config PATH status\n"
            "  netctl --config PATH ping\n"
            "  netctl --config PATH auth <token>\n"
            "  netctl --config PATH put <ns> <key> <ttl> <value>\n"
            "  netctl --config PATH get <ns> <key> [--token TOKEN]\n"
            "  netctl --config PATH list <ns> <prefix> <limit> <after|->\n"
            "  netctl --config PATH update <ns> <key> <ver> <ttl> <value>\n"
            "  netctl --config PATH delete <ns> <key> <ver>\n"
            "  netctl --config PATH health [--token TOKEN]\n"
            "  netctl --config PATH stats [--token TOKEN]\n"
            "  netctl --host HOST --port PORT <command> [args...]\n"
            "  netctl --help\n");
}

int main(int argc, char **argv)
{
    const char *config_path = "conf/netd.conf";
    const char *host_override = NULL;
    int port_override = 0;
    int opt = 0;
    int idx = 0;
    static struct option long_opts[] = {
        {"config", required_argument, 0, 'c'},
        {"host",   required_argument, 0, 'H'},
        {"port",   required_argument, 0, 'P'},
        {"token",  required_argument, 0, 't'},
        {"help",   no_argument,       0, 'h'},
        {0, 0, 0, 0}
    };
    const char *token_arg = NULL;
    while ((opt = getopt_long(argc, argv, "c:H:P:t:h", long_opts, &idx)) != -1) {
        switch (opt) {
        case 'c': config_path = optarg; break;
        case 'H': host_override = optarg; break;
        case 'P': port_override = atoi(optarg); break;
        case 't': token_arg = optarg; break;
        case 'h': usage(); return 0;
        default: usage(); return 2;
        }
    }
    if (optind >= argc) {
        usage();
        return 2;
    }
    const char *subcmd = argv[optind];
    const char *host = NULL;
    int port = 0;
    const char *pid_file = NULL;
    if (host_override != NULL && port_override > 0) {
        host = host_override;
        port = port_override;
        pid_file = NULL;
    } else {
        load_target(config_path, &host, &port, &pid_file);
    }

    if (strcmp(subcmd, "status") == 0) {
        if (pid_file == NULL) {
            fprintf(stderr, "netctl: status requires --config\n");
            return 2;
        }
        return do_status(pid_file);
    }
    if (strcmp(subcmd, "ping") == 0) {
        const char *lines[1] = {"PING"};
        return send_session(host, port, lines, 1);
    }
    if (strcmp(subcmd, "auth") == 0) {
        if (optind + 1 >= argc) {
            fprintf(stderr, "netctl: auth requires <token>\n");
            return 2;
        }
        char line[512];
        snprintf(line, sizeof(line), "AUTH %s", argv[optind + 1]);
        const char *lines[1] = {line};
        return send_session(host, port, lines, 1);
    }
    if (strcmp(subcmd, "put") == 0) {
        if (optind + 4 >= argc) {
            fprintf(stderr, "netctl: put requires <ns> <key> <ttl> <value> and --token\n");
            return 2;
        }
        if (token_arg == NULL) {
            fprintf(stderr, "netctl: put requires --token\n");
            return 2;
        }
        const char *ns = argv[optind + 1];
        const char *key = argv[optind + 2];
        const char *ttl = argv[optind + 3];
        const char *val = argv[optind + 4];
        char auth[512];
        snprintf(auth, sizeof(auth), "AUTH %s", token_arg);
        char put[1024];
        snprintf(put, sizeof(put), "PUT %s %s %s %s", ns, key, ttl, val);
        const char *lines[2] = {auth, put};
        return send_session(host, port, lines, 2);
    }
    if (strcmp(subcmd, "get") == 0) {
        if (optind + 2 >= argc) {
            fprintf(stderr, "netctl: get requires <ns> <key>\n");
            return 2;
        }
        if (token_arg == NULL) {
            fprintf(stderr, "netctl: get requires --token\n");
            return 2;
        }
        const char *ns = argv[optind + 1];
        const char *key = argv[optind + 2];
        char auth[512];
        snprintf(auth, sizeof(auth), "AUTH %s", token_arg);
        char get[512];
        snprintf(get, sizeof(get), "GET %s %s", ns, key);
        const char *lines[2] = {auth, get};
        return send_session(host, port, lines, 2);
    }
    if (strcmp(subcmd, "list") == 0) {
        if (optind + 4 >= argc) {
            fprintf(stderr, "netctl: list requires <ns> <prefix> <limit> <after|->\n");
            return 2;
        }
        if (token_arg == NULL) {
            fprintf(stderr, "netctl: list requires --token\n");
            return 2;
        }
        const char *ns = argv[optind + 1];
        const char *prefix = argv[optind + 2];
        const char *limit = argv[optind + 3];
        const char *after = argv[optind + 4];
        char auth[512];
        snprintf(auth, sizeof(auth), "AUTH %s", token_arg);
        char list[1024];
        snprintf(list, sizeof(list), "LIST %s %s %s %s", ns, prefix, limit, after);
        const char *lines[2] = {auth, list};
        return send_session(host, port, lines, 2);
    }
    if (strcmp(subcmd, "update") == 0) {
        if (optind + 5 >= argc) {
            fprintf(stderr, "netctl: update requires <ns> <key> <ver> <ttl> <value>\n");
            return 2;
        }
        if (token_arg == NULL) {
            fprintf(stderr, "netctl: update requires --token\n");
            return 2;
        }
        const char *ns = argv[optind + 1];
        const char *key = argv[optind + 2];
        const char *ver = argv[optind + 3];
        const char *ttl = argv[optind + 4];
        const char *val = argv[optind + 5];
        char auth[512];
        snprintf(auth, sizeof(auth), "AUTH %s", token_arg);
        char upd[1024];
        snprintf(upd, sizeof(upd), "UPDATE %s %s %s %s %s", ns, key, ver, ttl, val);
        const char *lines[2] = {auth, upd};
        return send_session(host, port, lines, 2);
    }
    if (strcmp(subcmd, "delete") == 0) {
        if (optind + 3 >= argc) {
            fprintf(stderr, "netctl: delete requires <ns> <key> <ver>\n");
            return 2;
        }
        if (token_arg == NULL) {
            fprintf(stderr, "netctl: delete requires --token\n");
            return 2;
        }
        const char *ns = argv[optind + 1];
        const char *key = argv[optind + 2];
        const char *ver = argv[optind + 3];
        char auth[512];
        snprintf(auth, sizeof(auth), "AUTH %s", token_arg);
        char del[512];
        snprintf(del, sizeof(del), "DELETE %s %s %s", ns, key, ver);
        const char *lines[2] = {auth, del};
        return send_session(host, port, lines, 2);
    }
    if (strcmp(subcmd, "health") == 0) {
        char auth[512] = "";
        const char *lines[2];
        int n_lines = 1;
        if (token_arg != NULL) {
            snprintf(auth, sizeof(auth), "AUTH %s", token_arg);
            lines[0] = auth;
            lines[1] = "HEALTH";
            n_lines = 2;
        } else {
            lines[0] = "HEALTH";
            n_lines = 1;
        }
        return send_session(host, port, lines, n_lines);
    }
    if (strcmp(subcmd, "stats") == 0) {
        char auth[512] = "";
        const char *lines[2];
        int n_lines = 1;
        if (token_arg != NULL) {
            snprintf(auth, sizeof(auth), "AUTH %s", token_arg);
            lines[0] = auth;
            lines[1] = "STATS";
            n_lines = 2;
        } else {
            lines[0] = "STATS";
            n_lines = 1;
        }
        return send_session(host, port, lines, n_lines);
    }
    if (strcmp(subcmd, "raw") == 0) {
        if (optind + 1 >= argc) {
            fprintf(stderr, "netctl: raw requires one argument\n");
            return 2;
        }
        return send_and_print(host, port, argv[optind + 1]);
    }
    if (strcmp(subcmd, "raw-multiline") == 0) {
        int fd = tcp_connect(host, port, 3000);
        if (fd < 0) {
            fprintf(stderr, "netctl: connect failed host=%s port=%d\n",
                    host, port);
            return 1;
        }
        char line[4096];
        while (fgets(line, sizeof(line), stdin) != NULL) {
            size_t ll = strlen(line);
            while (ll > 0 && (line[ll - 1] == '\n' || line[ll - 1] == '\r')) {
                line[--ll] = '\0';
            }
            if (ll == 0) {
                continue;
            }
            if (write_all(fd, line, ll) != 0 || write_all(fd, "\n", 1) != 0) {
                fprintf(stderr, "netctl: write failed\n");
                close(fd);
                return 1;
            }
            char resp[8192];
            int n = read_line(fd, resp, sizeof(resp), 5000);
            if (n < 0) {
                fprintf(stderr, "netctl: read failed\n");
                close(fd);
                return 1;
            }
            printf("%s\n", resp);
        }
        close(fd);
        return 0;
    }
    fprintf(stderr, "netctl: unknown subcommand '%s'\n", subcmd);
    usage();
    return 2;
}