package handlers

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"html/template"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"github.com/anomalyco/p14-workflow-automation/internal/auth"
	"github.com/anomalyco/p14-workflow-automation/internal/config"
	"github.com/anomalyco/p14-workflow-automation/internal/httpx"
	"github.com/anomalyco/p14-workflow-automation/internal/middleware"
	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/anomalyco/p14-workflow-automation/internal/render"
	"github.com/anomalyco/p14-workflow-automation/internal/repo"
	"github.com/anomalyco/p14-workflow-automation/internal/runner"
	"github.com/go-chi/chi/v5"
)

// Deps groups the dependencies shared by all handlers.
type Deps struct {
	Cfg       *config.Config
	DB        *sql.DB
	Auth      *auth.Service
	Users     *repo.Users
	AccessLog *repo.AccessLog
	Workflows *repo.Workflows
	Tools     *repo.Tools
	Runs      *repo.Runs
	Schedules *repo.Schedules
	Files     *repo.Files
	Webhooks  *repo.Webhooks
	HTTPA     *repo.HTTPActions
	Secrets   *repo.Secrets
	Logs      *repo.RunLogs
	Templates *repo.Templates
	Admin     *repo.Admin
	Audit     *repo.Audit
	Render    *render.Renderer
	Runner    *runner.Runner
	Bus       *runner.Bus
}

type ctxDataKey struct{}

func baseData(r *http.Request, title string, user *models.User) map[string]any {
	data := map[string]any{
		"Title": title,
		"user":  user,
		"now":   time.Now(),
	}
	return data
}

func renderPage(w http.ResponseWriter, r *http.Request, deps *Deps, name string, data map[string]any) {
	if data == nil {
		data = map[string]any{}
	}
	if _, ok := data["user"]; !ok {
		data["user"] = middleware.User(r.Context())
	}
	if _, ok := data["Title"]; !ok {
		data["Title"] = strings.Title(strings.ReplaceAll(name, "_", " "))
	}
	deps.Render.Render(w, name, data)
}

// ---- AGENT-01: account access ----

func (d *Deps) AccountHome(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	if user == nil {
		http.Redirect(w, r, "/account_access/sign_in", http.StatusFound)
		return
	}
	access, _ := d.AccessLog.Recent(r.Context(), user.ID, 50)
	renderPage(w, r, d, "account", map[string]any{
		"access_log": access,
		"Title":      "Account",
	})
}

func (d *Deps) AccountSignInForm(w http.ResponseWriter, r *http.Request) {
	if middleware.User(r.Context()) != nil {
		http.Redirect(w, r, "/dashboard", http.StatusFound)
		return
	}
	renderPage(w, r, d, "account_sign_in", map[string]any{})
}

func (d *Deps) AccountRegisterForm(w http.ResponseWriter, r *http.Request) {
	if middleware.User(r.Context()) != nil {
		http.Redirect(w, r, "/dashboard", http.StatusFound)
		return
	}
	renderPage(w, r, d, "account_register", map[string]any{})
}

func (d *Deps) AccountSignInPost(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		httpx.WriteError(w, http.StatusBadRequest, "invalid form", "")
		return
	}
	email := strings.ToLower(strings.TrimSpace(r.FormValue("email")))
	password := r.FormValue("password")
	user, err := d.Users.ByEmail(r.Context(), email)
	if err != nil || user == nil || !auth.CheckPassword(user.PasswordHash, password) {
		_ = d.AccessLog.Append(r.Context(), "", "sign_in", "error", "bad credentials "+email)
		if isHTML(r) {
			renderPage(w, r, d, "account_sign_in", map[string]any{"error": "invalid credentials", "email": email})
			return
		}
		httpx.WriteError(w, http.StatusUnauthorized, "invalid credentials", "")
		return
	}
	if user.Status != "active" {
		_ = d.AccessLog.Append(r.Context(), user.ID, "sign_in", "error", "account disabled")
		httpx.WriteError(w, http.StatusForbidden, "account disabled", "")
		return
	}
	token, _, err := d.Auth.CreateSession(r.Context(), user.ID)
	if err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, "session error", "")
		return
	}
	http.SetCookie(w, &http.Cookie{
		Name:     "p14_session",
		Value:    token,
		Path:     "/",
		HttpOnly: true,
		SameSite: http.SameSiteLaxMode,
		Expires:  time.Now().Add(d.Cfg.SessionLifetime),
	})
	_ = d.AccessLog.Append(r.Context(), user.ID, "sign_in", "ok", "session created")
	_ = d.Audit.Append(r.Context(), user.ID, "sign_in", user.ID, "")
	if isHTML(r) {
		http.Redirect(w, r, "/dashboard", http.StatusFound)
		return
	}
	httpx.WriteOK(w, map[string]any{"redirect": "/dashboard"})
}

func (d *Deps) AccountRegisterPost(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		httpx.WriteError(w, http.StatusBadRequest, "invalid form", "")
		return
	}
	email := strings.ToLower(strings.TrimSpace(r.FormValue("email")))
	name := strings.TrimSpace(r.FormValue("display_name"))
	password := r.FormValue("password")
	data := map[string]any{"email": email, "display_name": name}
	if email == "" || name == "" || len(password) < 6 {
		data["error"] = "all fields are required (password ≥ 6 chars)"
		if isHTML(r) {
			renderPage(w, r, d, "account_register", data)
			return
		}
		httpx.WriteError(w, http.StatusBadRequest, "all fields are required (password ≥ 6 chars)", "")
		return
	}
	if existing, _ := d.Users.ByEmail(r.Context(), email); existing != nil {
		data["error"] = "email already registered"
		if isHTML(r) {
			renderPage(w, r, d, "account_register", data)
			return
		}
		httpx.WriteError(w, http.StatusConflict, "email already registered", "")
		return
	}
	hash, err := auth.HashPassword(password)
	if err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, "hash error", "")
		return
	}
	user, err := d.Users.Create(r.Context(), email, name, hash, "user")
	if err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, "create error", "")
		return
	}
	token, _, err := d.Auth.CreateSession(r.Context(), user.ID)
	if err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, "session error", "")
		return
	}
	http.SetCookie(w, &http.Cookie{
		Name:     "p14_session", Value: token, Path: "/", HttpOnly: true,
		SameSite: http.SameSiteLaxMode, Expires: time.Now().Add(d.Cfg.SessionLifetime),
	})
	_ = d.AccessLog.Append(r.Context(), user.ID, "register", "ok", "registered")
	_ = d.Audit.Append(r.Context(), user.ID, "register", user.ID, email)
	if isHTML(r) {
		http.Redirect(w, r, "/dashboard", http.StatusFound)
		return
	}
	httpx.WriteOK(w, map[string]any{"redirect": "/dashboard"})
}

func (d *Deps) AccountSignOutPost(w http.ResponseWriter, r *http.Request) {
	if cookie, err := r.Cookie("p14_session"); err == nil {
		_ = d.Auth.DestroyByToken(r.Context(), cookie.Value)
	}
	http.SetCookie(w, &http.Cookie{
		Name: "p14_session", Value: "", Path: "/", MaxAge: -1, HttpOnly: true,
	})
	if user := middleware.User(r.Context()); user != nil {
		_ = d.AccessLog.Append(r.Context(), user.ID, "sign_out", "ok", "")
		_ = d.Audit.Append(r.Context(), user.ID, "sign_out", user.ID, "")
	}
	if isHTML(r) {
		http.Redirect(w, r, "/", http.StatusFound)
		return
	}
	httpx.WriteOK(w, nil)
}

func (d *Deps) AccountResetPost(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		httpx.WriteError(w, http.StatusBadRequest, "invalid form", "")
		return
	}
	email := strings.ToLower(strings.TrimSpace(r.FormValue("email")))
	user, _ := d.Users.ByEmail(r.Context(), email)
	if user == nil {
		httpx.WriteError(w, http.StatusBadRequest, "unknown email", "email")
		return
	}
	_ = d.AccessLog.Append(r.Context(), user.ID, "reset", "ok", "reset requested")
	httpx.WriteOK(w, map[string]any{"reset": "deterministic offline; contact admin"})
}

func isHTML(r *http.Request) bool {
	return strings.Contains(r.Header.Get("Accept"), "text/html") || r.Header.Get("X-Requested-With") == "fetch" && false
}

// ---- AGENT-02: workflow creation ----

func (d *Deps) WorkflowsPage(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	wfs, _ := d.Workflows.ListByUser(r.Context(), user.ID)
	form := map[string]string{"name": "", "description": "", "trigger": "manual", "cron": ""}
	createOpen := false
	if r.Method == http.MethodPost {
		if err := r.ParseForm(); err == nil {
			form["name"] = r.FormValue("name")
			form["description"] = r.FormValue("description")
			form["trigger"] = r.FormValue("trigger_kind")
			form["cron"] = r.FormValue("cron_expr")
			steps := r.FormValue("steps_json")
			if form["name"] == "" {
				renderPage(w, r, d, "workflow_creation", map[string]any{"workflows": wfs, "error": "name is required", "form": form, "create_open": true})
				return
			}
			if _, err := d.Workflows.ByName(r.Context(), user.ID, form["name"]); err == nil {
				// no existing
			} else if existing, _ := d.Workflows.ByName(r.Context(), user.ID, form["name"]); existing != nil {
				renderPage(w, r, d, "workflow_creation", map[string]any{"workflows": wfs, "error": "workflow name already in use", "form": form, "create_open": true})
				return
			}
			if _, err := d.Workflows.Create(r.Context(), user.ID, form["name"], form["description"], form["trigger"], form["cron"], []byte(steps)); err != nil {
				renderPage(w, r, d, "workflow_creation", map[string]any{"workflows": wfs, "error": err.Error(), "form": form, "create_open": true})
				return
			}
			_ = d.Audit.Append(r.Context(), user.ID, "workflow.create", form["name"], form["trigger"])
			http.Redirect(w, r, "/workflow_creation", http.StatusFound)
			return
		}
	}
	renderPage(w, r, d, "workflow_creation", map[string]any{"workflows": wfs, "form": form, "create_open": createOpen})
}

func (d *Deps) WorkflowRunPost(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	id := chi.URLParam(r, "id")
	wf, err := d.Workflows.ByID(r.Context(), id)
	if err != nil || wf == nil {
		httpx.WriteError(w, http.StatusNotFound, "workflow not found", "")
		return
	}
	if wf.UserID != user.ID && user.Role != "admin" {
		httpx.WriteError(w, http.StatusForbidden, "not your workflow", "")
		return
	}
	run, err := d.Runs.Create(r.Context(), user.ID, wf.ID, "manual")
	if err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, "queue error", "")
		return
	}
	go d.Runner.Execute(context.Background(), run.ID, user.ID, wf.ID)
	_ = d.Audit.Append(r.Context(), user.ID, "workflow.run", wf.ID, run.ID)
	if isHTML(r) {
		http.Redirect(w, r, "/task_execution?focus="+run.ID, http.StatusFound)
		return
	}
	httpx.WriteOK(w, map[string]any{"run_id": run.ID})
}

func (d *Deps) WorkflowArchivePost(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	id := chi.URLParam(r, "id")
	wf, err := d.Workflows.ByID(r.Context(), id)
	if err != nil || wf == nil {
		httpx.WriteError(w, http.StatusNotFound, "workflow not found", "")
		return
	}
	if wf.UserID != user.ID && user.Role != "admin" {
		httpx.WriteError(w, http.StatusForbidden, "not your workflow", "")
		return
	}
	stepsRaw, _ := json.Marshal(wf.Steps)
	if err := d.Workflows.Update(r.Context(), wf.ID, wf.Name, wf.Description, wf.TriggerKind, wf.CronExpr, "archived", stepsRaw); err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
		return
	}
	_ = d.Audit.Append(r.Context(), user.ID, "workflow.archive", wf.ID, "")
	http.Redirect(w, r, "/workflow_creation", http.StatusFound)
}

// ---- AGENT-03: tool catalog ----

func (d *Deps) ToolCatalogPage(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	q := r.URL.Query().Get("q")
	enabledOnly := r.URL.Query().Get("enabled_only") == "1"
	tools, _ := d.Tools.List(r.Context(), q, enabledOnly)
	form := map[string]string{"name": "", "description": ""}
	createOpen := false
	if r.Method == http.MethodPost {
		if err := r.ParseForm(); err == nil {
			form["name"] = r.FormValue("name")
			form["description"] = r.FormValue("description")
			kind := r.FormValue("kind")
			defJSON := r.FormValue("default_json")
			enabled := r.FormValue("enabled") == "1"
			if form["name"] == "" {
				renderPage(w, r, d, "tool_catalog", map[string]any{
					"tools": tools, "q": q, "enabled_only": enabledOnly,
					"form": form, "error": "name required", "create_open": true,
				})
				return
			}
			if existing, _ := d.Tools.ByName(r.Context(), form["name"]); existing != nil {
				renderPage(w, r, d, "tool_catalog", map[string]any{
					"tools": tools, "q": q, "enabled_only": enabledOnly,
					"form": form, "error": "tool already exists", "create_open": true,
				})
				return
			}
			if _, err := d.Tools.Create(r.Context(), form["name"], kind, form["description"], defJSON, enabled); err != nil {
				renderPage(w, r, d, "tool_catalog", map[string]any{
					"tools": tools, "q": q, "enabled_only": enabledOnly,
					"form": form, "error": err.Error(), "create_open": true,
				})
				return
			}
			_ = d.Audit.Append(r.Context(), user.ID, "tool.create", form["name"], kind)
			http.Redirect(w, r, "/tool_catalog", http.StatusFound)
			return
		}
	}
	// add a DefaultStr field for the template
	type viewTool struct {
		ID, Name, Kind, Description, DefaultStr string
		Enabled                                bool
	}
	views := make([]viewTool, 0, len(tools))
	for _, t := range tools {
		views = append(views, viewTool{
			ID:          t.ID,
			Name:        t.Name,
			Kind:        t.Kind,
			Description: t.Description,
			DefaultStr:  string(mustJSON(t.Default)),
			Enabled:     t.Enabled,
		})
	}
	renderPage(w, r, d, "tool_catalog", map[string]any{
		"tools": views, "q": q, "enabled_only": enabledOnly, "form": form, "create_open": createOpen,
	})
}

func (d *Deps) ToolTogglePost(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")
	t, err := d.Tools.ByID(r.Context(), id)
	if err != nil || t == nil {
		httpx.WriteError(w, http.StatusNotFound, "tool not found", "")
		return
	}
	if err := d.Tools.Update(r.Context(), id, t.Name, t.Kind, t.Description, string(mustJSON(t.Default)), !t.Enabled); err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
		return
	}
	user := middleware.User(r.Context())
	_ = d.Audit.Append(r.Context(), user.ID, "tool.toggle", id, fmt.Sprintf("enabled=%v", !t.Enabled))
	http.Redirect(w, r, "/tool_catalog", http.StatusFound)
}

func mustJSON(v map[string]string) []byte {
	b, _ := json.Marshal(v)
	return b
}

// ---- AGENT-04: task execution ----

func (d *Deps) TaskExecutionPage(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	if r.Method == http.MethodPost {
		wfID := r.FormValue("workflow_id")
		wf, err := d.Workflows.ByID(r.Context(), wfID)
		if err != nil || wf == nil {
			httpx.WriteError(w, http.StatusNotFound, "workflow not found", "")
			return
		}
		if wf.UserID != user.ID && user.Role != "admin" {
			httpx.WriteError(w, http.StatusForbidden, "not your workflow", "")
			return
		}
		run, err := d.Runs.Create(r.Context(), user.ID, wf.ID, "manual")
		if err != nil {
			httpx.WriteError(w, http.StatusInternalServerError, "queue error", "")
			return
		}
		go d.Runner.Execute(context.Background(), run.ID, user.ID, wf.ID)
		http.Redirect(w, r, "/task_execution?focus="+run.ID, http.StatusFound)
		return
	}
	runs, _ := d.Runs.ListByUser(r.Context(), user.ID)
	if user.Role == "admin" {
		runs, _ = d.Runs.ListAll(r.Context())
	}
	wfs, _ := d.Workflows.ListByUser(r.Context(), user.ID)
	data := map[string]any{"runs": runs, "workflows": wfs}
	if focus := r.URL.Query().Get("focus"); focus != "" {
		if run, err := d.Runs.ByID(r.Context(), focus); err == nil && run != nil {
			steps, _ := d.Runs.Steps(r.Context(), run.ID)
			run.Steps = steps
			data["focus_run"] = run
		}
	}
	renderPage(w, r, d, "task_execution", data)
}

// ---- AGENT-05: scheduled runs ----

func (d *Deps) SchedulesPage(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	if r.Method == http.MethodPost {
		wfID := r.FormValue("workflow_id")
		cron := r.FormValue("cron_expr")
		next := r.FormValue("next_run_at")
		if !d.Schedules.CronValid(cron) {
			httpx.WriteError(w, http.StatusBadRequest, "cron must have 5 fields", "cron_expr")
			return
		}
		nt, err := parseLocalTime(next)
		if err != nil {
			httpx.WriteError(w, http.StatusBadRequest, "invalid next_run_at", "next_run_at")
			return
		}
		wf, err := d.Workflows.ByID(r.Context(), wfID)
		if err != nil || wf == nil {
			httpx.WriteError(w, http.StatusNotFound, "workflow not found", "")
			return
		}
		if wf.UserID != user.ID && user.Role != "admin" {
			httpx.WriteError(w, http.StatusForbidden, "not your workflow", "")
			return
		}
		if _, err := d.Schedules.Create(r.Context(), user.ID, wf.ID, cron, nt, true); err != nil {
			httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
			return
		}
		_ = d.Audit.Append(r.Context(), user.ID, "schedule.create", wf.ID, cron)
		http.Redirect(w, r, "/scheduled_runs", http.StatusFound)
		return
	}
	schedules, _ := d.Schedules.ListByUser(r.Context(), user.ID)
	if user.Role == "admin" {
		schedules, _ = d.Schedules.ListAll(r.Context())
	}
	wfs, _ := d.Workflows.ListByUser(r.Context(), user.ID)
	form := map[string]string{"cron": "0 9 * * *", "next_run_at": time.Now().Add(time.Hour).Format("2006-01-02T15:04")}
	renderPage(w, r, d, "scheduled_runs", map[string]any{
		"schedules": schedules, "workflows": wfs, "form": form, "create_open": false,
	})
}

func parseLocalTime(s string) (time.Time, error) {
	if s == "" {
		return time.Now(), nil
	}
	t, err := time.ParseInLocation("2006-01-02T15:04", s, time.Local)
	if err != nil {
		return time.Time{}, err
	}
	return t, nil
}

func (d *Deps) ScheduleTogglePost(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	id := chi.URLParam(r, "id")
	sched, err := d.Schedules.ByID(r.Context(), id)
	if err != nil || sched == nil {
		httpx.WriteError(w, http.StatusNotFound, "schedule not found", "")
		return
	}
	if sched.UserID != user.ID && user.Role != "admin" {
		httpx.WriteError(w, http.StatusForbidden, "not your schedule", "")
		return
	}
	if err := d.Schedules.Update(r.Context(), id, sched.CronExpr, sched.NextRunAt, !sched.Enabled); err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
		return
	}
	http.Redirect(w, r, "/scheduled_runs", http.StatusFound)
}

func (d *Deps) ScheduleDeletePost(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	id := chi.URLParam(r, "id")
	sched, err := d.Schedules.ByID(r.Context(), id)
	if err != nil || sched == nil {
		httpx.WriteError(w, http.StatusNotFound, "schedule not found", "")
		return
	}
	if sched.UserID != user.ID && user.Role != "admin" {
		httpx.WriteError(w, http.StatusForbidden, "not your schedule", "")
		return
	}
	_ = d.Schedules.Delete(r.Context(), id)
	http.Redirect(w, r, "/scheduled_runs", http.StatusFound)
}

// ---- AGENT-06: workspace files ----

func (d *Deps) WorkspacePage(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	if r.Method == http.MethodPost && r.URL.Path == "/workspace_files" {
		if err := r.ParseForm(); err != nil {
			httpx.WriteError(w, http.StatusBadRequest, "invalid form", "")
			return
		}
		path := strings.TrimSpace(r.FormValue("path"))
		content := r.FormValue("content")
		if path == "" {
			renderWorkspace(w, r, d, "path required", "", path, content)
			return
		}
		if existing, _ := d.Files.ByPath(r.Context(), user.ID, path); existing != nil {
			if existing.UserID != user.ID {
				renderWorkspace(w, r, d, "access denied", "", path, content)
				return
			}
			if err := d.Files.Update(r.Context(), existing.ID, content); err != nil {
				renderWorkspace(w, r, d, err.Error(), "", path, content)
				return
			}
		} else {
			if _, err := d.Files.Create(r.Context(), user.ID, "", path, content, "text/plain"); err != nil {
				renderWorkspace(w, r, d, err.Error(), "", path, content)
				return
			}
		}
		_ = d.Audit.Append(r.Context(), user.ID, "workspace.save", path, fmt.Sprintf("size=%d", len(content)))
		http.Redirect(w, r, "/workspace_files", http.StatusFound)
		return
	}
	renderWorkspace(w, r, d, "", "", "", "")
}

func renderWorkspace(w http.ResponseWriter, r *http.Request, d *Deps, errMsg, viewID, path, content string) {
	user := middleware.User(r.Context())
	files, _ := d.Files.ListByUser(r.Context(), user.ID)
	stored, _ := d.Files.Stored(r.Context(), user.ID)
	data := map[string]any{
		"files":  files,
		"stored": stored,
		"form":   map[string]string{"path": path, "content": content},
	}
	if errMsg != "" {
		data["error"] = errMsg
		data["create_open"] = true
	}
	if viewID != "" {
		if f, _ := d.Files.ByID(r.Context(), viewID); f != nil {
			data["view"] = f
		}
	}
	if viewParam := r.URL.Query().Get("id"); viewParam != "" && viewID == "" {
		if f, _ := d.Files.ByID(r.Context(), viewParam); f != nil && f.UserID == user.ID {
			data["view"] = f
		}
	}
	renderPage(w, r, d, "workspace_files", data)
}

func (d *Deps) WorkspaceUploadPost(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	if err := r.ParseMultipartForm(d.Cfg.MaxUploadBytes); err != nil {
		httpx.WriteError(w, http.StatusBadRequest, err.Error(), "")
		return
	}
	file, header, err := r.FormFile("file")
	if err != nil {
		httpx.WriteError(w, http.StatusBadRequest, "missing file", "")
		return
	}
	defer file.Close()
	if header.Size > d.Cfg.MaxUploadBytes {
		httpx.WriteError(w, http.StatusRequestEntityTooLarge, "file too large", "")
		return
	}
	mime := header.Header.Get("Content-Type")
	if _, ok := d.Cfg.AllowedMimeTypes[mime]; !ok && mime != "" {
		httpx.WriteError(w, http.StatusUnsupportedMediaType, "unsupported mime", "")
		return
	}
	if mime == "" {
		mime = "application/octet-stream"
	}
	dir := filepath.Join(d.Cfg.UploadRoot, user.ID)
	if err := os.MkdirAll(dir, 0o755); err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
		return
	}
	stored := filepath.Join(dir, fmt.Sprintf("%d-%s", time.Now().UnixNano(), filepath.Base(header.Filename)))
	out, err := os.Create(stored)
	if err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
		return
	}
	defer out.Close()
	n, err := io.Copy(out, file)
	if err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
		return
	}
	if _, err := d.Files.StoreUpload(r.Context(), user.ID, "", header.Filename, stored, mime, int(n)); err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
		return
	}
	_ = d.Audit.Append(r.Context(), user.ID, "workspace.upload", header.Filename, mime)
	http.Redirect(w, r, "/workspace_files", http.StatusFound)
}

func (d *Deps) WorkspaceViewGet(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	id := r.URL.Query().Get("id")
	f, _ := d.Files.ByID(r.Context(), id)
	if f == nil || (f.UserID != user.ID && user.Role != "admin") {
		httpx.WriteError(w, http.StatusNotFound, "not found", "")
		return
	}
	renderWorkspace(w, r, d, "", id, "", "")
}

func (d *Deps) WorkspaceDeletePost(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	id := chi.URLParam(r, "id")
	if err := d.Files.Delete(r.Context(), id, user.ID); err != nil {
		httpx.WriteError(w, http.StatusNotFound, err.Error(), "")
		return
	}
	_ = d.Audit.Append(r.Context(), user.ID, "workspace.delete", id, "")
	http.Redirect(w, r, "/workspace_files", http.StatusFound)
}

// ---- AGENT-07: webhook triggers ----

func (d *Deps) WebhooksPage(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	if r.Method == http.MethodPost {
		if err := r.ParseForm(); err != nil {
			httpx.WriteError(w, http.StatusBadRequest, "invalid form", "")
			return
		}
		wfID := r.FormValue("workflow_id")
		desc := r.FormValue("description")
		wf, err := d.Workflows.ByID(r.Context(), wfID)
		if err != nil || wf == nil {
			httpx.WriteError(w, http.StatusNotFound, "workflow not found", "")
			return
		}
		if wf.UserID != user.ID && user.Role != "admin" {
			httpx.WriteError(w, http.StatusForbidden, "not your workflow", "")
			return
		}
		tok, _ := auth.RandomToken(18)
		if _, err := d.Webhooks.Create(r.Context(), user.ID, wf.ID, tok, desc); err != nil {
			httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
			return
		}
		_ = d.Audit.Append(r.Context(), user.ID, "webhook.create", wf.ID, tok)
		http.Redirect(w, r, "/webhook_triggers", http.StatusFound)
		return
	}
	hooks, _ := d.Webhooks.ListByUser(r.Context(), user.ID)
	wfs, _ := d.Workflows.ListByUser(r.Context(), user.ID)
	renderPage(w, r, d, "webhook_triggers", map[string]any{
		"webhooks": hooks, "workflows": wfs, "form": map[string]string{"description": ""}, "create_open": false,
	})
}

func (d *Deps) WebhookRevokePost(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	id := chi.URLParam(r, "id")
	if err := d.Webhooks.Revoke(r.Context(), id, user.ID); err != nil {
		httpx.WriteError(w, http.StatusNotFound, err.Error(), "")
		return
	}
	_ = d.Audit.Append(r.Context(), user.ID, "webhook.revoke", id, "")
	http.Redirect(w, r, "/webhook_triggers", http.StatusFound)
}

func (d *Deps) WebhookInvokePost(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	if err := r.ParseForm(); err != nil {
		httpx.WriteError(w, http.StatusBadRequest, "invalid form", "")
		return
	}
	token := r.FormValue("token")
	hook, _ := d.Webhooks.ByToken(r.Context(), token)
	if hook == nil {
		httpx.WriteError(w, http.StatusNotFound, "token not found", "")
		return
	}
	if hook.Revoked {
		httpx.WriteError(w, http.StatusGone, "webhook revoked", "")
		return
	}
	if hook.UserID != user.ID && user.Role != "admin" {
		httpx.WriteError(w, http.StatusForbidden, "not your webhook", "")
		return
	}
	wf, _ := d.Workflows.ByID(r.Context(), hook.WorkflowID)
	if wf == nil {
		httpx.WriteError(w, http.StatusNotFound, "workflow missing", "")
		return
	}
	run, err := d.Runs.Create(r.Context(), hook.UserID, wf.ID, "webhook")
	if err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, "queue error", "")
		return
	}
	go d.Runner.Execute(context.Background(), run.ID, hook.UserID, wf.ID)
	_ = d.Audit.Append(r.Context(), user.ID, "webhook.invoke", hook.ID, run.ID)
	httpx.WriteOK(w, map[string]any{"run_id": run.ID})
}

func (d *Deps) WebhookPublicInvoke(w http.ResponseWriter, r *http.Request) {
	token := chi.URLParam(r, "token")
	hook, _ := d.Webhooks.ByToken(r.Context(), token)
	if hook == nil || hook.Revoked {
		httpx.WriteError(w, http.StatusNotFound, "token not found", "")
		return
	}
	wf, _ := d.Workflows.ByID(r.Context(), hook.WorkflowID)
	if wf == nil {
		httpx.WriteError(w, http.StatusNotFound, "workflow missing", "")
		return
	}
	run, err := d.Runs.Create(r.Context(), hook.UserID, wf.ID, "webhook")
	if err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, "queue error", "")
		return
	}
	go d.Runner.Execute(context.Background(), run.ID, hook.UserID, wf.ID)
	_ = d.Audit.Append(r.Context(), "", "webhook.public", hook.ID, run.ID)
	httpx.WriteOK(w, map[string]any{"run_id": run.ID})
}

// ---- AGENT-08: external HTTP action ----

func (d *Deps) HTTPPage(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	if r.Method == http.MethodPost {
		if err := r.ParseForm(); err != nil {
			httpx.WriteError(w, http.StatusBadRequest, "invalid form", "")
			return
		}
		url := r.FormValue("url")
		method := strings.ToUpper(r.FormValue("method"))
		payload := r.FormValue("payload")
		if method == "" {
			method = "GET"
		}
		if !isLoopback(url) {
			renderHTTP(w, r, d, "only loopback URLs are allowed", url, method, payload)
			return
		}
		var body io.Reader
		if payload != "" && method != "GET" && method != "HEAD" {
			body = strings.NewReader(payload)
		}
		req, err := http.NewRequestWithContext(r.Context(), method, url, body)
		if err != nil {
			renderHTTP(w, r, d, err.Error(), url, method, payload)
			return
		}
		if body != nil {
			req.Header.Set("Content-Type", "application/json")
		}
		client := &http.Client{Timeout: d.Cfg.HTTPFetchTimeout}
		resp, err := client.Do(req)
		statusCode := 0
		respBody := ""
		errMsg := ""
		if err != nil {
			errMsg = err.Error()
		} else {
			defer resp.Body.Close()
			b, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
			statusCode = resp.StatusCode
			respBody = string(b)
		}
		if _, err := d.HTTPA.Record(r.Context(), user.ID, "", url, method, payload, statusCode, respBody, errMsg); err != nil {
			httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
			return
		}
		_ = d.Audit.Append(r.Context(), user.ID, "http.action", url, method)
		http.Redirect(w, r, "/external_http_action", http.StatusFound)
		return
	}
	renderHTTP(w, r, d, "", "", "GET", "")
}

func renderHTTP(w http.ResponseWriter, r *http.Request, d *Deps, errMsg, url, method, payload string) {
	user := middleware.User(r.Context())
	calls, _ := d.HTTPA.ListByUser(r.Context(), user.ID)
	if user.Role == "admin" {
		calls, _ = d.HTTPA.ListAll(r.Context())
	}
	data := map[string]any{
		"calls": calls,
		"form":  map[string]string{"url": url, "method": method, "payload": payload},
	}
	if errMsg != "" {
		data["error"] = errMsg
	}
	renderPage(w, r, d, "external_http_action", data)
}

func isLoopback(url string) bool {
	if !strings.HasPrefix(url, "http://") && !strings.HasPrefix(url, "https://") {
		return false
	}
	if strings.HasPrefix(url, "https://") {
		return false
	}
	if !strings.Contains(url, "://localhost") && !strings.Contains(url, "://127.0.0.1") && !strings.Contains(url, "://[::1]") {
		return false
	}
	return true
}

// ---- AGENT-09: secrets manager ----

func (d *Deps) SecretsPage(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	if r.Method == http.MethodPost {
		if err := r.ParseForm(); err != nil {
			httpx.WriteError(w, http.StatusBadRequest, "invalid form", "")
			return
		}
		name := strings.TrimSpace(r.FormValue("name"))
		val := r.FormValue("value")
		if name == "" || val == "" {
			renderSecrets(w, r, d, "name and value required", name)
			return
		}
		if existing, _ := d.Secrets.ByName(r.Context(), user.ID, name); existing != nil {
			renderSecrets(w, r, d, "secret with that name already exists", name)
			return
		}
		cipher, err := d.Auth.Encrypt(val)
		if err != nil {
			httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
			return
		}
		if _, err := d.Secrets.Create(r.Context(), user.ID, name, auth.MaskSecret(val), cipher); err != nil {
			httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
			return
		}
		_ = d.Audit.Append(r.Context(), user.ID, "secret.create", name, "")
		http.Redirect(w, r, "/secrets_manager", http.StatusFound)
		return
	}
	renderSecrets(w, r, d, "", "")
}

func renderSecrets(w http.ResponseWriter, r *http.Request, d *Deps, errMsg, name string) {
	user := middleware.User(r.Context())
	secrets, _ := d.Secrets.ListByUser(r.Context(), user.ID)
	if user.Role == "admin" {
		secrets, _ = d.Secrets.ListAll(r.Context())
	}
	data := map[string]any{
		"secrets": secrets,
		"form":    map[string]string{"name": name},
	}
	if errMsg != "" {
		data["error"] = errMsg
		data["create_open"] = true
	}
	renderPage(w, r, d, "secrets_manager", data)
}

func (d *Deps) SecretRevokePost(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	id := chi.URLParam(r, "id")
	if err := d.Secrets.Revoke(r.Context(), id, user.ID); err != nil {
		httpx.WriteError(w, http.StatusNotFound, err.Error(), "")
		return
	}
	_ = d.Audit.Append(r.Context(), user.ID, "secret.revoke", id, "")
	http.Redirect(w, r, "/secrets_manager", http.StatusFound)
}

// ---- AGENT-10: run logs and replay ----

func (d *Deps) RunLogsPage(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	q := r.URL.Query()
	filter := q.Get("run_id")
	level := q.Get("level")
	txt := q.Get("q")
	logs, _ := d.Logs.Filter(r.Context(), user.ID, filter, level, txt, user.Role == "admin")
	if r.Method == http.MethodPost && r.URL.Path == "/run_logs_and_replay/replay" {
		if err := r.ParseForm(); err != nil {
			httpx.WriteError(w, http.StatusBadRequest, "invalid form", "")
			return
		}
		runID := r.FormValue("run_id")
		run, _ := d.Runs.ByID(r.Context(), runID)
		if run == nil || (run.UserID != user.ID && user.Role != "admin") {
			httpx.WriteError(w, http.StatusNotFound, "run not found", "")
			return
		}
		steps, _ := d.Runs.Steps(r.Context(), run.ID)
		for _, st := range steps {
			_, _ = d.Logs.Append(r.Context(), user.ID, run.ID, "info", "replay: "+st.Name+" -> "+st.Status)
		}
		_, _ = d.Logs.Append(r.Context(), user.ID, run.ID, "info", "replay complete")
		_ = d.Audit.Append(r.Context(), user.ID, "logs.replay", run.ID, "")
		http.Redirect(w, r, "/run_logs_and_replay?run_id="+run.ID, http.StatusFound)
		return
	}
	renderPage(w, r, d, "run_logs_and_replay", map[string]any{
		"logs": logs,
		"q":    map[string]string{"run_id": filter, "level": level, "text": txt},
	})
}

// ---- AGENT-11: sharing and templates ----

func (d *Deps) TemplatesPage(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	if r.Method == http.MethodPost {
		if err := r.ParseForm(); err != nil {
			httpx.WriteError(w, http.StatusBadRequest, "invalid form", "")
			return
		}
		name := strings.TrimSpace(r.FormValue("name"))
		desc := r.FormValue("description")
		wfID := r.FormValue("workflow_id")
		visibility := r.FormValue("visibility")
		body := r.FormValue("body_json")
		if visibility == "" {
			visibility = "private"
		}
		if name == "" {
			renderTemplates(w, r, d, "name required", name, desc, visibility)
			return
		}
		if _, err := d.Templates.Create(r.Context(), user.ID, wfID, name, desc, body, visibility); err != nil {
			renderTemplates(w, r, d, err.Error(), name, desc, visibility)
			return
		}
		_ = d.Audit.Append(r.Context(), user.ID, "template.create", name, visibility)
		http.Redirect(w, r, "/sharing_and_templates", http.StatusFound)
		return
	}
	renderTemplates(w, r, d, "", "", "", "")
}

func renderTemplates(w http.ResponseWriter, r *http.Request, d *Deps, errMsg, name, desc, visibility string) {
	user := middleware.User(r.Context())
	templates, _ := d.Templates.List(r.Context(), user.ID, user.Role == "admin", "")
	wfs, _ := d.Workflows.ListByUser(r.Context(), user.ID)
	data := map[string]any{
		"templates": templates,
		"workflows": wfs,
		"form":      map[string]string{"name": name, "description": desc, "visibility": visibility},
	}
	if errMsg != "" {
		data["error"] = errMsg
		data["create_open"] = true
	}
	renderPage(w, r, d, "sharing_and_templates", data)
}

func (d *Deps) TemplateVisibilityPost(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	id := chi.URLParam(r, "id")
	if err := r.ParseForm(); err != nil {
		httpx.WriteError(w, http.StatusBadRequest, "invalid form", "")
		return
	}
	v := r.FormValue("visibility")
	if err := d.Templates.UpdateVisibility(r.Context(), id, user.ID, v, user.Role == "admin"); err != nil {
		httpx.WriteError(w, http.StatusNotFound, err.Error(), "")
		return
	}
	_ = d.Audit.Append(r.Context(), user.ID, "template.visibility", id, v)
	http.Redirect(w, r, "/sharing_and_templates", http.StatusFound)
}

func (d *Deps) TemplateDeletePost(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	id := chi.URLParam(r, "id")
	if err := d.Templates.Delete(r.Context(), id, user.ID, user.Role == "admin"); err != nil {
		httpx.WriteError(w, http.StatusNotFound, err.Error(), "")
		return
	}
	_ = d.Audit.Append(r.Context(), user.ID, "template.delete", id, "")
	http.Redirect(w, r, "/sharing_and_templates", http.StatusFound)
}

// ---- AGENT-12: admin governance ----

func (d *Deps) AdminPage(w http.ResponseWriter, r *http.Request) {
	settings, _ := d.Admin.ListSettings(r.Context())
	users, _ := d.Users.List(r.Context())
	audit, _ := d.Audit.Recent(r.Context(), 100)
	renderPage(w, r, d, "admin_governance", map[string]any{
		"settings": settings, "users": users, "audit": audit,
	})
}

func (d *Deps) AdminUpdateSetting(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	key := chi.URLParam(r, "key")
	val := r.FormValue("value")
	if err := d.Admin.SetSetting(r.Context(), key, val, user.ID); err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
		return
	}
	_ = d.Audit.Append(r.Context(), user.ID, "admin.setting", key, val)
	http.Redirect(w, r, "/admin_governance", http.StatusFound)
}

func (d *Deps) AdminUserToggleStatus(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	id := chi.URLParam(r, "id")
	target, _ := d.Users.ByID(r.Context(), id)
	if target == nil {
		httpx.WriteError(w, http.StatusNotFound, "user not found", "")
		return
	}
	newStatus := "active"
	if target.Status == "active" {
		newStatus = "disabled"
	}
	if err := d.Users.UpdateStatus(r.Context(), id, newStatus); err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
		return
	}
	_ = d.Audit.Append(r.Context(), user.ID, "admin.user.status", id, newStatus)
	http.Redirect(w, r, "/admin_governance", http.StatusFound)
}

func (d *Deps) AdminUserToggleRole(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	id := chi.URLParam(r, "id")
	target, _ := d.Users.ByID(r.Context(), id)
	if target == nil {
		httpx.WriteError(w, http.StatusNotFound, "user not found", "")
		return
	}
	newRole := "user"
	if target.Role == "user" {
		newRole = "admin"
	}
	if err := d.Users.UpdateRole(r.Context(), id, newRole); err != nil {
		httpx.WriteError(w, http.StatusInternalServerError, err.Error(), "")
		return
	}
	_ = d.Audit.Append(r.Context(), user.ID, "admin.user.role", id, newRole)
	http.Redirect(w, r, "/admin_governance", http.StatusFound)
}

// ---- Dashboard ----

func (d *Deps) Dashboard(w http.ResponseWriter, r *http.Request) {
	user := middleware.User(r.Context())
	wfs, _ := d.Workflows.ListByUser(r.Context(), user.ID)
	runs, _ := d.Runs.ListByUser(r.Context(), user.ID)
	schedules, _ := d.Schedules.ListByUser(r.Context(), user.ID)
	hooks, _ := d.Webhooks.ListByUser(r.Context(), user.ID)
	secrets, _ := d.Secrets.ListByUser(r.Context(), user.ID)
	files, _ := d.Files.ListByUser(r.Context(), user.ID)

	stats := map[string]int{
		"workflows": len(wfs),
		"runs":      len(runs),
		"schedules": len(schedules),
		"webhooks":  len(hooks),
		"secrets":   len(secrets),
		"files":     len(files),
	}
	recent := runs
	if len(recent) > 10 {
		recent = recent[:10]
	}
	renderPage(w, r, d, "dashboard", map[string]any{
		"stats":       stats,
		"recent_runs": recent,
	})
}

func (d *Deps) Home(w http.ResponseWriter, r *http.Request) {
	renderPage(w, r, d, "home", nil)
}

// ---- Health and api echo (used by HTTP tool and webhook testing) ----

func (d *Deps) Health(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	_, _ = w.Write([]byte(`{"ok":true,"status":"healthy","time":"` + time.Now().UTC().Format(time.RFC3339) + `"}`))
}

func (d *Deps) Echo(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	b, _ := io.ReadAll(io.LimitReader(r.Body, 4096))
	_, _ = w.Write([]byte(fmt.Sprintf(`{"ok":true,"method":%q,"body":%s}`, r.Method, string(b))))
}

// ---- Cron-style smoke endpoint ----

func (d *Deps) ListJSON(w http.ResponseWriter, r *http.Request, items any) {
	httpx.WriteOK(w, map[string]any{"items": items})
}

// Used by Seed fixtures to validate steps_json length.
var _ = strconv.Itoa
var _ = errors.New
var _ = template.HTMLEscapeString