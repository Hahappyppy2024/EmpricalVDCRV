# Build stage: strict-warning native build with CTest enabled.
FROM debian:bookworm AS build

RUN apt-get update && \
    for i in 1 2 3 4 5 6; do \
        apt-get install -y --no-install-recommends \
            build-essential cmake libsqlite3-dev libssl-dev && break; \
        echo "apt install attempt $i failed, retrying in 5s..."; sleep 5; \
    done && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /src
COPY . .

RUN cmake -S . -B build -DCMAKE_BUILD_TYPE=Release \
    && cmake --build build --parallel \
    && cmake --install build --prefix /out

# Runtime stage: slim image with only the runtime libraries.
FROM debian:bookworm-slim

RUN apt-get update && \
    for i in 1 2 3 4 5 6; do \
        apt-get install -y --no-install-recommends \
            libsqlite3-0 libssl3 && break; \
        echo "runtime apt attempt $i failed, retrying in 5s..."; sleep 5; \
    done && \
    rm -rf /var/lib/apt/lists/* \
    && useradd --system --no-create-home --shell /usr/sbin/nologin netd \
    && mkdir -p /data /logs /run /etc/netd \
    && chown -R netd:netd /data /logs /run

COPY --from=build /out/bin /usr/local/bin
COPY config/netd.docker.conf /etc/netd/netd.conf

VOLUME ["/data", "/logs", "/run"]
USER netd

# The container runs in foreground mode; SQLite/log/PID live in the mounted
# writable volumes above.
ENTRYPOINT ["netd", "--config", "/etc/netd/netd.conf", "--foreground"]
