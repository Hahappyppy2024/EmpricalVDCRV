# Multi-stage build for netd.
# Stage 1 — builder: compile the daemon and client using GCC 13 against
#           the SQLite3 and OpenSSL development headers.
# Stage 2 — runtime: copy the binaries and required configuration into a
#           minimal Debian image that runs the daemon in foreground mode
#           and persists state through mounted writable volumes.

FROM debian:bookworm-slim AS builder
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        build-essential \
        cmake \
        gcc \
        libc6-dev \
        libssl-dev \
        libsqlite3-dev \
        pkg-config \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /src
COPY CMakeLists.txt ./
COPY include ./include
COPY src ./src
COPY tools ./tools
COPY cmake ./cmake
COPY tests ./tests

RUN cmake -S . -B build -DCMAKE_BUILD_TYPE=Release \
        -DNETD_ENABLE_ASAN=OFF -DNETD_ENABLE_UBSAN=OFF \
    && cmake --build build --parallel --target netd netctl \
    && cmake --install build --prefix /stage

FROM debian:bookworm-slim
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        libsqlite3-0 \
        libssl3 \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd -r netd && useradd -r -g netd -d /var/lib/netd -s /usr/sbin/nologin netd \
    && mkdir -p /var/lib/netd /var/log/netd /var/run/netd /etc/netd \
    && chown -R netd:netd /var/lib/netd /var/log/netd /var/run/netd

COPY --from=builder /stage/bin/netd /usr/local/bin/netd
COPY --from=builder /stage/bin/netctl /usr/local/bin/netctl
COPY conf/netd.conf /etc/netd/netd.conf
COPY sql/schema.sql /usr/local/share/netd/sql/schema.sql

ENV NETD_CONFIG=/etc/netd/netd.conf
EXPOSE 9090
VOLUME ["/var/lib/netd", "/var/log/netd", "/var/run/netd"]
USER netd
WORKDIR /var/lib/netd
ENTRYPOINT ["/usr/local/bin/netd"]
CMD ["--config", "/etc/netd/netd.conf", "--foreground"]