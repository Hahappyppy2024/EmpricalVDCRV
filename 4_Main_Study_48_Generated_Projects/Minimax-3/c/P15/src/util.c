#include "netd/util.h"

#include <ctype.h>
#include <errno.h>
#include <fcntl.h>
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <sys/time.h>
#include <sys/types.h>
#include <time.h>
#include <unistd.h>

#ifndef O_CLOEXEC
#define O_CLOEXEC 0
#endif

#ifdef _WIN32
static int netd_mkdir_one(const char *path)
{
    return mkdir(path);
}
#else
static int netd_mkdir_one(const char *path)
{
    return mkdir(path, 0755);
}
#endif

int64_t netd_now_seconds(void)
{
    struct timespec ts;
    if (clock_gettime(CLOCK_REALTIME, &ts) != 0) {
        return (int64_t)time(NULL);
    }
    return (int64_t)ts.tv_sec;
}

int64_t netd_now_monotonic_ms(void)
{
    struct timespec ts;
    if (clock_gettime(CLOCK_MONOTONIC, &ts) != 0) {
        return 0;
    }
    return (int64_t)ts.tv_sec * 1000 + (int64_t)ts.tv_nsec / 1000000;
}

void netd_sleep_ms(int ms)
{
    if (ms <= 0) {
        return;
    }
    struct timespec ts;
    ts.tv_sec = ms / 1000;
    ts.tv_nsec = (long)(ms % 1000) * 1000000L;
    nanosleep(&ts, NULL);
}

bool netd_str_equal(const char *a, const char *b)
{
    if (a == NULL || b == NULL) {
        return a == b;
    }
    return strcmp(a, b) == 0;
}

bool netd_str_starts_with(const char *s, const char *prefix)
{
    if (s == NULL || prefix == NULL) {
        return false;
    }
    size_t pl = strlen(prefix);
    return strncmp(s, prefix, pl) == 0;
}

bool netd_str_ends_with(const char *s, const char *suffix)
{
    if (s == NULL || suffix == NULL) {
        return false;
    }
    size_t sl = strlen(s);
    size_t sufl = strlen(suffix);
    if (sufl > sl) {
        return false;
    }
    return strncmp(s + sl - sufl, suffix, sufl) == 0;
}

size_t netd_str_copy(char *dst, size_t dst_size, const char *src)
{
    if (dst == NULL || dst_size == 0) {
        return 0;
    }
    if (src == NULL) {
        dst[0] = '\0';
        return 0;
    }
    size_t i = 0;
    while (i + 1 < dst_size && src[i] != '\0') {
        dst[i] = src[i];
        i++;
    }
    dst[i] = '\0';
    return i;
}

static bool valid_token_char(char c)
{
    return (c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') ||
           (c >= '0' && c <= '9') || c == '-' || c == '_' || c == '.';
}

static bool valid_ns_char(char c)
{
    return (c >= 'a' && c <= 'z') || (c >= '0' && c <= '9') ||
           c == '-' || c == '_' || c == '*';
}

bool netd_str_is_valid_token(const char *s, size_t max_len)
{
    if (s == NULL) {
        return false;
    }
    size_t len = 0;
    while (s[len] != '\0') {
        if (len >= max_len) {
            return false;
        }
        if (!valid_token_char(s[len])) {
            return false;
        }
        len++;
    }
    return len >= 1 && len <= max_len;
}

bool netd_str_is_valid_namespace(const char *s, size_t max_len)
{
    if (s == NULL) {
        return false;
    }
    if (s[0] == '*' && s[1] == '\0') {
        return true;
    }
    size_t len = 0;
    while (s[len] != '\0') {
        if (len >= max_len) {
            return false;
        }
        if (!valid_ns_char(s[len])) {
            return false;
        }
        len++;
    }
    return len >= 1 && len <= max_len;
}

bool netd_str_is_valid_key(const char *s, size_t max_len)
{
    if (s == NULL) {
        return false;
    }
    size_t len = 0;
    while (s[len] != '\0') {
        if (len >= max_len) {
            return false;
        }
        unsigned char c = (unsigned char)s[len];
        if (c <= 0x20 || c == 0x7f) {
            return false;
        }
        len++;
    }
    return len >= 1;
}

bool netd_str_is_valid_utf8(const char *s, size_t len)
{
    if (s == NULL) {
        return false;
    }
    size_t i = 0;
    while (i < len) {
        unsigned char c = (unsigned char)s[i];
        if (c == 0x00) {
            return false;
        }
        if (c < 0x80) {
            i++;
            continue;
        }
        size_t extra = 0;
        unsigned int min_code = 0;
        if ((c & 0xE0) == 0xC0) {
            extra = 1;
            min_code = 0x80;
        } else if ((c & 0xF0) == 0xE0) {
            extra = 2;
            min_code = 0x800;
        } else if ((c & 0xF8) == 0xF0) {
            extra = 3;
            min_code = 0x10000;
        } else {
            return false;
        }
        if (i + extra >= len) {
            return false;
        }
        unsigned int code = c & (0xFFu >> (extra + 2));
        for (size_t k = 0; k < extra; k++) {
            unsigned char cc = (unsigned char)s[i + 1 + k];
            if ((cc & 0xC0) != 0x80) {
                return false;
            }
            code = (code << 6) | (cc & 0x3F);
        }
        if (code < min_code) {
            return false;
        }
        if (code >= 0xD800 && code <= 0xDFFF) {
            return false;
        }
        i += 1 + extra;
    }
    return true;
}

bool netd_parse_int(const char *s, int64_t *out)
{
    if (s == NULL || s[0] == '\0') {
        return false;
    }
    char *end = NULL;
    errno = 0;
    long long v = strtoll(s, &end, 10);
    if (errno != 0 || end == s || (end != NULL && *end != '\0')) {
        return false;
    }
    *out = (int64_t)v;
    return true;
}

bool netd_parse_uint(const char *s, uint64_t *out)
{
    if (s == NULL || s[0] == '\0') {
        return false;
    }
    char *end = NULL;
    errno = 0;
    unsigned long long v = strtoull(s, &end, 10);
    if (errno != 0 || end == s || (end != NULL && *end != '\0')) {
        return false;
    }
    *out = (uint64_t)v;
    return true;
}

const char *netd_basename(const char *path)
{
    if (path == NULL) {
        return "";
    }
    const char *slash = strrchr(path, '/');
    return slash ? slash + 1 : path;
}

int netd_ensure_directory(const char *path)
{
    if (path == NULL || path[0] == '\0') {
        return -1;
    }
    char tmp[512];
    size_t len = strlen(path);
    if (len >= sizeof(tmp)) {
        return -1;
    }
    memcpy(tmp, path, len + 1);
    if (tmp[len - 1] == '/') {
        tmp[len - 1] = '\0';
    }
    for (char *p = tmp + 1; *p; p++) {
        if (*p == '/') {
            *p = '\0';
            if (netd_mkdir_one(tmp) != 0 && errno != EEXIST) {
                return -1;
            }
            *p = '/';
        }
    }
    if (netd_mkdir_one(tmp) != 0 && errno != EEXIST) {
        return -1;
    }
    return 0;
}

int netd_path_join(char *out, size_t out_size, const char *dir, const char *name)
{
    if (out == NULL || out_size == 0 || dir == NULL || name == NULL) {
        return -1;
    }
    size_t dl = strlen(dir);
    int needs_slash = dl > 0 && dir[dl - 1] != '/';
    int n = snprintf(out, out_size, "%s%s%s", dir, needs_slash ? "/" : "", name);
    if (n < 0 || (size_t)n >= out_size) {
        return -1;
    }
    return 0;
}

int netd_path_dirname(char *out, size_t out_size, const char *path)
{
    if (out == NULL || out_size == 0 || path == NULL) {
        return -1;
    }
    size_t pl = strlen(path);
    if (pl == 0) {
        if (out_size > 0) {
            out[0] = '.';
            if (out_size > 1) {
                out[1] = '\0';
            }
        }
        return 0;
    }
    if (pl >= out_size) {
        return -1;
    }
    size_t cut = pl;
    while (cut > 0 && path[cut - 1] != '/' && path[cut - 1] != '\\') {
        cut--;
    }
    if (cut == 0) {
        out[0] = '.';
        out[1] = '\0';
        return 0;
    }
    if (cut == 1) {
        out[0] = path[0];
        out[1] = '\0';
        return 0;
    }
    memcpy(out, path, cut - 1);
    out[cut - 1] = '\0';
    return 0;
}

void netd_random_token(char *buf, size_t len)
{
    if (buf == NULL || len == 0) {
        return;
    }
    static const char alphabet[] =
        "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    int fd = open("/dev/urandom", O_RDONLY | O_CLOEXEC);
    unsigned char raw[64];
    if (fd >= 0 && (size_t)read(fd, raw, sizeof(raw)) == sizeof(raw)) {
        close(fd);
        for (size_t i = 0; i + 1 < len; i++) {
            buf[i] = alphabet[raw[i] % (sizeof(alphabet) - 1)];
        }
        buf[len - 1] = '\0';
        return;
    }
    if (fd >= 0) {
        close(fd);
    }
    srand((unsigned int)(time(NULL) ^ (unsigned int)getpid()));
    for (size_t i = 0; i + 1 < len; i++) {
        buf[i] = alphabet[rand() % (sizeof(alphabet) - 1)];
    }
    buf[len - 1] = '\0';
}

void netd_hex_encode(const unsigned char *in, size_t in_len, char *out)
{
    static const char hex[] = "0123456789abcdef";
    for (size_t i = 0; i < in_len; i++) {
        out[i * 2] = hex[(in[i] >> 4) & 0x0F];
        out[i * 2 + 1] = hex[in[i] & 0x0F];
    }
    out[in_len * 2] = '\0';
}