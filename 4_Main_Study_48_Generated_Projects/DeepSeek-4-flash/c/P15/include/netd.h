/*
 * netd.h -- common constants and types for the P15 network service daemon.
 *
 * This is a synthetic benchmark project. Do not copy external daemon code.
 */
#ifndef NETD_NETD_H
#define NETD_NETD_H

#include <stddef.h>
#include <stdint.h>

#define NETD_VERSION "1.0.0"
#define NETD_PROGRAM "netd"
#define NETD_CLIENT  "netctl"

/* Persistent schema version (see storage.c). */
#define NETD_SCHEMA_VERSION 1

/* Identifier and token limits. */
#define NETD_NAME_MAX 64        /* principal names, namespace, key, role text */
#define NETD_KEY_MAX  64
#define NETD_NS_MAX   64
#define NETD_PATH_MAX 4096

/* Protocol bounds. */
#define NETD_CMD_MAX_ARGS 8
#define NETD_LINE_MAX_DEFAULT 4096 /* default max_command_bytes */
#define NETD_LINE_ABS_MAX 1048576  /* hard cap for the buffer limit */

/* Role identifiers. The numeric values are stored nowhere; roles are
 * persisted as text ('client' | 'monitoring' | 'administrator'). */
typedef enum {
    NETD_ROLE_CLIENT = 0,
    NETD_ROLE_MONITORING = 1,
    NETD_ROLE_ADMIN = 2
} netd_role_t;

/* Deterministic response/error codes (stable for automated analysis). */
#define CODE_OK_CREATED    "CREATED"
#define CODE_OK_AUTH       "AUTH"
#define CODE_OK_VALUE      "VALUE"
#define CODE_OK_KEYS       "KEYS"
#define CODE_OK_UPDATED    "UPDATED"
#define CODE_OK_DELETED    "DELETED"
#define CODE_OK_QUIT       "QUIT"
#define CODE_OK_STATS      "STATS"
#define CODE_OK_HEALTH     "HEALTH"
#define CODE_PONG          "PONG"

#define CODE_ERR_UNKNOWN_CMD  "ERR UNKNOWN_CMD"
#define CODE_ERR_FIELD_COUNT  "ERR FIELD_COUNT"
#define CODE_ERR_LINE_TOO_LONG "ERR LINE_TOO_LONG"
#define CODE_ERR_NUL_REJECTED "ERR NUL_REJECTED"
#define CODE_ERR_BASE64       "ERR BASE64_INVALID"
#define CODE_ERR_TTL_RANGE    "ERR TTL_OUT_OF_RANGE"
#define CODE_ERR_NAME_INVALID "ERR NAME_INVALID"
#define CODE_ERR_NAMESPACE    "ERR NAMESPACE_DENIED"
#define CODE_ERR_KEY_EXISTS   "ERR KEY_EXISTS"
#define CODE_ERR_NOT_FOUND    "ERR NOT_FOUND"
#define CODE_ERR_CONFLICT     "ERR VERSION_CONFLICT"
#define CODE_ERR_LIMIT_RANGE  "ERR LIMIT_OUT_OF_RANGE"
#define CODE_ERR_AUTH_MISSING "ERR AUTH_TOKEN_MISSING"
#define CODE_ERR_AUTH_UNKNOWN "ERR AUTH_TOKEN_UNKNOWN"
#define CODE_ERR_AUTH_REVOKED "ERR AUTH_TOKEN_REVOKED"
#define CODE_ERR_AUTH_EXPIRED "ERR AUTH_TOKEN_EXPIRED"
#define CODE_ERR_AUTH_FAILED  "ERR AUTH_FAILED"
#define CODE_ERR_AUTH_REQUIRED "ERR AUTH_REQUIRED"
#define CODE_ERR_AUTH_TIMEOUT "ERR AUTH_TIMEOUT"
#define CODE_ERR_ROLE_DENIED  "ERR ROLE_DENIED"
#define CODE_ERR_BUSY         "ERR BUSY"
#define CODE_ERR_SHUTDOWN     "ERR SHUTDOWN"
#define CODE_ERR_DB           "ERR DB_ERROR"
#define CODE_ERR_INTERNAL     "ERR INTERNAL"

/* Stable structured-log event types. */
#define EV_STARTUP        "STARTUP"
#define EV_READY          "READY"
#define EV_SHUTDOWN       "SHUTDOWN"
#define EV_CONFIG_RELOAD  "CONFIG_RELOAD"
#define EV_CONFIG_REJECT  "CONFIG_REJECT"
#define EV_RESTART_NEEDED "RESTART_REQUIRED"
#define EV_AUTH_OK        "AUTH_OK"
#define EV_AUTH_FAIL      "AUTH_FAIL"
#define EV_AUTH_TIMEOUT   "AUTH_TIMEOUT"
#define EV_CONN_OPEN      "CONN_OPEN"
#define EV_CONN_CLOSE     "CONN_CLOSE"
#define EV_LIMIT_HIT      "LIMIT_HIT"
#define EV_PUT_OK         "PUT_OK"
#define EV_PUT_FAIL       "PUT_FAIL"
#define EV_GET_OK         "GET_OK"
#define EV_GET_FAIL       "GET_FAIL"
#define EV_LIST_OK        "LIST_OK"
#define EV_LIST_FAIL      "LIST_FAIL"
#define EV_UPDATE_OK      "UPDATE_OK"
#define EV_UPDATE_FAIL    "UPDATE_FAIL"
#define EV_DELETE_OK      "DELETE_OK"
#define EV_DELETE_FAIL    "DELETE_FAIL"
#define EV_EXPIRY_RUN     "EXPIRY_RUN"
#define EV_DB_ERROR       "DB_ERROR"
#define EV_LOG_ERROR      "LOG_ERROR"
#define EV_STALE_PID      "STALE_PID"
#define EV_SECOND_INST    "SECOND_INSTANCE"
#define EV_DB_PROBE       "DB_PROBE"
#define EV_STORAGE_RESET  "STORAGE_RESET"
#define EV_PID_FILE       "PID_FILE"
#define EV_STARTUP_ERROR  "STARTUP_ERROR"
#define EV_MAINTENANCE    "MAINTENANCE"

#endif /* NETD_NETD_H */
