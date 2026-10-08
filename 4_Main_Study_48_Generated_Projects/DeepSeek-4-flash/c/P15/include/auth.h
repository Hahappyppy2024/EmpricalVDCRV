/*
 * auth.h -- OpenSSL libcrypto SHA-256 token hashing and constant-time
 * digest comparison. Plaintext tokens are never stored.
 */
#ifndef NETD_AUTH_H
#define NETD_AUTH_H

#include <stddef.h>

#define NETD_SHA256_HEX_LEN 65 /* 64 hex chars + NUL */

/* Compute the lowercase SHA-256 hex digest of `token` into out_hex. */
int auth_hash_token(const char *token, char *out_hex, size_t outlen);

/* Constant-time comparison of two hex digests (same length). */
int auth_hex_equals(const char *a, const char *b);

#endif /* NETD_AUTH_H */
