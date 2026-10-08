package middleware

import (
	"context"
	"encoding/json"
	"net/http"

	"p14-agentic-platform/internal/models"
	"p14-agentic-platform/internal/service"
)

type ctxKey int

const (
	ctxUser ctxKey = iota
)

// Session loads the signed-in user from the session cookie into the context
// when a valid session exists; anonymous requests pass through untouched.
func Session(svc *service.Service, cookieName string) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			c, err := r.Cookie(cookieName)
			if err != nil {
				next.ServeHTTP(w, r)
				return
			}
			u, err := svc.UserByToken(c.Value)
			if err == nil {
				r = r.WithContext(context.WithValue(r.Context(), ctxUser, u))
			}
			next.ServeHTTP(w, r)
		})
	}
}

// UserFrom returns the signed-in user or nil.
func UserFrom(r *http.Request) *models.User {
	u, _ := r.Context().Value(ctxUser).(*models.User)
	return u
}

// RequireAuth rejects unauthenticated API requests with a stable 401.
func RequireAuth(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if UserFrom(r) == nil {
			writeJSON(w, http.StatusUnauthorized, map[string]any{"error": "not signed in"})
			return
		}
		next.ServeHTTP(w, r)
	})
}

// RequireAuthPage redirects unauthenticated page requests to /login.
func RequireAuthPage(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if UserFrom(r) == nil {
			http.Redirect(w, r, "/login", http.StatusFound)
			return
		}
		next.ServeHTTP(w, r)
	})
}

// RequireAdmin rejects non-admin requests with a stable 403.
func RequireAdmin(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		u := UserFrom(r)
		if u == nil {
			writeJSON(w, http.StatusUnauthorized, map[string]any{"error": "not signed in"})
			return
		}
		if u.Role != "admin" {
			writeJSON(w, http.StatusForbidden, map[string]any{"error": "admin role required"})
			return
		}
		next.ServeHTTP(w, r)
	})
}

func writeJSON(w http.ResponseWriter, status int, body any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(body)
}
