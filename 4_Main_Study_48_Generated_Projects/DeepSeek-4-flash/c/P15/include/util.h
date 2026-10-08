/*
 * util.h -- small POSIX helpers shared by the daemon and client tools.
 */
#ifndef NETD_UTIL_H
#define NETD_UTIL_H

#include <sys/types.h>
#include <stddef.h>
#include <stdint.h>

/* Wall-clock epoch seconds (expiry comparison uses this clock). */
int64_t util_now_epoch(void);

/* Monotonic milliseconds (uptime and durations). */
int64_t util_mono_ms(void);

/* Create all directory components of `path` (path treated as a directory). */
int util_mkdirs_dir(const char *path);

/* Create the parent directories of a file path. Returns 0 on success. */
int util_mkdirs_file(const char *path);

/* Hex encode/decode helpers. Returns 0 on success. */
int util_hex_encode(const uint8_t *in, size_t len, char *out, size_t outlen);
int util_hex_decode(const char *in, size_t len, uint8_t *out, size_t outcap);

/* Read the process comm name from /proc for stale-PID confirmation.
 * Returns 0 on success. */
int util_proc_name(long pid, char *out, size_t outlen);

/* Is a process with this PID alive (or permission-denied)? */
int util_pid_alive(long pid);

/*
 * Connect to host:port over IPv4 TCP with a bounded connect timeout.
 * Returns a connected blocking socket or -1. */
int util_tcp_connect(const char *host, int port, int timeout_ms);

/* Write all bytes with a bounded deadline; returns bytes written or -1. */
ssize_t util_write_all(int fd, const void *buf, size_t len, int timeout_ms);

/* Read one newline-terminated line (bounded) from a socket; returns the
 * length on success, 0 on EOF, -1 on timeout/error. */
int util_read_line(int fd, char *buf, size_t cap, int timeout_ms);

#endif /* NETD_UTIL_H */
