package config

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
)

type Config struct {
	Addr         string
	BaseURL      string
	DataDir      string
	DBPath       string
	UploadDir    string
	UploadLimit  int64
	SessionKey   string
	SessionTTL   int
	CookieName   string
	WebSocketID  string
	Environment  string
}

func Load() (*Config, error) {
	cfg := &Config{
		Addr:        env("APP_ADDR", ":8080"),
		BaseURL:     env("APP_BASE_URL", "http://localhost:8080"),
		DataDir:     env("APP_DATA_DIR", "data"),
		UploadLimit: 10 * 1024 * 1024,
		SessionKey:  env("APP_SESSION_KEY", "issue-tracker-session-key-please-change"),
		SessionTTL:  60 * 60 * 24 * 7,
		CookieName:  env("APP_COOKIE_NAME", "its_session"),
		WebSocketID: env("APP_WS_HEADER", "X-WS-Token"),
		Environment: env("APP_ENV", "development"),
	}

	cfg.DBPath = env("APP_DB_PATH", filepath.Join(cfg.DataDir, "its.db"))
	cfg.UploadDir = env("APP_UPLOAD_DIR", filepath.Join(cfg.DataDir, "uploads"))

	if err := os.MkdirAll(cfg.DataDir, 0o755); err != nil {
		return nil, fmt.Errorf("create data dir: %w", err)
	}
	if err := os.MkdirAll(cfg.UploadDir, 0o755); err != nil {
		return nil, fmt.Errorf("create upload dir: %w", err)
	}

	if !strings.HasPrefix(cfg.BaseURL, "http://") && !strings.HasPrefix(cfg.BaseURL, "https://") {
		return nil, fmt.Errorf("APP_BASE_URL must include scheme")
	}

	return cfg, nil
}

func env(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}