package middleware

import (
	"context"
	"net/http"
	"strings"

	"github.com/anomalyco/p14-workflow-automation/internal/auth"
	"github.com/anomalyco/p14-workflow-automation/internal/httpx"
	"github.com/anomalyco/p14-workflow-automation/internal/models"
)

type ctxKey string

const (
	ctxUser ctxKey = "user"
)

func Auth(svc *auth.Service, users UsersLoader, optional bool) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			cookie, err := r.Cookie("p14_session")
			if err != nil {
				if optional {
					next.ServeHTTP(w, r)
					return
				}
				httpx.WriteError(w, http.StatusUnauthorized, "authentication required", "")
				return
			}
			uid, err := svc.Lookup(r.Context(), cookie.Value)
			if err != nil {
				if optional {
					next.ServeHTTP(w, r)
					return
				}
				httpx.WriteError(w, http.StatusUnauthorized, "invalid session", "")
				return
			}
			user, err := users.ByID(r.Context(), uid)
			if err != nil || user == nil {
				httpx.WriteError(w, http.StatusUnauthorized, "user not found", "")
				return
			}
			if user.Status == "disabled" {
				httpx.WriteError(w, http.StatusForbidden, "account disabled", "")
				return
			}
			ctx := context.WithValue(r.Context(), ctxUser, user)
			next.ServeHTTP(w, r.WithContext(ctx))
		})
	}
}

func RequireRole(roles ...string) func(http.Handler) http.Handler {
	allowed := map[string]struct{}{}
	for _, r := range roles {
		allowed[r] = struct{}{}
	}
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			u := User(r.Context())
			if u == nil {
				httpx.WriteError(w, http.StatusUnauthorized, "authentication required", "")
				return
			}
			if _, ok := allowed[u.Role]; !ok {
				httpx.WriteError(w, http.StatusForbidden, "forbidden", "")
				return
			}
			next.ServeHTTP(w, r)
		})
	}
}

func User(ctx context.Context) *models.User {
	v, _ := ctx.Value(ctxUser).(*models.User)
	return v
}

func IsAdmin(ctx context.Context) bool {
	u := User(ctx)
	return u != nil && strings.EqualFold(u.Role, "admin")
}

type UsersLoader interface {
	ByID(ctx context.Context, id string) (*models.User, error)
}