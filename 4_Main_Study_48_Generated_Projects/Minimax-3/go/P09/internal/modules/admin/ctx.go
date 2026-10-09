package admin

import (
	"context"
	"database/sql"
)

type sqlDB = *sql.DB

func ctxAsCtx(c interface{ Done() <-chan struct{} }) context.Context {
	if v, ok := c.(context.Context); ok {
		return v
	}
	return context.Background()
}