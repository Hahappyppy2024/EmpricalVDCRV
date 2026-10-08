package config

import (
	"os"
	"strconv"
)

// Config holds all runtime configuration. Every value is sourced from the
// environment (see .env.example) so the application can run locally, in Docker
// or in any CI without code changes.
type Config struct {
	Addr            string
	DBPath          string
	DataDir         string
	SessionName     string
	SessionTTL      int
	Seed            bool
	WebBaseURL      string
	MaxFileBytes    int64
	MaxPayloadBytes int64
	LogLevel        string
	StepDelayMs     int
	SchedulerTickMs int
}

// Load reads configuration from environment variables with sane local defaults.
func Load() *Config {
	return &Config{
		Addr:            env("APP_ADDR", ":8080"),
		DBPath:          env("DB_PATH", "./data/p14.db"),
		DataDir:         env("DATA_DIR", "./data"),
		SessionName:     env("SESSION_COOKIE", "p14_session"),
		SessionTTL:      envInt("SESSION_TTL_SECONDS", 86400),
		Seed:            envBool("SEED_DB", false),
		WebBaseURL:      env("WEB_BASE_URL", "http://localhost:8080"),
		MaxFileBytes:    envInt64("MAX_FILE_BYTES", 5*1024*1024),
		MaxPayloadBytes: envInt64("MAX_PAYLOAD_BYTES", 1<<20),
		LogLevel:        env("LOG_LEVEL", "info"),
		StepDelayMs:     envInt("STEP_DELAY_MS", 120),
		SchedulerTickMs: envInt("SCHEDULER_TICK_MS", 20000),
	}
}

func env(key, def string) string {
	if v, ok := os.LookupEnv(key); ok && v != "" {
		return v
	}
	return def
}

func envInt(key string, def int) int {
	if v, ok := os.LookupEnv(key); ok {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return def
}

func envInt64(key string, def int64) int64 {
	if v, ok := os.LookupEnv(key); ok {
		if n, err := strconv.ParseInt(v, 10, 64); err == nil {
			return n
		}
	}
	return def
}

func envBool(key string, def bool) bool {
	if v, ok := os.LookupEnv(key); ok {
		if b, err := strconv.ParseBool(v); err == nil {
			return b
		}
	}
	return def
}
