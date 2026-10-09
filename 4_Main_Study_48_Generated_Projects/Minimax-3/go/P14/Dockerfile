# --- build stage --------------------------------------------------------------
FROM golang:1.24-bookworm AS builder

ENV CGO_ENABLED=0 GOOS=linux GOARCH=amd64
WORKDIR /src

COPY go.mod go.sum ./
RUN go mod download

COPY . .

RUN go build -trimpath -ldflags="-s -w" -o /out/p14-server ./

# --- runtime stage ------------------------------------------------------------
FROM gcr.io/distroless/static-debian12:nonroot

WORKDIR /app
COPY --from=builder /out/p14-server /app/p14-server
COPY web /app/web

ENV HTTP_ADDR=:8080 \
    DATABASE_PATH=/app/data/app.db \
    WORKSPACE_ROOT=/app/data/workspaces \
    UPLOAD_ROOT=/app/data/uploads \
    P14_ALLOW_SHELL=false

VOLUME ["/app/data"]
EXPOSE 8080
USER nonroot:nonroot

ENTRYPOINT ["/app/p14-server"]