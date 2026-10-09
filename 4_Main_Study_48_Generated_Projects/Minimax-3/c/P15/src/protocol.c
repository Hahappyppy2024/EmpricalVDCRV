#include "netd/protocol.h"
#include "netd/util.h"

#include <ctype.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

int netd_parser_init(netd_parser_t *p, size_t initial_cap)
{
    if (p == NULL || initial_cap < 16) {
        return -1;
    }
    memset(p, 0, sizeof(*p));
    p->buf = malloc(initial_cap);
    if (p->buf == NULL) {
        return -1;
    }
    p->buf[0] = '\0';
    p->buf_cap = initial_cap;
    p->buf_len = 0;
    p->overflow = false;
    p->bad_utf8 = false;
    p->started_at = netd_now_seconds();
    return 0;
}

void netd_parser_destroy(netd_parser_t *p)
{
    if (p == NULL) {
        return;
    }
    free(p->buf);
    p->buf = NULL;
    p->buf_cap = 0;
    p->buf_len = 0;
}

void netd_parser_reset(netd_parser_t *p)
{
    if (p == NULL) {
        return;
    }
    p->buf_len = 0;
    p->overflow = false;
    p->bad_utf8 = false;
    if (p->buf_cap > 0) {
        p->buf[0] = '\0';
    }
}

static int ensure_capacity(netd_parser_t *p, size_t needed)
{
    if (needed <= p->buf_cap) {
        return 0;
    }
    size_t new_cap = p->buf_cap;
    while (new_cap < needed) {
        new_cap *= 2;
    }
    char *nb = realloc(p->buf, new_cap);
    if (nb == NULL) {
        return -1;
    }
    p->buf = nb;
    p->buf_cap = new_cap;
    return 0;
}

netd_parse_status_t netd_parser_append(netd_parser_t *p,
                                       const char *data,
                                       size_t len,
                                       size_t max_bytes)
{
    if (p == NULL || data == NULL) {
        return NETD_PARSE_OCTET_REJECTED;
    }
    if (p->overflow || p->bad_utf8) {
        return NETD_PARSE_BAD_UTF8;
    }
    if (p->buf_len + len + 1 > max_bytes) {
        p->overflow = true;
        return NETD_PARSE_TOO_LONG;
    }
    if (ensure_capacity(p, p->buf_len + len + 1) != 0) {
        return NETD_PARSE_OCTET_REJECTED;
    }
    for (size_t i = 0; i < len; i++) {
        if (data[i] == '\0') {
            p->bad_utf8 = true;
            return NETD_PARSE_EMBEDDED_NUL;
        }
    }
    memcpy(p->buf + p->buf_len, data, len);
    p->buf_len += len;
    p->buf[p->buf_len] = '\0';
    return NETD_PARSE_INCOMPLETE;
}

int netd_parser_extract_lines(netd_parser_t *p,
                              char **out_lines,
                              size_t *out_lens,
                              int max_lines,
                              int *out_count)
{
    if (out_count != NULL) {
        *out_count = 0;
    }
    if (p == NULL || p->buf == NULL || p->buf_len == 0) {
        return 0;
    }
    int count = 0;
    size_t start = 0;
    for (size_t i = 0; i < p->buf_len; i++) {
        if (p->buf[i] == '\n') {
            size_t end = i;
            size_t len = end - start;
            if (len > 0 && p->buf[end - 1] == '\r') {
                len--;
            }
            if (count < max_lines) {
                if (out_lines != NULL) {
                    p->buf[end] = '\0';
                    out_lines[count] = p->buf + start;
                }
                if (out_lens != NULL) {
                    out_lens[count] = len;
                }
                count++;
            } else {
                break;
            }
            start = i + 1;
        }
    }
    if (start < p->buf_len) {
        size_t remaining = p->buf_len - start;
        memmove(p->buf, p->buf + start, remaining);
        p->buf_len = remaining;
        p->buf[p->buf_len] = '\0';
    } else {
        p->buf_len = 0;
    }
    if (out_count != NULL) {
        *out_count = count;
    }
    return count;
}

const char *netd_parse_status_string(netd_parse_status_t s)
{
    switch (s) {
    case NETD_PARSE_OK: return "OK";
    case NETD_PARSE_INCOMPLETE: return "INCOMPLETE";
    case NETD_PARSE_TOO_LONG: return "TOO_LONG";
    case NETD_PARSE_BAD_UTF8: return "BAD_UTF8";
    case NETD_PARSE_INVALID_LINE: return "INVALID_LINE";
    case NETD_PARSE_BAD_COUNT: return "BAD_COUNT";
    case NETD_PARSE_EMBEDDED_NUL: return "EMBEDDED_NUL";
    case NETD_PARSE_OCTET_REJECTED: return "OCTET_REJECTED";
    }
    return "UNKNOWN";
}

typedef struct {
    const char *name;
    netd_command_kind_t kind;
    int field_count;
} cmd_info_t;

static const cmd_info_t CMD_TABLE[] = {
    {"AUTH",   NETD_CMD_AUTH,   1},
    {"QUIT",   NETD_CMD_QUIT,   0},
    {"PUT",    NETD_CMD_PUT,    4},
    {"GET",    NETD_CMD_GET,    2},
    {"LIST",   NETD_CMD_LIST,   4},
    {"UPDATE", NETD_CMD_UPDATE, 5},
    {"DELETE", NETD_CMD_DELETE, 3},
    {"PING",   NETD_CMD_PING,   0},
    {"HEALTH", NETD_CMD_HEALTH, 0},
    {"STATS",  NETD_CMD_STATS,  0}
};

netd_command_kind_t netd_command_lookup(const char *name)
{
    if (name == NULL) {
        return NETD_CMD_UNKNOWN;
    }
    for (size_t i = 0; i < sizeof(CMD_TABLE) / sizeof(CMD_TABLE[0]); i++) {
        if (strcmp(CMD_TABLE[i].name, name) == 0) {
            return CMD_TABLE[i].kind;
        }
    }
    return NETD_CMD_UNKNOWN;
}

static int expected_count(netd_command_kind_t k)
{
    for (size_t i = 0; i < sizeof(CMD_TABLE) / sizeof(CMD_TABLE[0]); i++) {
        if (CMD_TABLE[i].kind == k) {
            return CMD_TABLE[i].field_count;
        }
    }
    return -1;
}

int netd_command_parse(const char *line, size_t len, netd_command_t *out)
{
    if (out == NULL || line == NULL) {
        return -1;
    }
    memset(out, 0, sizeof(*out));

    if (len > NETD_MAX_LINE_LEN) {
        return NETD_PARSE_TOO_LONG;
    }
    memcpy(out->raw_line, line, len);
    out->raw_line[len] = '\0';
    out->raw_len = len;

    if (!netd_str_is_valid_utf8(line, len)) {
        return NETD_PARSE_BAD_UTF8;
    }

    char tmp[NETD_MAX_LINE_LEN + 1];
    memset(tmp, 0, sizeof(tmp));
    memcpy(tmp, line, len);
    tmp[len] = '\0';

    char *p = tmp;
    while (*p == ' ' || *p == '\t') {
        p++;
    }
    char *cmd_start = p;
    while (*p && *p != ' ' && *p != '\t') {
        p++;
    }
    if (p == cmd_start) {
        return NETD_PARSE_INVALID_LINE;
    }
    *p = '\0';
    char *cmd_name = cmd_start;
    for (char *q = cmd_name; *q; q++) {
        *q = (char)toupper((unsigned char)*q);
    }
    out->kind = netd_command_lookup(cmd_name);
    if (out->kind == NETD_CMD_UNKNOWN) {
        return NETD_PARSE_INVALID_LINE;
    }
    int expected = expected_count(out->kind);
    if (expected < 0) {
        return NETD_PARSE_INVALID_LINE;
    }

    char *cursor = p + 1;
    int idx = 0;
    while (*cursor && idx < NETD_MAX_FIELDS) {
        while (*cursor == ' ' || *cursor == '\t') {
            cursor++;
        }
        if (*cursor == '\0') {
            break;
        }
        char *field_start = cursor;
        while (*cursor && *cursor != ' ' && *cursor != '\t') {
            cursor++;
        }
        size_t flen = (size_t)(cursor - field_start);
        if (*cursor != '\0') {
            *cursor = '\0';
            cursor++;
        }
        if (flen == 0 || flen >= NETD_MAX_FIELD_LEN) {
            return NETD_PARSE_INVALID_LINE;
        }
        memcpy(out->fields[idx], field_start, flen);
        out->fields[idx][flen] = '\0';
        out->field_lens[idx] = flen;
        idx++;
    }
    out->field_count = idx;
    if (idx != expected) {
        return NETD_PARSE_BAD_COUNT;
    }
    for (int i = 0; i < idx; i++) {
        if (out->field_lens[i] == 0) {
            return NETD_PARSE_INVALID_LINE;
        }
        if (memchr(out->fields[i], '\0', out->field_lens[i]) != NULL) {
            return NETD_PARSE_EMBEDDED_NUL;
        }
    }
    return NETD_PARSE_OK;
}