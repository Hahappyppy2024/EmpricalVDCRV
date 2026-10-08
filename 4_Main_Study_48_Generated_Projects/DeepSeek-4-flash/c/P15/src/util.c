/*
 * util.c -- POSIX helpers: time, directories, hex, process checks, TCP.
 */
#include "util.h"

#include <errno.h>
#include <fcntl.h>
#include <limits.h>
#include <netdb.h>
#include <poll.h>
#include <signal.h>
#include <stdio.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/stat.h>
#include <sys/time.h>
#include <sys/types.h>
#include <time.h>
#include <unistd.h>

#include <arpa/inet.h>
#include <netinet/in.h>

int64_t util_now_epoch(void) {
    return (int64_t)time(NULL);
}

int64_t util_mono_ms(void) {
    struct timespec ts;
    if (clock_gettime(CLOCK_MONOTONIC, &ts) != 0) {
        return 0;
    }
    return (int64_t)ts.tv_sec * 1000 + ts.tv_nsec / 1000000;
}

int util_mkdirs_dir(const char *path) {
    char tmp[PATH_MAX];
    snprintf(tmp, sizeof(tmp), "%s", path);
    size_t len = strlen(tmp);
    if (len == 0) return -1;
    if (tmp[len - 1] == '/') tmp[len - 1] = '\0';
    for (char *p = tmp + 1; *p; p++) {
        if (*p == '/') {
            *p = '\0';
            if (mkdir(tmp, 0755) != 0 && errno != EEXIST) return -1;
            *p = '/';
        }
    }
    if (mkdir(tmp, 0755) != 0 && errno != EEXIST) return -1;
    return 0;
}

int util_mkdirs_file(const char *path) {
    char tmp[PATH_MAX];
    snprintf(tmp, sizeof(tmp), "%s", path);
    char *slash = strrchr(tmp, '/');
    if (slash == NULL) return 0; /* no parent directory */
    *slash = '\0';
    if (*tmp == '\0') return 0;
    return util_mkdirs_dir(tmp);
}

int util_hex_encode(const uint8_t *in, size_t len, char *out, size_t outlen) {
    if (outlen < len * 2 + 1) return -1;
    static const char hexd[] = "0123456789abcdef";
    for (size_t i = 0; i < len; i++) {
        out[i * 2] = hexd[(in[i] >> 4) & 0x0f];
        out[i * 2 + 1] = hexd[in[i] & 0x0f];
    }
    out[len * 2] = '\0';
    return 0;
}

static int hex_val(char c) {
    if (c >= '0' && c <= '9') return c - '0';
    if (c >= 'a' && c <= 'f') return c - 'a' + 10;
    if (c >= 'A' && c <= 'F') return c - 'A' + 10;
    return -1;
}

int util_hex_decode(const char *in, size_t len, uint8_t *out, size_t outcap) {
    if (len % 2 != 0 || len / 2 > outcap) return -1;
    for (size_t i = 0; i < len; i += 2) {
        int hi = hex_val(in[i]);
        int lo = hex_val(in[i + 1]);
        if (hi < 0 || lo < 0) return -1;
        out[i / 2] = (uint8_t)((hi << 4) | lo);
    }
    return 0;
}

int util_proc_name(long pid, char *out, size_t outlen) {
    char path[64];
    snprintf(path, sizeof(path), "/proc/%ld/comm", pid);
    FILE *f = fopen(path, "r");
    if (f == NULL) return -1;
    if (fgets(out, (int)outlen, f) == NULL) {
        fclose(f);
        return -1;
    }
    fclose(f);
    size_t n = strlen(out);
    while (n > 0 && (out[n - 1] == '\n' || out[n - 1] == '\r')) out[--n] = '\0';
    return 0;
}

int util_pid_alive(long pid) {
    if (pid <= 0) return 0;
    if (kill((pid_t)pid, 0) == 0) return 1;
    return errno == EPERM;
}

int util_tcp_connect(const char *host, int port, int timeout_ms) {
    char portstr[16];
    snprintf(portstr, sizeof(portstr), "%d", port);

    struct addrinfo hints;
    memset(&hints, 0, sizeof(hints));
    hints.ai_family = AF_INET;
    hints.ai_socktype = SOCK_STREAM;

    struct addrinfo *res = NULL;
    if (getaddrinfo(host, portstr, &hints, &res) != 0 || res == NULL) {
        return -1;
    }

    int fd = socket(res->ai_family, res->ai_socktype, res->ai_protocol);
    if (fd < 0) {
        freeaddrinfo(res);
        return -1;
    }

    int fl = fcntl(fd, F_GETFL, 0);
    fcntl(fd, F_SETFL, fl | O_NONBLOCK);

    int rc = connect(fd, res->ai_addr, res->ai_addrlen);
    if (rc != 0 && errno == EINPROGRESS) {
        struct pollfd pfd;
        pfd.fd = fd;
        pfd.events = POLLOUT;
        int pr = poll(&pfd, 1, timeout_ms);
        if (pr <= 0 || (pfd.revents & (POLLERR | POLLHUP))) {
            close(fd);
            freeaddrinfo(res);
            return -1;
        }
        int soerr = 0;
        socklen_t slen = sizeof(soerr);
        if (getsockopt(fd, SOL_SOCKET, SO_ERROR, &soerr, &slen) != 0 ||
            soerr != 0) {
            close(fd);
            freeaddrinfo(res);
            return -1;
        }
    } else if (rc != 0) {
        close(fd);
        freeaddrinfo(res);
        return -1;
    }

    fcntl(fd, F_SETFL, fl);
    freeaddrinfo(res);
    return fd;
}

ssize_t util_write_all(int fd, const void *buf, size_t len, int timeout_ms) {
    const uint8_t *p = buf;
    size_t left = len;
    int64_t deadline = util_mono_ms() + timeout_ms;
    while (left > 0) {
        int64_t now = util_mono_ms();
        if (now >= deadline) return -1;
        int tmo = (int)(deadline - now);
        struct pollfd pfd;
        pfd.fd = fd;
        pfd.events = POLLOUT;
        int pr = poll(&pfd, 1, tmo);
        if (pr <= 0) return -1;
        if (pr < 0 && errno == EINTR) continue;
        ssize_t n = send(fd, p, left, 0);
        if (n < 0) {
            if (errno == EINTR) continue;
            if (errno == EAGAIN || errno == EWOULDBLOCK) continue;
            return -1;
        }
        if (n == 0) return -1;
        p += n;
        left -= (size_t)n;
    }
    return (ssize_t)len;
}

int util_read_line(int fd, char *buf, size_t cap, int timeout_ms) {
    size_t used = 0;
    int64_t deadline = util_mono_ms() + timeout_ms;
    while (used + 1 < cap) {
        int64_t now = util_mono_ms();
        if (now >= deadline) return -1;
        int tmo = (int)(deadline - now);
        struct pollfd pfd;
        pfd.fd = fd;
        pfd.events = POLLIN;
        int pr = poll(&pfd, 1, tmo);
        if (pr == 0) return -1;
        if (pr < 0) {
            if (errno == EINTR) continue;
            return -1;
        }
        ssize_t n = recv(fd, buf + used, cap - used - 1, 0);
        if (n == 0) return used > 0 ? (int)used : 0; /* EOF */
        if (n < 0) {
            if (errno == EINTR || errno == EAGAIN || errno == EWOULDBLOCK) {
                continue;
            }
            return -1;
        }
        for (ssize_t i = 0; i < n; i++) {
            if (buf[used] == '\n') {
                buf[used] = '\0';
                return (int)used;
            }
            used++;
        }
    }
    buf[used] = '\0';
    return -1; /* line longer than cap */
}
