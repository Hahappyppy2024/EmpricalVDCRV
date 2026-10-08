# P15 Lightweight Network Service / System Daemon

`netd` is a C17/POSIX TCP service with a bounded pthread worker pool, strict configuration, hashed-token authentication, namespaced versioned records, TTL expiry, live reload, metrics, audit logging, graceful shutdown, and SQLite persistence. `netctl` reports status and requests a normal stop.

## Prerequisites and build

Supported baseline: Linux x86-64, GCC 13 or Clang 17, CMake 3.25+, SQLite 3, OpenSSL 3, pthreads, and Python 3.10+ for functional tests.

Ubuntu 24.04 dependencies:

```bash
sudo apt-get update
sudo apt-get install -y build-essential cmake libsqlite3-dev libssl-dev python3
cmake -S . -B build -DCMAKE_BUILD_TYPE=Release
cmake --build build -j2
```

The fallback build, useful on a host that has the runtime libraries but no CMake, is `make all`.

## Database, startup, lifecycle, and client use

```bash
mkdir -p var
./build/netd --config netd.conf --reset-db
./build/netd --config netd.conf --foreground
./build/netd --config netd.conf --daemon
./build/netctl status --config netd.conf
./build/netctl stop --config netd.conf
kill -TERM "$(cat var/netd.pid)"
```

Any TCP line client can exercise the protocol, for example `nc 127.0.0.1 9090`. Deterministic development tokens are `client-token`, `monitor-token`, `admin-token`, and the intentionally rejected `revoked-token`. Only SHA-256 hashes are persisted.

Docker:

```bash
docker compose up --build
docker compose down
```

The container runs in foreground mode and mounts `./var` by default. Override `NETD_PORT` and `NETD_DATA_VOLUME` through compose variables.

## Configuration

The parser requires exact `key=value` lines. Unknown keys, duplicates, empty values, malformed integers, out-of-range values, and unsupported bind addresses fail atomically before the listener opens. See `netd.conf` for all keys. Reload with `kill -HUP $(cat var/netd.pid)`. Address, port, database path, PID path, worker count, and command-byte limit require restart; other settings reload atomically. SIGHUP also reopens the audit log.

## Protocol

All requests and responses are bounded newline-delimited ASCII/UTF-8. Embedded NUL, invalid Base64, extra/missing fields, and overlong lines are rejected.

| Command | Role | Success response |
|---|---|---|
| `AUTH <token>` | Unauthenticated | `OK AUTH <role>` |
| `QUIT` | Any | `OK BYE` |
| `PING` | Authenticated | `OK PONG` |
| `PUT <ns> <key> <ttl> <base64>` | Granted client/admin | `OK CREATED 1 <epoch\|NONE>` |
| `GET <ns> <key>` | Granted client/admin | `OK RECORD <version> <base64> <epoch\|NONE>` |
| `LIST <ns> <prefix> <limit> <after\|->` | Granted client/admin | `OK LIST <count> <comma-keys\|->` |
| `UPDATE <ns> <key> <version> <ttl> <base64>` | Granted client/admin | `OK UPDATED <new-version>` |
| `DELETE <ns> <key> <version>` | Granted client/admin | `OK DELETED` |
| `HEALTH` | Monitoring/admin | `OK HEALTH READY` or `DEGRADED` |
| `STATS` | Monitoring/admin | Bounded counter line |

Errors have the stable form `ERR <code> <message>`.

## Functional tests and coverage

Each use case has its own test file under `tests/test_netd_XX.py`.

```bash
make test
make coverage
```

`make coverage` uses GCC edge instrumentation and fails if raw whole-project branch coverage is below 85%. The last verified results are recorded in `TEST_RESULTS.md`. Sanitizer builds are available with `cmake --preset asan` and `cmake --preset ubsan`.

## Security tests

The two-layer PowerShell TCP attack suite is under `tests/exploit`. It mirrors the supplied OWASP A01–A10 folder organization while testing only attack surfaces that exist in this native daemon.

```bash
make all
pwsh -File ./tests/exploit/run_all_tests.ps1
# or: make security-test
```

The suite uses an isolated database and port, emits JSON reports, and distinguishes `PASS`, candidate `FAIL`, `ERROR`, and `NOT_APPLICABLE`. See `tests/exploit/SECURITY_ORACLE.md`, `TEST_COVERAGE.md`, and `MANUAL_ATTACK_VALIDATION.md`. The current design is expected to expose candidate findings for plaintext TCP, missing authentication throttling, and deterministic fixture credentials; these are not converted into false PASS results.

## Traceability

| Use case | Test | Main implementation | Observable interface/configuration |
|---|---|---|---|
| NETD-01 | `test_netd_01.py` | `config.c`, `netd.c`, `server.c`, `store_initialize` | `--foreground`, `--daemon`, `--init-db`, `--reset-db`, all config keys |
| NETD-02 | `test_netd_02.py` | PID locking and signal loop in `server.c`; `netctl.c` | `netctl status/stop`, SIGTERM/SIGINT, `pid_file` |
| NETD-03 | `test_netd_03.py` | `store_auth`, session state in `protocol.c` | `AUTH`, `QUIT`; four seeded tokens |
| NETD-04 | `test_netd_04.py` | `base64_decode`, `store_put` | `PUT`, `max_ttl_seconds` |
| NETD-05 | `test_netd_05.py` | `store_get`, `store_list` | `GET`, `LIST`, `max_list_limit` |
| NETD-06 | `test_netd_06.py` | `store_update`, `store_delete` | `UPDATE`, `DELETE`, expected versions |
| NETD-07 | `test_netd_07.py` | `store_expire`, server maintenance loop | TTL, `expiry_scan_interval_seconds`, `expiry_batch_size` |
| NETD-08 | `test_netd_08.py` | worker queue and per-connection parser | `worker_count`, `max_clients`, idle/auth/byte limits |
| NETD-09 | `test_netd_09.py` | async-safe flags and control-loop reload | SIGHUP, reloadable/non-reloadable keys |
| NETD-10 | `test_netd_10.py` | `store_integrity`, metrics snapshot | `PING`, `HEALTH`, `STATS` |
| NETD-11 | `test_netd_11.py` | redacted locked logger in `log.c` | audit file, `log_flush`, `strict_audit` |
| NETD-12 | `test_netd_12.py` | shutdown ordering, WAL recovery, integrity gate | SIGTERM/SIGINT and restart |

## Deterministic choices

Identifiers are 1–64 characters from `[A-Za-z0-9_.-]`; decoded values are limited to 4096 bytes; LIST emits comma-separated ordered keys on one bounded response line. The client principal can access `public` and `client`; monitoring has no record grant; administrator has wildcard access. TTL comparisons use epoch seconds and treat `expires_at <= now` as unavailable.
