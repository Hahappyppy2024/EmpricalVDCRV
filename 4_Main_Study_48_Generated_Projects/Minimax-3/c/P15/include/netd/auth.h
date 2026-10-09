#ifndef NETD_AUTH_H
#define NETD_AUTH_H

#include <stdint.h>
#include <stdbool.h>
#include <stddef.h>

typedef struct netd_auth netd_auth_t;

netd_auth_t *netd_auth_create(void);
void netd_auth_destroy(netd_auth_t *auth);

int netd_auth_hash_token(netd_auth_t *auth, const char *token, char *out_hex, size_t out_size);
int netd_auth_constant_time_equal(const char *a, const char *b);
int netd_auth_generate_token(char *out, size_t out_size);

#endif