package webhooks

import (
	"context"
	"database/sql"
	"net/http"
	"strconv"

	"github.com/go-chi/chi/v5"

	"github.com/anomaly/p09/internal/auth"
	"github.com/anomaly/p09/internal/httpx"
	"github.com/anomaly/p09/internal/models"
	"github.com/anomaly/p09/internal/templates"
)

func (h *Handler) Page(w http.ResponseWriter, r *http.Request, tpl *templates.Engine) {
	projectID, err := strconv.ParseInt(chi.URLParam(r, "projectID"), 10, 64)
	if err != nil {
		http.Error(w, "invalid project id", http.StatusBadRequest)
		return
	}
	u, ok := auth.UserFromContext(r.Context())
	if !ok || !canManageWebhook(r.Context(), h.Service.DB, u, projectID) {
		http.Error(w, "forbidden", http.StatusForbidden)
		return
	}
	var project models.Project
	if err := h.Service.DB.QueryRowContext(r.Context(), `SELECT id, slug, name FROM projects WHERE id=?`, projectID).Scan(&project.ID, &project.Slug, &project.Name); err != nil {
		http.Error(w, "project not found", http.StatusNotFound)
		return
	}
	hooks, err := ListProjectWebhooks(r.Context(), h.Service.DB, projectID)
	if err != nil {
		hooks = nil
	}
	deliveries, err := LookupDeliveries(r.Context(), h.Service.DB, projectID)
	if err != nil {
		deliveries = nil
	}
	tpl.Render(w, "webhooks.html", map[string]any{
		"User":       u,
		"Project":    project,
		"Webhooks":   hooks,
		"Deliveries": deliveries,
		"CSRFToken":  auth.CSRFToken(r.Context()),
	})
}

func canManageWebhookCtx(ctx context.Context, db *sql.DB, u *models.User, projectID int64) bool {
	return canManageWebhook(ctx, db, u, projectID)
}

var _ = httpx.WriteMessage