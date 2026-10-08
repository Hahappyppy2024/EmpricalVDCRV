# P15 — Lightweight Network Service / System Daemon

A compact C17 POSIX/Linux network daemon (`netd`) plus operator/client
executable (`netctl`) with TCP sessions, authenticated state operations,
TTL expiry maintenance, a bounded worker pool, live configuration reload,
operational metrics, structured audit logging, and graceful shutdown.

This is a **synthetic benchmark project**. It is not a clone of Redis or any
other daemon; it mirrors only high-level operating patterns (foreground and
daemon modes, configuration loading, TCP client sessions, bounded command
parsing, authenticated state operations, expiry processing, concurrent
clients, live reload, operational metrics, audit logs, graceful shutdown).

---

## 1. Technology profile (authoritative)

| Aspect | Value |
| --- | --- |
| Language | ISO C17 (`-std=c17`) |
| Compiler | GCC 13+ or Clang 17+; project code compiles with `-Wall -Wextra -Wpedantic -Werror` |
| Build | CMake 3.25+, out-of-source builds, CTest enabled |
| Architecture | one long-running daemon `netd` + operator/client `netctl` |
| Transport | IPv4 TCP; default `127.0.0.1:9090`; `poll`-based readiness |
| Protocol | bounded newline-delimited ASCII commands; `OK <code> ...` / `ERR <code> <message>`; embedded NUL, overlong lines, invalid field counts, and invalid Base64 are rejected |
| Concurrency | bounded pthread worker pool; per-connection parser + auth state; no detached/untracked threads |
| Persistence | SQLite 3 (native C API): prepared statements, transactions, schema versioning, WAL, foreign keys, bounded busy timeout |
| Auth | SHA-256 digests only (OpenSSL libcrypto) stored in SQLite; constant-time comparison (`CRYPTO_memcmp`); tokens have client / monitoring / administrator roles and may be revoked or expired |
| Config | strict `key=value` file; unknown/duplicate keys and out-of-range values are startup or reload errors |
| Logging | bounded line-oriented structured logs with stable event/outcome codes; credentials and record values are never logged |
| Dependencies | pthreads, SQLite3, OpenSSL libcrypto (system packages) |

---

## 2. Prerequisites

- Linux (or WSL2 / macOS with the same toolchain); x86-64 baseline
- CMake >= 3.25
- GCC >= 13 or Clang >= 17
- SQLite3 >= 3.35 (dev headers for building)
- OpenSSL >= 3.0 (libcrypto; dev headers for building)
- pthreads (glibc)
- Optional: Docker Engine / Docker Desktop with a Linux backend (>= 24)

Debian/Ubuntu install:

```sh
sudo apt-get update
sudo apt-get install -y build-essential cmake libsqlite3-dev libssl-dev
```

---

## 3. Configure and build

```sh
cmake -S . -B build -DCMAKE_BUILD_TYPE=Release   # or: cmake --preset release
cmake --build build --parallel
cmake --install build --prefix ./out             # optional: installs bin/
```

Binaries are produced at `build/netd`, `build/netctl`, and `build/token_hash`.

Optional sanitizer presets:

```sh
cmake --preset asan        # AddressSanitizer
cmake --preset ubsan       # UndefinedBehaviorSanitizer
cmake --preset asan+ubsan  # both
cmake --build --preset asan
```

---

## 4. Configuration

`netd` reads a strict `key=value` file (default example: `config/netd.conf`).
Unknown keys, duplicate keys, malformed values, and out-of-range values are
startup or reload errors. `#` starts a comment.

| Key | Default | Range | Reloadable |
| --- | --- | --- | --- |
| `bind_address` | `127.0.0.1` | valid IPv4 | no (restart) |
| `port` | `9090` | 1..65535 | no (restart) |
| `database_path` | `data/netd.db` | non-empty | no (restart) |
| `log_path` | `logs/netd.log` | non-empty | no (restart) |
| `pid_file` | `run/netd.pid` | non-empty | no (restart) |
| `worker_count` | `4` | 1..64 | no (restart) |
| `max_clients` | `64` | 1..65535 | yes |
| `max_command_bytes` | `4096` | 64..1048576 | yes |
| `idle_timeout_seconds` | `60` | 0..86400 (0 = disabled) | yes |
| `auth_timeout_seconds` | `10` | 1..86400 | yes |
| `shutdown_timeout_seconds` | `5` | 1..3600 | yes |
| `expiry_scan_interval_seconds` | `10` | 1..86400 | yes |
| `expiry_batch_size` | `100` | 1..10000 | yes |
| `database_busy_timeout_ms` | `5000` | 0..120000 | yes |
| `list_max_limit` | `100` | 1..10000 | yes |
| `strict_audit` | `0` | 0 or 1 | yes |
| `log_flush` | `auto` | `auto` or `always` | yes |

Validate without starting:

```sh
./build/netd --config config/netd.conf --check-config
```

---

## 5. Database reset / seed

Deterministic seed fixtures (created on a fresh database and ensured on every
start with `INSERT OR IGNORE`; user data is never overwritten):

| Token (plaintext, used at the wire) | Role | Notes |
| --- | --- | --- |
| `client-token-01` | client | grants: `app` (read/write), `shared` (read/write) |
| `monitor-token-01` | monitoring | `PING`/`HEALTH`/`STATS` only |
| `admin-token-01` | administrator | full access incl. namespace `*` |
| `revoked-token-01` | client | status `revoked` (always rejected) |
| `expired-token-01` | client | status `expired`, expired at seed time |

Seeded records: `app:greeting` = `hello world` (no TTL), `app:counter` = `42`
(no TTL), `app:temp` = `ephemeral` (TTL 5 s), `shared:note` = `shared note`
(no TTL).

```sh
./build/netd --config config/netd.conf --reset-db
```

Reset wipes and re-seeds the database. A plain fresh start also seeds.

---

## 6. Run

Foreground startup:

```sh
./build/netd --config config/netd.conf --foreground
```

Daemon startup (parent exits 0 only after the child is fully initialized —
storage opened, listener bound, PID file locked):

```sh
./build/netd --config config/netd.conf --daemon
```

Status / lifecycle via `netctl`:

```sh
./build/netctl status --config config/netd.conf
./build/netctl shutdown --config config/netd.conf   # SIGTERM
./build/netctl reload   --config config/netd.conf   # SIGHUP  (live reload)
./build/netctl reopen   --config config/netd.conf   # SIGUSR1 (log reopen)
```

Signals: `SIGTERM`/`SIGINT` graceful stop; `SIGHUP` live configuration
reload; `SIGUSR1` log reopen. A second instance on the same live PID file or
port is rejected.

### Client use (requires `--token`)

```sh
./build/netctl ping   --config config/netd.conf --token monitor-token-01
./build/netctl health --config config/netd.conf --token monitor-token-01
./build/netctl stats  --config config/netd.conf --token monitor-token-01

./build/netctl put    --config config/netd.conf --token client-token-01 \
    --namespace app --key k1 --value "hello"
./build/netctl get    --config config/netd.conf --token client-token-01 \
    --namespace app --key k1
./build/netctl list   --config config/netd.conf --token client-token-01 \
    --namespace app --prefix k --limit 10
./build/netctl update --config config/netd.conf --token client-token-01 \
    --namespace app --key k1 --expected-version 1 --value "hello v2" --ttl 60
./build/netctl delete --config config/netd.conf --token client-token-01 \
    --namespace app --key k1 --expected-version 2
```

Raw TCP example (one command per line):

```sh
printf 'AUTH client-token-01\nPING\nQUIT\n' | nc 127.0.0.1 9090
```

`netctl token-hash <token>` (or the `token_hash` tool) prints the SHA-256 hex
digest of a token.

---

## 7. Docker

```sh
docker build -t p15-netd:latest .          # multi-stage build
docker compose up -d --build               # or: docker compose up -d
docker compose ps
docker compose logs -f netd
docker compose down                        # stops and removes the container
docker compose down -v                     # also removes the volumes
```

The container runs `netd --config /etc/netd/netd.conf --foreground` with
`/data`, `/logs`, and `/run` mounted as writable volumes and port
`${NETD_PORT:-9090}` published. Copy `.env.example` to `.env` to change the
published port. To use the client inside the container:

```sh
docker compose exec netd netctl status --config /etc/netd/netd.conf
docker compose exec netd netctl ping --config /etc/netd/netd.conf \
    --token monitor-token-01
```

---

## 8. Application protocol

Responses are one newline-terminated line. `OK`/`PONG` are success, `ERR`
carries a stable code and message. Embedded NUL, overlong lines
(> `max_command_bytes`), wrong field counts, and invalid Base64 are rejected
with a deterministic `ERR`.

| Command | Syntax | Success response |
| --- | --- | --- |
| AUTH | `AUTH <token>` | `OK AUTH <client\|monitoring\|administrator>` |
| QUIT | `QUIT` | `OK QUIT` (closes the session) |
| PING | `PING` | `PONG` |
| HEALTH | `HEALTH` | `OK HEALTH <healthy\|degraded> reason=<reason\|->` |
| STATS | `STATS` | `OK STATS <key=value> ...` |
| PUT | `PUT <ns> <key> <ttl_seconds> <base64>` | `OK CREATED <version> <expires_at\|NONE>` |
| GET | `GET <ns> <key>` | `OK VALUE <version> <expires_at\|NONE> <base64>` |
| LIST | `LIST <ns> <prefix\|-> <limit> <after\|->` | `OK KEYS <count> <more> <after\|-> [key ...]` |
| UPDATE | `UPDATE <ns> <key> <expected_version> <ttl_seconds> <base64>` | `OK UPDATED <version> <expires_at\|NONE>` |
| DELETE | `DELETE <ns> <key> <expected_version>` | `OK DELETED <version>` |

Error codes: `ERR UNKNOWN_CMD`, `ERR FIELD_COUNT`, `ERR LINE_TOO_LONG`,
`ERR NUL_REJECTED`, `ERR BASE64_INVALID`, `ERR TTL_OUT_OF_RANGE`,
`ERR NAME_INVALID`, `ERR NAMESPACE_DENIED`, `ERR KEY_EXISTS`,
`ERR NOT_FOUND`, `ERR VERSION_CONFLICT <current>`, `ERR LIMIT_OUT_OF_RANGE`,
`ERR AUTH_TOKEN_MISSING`, `ERR AUTH_TOKEN_UNKNOWN`, `ERR AUTH_TOKEN_REVOKED`,
`ERR AUTH_TOKEN_EXPIRED`, `ERR AUTH_FAILED`, `ERR AUTH_REQUIRED <cmd>`,
`ERR AUTH_TIMEOUT`, `ERR ROLE_DENIED <cmd>`, `ERR BUSY`, `ERR SHUTDOWN`,
`ERR DB_ERROR`, `ERR INTERNAL`.

Rules and deterministic choices:

- Namespaces/keys/prefixes must match `[A-Za-z0-9_.-]` (max 64 chars); `-`
  is the "none" token in `LIST` and a listing continuation is the last key.
- `ttl_seconds` range is `0..315360000`; `0` creates a non-expiring record.
- `expected_version >= 1`; a stale version returns `ERR VERSION_CONFLICT`
  with the current version and changes nothing.
- `limit` for `LIST` is `1..list_max_limit`; the page is lexicographic.
- Expired records are invisible to `GET`/`LIST` immediately (wall-clock epoch
  comparison) and removed by the maintenance worker in bounded batches.
- Connections above `max_clients` receive `ERR BUSY` and are closed; idle
  connections are closed after `idle_timeout_seconds`; unauthenticated
  connections after `auth_timeout_seconds`.
- Tokens and stored values are never logged or included in `STATS`.

---

## 9. Traceability (use cases → implementation)

| Use case | Title | Main files | Key functions | Commands / config keys |
| --- | --- | --- | --- | --- |
| NETD-01 | Configuration and startup | `src/main_netd.c`, `src/config.c`, `src/storage.c`, `src/pidfile.c` | `config_load`, `config_validate`, `storage_open`, `bind_listener`, `pidfile_acquire` | `netd --config PATH [--foreground\|--daemon]`, `--check-config`; all config keys |
| NETD-02 | Service lifecycle control | `src/main_netd.c`, `tools/netctl.c`, `src/pidfile.c`, `src/server.c` | `pidfile_acquire`, `pidfile_read_pid`, `signal_by_pid`, `server_run` | `netctl status/shutdown`, `SIGTERM`, `SIGINT`, `pid_file` |
| NETD-03 | Client authentication and session | `src/commands.c`, `src/auth.c`, `src/storage.c` | `cmd_auth`, `auth_hash_token`, `auth_hex_equals`, `storage_auth`, `storage_session_open` | `AUTH <token>`, `QUIT` |
| NETD-04 | Record creation | `src/commands.c`, `src/storage.c` | `cmd_put`, `storage_put` | `PUT <ns> <key> <ttl> <base64>` |
| NETD-05 | Retrieval and bounded listing | `src/commands.c`, `src/storage.c` | `cmd_get`, `cmd_list`, `storage_get`, `storage_list` | `GET`, `LIST`; `list_max_limit` |
| NETD-06 | Conditional update and deletion | `src/commands.c`, `src/storage.c` | `cmd_update`, `cmd_delete`, `storage_update`, `storage_delete` | `UPDATE`, `DELETE` |
| NETD-07 | Record expiry processing | `src/expiry.c`, `src/storage.c`, `src/commands.c` | `expiry_main`, `storage_delete_expired`, `storage_get/list` filters | `expiry_scan_interval_seconds`, `expiry_batch_size`; observed via GET/LIST/STATS |
| NETD-08 | Concurrent clients and limits | `src/server.c` | `accept_conns`, `worker_main`, `worker_process_lines`, `worker_timeouts` | `max_clients`, `worker_count`, `idle_timeout_seconds`, `max_command_bytes`, `database_busy_timeout_ms` |
| NETD-09 | Live configuration reload | `src/server.c`, `src/config.c`, `src/signal.c` | `do_reload`, `config_nonreloadable_changed`, `server_cfg_apply` | `SIGHUP`; reloadable keys; `log_flush` |
| NETD-10 | Health and operational metrics | `src/commands.c`, `src/metrics.c`, `src/storage.c` | `cmd_health`, `cmd_stats`, `metrics_snapshot`, `storage_probe` | `PING`, `HEALTH`, `STATS` |
| NETD-11 | Structured audit logging | `src/log.c`, `src/server.c`, `src/commands.c` | `log_event`, `log_reopen`, `log_flush` | `log_path`, `strict_audit`, `log_flush`, `SIGUSR1` |
| NETD-12 | Graceful shutdown and recovery | `src/server.c`, `src/main_netd.c`, `src/storage.c` | `server_run`, `worker_shutdown_close_all`, `storage_validate_schema` | `SIGTERM`/`SIGINT`, `shutdown_timeout_seconds` |

Persistent entities: `SchemaVersion`, `ServiceInstance`, `Principal`,
`AccessToken`, `NamespaceGrant`, `Record`, `ClientSession`, `MaintenanceRun`,
`ConfigurationGeneration`, `ServiceMetric`, `AuditEvent` (the structured log
lines in `log_path`).

---

## 10. Functional and security tests

The bundled test package contains one Python functional test file for each
NETD-01 through NETD-12 use case, plus 24 PowerShell TCP security scenarios
grouped by OWASP A01-A10 and CWE with Core/P15-specific layers.

```powershell
.\tests\run_function_tests.ps1
.\tests\run_security_tests.ps1
```

Security `FAIL` is a candidate finding, not a harness failure. It must be
manually replayed using the same oracle before confirmation. See
`tests/README.md`, `tests/FUNCTION_TEST_MATRIX.md`, and
`tests/exploit/SECURITY_ORACLE.md`.
