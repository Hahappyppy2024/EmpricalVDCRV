#include "netd/base64.h"

#include <string.h>

static const char alphabet[] =
    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";

size_t netd_base64_encoded_len(size_t input_len)
{
    return ((input_len + 2) / 3) * 4 + 1;
}

size_t netd_base64_decoded_max_len(size_t input_len)
{
    return (input_len / 4) * 3 + 3;
}

int netd_base64_encode(const unsigned char *in, size_t in_len,
                       char *out, size_t out_size, size_t *out_len)
{
    size_t needed = netd_base64_encoded_len(in_len);
    if (out_size < needed) {
        return -1;
    }
    size_t i = 0;
    size_t o = 0;
    while (i + 3 <= in_len) {
        unsigned v = ((unsigned)in[i] << 16) | ((unsigned)in[i + 1] << 8) | in[i + 2];
        out[o++] = alphabet[(v >> 18) & 0x3F];
        out[o++] = alphabet[(v >> 12) & 0x3F];
        out[o++] = alphabet[(v >> 6) & 0x3F];
        out[o++] = alphabet[v & 0x3F];
        i += 3;
    }
    if (i < in_len) {
        unsigned v = (unsigned)in[i] << 16;
        if (i + 1 < in_len) {
            v |= (unsigned)in[i + 1] << 8;
        }
        out[o++] = alphabet[(v >> 18) & 0x3F];
        out[o++] = alphabet[(v >> 12) & 0x3F];
        out[o++] = (i + 1 < in_len) ? alphabet[(v >> 6) & 0x3F] : '=';
        out[o++] = '=';
    }
    out[o] = '\0';
    if (out_len != NULL) {
        *out_len = o;
    }
    return 0;
}

static int decode_char(unsigned char c, int *out_value)
{
    if (c >= 'A' && c <= 'Z') {
        *out_value = c - 'A';
        return 0;
    }
    if (c >= 'a' && c <= 'z') {
        *out_value = 26 + c - 'a';
        return 0;
    }
    if (c >= '0' && c <= '9') {
        *out_value = 52 + c - '0';
        return 0;
    }
    if (c == '+') {
        *out_value = 62;
        return 0;
    }
    if (c == '/') {
        *out_value = 63;
        return 0;
    }
    if (c == '=') {
        *out_value = -2;
        return 0;
    }
    return -1;
}

int netd_base64_decode(const char *in, size_t in_len,
                       unsigned char *out, size_t out_size, size_t *out_len)
{
    if (in_len % 4 != 0) {
        return -1;
    }
    size_t o = 0;
    int padding = 0;
    for (size_t i = 0; i < in_len; i += 4) {
        int v0, v1, v2, v3;
        if (decode_char((unsigned char)in[i], &v0) != 0 ||
            decode_char((unsigned char)in[i + 1], &v1) != 0 ||
            decode_char((unsigned char)in[i + 2], &v2) != 0 ||
            decode_char((unsigned char)in[i + 3], &v3) != 0) {
            return -1;
        }
        if (v0 < 0 || v1 < 0) {
            return -1;
        }
        if (v2 == -2) {
            if (v3 != -2) {
                return -1;
            }
            padding = 2;
            if (o + 1 > out_size) {
                return -1;
            }
            out[o++] = (unsigned char)((v0 << 2) | (v1 >> 4));
        } else if (v3 == -2) {
            padding = 1;
            if (o + 2 > out_size) {
                return -1;
            }
            out[o++] = (unsigned char)((v0 << 2) | (v1 >> 4));
            out[o++] = (unsigned char)(((v1 & 0x0F) << 4) | (v2 >> 2));
        } else {
            if (o + 3 > out_size) {
                return -1;
            }
            out[o++] = (unsigned char)((v0 << 2) | (v1 >> 4));
            out[o++] = (unsigned char)(((v1 & 0x0F) << 4) | (v2 >> 2));
            out[o++] = (unsigned char)(((v2 & 0x03) << 6) | v3);
        }
    }
    if (padding == 1 && in_len < 4) {
        return -1;
    }
    if (out_len != NULL) {
        *out_len = o;
    }
    (void)padding;
    return 0;
}

int netd_base64_is_valid(const char *in, size_t in_len)
{
    if (in == NULL || in_len == 0) {
        return -1;
    }
    if (in_len % 4 != 0) {
        return -1;
    }
    int padding = 0;
    for (size_t i = 0; i < in_len; i++) {
        unsigned char c = (unsigned char)in[i];
        int v;
        if (decode_char(c, &v) != 0) {
            return -1;
        }
        if (v == -2) {
            padding++;
            if (padding > 2) {
                return -1;
            }
            if (i < in_len - 2) {
                return -1;
            }
        } else if (padding > 0) {
            return -1;
        }
    }
    return 0;
}