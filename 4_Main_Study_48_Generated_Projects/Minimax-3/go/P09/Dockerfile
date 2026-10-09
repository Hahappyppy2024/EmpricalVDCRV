# Build stage
FROM golang:1.24-alpine AS builder

ENV CGO_ENABLED=0 GOFLAGS="-trimpath"

WORKDIR /src

COPY go.mod go.sum ./
RUN go mod download

COPY . .

RUN go build -ldflags="-s -w" -o /out/server ./cmd/server
RUN go build -ldflags="-s -w" -o /out/seed ./cmd/seed

# Runtime stage
FROM alpine:3.19

RUN apk add --no-cache ca-certificates tzdata && \
    addgroup -S app && adduser -S app -G app

WORKDIR /app

COPY --from=builder /out/server /app/server
COPY --from=builder /out/seed /app/seed
COPY --from=builder /src/web /app/web
COPY --from=builder /src/internal/templates/files /app/internal/templates/files
COPY docker-entrypoint.sh /app/docker-entrypoint.sh

RUN chmod +x /app/docker-entrypoint.sh && \
    mkdir -p /app/data && \
    chown -R app:app /app

USER app

ENV APP_ADDR=":8080" \
    APP_BASE_URL="http://localhost:8080" \
    APP_DATA_DIR="/app/data" \
    APP_DB_PATH="/app/data/its.db" \
    APP_UPLOAD_DIR="/app/data/uploads"

EXPOSE 8080

ENTRYPOINT ["/app/docker-entrypoint.sh"]
CMD ["/app/server"]