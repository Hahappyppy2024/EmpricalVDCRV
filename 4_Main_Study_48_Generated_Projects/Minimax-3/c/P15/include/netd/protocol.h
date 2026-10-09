#ifndef NETD_PROTOCOL_H
#define NETD_PROTOCOL_H

#include <stdint.h>
#include <stddef.h>
#include <stdbool.h>

#define NETD_MAX_FIELDS 8
#define NETD_MAX_FIELD_LEN 256
#define NETD_MAX_LINE_LEN 2048

typedef enum {
    NETD_CMD_UNKNOWN = 0,
    NETD_CMD_AUTH,
    NETD_CMD_QUIT,
    NETD_CMD_PUT,
    NETD_CMD_GET,
    NETD_CMD_LIST,
    NETD_CMD_UPDATE,
    NETD_CMD_DELETE,
    NETD_CMD_PING,
    NETD_CMD_HEALTH,
    NETD_CMD_STATS
} netd_command_kind_t;

typedef struct netd_command {
    netd_command_kind_t kind;
    char fields[NETD_MAX_FIELDS][NETD_MAX_FIELD_LEN];
    size_t field_lens[NETD_MAX_FIELDS];
    int field_count;
    char raw_line[NETD_MAX_LINE_LEN];
    size_t raw_len;
} netd_command_t;

typedef enum {
    NETD_PARSE_OK = 0,
    NETD_PARSE_INCOMPLETE,
    NETD_PARSE_TOO_LONG,
    NETD_PARSE_BAD_UTF8,
    NETD_PARSE_INVALID_LINE,
    NETD_PARSE_BAD_COUNT,
    NETD_PARSE_EMBEDDED_NUL,
    NETD_PARSE_OCTET_REJECTED
} netd_parse_status_t;

typedef struct netd_parser {
    char *buf;
    size_t buf_cap;
    size_t buf_len;
    bool overflow;
    bool bad_utf8;
    int64_t started_at;
} netd_parser_t;

int netd_parser_init(netd_parser_t *p, size_t initial_cap);
void netd_parser_destroy(netd_parser_t *p);
void netd_parser_reset(netd_parser_t *p);

netd_parse_status_t netd_parser_append(netd_parser_t *p,
                                       const char *data,
                                       size_t len,
                                       size_t max_bytes);

int netd_parser_extract_lines(netd_parser_t *p,
                              char **out_lines,
                              size_t *out_lens,
                              int max_lines,
                              int *out_count);

const char *netd_parse_status_string(netd_parse_status_t s);
netd_command_kind_t netd_command_lookup(const char *name);

int netd_command_parse(const char *line, size_t len, netd_command_t *out);

#endif