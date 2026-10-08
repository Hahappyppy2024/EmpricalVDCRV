// Package web wires the router, middleware, page handlers and API handlers.
package web

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"html/template"
	"log"
	"net/http"
	"os"
	"strings"
	"time"

	"github.com/go-chi/chi/v5"

	"issuetracker/assets"
	"issuetracker/internal/auth"
	"issuetracker/internal/config"
	"issuetracker/internal/models"
	"issuetracker/internal/service"
	"issuetracker/internal/store"
)

// App holds the shared dependencies of the application.
type App struct {
	Cfg   config.Config
	DB    *sql.DB
	Store *store.Store
	Auth  *auth.Manager
	Bus   *service.EventBus
	Hooks *service.WebhookService
	Tpl   *template.Template
}

// New creates a fully wired App.
func New(cfg config.Config, db *sql.DB) (*App, error) {
	st := store.New(db)
	bus := service.NewEventBus()
	app := &App{
		Cfg:   cfg,
		DB:    db,
		Store: st,
		Auth:  auth.NewManager(st, time.Duration(cfg.SessionTTLHours)*time.Hour),
		Bus:   bus,
		Hooks: service.NewWebhookService(st, 3),
	}
	tpl, err := parseTemplates()
	if err != nil {
		return nil, err
	}
	app.Tpl = tpl
	if err := os.MkdirAll(cfg.UploadDir, 0o755); err != nil {
		return nil, fmt.Errorf("create upload dir: %w", err)
	}
	return app, nil
}

type ctxKey int

const ctxUser ctxKey = iota

func parseTemplates() (*template.Template, error) {
	funcs := template.FuncMap{
		"formatTime": func(t time.Time) string {
			if t.IsZero() {
				return ""
			}
			return t.UTC().Format("2006-01-02 15:04")
		},
		"join":     func(sep string, items []string) string { return strings.Join(items, sep) },
		"add":      func(a, b int) int { return a + b },
		"hasLabel": hasLabel,
	}
	t, err := template.New("base.html").Funcs(funcs).ParseFS(assets.Files, "templates/*.html")
	if err != nil {
		return nil, err
	}
	return t, nil
}

// Routes builds the router with all page and API routes.
func (a *App) Routes() http.Handler {
	r := chi.NewRouter()
	r.Use(recoverer)
	r.Use(a.withUser)

	r.Handle("/static/*", http.StripPrefix("/static/", http.FileServerFS(assets.Files)))

	// Health + local webhook listener
	r.Get("/healthz", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"ok":true,"service":"p09-issue-tracking-system"}`))
	})
	r.Post("/api/issue/webhooks/listener", a.webhookListener)

	// Account access (ISSUE-01)
	r.Get("/login", a.pageLogin)
	r.Post("/login", a.handleLogin)
	r.Get("/register", a.pageRegister)
	r.Post("/register", a.handleRegister)
	r.Post("/logout", a.requireAuth(a.handleLogout))
	r.Get("/profile", a.requireAuth(a.pageProfile))

	// Dashboard / search pages
	r.Get("/", a.requireAuth(a.pageDashboard))
	r.Get("/dashboard", a.requireAuth(a.pageDashboard))
	r.Get("/search", a.requireAuth(a.pageSearch))

	// Project management (ISSUE-02)
	r.Get("/projects", a.requireAuth(a.pageProjects))
	r.Get("/projects/new", a.requireRoles(models.RoleMaintainer, models.RoleAdmin)(a.pageProjectNew))
	r.Post("/projects/new", a.requireRoles(models.RoleMaintainer, models.RoleAdmin)(a.handleProjectCreate))
	r.Get("/projects/{slug}", a.requireAuth(a.pageProject))
	r.Post("/projects/{slug}/labels", a.requireProjectManage(a.handleLabelCreate))
	r.Post("/projects/{slug}/milestones", a.requireProjectManage(a.handleMilestoneCreate))
	r.Post("/projects/{slug}/members", a.requireProjectManage(a.handleMemberAdd))
	r.Post("/projects/{slug}/members/{userID}/remove", a.requireProjectManage(a.handleMemberRemove))
	r.Post("/projects/{slug}/visibility", a.requireProjectManage(a.handleVisibilityChange))

	// Issue workflows (ISSUE-03, ISSUE-05, ISSUE-06, ISSUE-07)
	r.Get("/projects/{slug}/issues/new", a.requireAuth(a.pageIssueNew))
	r.Post("/projects/{slug}/issues/new", a.requireAuth(a.handleIssueCreate))
	r.Get("/issues/{id}", a.requireAuth(a.pageIssue))
	r.Post("/issues/{id}/comments", a.requireAuth(a.handleCommentCreate))

	// Webhooks page (ISSUE-09)
	r.Get("/projects/{slug}/webhooks", a.requireProjectAccess(a.pageWebhooks))
	r.Post("/projects/{slug}/webhooks", a.requireProjectManage(a.handleWebhookCreate))

	// Import/export page (ISSUE-10)
	r.Get("/projects/{slug}/import-export", a.requireProjectAccess(a.pageImportExport))

	// Admin operations (ISSUE-11)
	r.Get("/admin", a.requireRoles(models.RoleAdmin)(a.pageAdmin))

	// Real-time events (WebSocket)
	r.Get("/ws/projects/{projectID}", a.requireAuth(a.handleWS))

	// --- Use-case API contract routes ---
	for _, uc := range []struct {
		path string
		impl useCaseHandler
	}{
		{"account_access", a.accountAccessAPI},
		{"project_management", a.projectManagementAPI},
		{"issue_creation", a.issueCreationAPI},
		{"issue_search", a.issueSearchAPI},
		{"comments", a.commentsAPI},
		{"assignment_and_workflow", a.assignmentWorkflowAPI},
		{"attachments", a.attachmentsAPI},
		{"private_projects", a.privateProjectsAPI},
		{"webhooks", a.webhooksAPI},
		{"import_export", a.importExportAPI},
		{"admin_operations", a.adminOperationsAPI},
		{"frontend_api_integration_and_errors", a.frontendErrorsAPI},
	} {
		uc := uc
		r.Get("/api/issue/"+uc.path, a.requireAuth(func(w http.ResponseWriter, r *http.Request) {
			uc.impl(w, r, "get")
		}))
		r.Post("/api/issue/"+uc.path, a.requireAuth(func(w http.ResponseWriter, r *http.Request) {
			uc.impl(w, r, "post")
		}))
		r.Patch("/api/issue/"+uc.path+"/{id}", a.requireAuth(func(w http.ResponseWriter, r *http.Request) {
			uc.impl(w, r, "patch")
		}))
	}
	r.Delete("/api/issue/comments/{id}", a.requireAuth(func(w http.ResponseWriter, r *http.Request) {
		a.commentsAPI(w, r, "delete")
	}))

	// Attachment download with access control
	r.Get("/api/files/{id}/download", a.requireAuth(a.fileDownload))

	return r
}

// useCaseHandler dispatches GET/POST/PATCH for one use-case API route group.
type useCaseHandler func(w http.ResponseWriter, r *http.Request, method string)

func (a *App) webhookListener(w http.ResponseWriter, r *http.Request) {
	_ = r
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusNoContent)
}

// render executes a template with the shared base layout.
func (a *App) render(w http.ResponseWriter, r *http.Request, name string, data map[string]any) {
	if data == nil {
		data = map[string]any{}
	}
	data["SiteName"] = a.Store.GetSetting("site_name", "Issue Tracker")
	data["User"] = userFrom(r)
	data["Flash"] = flashFrom(r)
	data["Title"] = firstNonEmpty(data["Title"], data["SiteName"])
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	if err := a.Tpl.ExecuteTemplate(w, name, data); err != nil {
		log.Printf("render %s: %v", name, err)
	}
}

// PageData returns a fresh data map for a page render.
func (a *App) PageData(r *http.Request, title string) map[string]any {
	return map[string]any{"Title": title}
}

func userFrom(r *http.Request) *models.User {
	u, _ := r.Context().Value(ctxUser).(*models.User)
	return u
}

func flashFrom(r *http.Request) string {
	return r.URL.Query().Get("flash")
}

// writeJSON writes a deterministic JSON response.
func writeJSON(w http.ResponseWriter, status int, body any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(body)
}

// okJSON wraps data in {"ok":true,"data":...}.
func okJSON(w http.ResponseWriter, data any) {
	writeJSON(w, http.StatusOK, map[string]any{"ok": true, "data": data})
}

// errJSON writes {"ok":false,"error":{"code":...,"message":...}}.
func errJSON(w http.ResponseWriter, status int, code, message string) {
	writeJSON(w, status, map[string]any{"ok": false, "error": map[string]string{"code": code, "message": message}})
}

func firstNonEmpty(vals ...any) string {
	for _, v := range vals {
		if s, ok := v.(string); ok && s != "" {
			return s
		}
	}
	return ""
}

// recoverer converts panics into deterministic 500 responses.
func recoverer(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		defer func() {
			if rec := recover(); rec != nil {
				log.Printf("panic: %v", rec)
				errJSON(w, http.StatusInternalServerError, "internal_error", "an internal error occurred")
			}
		}()
		next.ServeHTTP(w, r)
	})
}

// withUser resolves the session cookie into the request context.
func (a *App) withUser(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if u := a.Auth.CurrentUser(r); u != nil {
			r = r.WithContext(context.WithValue(r.Context(), ctxUser, u))
		}
		next.ServeHTTP(w, r)
	})
}

func (a *App) requireAuth(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if userFrom(r) == nil {
			if strings.HasPrefix(r.URL.Path, "/api/") {
				errJSON(w, http.StatusUnauthorized, "unauthorized", "sign in required")
				return
			}
			http.Redirect(w, r, "/login", http.StatusSeeOther)
			return
		}
		next(w, r)
	}
}

func (a *App) requireRoles(roles ...string) func(http.HandlerFunc) http.HandlerFunc {
	return func(next http.HandlerFunc) http.HandlerFunc {
		return func(w http.ResponseWriter, r *http.Request) {
			u := userFrom(r)
			if u == nil {
				if strings.HasPrefix(r.URL.Path, "/api/") {
					errJSON(w, http.StatusUnauthorized, "unauthorized", "sign in required")
					return
				}
				http.Redirect(w, r, "/login", http.StatusSeeOther)
				return
			}
			for _, role := range roles {
				if u.Role == role {
					next(w, r)
					return
				}
			}
			if strings.HasPrefix(r.URL.Path, "/api/") {
				errJSON(w, http.StatusForbidden, "forbidden", "this action requires role "+strings.Join(roles, " or "))
				return
			}
			http.Error(w, "Forbidden", http.StatusForbidden)
		}
	}
}
