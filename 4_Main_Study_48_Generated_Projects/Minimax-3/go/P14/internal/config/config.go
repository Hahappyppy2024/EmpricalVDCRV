package config

import (
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"time"
)

type Config struct {
	HTTPAddr         string
	DatabasePath     string
	WorkspaceRoot    string
	UploadRoot       string
	SessionSecret    string
	SessionLifetime  time.Duration
	RunnerBufferSize int
	MaxUploadBytes   int64
	AllowedMimeTypes map[string]struct{}
	WebhookHost      string
	HTTPFetchTimeout time.Duration
}

func Load() (*Config, error) {
	cfg := &Config{
		HTTPAddr:         env("HTTP_ADDR", ":8080"),
		DatabasePath:     env("DATABASE_PATH", "data/app.db"),
		WorkspaceRoot:    env("WORKSPACE_ROOT", "data/workspaces"),
		UploadRoot:       env("UPLOAD_ROOT", "data/uploads"),
		SessionSecret:    env("SESSION_SECRET", "p14-dev-secret-change-me"),
		WebhookHost:      env("WEBHOOK_HOST", "http://localhost:8080"),
		HTTPFetchTimeout: time.Duration(envInt("HTTP_FETCH_TIMEOUT_MS", 5000)) * time.Millisecond,
		SessionLifetime:  time.Duration(envInt("SESSION_LIFETIME_MIN", 60*24)) * time.Minute,
		RunnerBufferSize: envInt("RUNNER_BUFFER_SIZE", 256),
		MaxUploadBytes:   int64(envInt("MAX_UPLOAD_BYTES", 5*1024*1024)),
	}
	cfg.AllowedMimeTypes = map[string]struct{}{
		"text/plain":             {},
		"text/csv":               {},
		"text/html":              {},
		"application/json":       {},
		"application/xml":        {},
		"application/octet-stream": {},
	}

	if err := os.MkdirAll(filepath.Dir(cfg.DatabasePath), 0o755); err != nil {
		return nil, fmt.Errorf("create db dir: %w", err)
	}
	if err := os.MkdirAll(cfg.WorkspaceRoot, 0o755); err != nil {
		return nil, fmt.Errorf("create workspace dir: %w", err)
	}
	if err := os.MkdirAll(cfg.UploadRoot, 0o755); err != nil {
		return nil, fmt.Errorf("create upload dir: %w", err)
	}
	return cfg, nil
}

func env(key, def string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return def
}

func envInt(key string, def int) int {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return def
}