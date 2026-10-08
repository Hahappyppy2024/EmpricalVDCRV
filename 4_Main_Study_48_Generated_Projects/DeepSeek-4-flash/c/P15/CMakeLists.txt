cmake_minimum_required(VERSION 3.25)

project(P15_netd
    VERSION 1.0.0
    DESCRIPTION "P15 Lightweight Network Service / System Daemon"
    LANGUAGES C)

set(CMAKE_C_STANDARD 17)
set(CMAKE_C_STANDARD_REQUIRED ON)
set(CMAKE_C_EXTENSIONS OFF)

if(NOT CMAKE_BUILD_TYPE)
    set(CMAKE_BUILD_TYPE RelWithDebInfo)
endif()

# Project source must compile with strict warnings and treat warnings as errors.
add_compile_options(-Wall -Wextra -Wpedantic -Werror)

# Feature test macro for POSIX APIs (clock_gettime, poll, strtok_r, fork, ...).
add_compile_definitions(_GNU_SOURCE=1)

set(THREADS_PREFER_PTHREAD_FLAG ON)
find_package(Threads REQUIRED)
find_package(SQLite3 REQUIRED)
find_package(OpenSSL REQUIRED COMPONENTS Crypto)

# Shared core used by both the daemon and the operator/client tools.
add_library(netdlib STATIC
    src/config.c
    src/log.c
    src/storage.c
    src/auth.c
    src/base64.c
    src/metrics.c
    src/util.c
    src/pidfile.c
    src/daemon.c
    src/signal.c)
target_include_directories(netdlib PUBLIC include)
target_link_libraries(netdlib PUBLIC
    Threads::Threads
    SQLite::SQLite3
    OpenSSL::Crypto)

# Long-running daemon.
add_executable(netd
    src/main_netd.c
    src/server.c
    src/commands.c
    src/expiry.c)
target_link_libraries(netd PRIVATE netdlib)

# Operator / client / status executable.
add_executable(netctl tools/netctl.c)
target_link_libraries(netctl PRIVATE netdlib)

# Small operator utility: SHA-256 hash of a token (never stored in plaintext).
add_executable(token_hash tools/token_hash.c)
target_link_libraries(token_hash PRIVATE netdlib)

# Register the 12 use-case functional tests with CTest when Python is present.
enable_testing()
find_package(Python3 COMPONENTS Interpreter)
if(Python3_Interpreter_FOUND)
    add_test(
        NAME p15_function_tests
        COMMAND ${Python3_EXECUTABLE} -m unittest discover
                -s ${CMAKE_SOURCE_DIR}/tests/function
                -p test_netd_*.py -v)
    set_tests_properties(p15_function_tests PROPERTIES
        WORKING_DIRECTORY ${CMAKE_SOURCE_DIR}
        ENVIRONMENT "NETD_SOURCE_DIR=${CMAKE_SOURCE_DIR};NETD_BUILD_DIR=${CMAKE_BINARY_DIR}")
endif()

install(TARGETS netd netctl token_hash RUNTIME DESTINATION bin)
