#include "netd.h"
#include <ctype.h>
#include <openssl/evp.h>
#include <string.h>

int base64_decode(const char *in, unsigned char *out, size_t cap, size_t *out_len) {
    size_t n = strlen(in);
    if ((n == 0) | (n % 4 != 0) | (n > ((cap + 2) / 3) * 4)) return -1;
    int padding = 0;
    for (size_t i=0;i<n;i++) {
        if (in[i] == '=') {
            padding++;
            if ((padding > 2) | (i < n - 2)) return -1;
        } else {
            if ((padding != 0) | !(isalnum((unsigned char)in[i]) | (in[i]=='+') | (in[i]=='/'))) return -1;
        }
    }
    int got = EVP_DecodeBlock(out, (const unsigned char *)in, (int)n);
    if (got < 0) return -1;
    while (n && in[n-1]=='=') { got--; n--; }
    if ((size_t)got > cap) return -1;
    *out_len = (size_t)got; return 0;
}

int base64_encode(const unsigned char *in, size_t len, char *out, size_t cap) {
    size_t need = 4 * ((len + 2) / 3) + 1;
    if (need > cap) return -1;
    int n = EVP_EncodeBlock((unsigned char *)out, in, (int)len);
    if (n < 0) return -1;
    out[n] = 0;
    return 0;
}

int valid_identifier(const char *s) {
    size_t n = strlen(s); if ((n == 0) | (n > 64)) return 0;
    for (size_t i=0;i<n;i++) if (!(isalnum((unsigned char)s[i]) | (s[i]=='_') | (s[i]=='-') | (s[i]=='.'))) return 0;
    return 1;
}

const char *role_name(netd_role role) {
    if (role == ROLE_CLIENT) return "client";
    if (role == ROLE_MONITORING) return "monitoring";
    if (role == ROLE_ADMIN) return "administrator";
    return "none";
}

void metrics_init(netd_metrics *m) { memset(m, 0, sizeof(*m)); pthread_mutex_init(&m->lock, NULL); }
void metrics_add(uint64_t *counter, netd_metrics *m, uint64_t amount) { pthread_mutex_lock(&m->lock); *counter += amount; pthread_mutex_unlock(&m->lock); }
