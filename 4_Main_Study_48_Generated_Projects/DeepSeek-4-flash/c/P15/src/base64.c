/*
 * base64.c -- strict RFC 4648 Base64 codec.
 */
#include "proto.h"

#include <stdio.h>
#include <string.h>

static const char b64_alphabet[] =
    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";

static int b64_val(char c) {
    if (c >= 'A' && c <= 'Z') return c - 'A';
    if (c >= 'a' && c <= 'z') return c - 'a' + 26;
    if (c >= '0' && c <= '9') return c - '0' + 52;
    if (c == '+') return 62;
    if (c == '/') return 63;
    return -1;
}

int proto_b64_encode(const uint8_t *in, size_t inlen, char *out, size_t outlen) {
    if (inlen > (SIZE_MAX / 4) * 3) return -1;
    size_t need = ((inlen + 2) / 3) * 4 + 1;
    if (outlen < need) return -1;

    size_t o = 0;
    size_t i = 0;
    while (i + 3 <= inlen) {
        uint32_t v = ((uint32_t)in[i] << 16) | ((uint32_t)in[i + 1] << 8) |
                     (uint32_t)in[i + 2];
        out[o++] = b64_alphabet[(v >> 18) & 63];
        out[o++] = b64_alphabet[(v >> 12) & 63];
        out[o++] = b64_alphabet[(v >> 6) & 63];
        out[o++] = b64_alphabet[v & 63];
        i += 3;
    }
    size_t rem = inlen - i;
    if (rem == 1) {
        uint32_t v = (uint32_t)in[i] << 16;
        out[o++] = b64_alphabet[(v >> 18) & 63];
        out[o++] = b64_alphabet[(v >> 12) & 63];
        out[o++] = '=';
        out[o++] = '=';
    } else if (rem == 2) {
        uint32_t v = ((uint32_t)in[i] << 16) | ((uint32_t)in[i + 1] << 8);
        out[o++] = b64_alphabet[(v >> 18) & 63];
        out[o++] = b64_alphabet[(v >> 12) & 63];
        out[o++] = b64_alphabet[(v >> 6) & 63];
        out[o++] = '=';
    }
    out[o] = '\0';
    return 0;
}

int proto_b64_decode(const char *in, size_t inlen, uint8_t *out, size_t outcap,
                     size_t *out_len, char *err, size_t errlen) {
    *out_len = 0;
    if (inlen == 0) return 0;
    if (inlen % 4 != 0) {
        snprintf(err, errlen, "invalid Base64 length");
        return -1;
    }

    size_t pads = 0;
    if (in[inlen - 1] == '=') pads++;
    if (inlen >= 2 && in[inlen - 2] == '=') pads++;
    if (pads > 2) {
        snprintf(err, errlen, "invalid Base64 padding");
        return -1;
    }
    for (size_t i = 0; i + pads < inlen; i++) {
        if (b64_val(in[i]) < 0) {
            snprintf(err, errlen, "invalid Base64 character");
            return -1;
        }
    }

    size_t decoded = (inlen / 4) * 3 - pads;
    if (decoded > outcap) {
        snprintf(err, errlen, "decoded value too large");
        return -1;
    }

    size_t o = 0;
    for (size_t i = 0; i < inlen; i += 4) {
        int a = b64_val(in[i]);
        int b = b64_val(in[i + 1]);
        int c = (in[i + 2] == '=') ? 0 : b64_val(in[i + 2]);
        int d = (in[i + 3] == '=') ? 0 : b64_val(in[i + 3]);
        uint32_t v = ((uint32_t)a << 18) | ((uint32_t)b << 12) |
                     ((uint32_t)c << 6) | (uint32_t)d;
        out[o++] = (uint8_t)((v >> 16) & 0xff);
        if (in[i + 2] != '=') out[o++] = (uint8_t)((v >> 8) & 0xff);
        if (in[i + 3] != '=') out[o++] = (uint8_t)(v & 0xff);
    }

    /* Reject non-canonical encodings: leftover bits must be zero.
     * "XY=" : the 3rd char's low 2 bits must be 0.
     * "X==" : the 2nd char's low 4 bits must be 0. */
    size_t q = inlen / 4;
    if (q > 0 && pads == 1) {
        int c = b64_val(in[inlen - 2]);
        if (c < 0 || (c & 0x03) != 0) {
            snprintf(err, errlen, "non-canonical Base64 padding");
            return -1;
        }
    }
    if (q > 0 && pads == 2) {
        int b = b64_val(in[inlen - 3]);
        if (b < 0 || (b & 0x0f) != 0) {
            snprintf(err, errlen, "non-canonical Base64 padding");
            return -1;
        }
    }

    *out_len = decoded;
    return 0;
}
