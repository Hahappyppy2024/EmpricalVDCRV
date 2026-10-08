/*
 * proto.h -- strict Base64 codec used by the TCP protocol.
 */
#ifndef NETD_PROTO_H
#define NETD_PROTO_H

#include <stddef.h>
#include <stdint.h>

/* Encode inlen bytes to Base64 (RFC 4648, standard alphabet, padded).
 * Returns 0 on success, -1 when out is too small. */
int proto_b64_encode(const uint8_t *in, size_t inlen, char *out, size_t outlen);

/*
 * Strictly decode a Base64 string (length must be a multiple of 4, padding
 * must be correct, non-alphabet characters are rejected). Returns 0 on
 * success and sets *out_len; -1 and a message in err/errlen on failure.
 */
int proto_b64_decode(const char *in, size_t inlen, uint8_t *out,
                     size_t outcap, size_t *out_len, char *err, size_t errlen);

#endif /* NETD_PROTO_H */
