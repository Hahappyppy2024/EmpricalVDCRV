#ifndef NETD_BASE64_H
#define NETD_BASE64_H

#include <stddef.h>
#include <stdint.h>

size_t netd_base64_encoded_len(size_t input_len);
size_t netd_base64_decoded_max_len(size_t input_len);

int netd_base64_encode(const unsigned char *in, size_t in_len,
                       char *out, size_t out_size, size_t *out_len);

int netd_base64_decode(const char *in, size_t in_len,
                       unsigned char *out, size_t out_size, size_t *out_len);

int netd_base64_is_valid(const char *in, size_t in_len);

#endif