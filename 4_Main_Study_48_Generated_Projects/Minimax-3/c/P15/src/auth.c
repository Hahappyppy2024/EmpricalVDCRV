#include "netd/auth.h"
#include "netd/util.h"

#include <openssl/evp.h>
#include <openssl/sha.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

struct netd_auth {
    int dummy;
};

netd_auth_t *netd_auth_create(void)
{
    netd_auth_t *a = calloc(1, sizeof(*a));
    return a;
}

void netd_auth_destroy(netd_auth_t *auth)
{
    free(auth);
}

int netd_auth_hash_token(netd_auth_t *auth, const char *token,
                         char *out_hex, size_t out_size)
{
    (void)auth;
    if (token == NULL || out_hex == NULL || out_size < 65) {
        return -1;
    }
    unsigned char digest[SHA256_DIGEST_LENGTH];
    if (!EVP_Digest(token, strlen(token), digest, NULL, EVP_sha256(), NULL)) {
        return -1;
    }
    netd_hex_encode(digest, SHA256_DIGEST_LENGTH, out_hex);
    return 0;
}

int netd_auth_constant_time_equal(const char *a, const char *b)
{
    if (a == NULL || b == NULL) {
        return 0;
    }
    size_t la = strlen(a);
    size_t lb = strlen(b);
    if (la != lb) {
        return 0;
    }
    unsigned char diff = 0;
    for (size_t i = 0; i < la; i++) {
        diff |= (unsigned char)(a[i] ^ b[i]);
    }
    return diff == 0;
}

int netd_auth_generate_token(char *out, size_t out_size)
{
    if (out == NULL || out_size < 33) {
        return -1;
    }
    netd_random_token(out, out_size);
    return 0;
}