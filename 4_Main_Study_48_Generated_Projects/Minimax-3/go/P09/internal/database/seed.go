package database

import (
	"context"
	"database/sql"
)

// Seed runs the deterministic seed data. It is safe to call multiple times;
// existing rows are not duplicated.
func Seed(ctx context.Context, db *sql.DB) error {
	return SeedFn(ctx, db)
}