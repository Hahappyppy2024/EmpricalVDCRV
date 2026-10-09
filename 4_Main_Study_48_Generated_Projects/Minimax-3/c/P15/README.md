# netd — Lightweight Network Service / System Daemon

A compact native POSIX/Linux network service daemon (`netd`) and operator
client (`netctl`) implemented in ISO C17. The service accepts bounded
newline-delimited commands over IPv4 TCP, authenticates clients with
deterministic SHA-256 hashed opaque tokens, stores namespaced key/value
records in SQLite (WAL, foreign keys, schema versioning), and emits
structured audit and operational logs.

This project is a synthetic benchmark aligned with Redis and traditional
POSIX daemon operating patterns. It preserves the benchmark's independent
identity while reflecting comparable workflow anchors (foreground and
daemon mode, configuration loading, TCP sessions, authenticated state
operations, expiry processing, concurrent clients, live reload, metrics,
audit logs, and graceful shutdown).

---

## 1. Prerequisites

| Component       | Minimum version | Notes |
|-----------------|-----------------|-------|
| Linux kernel    | 3.10 (POSIX)    | x86-64 baseline |
| GCC or Clang    | GCC 13 / Clang 17 | strict warnings as errors |
| CMake           | 3.25            | out-of-source builds |
| SQLite3         | 3.35            | prepared statements, WAL, FK, busy-timeout |
| OpenSSL         | 3.0 (libcrypto) | SHA-256 |
| pthreads        | POSIX           | bounded worker pool |
| Docker          | 24+             | optional, for reproducible container builds |

Tested on Debian 12 (bookworm) with GCC 12.2 / CMake 3.25 / SQLite 3.40 /
OpenSSL 3.0.

---

## 2. Install dependencies

### Debian / Ubuntu

```bash
sudo apt-get update
sudo apt-get install -y build-essential cmake libssl-dev libsqlite3-dev \
    pkg-config
```

### RHEL / Fedora

```bash
sudo dnf install -y gcc cmake openssl-devel sqlite-devel pkgconfig
```

---

## 3. Configure and build

```bash
cmake -S . -B build -DCMAKE_BUILD_TYPE=Release
cmake --build build --parallel
```

Optional sanitiser presets:

```bash
cmake -S . -B build-asan -DCMAKE_BUILD_TYPE=Debug -DNETD_ENABLE_ASAN=ON
cmake --build build-asan --parallel

cmake -S . -B build-ubsan -DCMAKE_BUILD_TYPE=Debug -DNETD_ENABLE_UBSAN=ON
cmake --build build-ubsan --parallel
```

Outputs are written to `build/bin/netd` and `build/bin/netctl`.

---

## 4. Database reset and seed

The daemon performs deterministic seeding on first start. To force a
reset of the SQLite database file:

```bash
./build/bin/netd --config conf/netd.conf --reset --foreground
```

`--reset` removes the configured `database_path` (plus its `-wal` and
`-shm` files) before opening the listener. Re-running without `--reset`
is idempotent: the seeding function detects existing rows and skips.

---

## 5. Foreground startup

```bash
./build/bin/netd --config conf/netd.conf --foreground
```

Logs and PID file are written to the paths declared in `conf/netd.conf`
(default `./var/netd.log`, `./var/netd.audit.log`, `./var/netd.pid`).

Validate configuration without starting:

```bash
./build/bin/netd --config conf/netd.conf --print-config
```

---

## 6. Daemon startup

```bash
./build/bin/netd --config conf/netd.conf --daemon
```

`--daemon` performs a `fork`/`setsid`/`/dev/null` redirection sequence.
The PID file is rewritten with the daemonised PID; a stale file is
reclaimed when the previous process is no longer live.

Check the live process:

```bash
./build/bin/netctl --config conf/netd.conf status
# -> status=running pid=...
```

---

## 7. netctl — client and status tool

`netctl` reads the same `conf/netd.conf` to obtain the bind address and
port, or accepts explicit `--host` and `--port` overrides. The
`--token` argument is required for any command that needs an
authenticated session.

| Subcommand | Description |
|------------|-------------|
| `status`              | Read PID file and report running/not-running state |
| `ping`                | Send `PING` over a fresh connection |
| `auth <token>`        | Send `AUTH <token>` over a fresh connection |
| `put <ns> <key> <ttl> <b64>` | Create a record (`--token` required) |
| `get <ns> <key>`      | Retrieve one record (`--token` required) |
| `list <ns> <prefix> <limit> <after\|->` | List ordered keys (`--token` required) |
| `update <ns> <key> <ver> <ttl> <b64>` | Conditional update (`--token` required) |
| `delete <ns> <key> <ver>` | Conditional delete (`--token` required) |
| `health`              | `HEALTH` probe (`--token` for monitoring/admin) |
| `stats`               | `STATS` counters (`--token` for monitoring/admin) |
| `raw <line>`          | Send one raw command line (no framing) |

Examples (using seeded tokens from `conf/netd.conf`):

```bash
./build/bin/netctl --config conf/netd.conf status
./build/bin/netctl --config conf/netd.conf --token client-token-001 \
    put app greeting 0 SGVsbG8sIFdvcmxkIQ==
./build/bin/netctl --config conf/netd.conf --token client-token-001 \
    get app greeting
./build/bin/netctl --config conf/netd.conf --token client-token-001 \
    list app _ 10 -
./build/bin/netctl --config conf/netd.conf --token monitor-token-001 \
    health
./build/bin/netctl --config conf/netd.conf --token admin-token-001 stats
```

---

## 8. Graceful shutdown

```bash
kill -TERM $(cat var/netd.pid)
# or
kill -INT $(cat var/netd.pid)
```

The daemon closes the listener, completes in-flight commands within the
configured `shutdown_timeout_seconds`, joins worker threads, removes
the PID file, and exits zero. `SIGKILL` should only be used when the
daemon fails to honour `SIGTERM`.

Live reload of reloadable configuration keys:

```bash
kill -HUP $(cat var/netd.pid)
```

Log reopening (rotate-friendly close/reopen of audit and structured logs):

```bash
kill -USR1 $(cat var/netd.pid)
```

---

## 9. Docker

```bash
docker build -t netd:1.0.0 .
docker run --rm -it \
    -p 9090:9090 \
    -v netd-data:/var/lib/netd \
    -v netd-log:/var/log/netd \
    -v netd-run:/var/run/netd \
    --env-file .env.example \
    netd:1.0.0
```

Or with the bundled compose file:

```bash
docker compose up --build
```

The image runs `netd --foreground` as the entry point and writes
persistent state to the named volumes.

---

## 10. Configuration keys (`conf/netd.conf`)

Strict `key=value` parser. Unknown keys, duplicate keys, malformed
values, and out-of-range values are startup errors. Trailing `#`-style
comments and blank lines are ignored.

| Key | Range / Format | Reloadable | Description |
|-----|----------------|------------|-------------|
| `bind_address`                | IPv4 literal           | restart required | TCP listener bind |
| `port`                        | 1..65535                | restart required | TCP listener port |
| `database_path`               | file path              | restart required | SQLite database file |
| `log_path`                    | file path              | yes (SIGHUP)     | Structured log file |
| `audit_log_path`              | file path              | yes (SIGHUP)     | Audit log file |
| `pid_file`                    | file path              | restart required | PID file path |
| `worker_count`                | 1..256                 | yes (SIGHUP)     | Bounded pthread worker pool size |
| `idle_timeout_seconds`        | 1..86400               | yes (SIGHUP)     | Idle timeout for unauthenticated clients |
| `max_clients`                 | 1..8192                | yes (SIGHUP)     | Concurrent connection ceiling |
| `max_command_bytes`           | 64..1048576            | yes (SIGHUP)     | Per-command line byte limit |
| `expiry_scan_interval_seconds`| 1..3600                | yes (SIGHUP)     | Expiry worker scan interval |
| `expiry_batch_size`           | 1..100000              | yes (SIGHUP)     | Expiry worker batch size |
| `shutdown_timeout_seconds`    | 1..600                 | yes (SIGHUP)     | Graceful shutdown deadline |
| `strict_audit`                | true / false           | yes (SIGHUP)     | Refuse startup if audit log is unwritable |
| `database_busy_timeout_ms`    | 0..60000               | yes (SIGHUP)     | SQLite busy timeout |

---

## 11. Protocol reference

Each command is one line terminated by `\n` (or `\r\n`). Field separator
is whitespace. Values are case-preserved. Successful responses begin with
`OK `, errors with `ERR ` followed by a stable code.

### Authentication

| Command | Fields | Auth required | Response |
|---------|--------|---------------|----------|
| `AUTH <token>` | token | no (transitions state) | `OK AUTH <role> principal=<name>` / `ERR AUTH_*` |
| `QUIT` | — | yes | `OK BYE` |

### Records (require authenticated client or administrator role)

| Command | Fields | Response |
|---------|--------|----------|
| `PUT <namespace> <key> <ttl_seconds> <base64_value>` | ns, key, ttl, value | `OK CREATED <version> <expires_at\|NONE>` / `ERR *_EXISTS`, `ERR INVALID_BASE64`, `ERR INVALID_TTL`, `ERR ACCESS_DENIED`, `ERR INVALID_NAMESPACE`, `ERR INVALID_KEY` |
| `GET <namespace> <key>` | ns, key | `OK VALUE <version> <base64>` / `OK NOTFOUND` / `ERR ACCESS_DENIED` |
| `LIST <namespace> <prefix> <limit> <after\|->` | ns, prefix, limit, after | `OK KEYS <count> [<key> ...] NEXT <key\|->` |
| `UPDATE <namespace> <key> <expected_version> <ttl_seconds> <base64_value>` | ns, key, ver, ttl, value | `OK UPDATED <version> <expires_at\|NONE>` / `ERR CONFLICT`, `ERR NOT_FOUND`, `ERR ACCESS_DENIED` |
| `DELETE <namespace> <key> <expected_version>` | ns, key, ver | `OK DELETED` / `ERR CONFLICT`, `ERR NOT_FOUND`, `ERR ACCESS_DENIED` |

### Monitoring (require monitoring or administrator role)

| Command | Fields | Response |
|---------|--------|----------|
| `PING`    | — | `OK PONG` |
| `HEALTH`  | — | `OK HEALTH <state> reason=<text>` where `<state>` is `OK`, `DEGRADED`, or `STOPPING` |
| `STATS`   | — | `OK STATS bind=<addr> port=<n> workers=<n> max_clients=<n> max_command_bytes=<n> config_generation=<n> uptime_seconds=<n> connections_accepted=<n> connections_closed=<n> commands_total=<n> auth_success=<n> auth_failed=<n> records_put=<n> records_get=<n> records_updated=<n> records_deleted=<n> records_expired=<n> list_operations=<n> bytes_in=<n> bytes_out=<n> log_write_errors=<n> db_busy_timeouts=<n>` |

### Stable error codes

`AUTH_REQUIRED`, `AUTH_INVALID`, `AUTH_EXPIRED`, `AUTH_REVOKED`,
`AUTH_TIMEOUT`, `AUTH_FORBIDDEN`, `ACCESS_DENIED`, `NOT_FOUND`,
`ALREADY_EXISTS`, `CONFLICT`, `BAD_REQUEST`, `TOO_LARGE`,
`INVALID_TTL`, `INVALID_BASE64`, `INVALID_NAMESPACE`, `INVALID_KEY`,
`LIMIT_EXCEEDED`, `BUSY`, `SHUTTING_DOWN`, `PROTOCOL_INVALID_LINE`,
`PROTOCOL_TOO_LONG`, `PROTOCOL_BAD_UTF8`, `PROTOCOL_BAD_COUNT`,
`INTERNAL`.

---

## 12. Traceability table

| Use case | Title | Main files | Key functions | Protocol commands | Configuration keys |
|----------|-------|------------|---------------|-------------------|--------------------|
| NETD-01 | Configuration and startup | `src/config.c`, `src/main.c`, `src/server.c` | `netd_config_load`, `netd_server_create`, `netd_server_start`, `netd_pid_acquire` | (startup) | all |
| NETD-02 | Service lifecycle control | `src/main.c`, `src/server.c`, `src/pidfile.c`, `tools/netctl.c` | `netd_server_stop`, `netd_server_run`, `netd_pid_acquire`, `do_status` | (lifecycle) | `pid_file`, `shutdown_timeout_seconds` |
| NETD-03 | Client authentication and session | `src/auth.c`, `src/storage.c`, `src/server.c` | `handle_auth`, `netd_storage_find_token`, `netd_auth_hash_token` | `AUTH <token>`, `QUIT` | `database_busy_timeout_ms` |
| NETD-04 | Record creation | `src/server.c`, `src/storage.c` | `handle_put`, `netd_storage_record_put` | `PUT <ns> <key> <ttl> <b64>` | `max_command_bytes` |
| NETD-05 | Record retrieval and listing | `src/server.c`, `src/storage.c` | `handle_get`, `handle_list`, `netd_storage_record_get`, `netd_storage_record_list` | `GET`, `LIST` | `max_command_bytes` |
| NETD-06 | Conditional update and deletion | `src/server.c`, `src/storage.c` | `handle_update`, `handle_delete`, `netd_storage_record_update`, `netd_storage_record_delete` | `UPDATE`, `DELETE` | `max_command_bytes` |
| NETD-07 | Record expiry processing | `src/expiry.c`, `src/storage.c` | `netd_expiry_worker_run_once`, `netd_storage_expiry_run` | (background) | `expiry_scan_interval_seconds`, `expiry_batch_size` |
| NETD-08 | Concurrent client handling | `src/server.c`, `src/connection.c`, `src/worker.c` | `do_accept`, `handle_client_input`, `netd_worker_pool_submit`, `close_connection` | (concurrency) | `max_clients`, `worker_count`, `idle_timeout_seconds`, `max_command_bytes` |
| NETD-09 | Live configuration reload | `src/signals.c`, `src/server.c`, `src/config.c` | `netd_server_reload`, `netd_signals_install`, `netd_config_is_reloadable` | (SIGHUP) | reloadable subset (`worker_count`, `idle_timeout_seconds`, `max_clients`, `max_command_bytes`, `expiry_scan_interval_seconds`, `expiry_batch_size`, `shutdown_timeout_seconds`, `strict_audit`, `audit_log_path`, `log_path`, `database_busy_timeout_ms`) |
| NETD-10 | Health and operational metrics | `src/server.c`, `src/metrics.c`, `src/storage.c` | `handle_ping`, `handle_health`, `handle_stats`, `netd_metrics_format_stats`, `netd_storage_health_probe` | `PING`, `HEALTH`, `STATS` | (read-only) |
| NETD-11 | Structured audit logging | `src/audit.c`, `src/server.c` | `netd_audit_emit`, `netd_server_audit_event`, `netd_audit_reopen` | (background) | `audit_log_path`, `strict_audit` |
| NETD-12 | Graceful shutdown and recovery | `src/server.c`, `src/signals.c`, `src/expiry.c`, `src/worker.c`, `src/storage.c` | `netd_server_stop`, `netd_signals_install`, `netd_storage_health_probe`, `netd_storage_init_schema`, `netd_expiry_worker_stop`, `netd_worker_pool_stop` | `SIGTERM` / `SIGINT` | `shutdown_timeout_seconds` |

---

## 13. Persistent schema

See `sql/schema.sql`. The schema is created at startup using prepared
statements. Schema versioning is recorded in `schema_version`. WAL mode
and `foreign_keys = ON` are enabled for every connection.

Seed data inserted on first start (only if `principals` is empty):

- Three principals: `client-alpha` (client), `monitoring-01`
  (monitoring), `admin-root` (administrator).
- Three access tokens (SHA-256 hashes of `client-token-001`,
  `monitor-token-001`, `admin-token-001`). Plaintext tokens are never
  written to SQLite or logs.
- Namespace grants: `client-alpha` reads `public` and writes `app`;
  `monitoring-01` has admin on `metrics`; `admin-root` has admin on
  `*`.
- Five seed records in `app`, `public`, and `metrics`.

---

## 14. Build directory layout

```
P15_Lightweight_Network_Service_System_Daemon/
├── CMakeLists.txt
├── Dockerfile
├── docker-compose.yml
├── .env.example
├── README.md
├── conf/
│   └── netd.conf
├── sql/
│   └── schema.sql
├── include/
│   └── netd/
│       ├── audit.h
│       ├── auth.h
│       ├── base64.h
│       ├── common.h
│       ├── config.h
│       ├── connection.h
│       ├── expiry.h
│       ├── log.h
│       ├── metrics.h
│       ├── pidfile.h
│       ├── protocol.h
│       ├── server.h
│       ├── signals.h
│       ├── signals_platform.h
│       ├── storage.h
│       ├── util.h
│       └── worker.h
├── src/
│   ├── audit.c
│   ├── auth.c
│   ├── base64.c
│   ├── config.c
│   ├── connection.c
│   ├── expiry.c
│   ├── log.c
│   ├── main.c
│   ├── metrics.c
│   ├── pidfile.c
│   ├── protocol.c
│   ├── seed.c
│   ├── server.c
│   ├── signals.c
│   ├── storage.c
│   ├── util.c
│   └── worker.c
├── tools/
│   └── netctl.c
├── scripts/
│   ├── full-smoke.sh
│   ├── step-smoke.sh
│   └── ...
└── tests/
    ├── CMakeLists.txt
    └── ...
```

---

## 15. Smoke check (Debian 12)

A reproducible smoke check is provided in `scripts/step-smoke.sh`. The
script builds the project, starts the daemon with `--reset --foreground`,
exercises every use case via `netctl`, sends `SIGHUP` and `SIGTERM`, and
prints the structured log and audit log.

```text
=== status ===
status=running pid=...
=== auth+put ===
OK AUTH client principal=client-alpha
OK CREATED 1 ...
=== get ===
OK AUTH client principal=client-alpha
OK VALUE 1 aGVsbG8=
=== health ===
OK HEALTH OK reason=ready
=== stats ===
OK STATS bind=127.0.0.1 port=9090 workers=4 ...
=== concurrent ===
OK AUTH client principal=client-alpha
OK CREATED 1 ...
=== sighup ===
reload complete generation=2
=== stop ===
sent
```

---

## 16. Limitations and determinism notes

- `raw` on the wire protocol is an internal newline-delimited text
  protocol; it is not framed by length prefixes. Embedded NUL bytes,
  overlong lines, invalid UTF-8, invalid Base64, and invalid field
  counts are rejected with stable error codes.
- Database seeding runs once per database file: subsequent restarts
  preserve all committed records.
- Plaintext tokens are never stored. Only their SHA-256 hash and a
  short non-secret prefix are persisted in `access_tokens`.
- Logger output is line-oriented and redacted: plaintext tokens, Base64
  values, and decoded values are never logged.
