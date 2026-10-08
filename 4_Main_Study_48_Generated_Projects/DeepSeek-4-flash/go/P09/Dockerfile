# --- Stage 1: build ---
FROM golang:1.24-alpine AS build
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 go build -o /out/issuetracker .

# --- Stage 2: runtime ---
FROM alpine:3.20
RUN apk add --no-cache ca-certificates tzdata
WORKDIR /app
COPY --from=build /out/issuetracker /app/issuetracker
ENV PORT=8080 DB_PATH=/app/data/issuetracker.db UPLOAD_DIR=/app/data/uploads APP_URL=http://localhost:8080
RUN mkdir -p /app/data/uploads
EXPOSE 8080
ENTRYPOINT ["/app/issuetracker"]
