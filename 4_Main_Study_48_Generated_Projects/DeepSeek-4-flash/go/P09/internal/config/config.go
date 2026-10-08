// Package config loads the application configuration from environment variables.
package config

import (
	"os"
	"strconv"
)

// Config holds the runtime configuration of the application.
type Config struct {
	// Port is the HTTP listen port.
	Port string
	// DBPath is the SQLite database file path.
	DBPath string
	// UploadDir is the directory where uploaded/stored files are kept.
	UploadDir string
	// SessionSecret is used to sign session cookies (fallback for dev).
	SessionSecret string
	// SessionTTLHours is the session lifetime in hours.
	SessionTTLHours int
	// MaxUploadMB is the maximum allowed upload size in megabytes.
	MaxUploadMB int64
	// AppURL is the public base URL, used for webhook seed URLs and links.
	AppURL string
}

// Load reads the configuration from environment variables, applying the
// documented defaults defined in .env.example.
func Load() Config {
	return Config{
		Port:            getEnv("PORT", "8080"),
		DBPath:          getEnv("DB_PATH", "data/issuetracker.db"),
		UploadDir:       getEnv("UPLOAD_DIR", "data/uploads"),
		SessionSecret:   getEnv("SESSION_SECRET", "dev-only-secret-change-me"),
		SessionTTLHours: getEnvInt("SESSION_TTL_HOURS", 24),
		MaxUploadMB:     getEnvInt64("MAX_UPLOAD_MB", 5),
		AppURL:          getEnv("APP_URL", "http://localhost:8080"),
	}
}

func getEnv(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}

func getEnvInt(key string, fallback int) int {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return fallback
}

func getEnvInt64(key string, fallback int64) int64 {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.ParseInt(v, 10, 64); err == nil {
			return n
		}
	}
	return fallback
}
