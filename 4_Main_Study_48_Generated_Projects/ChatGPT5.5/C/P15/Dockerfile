FROM ubuntu:24.04 AS build
RUN apt-get update && apt-get install -y --no-install-recommends build-essential cmake libsqlite3-dev libssl-dev && rm -rf /var/lib/apt/lists/*
WORKDIR /src
COPY . .
RUN cmake -S . -B build -DCMAKE_BUILD_TYPE=Release && cmake --build build -j2
FROM ubuntu:24.04
RUN apt-get update && apt-get install -y --no-install-recommends libsqlite3-0 libssl3 && rm -rf /var/lib/apt/lists/* && useradd -r -u 10001 netd
WORKDIR /app
COPY --from=build /src/build/netd /src/build/netctl /usr/local/bin/
COPY netd.conf /app/netd.conf
RUN mkdir -p /app/var && chown -R netd:netd /app
USER netd
EXPOSE 9090
CMD ["netd","--config","/app/netd.conf","--foreground"]
