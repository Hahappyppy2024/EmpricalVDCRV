/*
 * token_hash.c -- operator utility: print the SHA-256 hex digest of a token
 * so it can be stored in the access_tokens table without ever persisting the
 * plaintext token.
 *
 * Usage: token_hash <token>
 */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "auth.h"
#include "netd.h"

int main(int argc, char **argv) {
    if (argc != 2) {
        fprintf(stderr, "usage: token_hash <token>\n");
        return 2;
    }
    char hex[NETD_SHA256_HEX_LEN];
    if (auth_hash_token(argv[1], hex, sizeof(hex)) != 0) {
        fprintf(stderr, "token_hash: hashing failed\n");
        return 1;
    }
    printf("%s\n", hex);
    return 0;
}
