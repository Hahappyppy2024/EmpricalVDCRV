#ifndef NETD_UTIL_H
#define NETD_UTIL_H

#include <stddef.h>
#include <stdint.h>
#include <stdbool.h>
#include <time.h>

int64_t netd_now_seconds(void);
int64_t netd_now_monotonic_ms(void);
void netd_sleep_ms(int ms);
bool netd_str_equal(const char *a, const char *b);
bool netd_str_starts_with(const char *s, const char *prefix);
bool netd_str_ends_with(const char *s, const char *suffix);
size_t netd_str_copy(char *dst, size_t dst_size, const char *src);
bool netd_str_is_valid_token(const char *s, size_t max_len);
bool netd_str_is_valid_namespace(const char *s, size_t max_len);
bool netd_str_is_valid_key(const char *s, size_t max_len);
bool netd_str_is_valid_utf8(const char *s, size_t len);
bool netd_parse_int(const char *s, int64_t *out);
bool netd_parse_uint(const char *s, uint64_t *out);
const char *netd_basename(const char *path);
int netd_ensure_directory(const char *path);
int netd_path_join(char *out, size_t out_size, const char *dir, const char *name);
int netd_path_dirname(char *out, size_t out_size, const char *path);
void netd_random_token(char *buf, size_t len);
void netd_hex_encode(const unsigned char *in, size_t in_len, char *out);

#endif