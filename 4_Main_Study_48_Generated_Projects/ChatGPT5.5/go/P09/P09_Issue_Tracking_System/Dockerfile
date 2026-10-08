FROM golang:1.24-bookworm AS build
WORKDIR /src
COPY go.mod go.sum* ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -o /out/server ./cmd/server && CGO_ENABLED=0 GOOS=linux go build -o /out/dbreset ./cmd/dbreset
FROM alpine:3.22
WORKDIR /app
COPY --from=build /out/server /app/server
COPY --from=build /out/dbreset /app/dbreset
COPY web /app/web
RUN mkdir -p /app/data /app/storage
ENV APP_ADDR=:8080 DB_PATH=/app/data/p09.db SESSION_COOKIE=p09_session
EXPOSE 8080
CMD ["/bin/sh", "-c", "/app/dbreset && exec /app/server"]
