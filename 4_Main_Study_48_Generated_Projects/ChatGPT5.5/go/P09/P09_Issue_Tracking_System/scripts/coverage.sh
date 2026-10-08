#!/usr/bin/env bash
set -euo pipefail

mkdir -p coverage
BRANCH_THRESHOLD="${BRANCH_THRESHOLD:-85}"

echo "== Functional tests + Go line/statement coverage =="
go test ./tests/function -count=1 -coverpkg=./internal/app -covermode=atomic -coverprofile=coverage/line.out 2>&1 | tee coverage/functional-test.log

go tool cover -func=coverage/line.out | tee coverage/line.txt
go tool cover -html=coverage/line.out -o coverage/line.html
LINE_COVERAGE="$(go tool cover -func=coverage/line.out | awk '/^total:/ {gsub(/%/, "", $3); print $3}')"
echo "Line/statement coverage: ${LINE_COVERAGE}%"

echo
echo "== Instrumented branch-outcome coverage =="
go run ./tools/branchcov -target internal/app -tests ./tests/function -threshold "$BRANCH_THRESHOLD" -out coverage/branch.json 2>&1 | tee coverage/branch.txt

echo
echo "Artifacts:"
echo "  coverage/line.out"
echo "  coverage/line.html"
echo "  coverage/line.txt"
echo "  coverage/branch.json"
echo "  coverage/branch.txt"
