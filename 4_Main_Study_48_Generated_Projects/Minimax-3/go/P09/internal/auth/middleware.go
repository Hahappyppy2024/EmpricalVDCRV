package auth

import (
	"context"
	"net/http"
	"strings"
)

type Middleware struct {
	svc *Service
}

func NewMiddleware(svc *Service) *Middleware {
	return &Middleware{svc: svc}
}

func (m *Middleware) Authenticate(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		c, err := r.Cookie(m.svc.CookieName())
		if err != nil || c.Value == "" {
			next.ServeHTTP(w, r)
			return
		}
		sess, u, err := m.svc.LookupSession(r.Context(), c.Value)
		if err != nil {
			m.svc.ClearCookie(w)
			next.ServeHTTP(w, r)
			return
		}
		ctx := WithUser(r.Context(), u, sess)
		next.ServeHTTP(w, r.WithContext(ctx))
	})
}

func RequireAuth(svc *Service) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			u, ok := UserFromContext(r.Context())
			if !ok {
				http.Redirect(w, r, "/login?next="+r.URL.Path, http.StatusSeeOther)
				return
			}
			if u.Status != "active" {
				svc.ClearCookie(w)
				http.Error(w, "account suspended", http.StatusForbidden)
				return
			}
			next.ServeHTTP(w, r)
		})
	}
}

func RequireRole(svc *Service, roles ...string) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			u, ok := UserFromContext(r.Context())
			if !ok {
				http.Error(w, "unauthorized", http.StatusUnauthorized)
				return
			}
			for _, role := range roles {
				if u.Role == role {
					next.ServeHTTP(w, r)
					return
				}
			}
			http.Error(w, "forbidden", http.StatusForbidden)
		})
	}
}

func CSRFProtect(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodPost || r.Method == http.MethodPut || r.Method == http.MethodPatch || r.Method == http.MethodDelete {
			path := r.URL.Path
			if path == "/api/issue/account_access/login" || path == "/api/issue/account_access/register" {
				next.ServeHTTP(w, r)
				return
			}
			sess, ok := SessionFromContext(r.Context())
			if !ok {
				http.Error(w, "missing session", http.StatusUnauthorized)
				return
			}
			token := r.FormValue("_csrf")
			if token == "" {
				token = r.Header.Get("X-CSRF-Token")
			}
			if !CompareCSRF(token, sess.CSRFToken) {
				http.Error(w, "invalid CSRF token", http.StatusForbidden)
				return
			}
		}
		next.ServeHTTP(w, r)
	})
}

func ClientIP(r *http.Request) string {
	if v := r.Header.Get("X-Forwarded-For"); v != "" {
		parts := strings.Split(v, ",")
		return strings.TrimSpace(parts[0])
	}
	if v := r.Header.Get("X-Real-IP"); v != "" {
		return v
	}
	return r.RemoteAddr
}

type ctxInt int

const ctxFlashKey ctxInt = 100

func SetFlash(ctx context.Context, key string, value any) context.Context {
	m, _ := ctx.Value(ctxFlashKey).(map[string]any)
	if m == nil {
		m = map[string]any{}
	}
	m[key] = value
	return context.WithValue(ctx, ctxFlashKey, m)
}

func TakeFlash(ctx context.Context, key string) (any, bool) {
	m, ok := ctx.Value(ctxFlashKey).(map[string]any)
	if !ok {
		return nil, false
	}
	v, found := m[key]
	delete(m, key)
	return v, found
}