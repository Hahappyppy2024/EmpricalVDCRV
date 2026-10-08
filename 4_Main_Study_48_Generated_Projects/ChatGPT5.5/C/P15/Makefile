CC ?= gcc
CPPFLAGS := -Iinclude -D_POSIX_C_SOURCE=200809L
CFLAGS := -std=c17 -Wall -Wextra -Wpedantic -Werror -O2
LDLIBS := -l:libsqlite3.so.0 -lcrypto -lpthread
CORE := src/config.c src/util.c src/log.c src/db.c src/protocol.c
.PHONY: all clean test coverage security-test
all: build/netd build/netctl
build:
	mkdir -p build
build/netd: $(CORE) src/server.c src/netd.c | build
	$(CC) $(CPPFLAGS) $(CFLAGS) $^ $(LDLIBS) -o $@
build/netctl: src/config.c src/netctl.c | build
	$(CC) $(CPPFLAGS) $(CFLAGS) $^ $(LDLIBS) -o $@
test: all
	NETD_BUILD_DIR=$(CURDIR)/build NETD_SOURCE_DIR=$(CURDIR) python3 -m unittest discover -s tests -p 'test_netd_*.py' -v
coverage: CFLAGS += -O0 -g --coverage
coverage: LDLIBS += --coverage
coverage: clean all
	NETD_BUILD_DIR=$(CURDIR)/build NETD_SOURCE_DIR=$(CURDIR) python3 -m unittest discover -s tests -p 'test_netd_*.py' -v
	python3 tools/coverage_report.py build
security-test: all
	pwsh -File ./tests/exploit/run_all_tests.ps1
clean:
	rm -rf build
