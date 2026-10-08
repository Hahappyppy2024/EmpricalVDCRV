// Package store implements the repository/data-access layer on top of SQLite.
package store

import (
	"database/sql"
	"errors"
	"time"
)

// Store wraps the SQLite connection and provides typed data access.
type Store struct {
	DB *sql.DB
}

// New creates a Store for the given connection.
func New(db *sql.DB) *Store {
	return &Store{DB: db}
}

// Common errors returned by the data-access layer.
var (
	ErrNotFound  = errors.New("record not found")
	ErrDuplicate = errors.New("duplicate record")
)

const timeLayout = "2006-01-02T15:04:05Z07:00"

func parseTime(s string) time.Time {
	t, err := time.Parse(timeLayout, s)
	if err != nil {
		return time.Time{}
	}
	return t
}

func formatTime(t time.Time) string {
	return t.UTC().Format(timeLayout)
}

// Now returns the current UTC time formatted for storage.
func Now() string { return formatTime(time.Now()) }
