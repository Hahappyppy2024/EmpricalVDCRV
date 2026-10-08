# Build stage
FROM golang:1.24-alpine AS build
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-s -w" -o /out/p14-agentic .

# Runtime stage
FROM alpine:3.20
RUN apk add --no-cache ca-certificates tzdata
WORKDIR /app
COPY --from=build /out/p14-agentic /app/p14-agentic
RUN mkdir -p /app/data
EXPOSE 8080
ENV APP_ADDR=:8080 \
    DB_PATH=/app/data/p14.db \
    DATA_DIR=/app/data \
    WEB_BASE_URL=http://localhost:8080
CMD ["/app/p14-agentic"]
