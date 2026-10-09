#!/bin/bash
# Single-shot smoke test - everything in one shell to avoid docker container overhead
set -u
cd /src

apt-get update -qq >/dev/null 2>&1
apt-get install -y -qq --no-install-recommends \
    gcc cmake libssl-dev libsqlite3-dev make libssl3 sqlite3 2>&1 | tail -1
which cmake gcc make sqlite3 >/dev/null || { echo "DEPS MISSING"; exit 1; }

rm -rf build var
mkdir -p var

cmake -S . -B build -DCMAKE_BUILD_TYPE=Release > /tmp/cmake.log 2>&1
cmake --build build --parallel > /tmp/build.log 2>&1
RC=$?
if [ $RC -ne 0 ]; then
    tail -10 /tmp/build.log
    exit $RC
fi
ls build/bin/

setsid ./build/bin/netd --config conf/netd.conf --reset --foreground \
    > var/log 2>&1 < /dev/null &
sleep 2
PID=$(cat var/netd.pid)
echo "daemon pid=$PID"

step() { echo; echo "=== $1 ==="; }

step "status"
./build/bin/netctl --config conf/netd.conf status

step "PING (unauth)"
./build/bin/netctl --host 127.0.0.1 --port 9090 raw "PING"
echo "rc=$?"

step "bad token"
./build/bin/netctl --config conf/netd.conf --token "WRONG" get app greeting
echo "rc=$?"

step "auth + ping on same connection"
printf 'AUTH client-token-001\nPING\n' | ./build/bin/netctl --host 127.0.0.1 --port 9090 raw-multiline

step "PUT"
./build/bin/netctl --config conf/netd.conf --token client-token-001 \
    put app smoke 30 aGVsbG8=
echo "rc=$?"

step "GET"
./build/bin/netctl --config conf/netd.conf --token client-token-001 get app smoke
echo "rc=$?"

step "LIST"
./build/bin/netctl --config conf/netd.conf --token client-token-001 list app _ 10 -
echo "rc=$?"

step "UPDATE"
./build/bin/netctl --config conf/netd.conf --token client-token-001 \
    update app smoke 1 60 VU5EQVRFRA==
echo "rc=$?"

step "DELETE"
./build/bin/netctl --config conf/netd.conf --token client-token-001 delete app smoke 2
echo "rc=$?"

step "HEALTH"
./build/bin/netctl --config conf/netd.conf --token monitor-token-001 health
echo "rc=$?"

step "STATS"
./build/bin/netctl --config conf/netd.conf --token admin-token-001 stats
echo "rc=$?"

step "CONCURRENT"
(
    ./build/bin/netctl --config conf/netd.conf --token client-token-001 put app a 30 YQ== &
    ./build/bin/netctl --config conf/netd.conf --token client-token-001 put app b 30 Yg== &
    ./build/bin/netctl --config conf/netd.conf --token client-token-001 put app c 30 Yw== &
    wait
)

step "SIGHUP reload"
kill -HUP "$PID"
sleep 1
grep -q "CONFIG_RELOAD outcome=OK" var/log && echo "pass: reload applied" || echo "WARN"

step "SIGTERM"
kill -TERM "$PID"
for i in $(seq 1 20); do
    if ! kill -0 "$PID" 2>/dev/null; then break; fi
    sleep 1
done
if ! kill -0 "$PID" 2>/dev/null; then echo "pass: stopped after ${i}s"; else echo "WARN: still alive"; fi

step "audit log"
head -10 var/netd.audit.log

step "restart preserves seed"
setsid ./build/bin/netd --config conf/netd.conf --foreground > var/log2 2>&1 < /dev/null &
sleep 2
NEW_PID=$(cat var/netd.pid)
./build/bin/netctl --config conf/netd.conf --token client-token-001 get app greeting
kill -TERM "$NEW_PID"
sleep 1

rm -rf var
echo
echo "=== DONE ==="