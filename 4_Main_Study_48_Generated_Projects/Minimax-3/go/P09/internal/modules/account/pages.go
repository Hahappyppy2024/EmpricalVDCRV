package account

import (
	"net/http"

	"github.com/anomaly/p09/internal/auth"
	"github.com/anomaly/p09/internal/httpx"
	"github.com/anomaly/p09/internal/templates"
)

func (h *Handler) Page(w http.ResponseWriter, r *http.Request, tpl *templates.Engine) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		http.Redirect(w, r, "/login", http.StatusSeeOther)
		return
	}
	csrf := auth.CSRFToken(r.Context())
	events, err := h.recentEvents(r.Context(), u.ID)
	if err != nil {
		events = nil
	}
	tpl.Render(w, "account.html", map[string]any{
		"User":         u,
		"CSRFToken":    csrf,
		"AccessEvents": events,
	})
}

func (h *Handler) SessionsPage(w http.ResponseWriter, r *http.Request, tpl *templates.Engine) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		http.Redirect(w, r, "/login", http.StatusSeeOther)
		return
	}
	sess, _ := auth.SessionFromContext(r.Context())
	sessions, err := h.listSessions(r.Context(), u.ID)
	if err != nil {
		sessions = nil
	}
	tpl.Render(w, "account_sessions.html", map[string]any{
		"User":             u,
		"Sessions":         sessions,
		"CSRFToken":        auth.CSRFToken(r.Context()),
		"CurrentSessionID": sess.ID,
	})
}

func (h *Handler) LoginPage(w http.ResponseWriter, r *http.Request, tpl *templates.Engine) {
	tpl.Render(w, "login.html", map[string]any{
		"Next":          r.URL.Query().Get("next"),
		"SeedAccounts":  defaultSeedAccounts,
		"CSRFToken":     guestCSRF(),
	})
}

func (h *Handler) RegisterPage(w http.ResponseWriter, r *http.Request, tpl *templates.Engine) {
	tpl.Render(w, "register.html", map[string]any{
		"CSRFToken": guestCSRF(),
	})
}

func guestCSRF() string {
	return httpx.RandomToken(16)
}