package handler

import (
	"encoding/json"
	"errors"
	"io"
	"net/http"
	"strconv"

	"github.com/go-chi/chi/v5"
	chimw "github.com/go-chi/chi/v5/middleware"

	"p14-agentic-platform/internal/config"
	"p14-agentic-platform/internal/middleware"
	"p14-agentic-platform/internal/service"
	"p14-agentic-platform/internal/web"
)

// Server wires routes, services and rendering together.
type Server struct {
	cfg    *config.Config
	svc    *service.Service
	render *web.Renderer
}

func New(cfg *config.Config, svc *service.Service) *Server {
	return &Server{cfg: cfg, svc: svc, render: web.NewRenderer()}
}

// Routes builds the full HTTP router.
func (s *Server) Routes() http.Handler {
	r := chi.NewRouter()
	r.Use(chimw.RequestID, chimw.Logger, chimw.Recoverer)
	r.Use(middleware.Session(s.svc, s.cfg.SessionName))

	// Deterministic local adapter for external HTTP actions (AGENT-08).
	r.Get("/internal/echo", s.echo)
	r.Post("/internal/echo", s.echo)
	r.Put("/internal/echo", s.echo)
	r.Patch("/internal/echo", s.echo)
	r.Delete("/internal/echo", s.echo)

	r.Handle("/static/*", web.Static())

	// Public auth pages.
	r.Get("/login", s.pageLogin)
	r.Get("/register", s.pageRegister)
	r.Post("/login", s.apiLogin)
	r.Post("/register", s.apiRegister)
	r.Post("/logout", s.apiLogout)

	// Public inbound webhook trigger (AGENT-07).
	r.Post("/api/agent/webhooks/{token}/trigger", s.apiWebhookTrigger)

	// Authenticated page routes.
	r.Group(func(pr chi.Router) {
		pr.Use(middleware.RequireAuthPage)
		pr.Get("/", s.pageDashboard)
		pr.Get("/account", s.pageResource(resourcePage("account_access", "Account access", "Sign-in, registration and account access history")))
		pr.Get("/workflows", s.pageResource(resourcePage("workflow_creation", "Workflow creation", "Define workflows with triggers, steps, conditions and names")))
		pr.Get("/tools", s.pageResource(resourcePage("tool_catalog", "Tool catalog", "Admins define tools; users browse allowed tools")))
		pr.Get("/runs", s.pageResource(resourcePage("task_execution", "Task execution", "Run workflows manually and inspect step-by-step results")))
		pr.Get("/schedules", s.pageResource(resourcePage("scheduled_runs", "Scheduled runs", "Configure recurring or delayed workflow runs")))
		pr.Get("/files", s.pageResource(resourcePage("workspace_files", "Workspace files", "Read and write files within a scoped workspace")))
		pr.Get("/webhooks", s.pageResource(resourcePage("webhook_triggers", "Webhook triggers", "Create inbound webhook URLs to trigger workflows")))
		pr.Get("/http-actions", s.pageResource(resourcePage("external_http_action", "External HTTP actions", "Call configured HTTP endpoints with bounded options")))
		pr.Get("/secrets", s.pageResource(resourcePage("secrets_manager", "Secrets manager", "Store masked tokens for workflow actions")))
		pr.Get("/logs", s.pageResource(resourcePage("run_logs_and_replay", "Run logs and replay", "Inspect logs, retry failed steps, and compare outputs")))
		pr.Get("/templates", s.pageResource(resourcePage("sharing_and_templates", "Sharing and templates", "Publish and reuse workflow templates")))
		pr.Get("/runs/{id}", s.pageRunDetail)
		pr.Get("/admin", s.pageAdmin)
	})

	// Authenticated JSON API.
	r.Route("/api", func(ar chi.Router) {
		ar.Group(func(api chi.Router) {
			api.Use(middleware.RequireAuth)
			api.Get("/agent/me", s.apiMe)

			api.Route("/agent/{resource}", func(rr chi.Router) {
				rr.Get("/", s.apiList)
				rr.Post("/", s.apiCreate)
				rr.Get("/users", s.apiAdminUsers)
				rr.Get("/audit", s.apiAdminAudit)
				rr.Post("/run-scheduler", s.apiRunScheduler)
				rr.Post("/users/{id}", s.apiAdminUpdateUser)
				rr.Post("/{id}/execute", s.apiExecuteHTTPAction)
				rr.Get("/{id}/download", s.apiDownloadFile)
				rr.Post("/{id}/replay", s.apiReplayRun)
				rr.Post("/{id}/publish", s.apiPublishTemplate)
				rr.Get("/{id}", s.apiGet)
				rr.Patch("/{id}", s.apiUpdate)
				rr.Delete("/{id}", s.apiDelete)
			})
		})
	})

	// Live run log streaming (WebSocket).
	r.Get("/api/agent/ws/runs/{id}", middleware.RequireAuth(http.HandlerFunc(s.wsRunLogs)).ServeHTTP)

	return r
}

// echo is the deterministic local adapter used by external HTTP actions.
func (s *Server) echo(w http.ResponseWriter, r *http.Request) {
	body, _ := io.ReadAll(io.LimitReader(r.Body, s.cfg.MaxPayloadBytes))
	var payload any
	if len(body) > 0 {
		_ = json.Unmarshal(body, &payload)
	}
	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(map[string]any{
		"ok":      true,
		"service": "p14-deterministic-echo",
		"method":  r.Method,
		"path":    r.URL.Path,
		"payload": payload,
	})
}

// --- shared helpers ---------------------------------------------------------

func (s *Server) readJSON(w http.ResponseWriter, r *http.Request) (map[string]any, bool) {
	body, err := io.ReadAll(io.LimitReader(r.Body, s.cfg.MaxPayloadBytes))
	if err != nil {
		writeErr(w, service.BadRequest("unable to read request body"))
		return nil, false
	}
	var m map[string]any
	if len(body) > 0 {
		if err := json.Unmarshal(body, &m); err != nil {
			writeErr(w, service.BadRequest("request body must be valid JSON"))
			return nil, false
		}
	}
	if m == nil {
		m = map[string]any{}
	}
	return m, true
}

func idParam(r *http.Request) (int64, bool) {
	n, err := strconv.ParseInt(chi.URLParam(r, "id"), 10, 64)
	return n, err == nil
}

func writeJSON(w http.ResponseWriter, status int, body any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(body)
}

func writeErr(w http.ResponseWriter, err error) {
	var appErr *service.AppError
	if errors.As(err, &appErr) {
		writeJSON(w, appErr.Status, map[string]any{"error": appErr.Message})
		return
	}
	writeJSON(w, http.StatusInternalServerError, map[string]any{"error": "internal error"})
}

func user(r *http.Request) any {
	return middleware.UserFrom(r)
}
