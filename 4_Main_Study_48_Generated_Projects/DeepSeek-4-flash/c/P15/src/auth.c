/*
 * auth.c -- OpenSSL SHA-256 token hashing and constant-time comparison.
 * Plaintext tokens are never stored; only the digest is persisted.
 */
#include "auth.h"

#include <ctype.h>
#include <openssl/crypto.h>
#include <openssl/evp.h>
#include <stdio.h>
#include <string.h>

int auth_hash_token(const char *token, char *out_hex, size_t outlen) {
    if (token == NULL || out_hex == NULL || outlen < NETD_SHA256_HEX_LEN) {
        return -1;
    }
    unsigned char digest[EVP_MAX_MD_SIZE];
    unsigned int dlen = 0;
    EVP_MD_CTX *ctx = EVP_MD_CTX_new();
    if (ctx == NULL) return -1;

    int rc = -1;
    if (EVP_DigestInit_ex(ctx, EVP_sha256(), NULL) == 1 &&
        EVP_DigestUpdate(ctx, token, strlen(token)) == 1 &&
        EVP_DigestFinal_ex(ctx, digest, &dlen) == 1) {
        static const char hexd[] = "0123456789abcdef";
        size_t o = 0;
        for (unsigned int i = 0; i < dlen && o + 2 < outlen; i++) {
            out_hex[o++] = hexd[(digest[i] >> 4) & 0x0f];
            out_hex[o++] = hexd[digest[i] & 0x0f];
        }
        out_hex[o] = '\0';
        rc = 0;
    }
    EVP_MD_CTX_free(ctx);
    return rc;
}

int auth_hex_equals(const char *a, const char *b) {
    size_t n = strlen(a);
    if (n != strlen(b)) return 0;
    if (n == 0) return 0;
    return CRYPTO_memcmp(a, b, n) == 0;
}
