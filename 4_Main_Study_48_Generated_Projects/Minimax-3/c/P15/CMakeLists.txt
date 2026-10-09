cmake_minimum_required(VERSION 3.25)
project(netd
    VERSION 1.0.0
    DESCRIPTION "Lightweight Network Service / System Daemon"
    LANGUAGES C)

set(CMAKE_C_STANDARD 17)
set(CMAKE_C_STANDARD_REQUIRED ON)
set(CMAKE_C_EXTENSIONS OFF)
set(CMAKE_EXPORT_COMPILE_COMMANDS ON)

if(NOT CMAKE_BUILD_TYPE)
    set(CMAKE_BUILD_TYPE Release CACHE STRING "Build type" FORCE)
endif()

list(APPEND CMAKE_MODULE_PATH "${CMAKE_SOURCE_DIR}/cmake")

find_package(Threads REQUIRED)
find_package(OpenSSL REQUIRED)
find_package(SQLite3 REQUIRED)

include(CMakeDependentOption)
include(CheckCCompilerFlag)

option(NETD_ENABLE_ASAN "Enable AddressSanitizer" OFF)
option(NETD_ENABLE_UBSAN "Enable UndefinedBehaviorSanitizer" OFF)

set(NETD_CORE_SOURCES
    src/main.c
    src/config.c
    src/log.c
    src/protocol.c
    src/server.c
    src/connection.c
    src/worker.c
    src/storage.c
    src/auth.c
    src/metrics.c
    src/signals.c
    src/expiry.c
    src/pidfile.c
    src/base64.c
    src/audit.c
    src/seed.c
    src/util.c)

add_executable(netd ${NETD_CORE_SOURCES})
add_executable(netctl tools/netctl.c src/util.c src/config.c)

set(NETD_COMMON_FLAGS
    -std=c17
    -Wall
    -Wextra
    -Wpedantic
    -Werror
    -Wshadow
    -Wstrict-prototypes
    -Wmissing-prototypes
    -Wpointer-arith
    -Wformat=2
    -Wno-unused-parameter
    -D_POSIX_C_SOURCE=200809L
    -D_DEFAULT_SOURCE
    -D_GNU_SOURCE)

target_compile_options(netd PRIVATE ${NETD_COMMON_FLAGS})
target_compile_options(netctl PRIVATE ${NETD_COMMON_FLAGS} -Wno-unused-function)

if(CMAKE_BUILD_TYPE STREQUAL "Debug")
    target_compile_options(netd PRIVATE -O0 -g3 -fno-omit-frame-pointer)
    target_compile_options(netctl PRIVATE -O0 -g3)
else()
    target_compile_options(netd PRIVATE -O2 -g)
    target_compile_options(netctl PRIVATE -O2 -g)
endif()

if(NETD_ENABLE_ASAN)
    message(STATUS "Enabling AddressSanitizer")
    target_compile_options(netd PRIVATE -fsanitize=address -fno-omit-frame-pointer)
    target_link_options(netd PRIVATE -fsanitize=address)
endif()

if(NETD_ENABLE_UBSAN)
    message(STATUS "Enabling UndefinedBehaviorSanitizer")
    target_compile_options(netd PRIVATE -fsanitize=undefined -fno-sanitize-recover=all)
    target_link_options(netd PRIVATE -fsanitize=undefined)
endif()

target_include_directories(netd PRIVATE
    ${CMAKE_SOURCE_DIR}/include
    ${SQLite3_INCLUDE_DIRS})

target_include_directories(netctl PRIVATE
    ${CMAKE_SOURCE_DIR}/include)

target_link_libraries(netd PRIVATE
    Threads::Threads
    OpenSSL::Crypto
    SQLite::SQLite3)

target_link_libraries(netctl PRIVATE Threads::Threads)

set_target_properties(netd PROPERTIES
    RUNTIME_OUTPUT_DIRECTORY "${CMAKE_BINARY_DIR}/bin")

set_target_properties(netctl PROPERTIES
    RUNTIME_OUTPUT_DIRECTORY "${CMAKE_BINARY_DIR}/bin")

install(TARGETS netd netctl RUNTIME DESTINATION bin)
install(DIRECTORY conf/ DESTINATION etc/netd)
install(DIRECTORY sql/ DESTINATION share/netd/sql)

enable_testing()
add_subdirectory(tests)