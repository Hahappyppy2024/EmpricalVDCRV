package handler

import (
	"encoding/json"
	"html/template"
	"net/http"
	"strconv"

	"github.com/go-chi/chi/v5"

	"p14-agentic-platform/internal/middleware"
	"p14-agentic-platform/internal/service"
)

// Field describes one form input.
type Field struct {
	Key         string   `json:"key"`
	Label       string   `json:"label"`
	Type        string   `json:"type"` // text, textarea, number, select, checkbox, url, password
	Required    bool     `json:"required"`
	Options     []string `json:"options,omitempty"`
	Help        string   `json:"help,omitempty"`
	Placeholder string   `json:"placeholder,omitempty"`
}

// Column describes one table column.
type Column struct {
	Key    string `json:"key"`
	Label  string `json:"label"`
	Render string `json:"render,omitempty"` // "", "bool", "link", "json"
	Href   string `json:"href,omitempty"`   // link target with {value} placeholder
}

// Action describes a per-row button.
type Action struct {
	Key     string         `json:"key"`
	Label   string         `json:"label"`
	Method  string         `json:"method"`
	Path    string         `json:"path"`
	Body    map[string]any `json:"body,omitempty"`
	Confirm string         `json:"confirm,omitempty"`
}

// PageConfig is the rendering contract shared with the browser (window.PAGE).
type PageConfig struct {
	Resource     string   `json:"resource"`
	Title        string   `json:"title"`
	Description  string   `json:"description"`
	CreateLabel  string   `json:"createLabel"`
	CanCreate    bool     `json:"canCreate"`
	ReplayForm   bool     `json:"replayForm"`
	Fields       []Field  `json:"fields"`
	Columns      []Column `json:"columns"`
	Filters      []Field  `json:"filters"`
	Actions      []Action `json:"actions"`
	ExtraNote    string   `json:"extraNote,omitempty"`
	JsonEchoHint bool     `json:"jsonEchoHint,omitempty"`
}

func text(key, label string, required bool, help string) Field {
	return Field{Key: key, Label: label, Type: "text", Required: required, Help: help}
}

func selectField(key, label string, required bool, options []string, help string) Field {
	return Field{Key: key, Label: label, Type: "select", Required: required, Options: options, Help: help}
}

func checkbox(key, label string, help string) Field {
	return Field{Key: key, Label: label, Type: "checkbox", Help: help}
}

func numField(key, label string, required bool, help string) Field {
	return Field{Key: key, Label: label, Type: "number", Required: required, Help: help}
}

func textarea(key, label string, required bool, help string) Field {
	return Field{Key: key, Label: label, Type: "textarea", Required: required, Help: help}
}

func col(key, label string) Column     { return Column{Key: key, Label: label} }
func colBool(key, label string) Column { return Column{Key: key, Label: label, Render: "bool"} }
func colLink(key, label, href string) Column {
	return Column{Key: key, Label: label, Render: "link", Href: href}
}

// resourcePage builds the PageConfig for a generic resource page.
func resourcePage(resource, title, description string) PageConfig {
	switch resource {
	case "account_access":
		return PageConfig{
			Resource: resource, Title: title, Description: description, CreateLabel: "Record access event",
			Fields: []Field{
				text("account", "Account", true, "username or email that was accessed"),
				selectField("action", "Action", true, []string{"signin", "register", "reset", "profile"}, ""),
				selectField("status", "Status", false, []string{"ok", "rejected"}, ""),
			},
			Columns: []Column{col("id", "#"), col("account", "Account"), col("action", "Action"), col("status", "Status"), col("created_at", "When")},
			Filters: []Field{selectField("status", "Status", false, []string{"ok", "rejected"}, "")},
		}
	case "workflow_creation":
		return PageConfig{
			Resource: resource, Title: title, Description: description, CreateLabel: "Create workflow",
			Fields: []Field{
				text("name", "Name", true, ""),
				textarea("description", "Description", false, ""),
				selectField("trigger_type", "Trigger type", false, []string{"manual", "webhook", "schedule", "http"}, ""),
				textarea("steps_json", "Steps (JSON array)", false, `[{"name":"run tests","tool":"shell","command":"go test ./..."}]`),
				textarea("conditions_json", "Conditions (JSON object)", false, `{"branch":"main"}`),
				checkbox("enabled", "Enabled", ""),
			},
			Columns: []Column{colLink("id", "#", "/runs/{value}"), col("name", "Name"), col("trigger_type", "Trigger"), colBool("enabled", "Enabled"), col("created_at", "Created")},
			Filters: []Field{selectField("trigger_type", "Trigger", false, []string{"manual", "webhook", "schedule", "http"}, ""), selectField("enabled", "Enabled", false, []string{"true", "false"}, "")},
		}
	case "tool_catalog":
		return PageConfig{
			Resource: resource, Title: title, Description: description, CreateLabel: "Define tool",
			Fields: []Field{
				text("name", "Name", true, ""),
				selectField("category", "Category", true, []string{"runtime", "integration", "storage", "security", "notification", "scanner"}, ""),
				textarea("description", "Description", false, ""),
				text("version", "Version", false, ""),
				checkbox("is_public", "Public", ""),
				checkbox("enabled", "Enabled", ""),
			},
			Columns: []Column{col("id", "#"), col("name", "Name"), col("category", "Category"), col("version", "Version"), colBool("is_public", "Public"), colBool("enabled", "Enabled")},
			Filters: []Field{selectField("category", "Category", false, []string{"runtime", "integration", "storage", "security", "notification", "scanner"}, ""), selectField("enabled", "Enabled", false, []string{"true", "false"}, "")},
		}
	case "task_execution":
		return PageConfig{
			Resource: resource, Title: title, Description: description, CreateLabel: "Run workflow",
			Fields: []Field{
				numField("workflow_id", "Workflow ID", true, "id of one of your workflows"),
				selectField("trigger", "Trigger", false, []string{"manual", "webhook", "schedule", "http"}, ""),
			},
			Columns: []Column{colLink("id", "#", "/runs/{value}"), col("workflow_id", "Workflow"), col("trigger", "Trigger"), col("status", "Status"), col("started_at", "Started"), col("finished_at", "Finished")},
			Filters: []Field{selectField("status", "Status", false, []string{"running", "success", "failed", "canceled", "pending"}, ""), selectField("trigger", "Trigger", false, []string{"manual", "webhook", "schedule", "http", "replay"}, "")},
			Actions: []Action{{Key: "replay", Label: "Replay", Method: "POST", Path: "/api/agent/task_execution/{id}/replay", Confirm: "Re-run this workflow?"}},
		}
	case "scheduled_runs":
		return PageConfig{
			Resource: resource, Title: title, Description: description, CreateLabel: "Add schedule",
			Fields: []Field{
				numField("workflow_id", "Workflow ID", true, "id of your workflow"),
				text("name", "Name", false, ""),
				text("cron_expr", "Cron expression", true, "min hour day month weekday, e.g. 0 2 * * *"),
				checkbox("enabled", "Enabled", ""),
			},
			Columns: []Column{col("id", "#"), col("name", "Name"), col("workflow_id", "Workflow"), col("cron_expr", "Cron"), col("next_run_at", "Next run"), colBool("enabled", "Enabled")},
			Filters: []Field{selectField("enabled", "Enabled", false, []string{"true", "false"}, "")},
		}
	case "workspace_files":
		return PageConfig{
			Resource: resource, Title: title, Description: description, CreateLabel: "Reference file",
			Fields: []Field{
				text("name", "Name", true, "file name"),
				text("file_path", "File path", false, "e.g. output/report.txt"),
			},
			Columns:   []Column{col("id", "#"), col("name", "Name"), col("size", "Size (bytes)"), col("content_type", "Type"), col("status", "Status"), col("created_at", "Uploaded")},
			Filters:   []Field{selectField("status", "Status", false, []string{"stored", "referenced"}, "")},
			Actions:   []Action{{Key: "download", Label: "Download", Method: "GET", Path: "/api/agent/workspace_files/{id}/download"}},
			ExtraNote: "Use the upload form below to store file content; the form above only references a file path.",
		}
	case "webhook_triggers":
		return PageConfig{
			Resource: resource, Title: title, Description: description, CreateLabel: "Create webhook",
			Fields: []Field{
				numField("workflow_id", "Workflow ID", true, "id of your workflow"),
				text("name", "Name", false, ""),
				text("token", "Token", false, "leave empty to auto-generate"),
				checkbox("enabled", "Enabled", ""),
			},
			Columns:   []Column{col("id", "#"), col("name", "Name"), col("workflow_id", "Workflow"), col("token", "Token"), colBool("enabled", "Enabled")},
			Filters:   []Field{selectField("enabled", "Enabled", false, []string{"true", "false"}, "")},
			ExtraNote: "Trigger a workflow with: curl -X POST {WEB_BASE_URL}/api/agent/webhooks/{token}/trigger",
		}
	case "external_http_action":
		return PageConfig{
			Resource: resource, Title: title, Description: description, CreateLabel: "Add HTTP action",
			Fields: []Field{
				text("name", "Name", true, ""),
				text("url", "URL", true, "use http://localhost:8080/internal/echo offline"),
				selectField("method", "Method", false, []string{"GET", "POST", "PUT", "PATCH", "DELETE"}, ""),
				textarea("payload", "Payload (JSON body)", false, `{"event":"deploy"}`),
			},
			Columns:      []Column{col("id", "#"), col("name", "Name"), col("url", "URL"), col("method", "Method"), col("last_status", "Last status"), col("created_at", "Created")},
			Filters:      []Field{selectField("method", "Method", false, []string{"GET", "POST", "PUT", "PATCH", "DELETE"}, "")},
			Actions:      []Action{{Key: "execute", Label: "Execute", Method: "POST", Path: "/api/agent/external_http_action/{id}/execute", Confirm: "Call this endpoint now?"}},
			JsonEchoHint: true,
		}
	case "secrets_manager":
		return PageConfig{
			Resource: resource, Title: title, Description: description, CreateLabel: "Store secret",
			Fields: []Field{
				text("name", "Name", true, "e.g. DEPLOY_TOKEN"),
				Field{Key: "value", Label: "Value", Type: "password", Required: true, Help: "stored masked; raw value is never returned"},
			},
			Columns: []Column{col("id", "#"), col("name", "Name"), col("masked_value", "Masked value"), col("created_at", "Created")},
			Filters: []Field{},
		}
	case "run_logs_and_replay":
		return PageConfig{
			Resource: resource, Title: title, Description: description, CreateLabel: "Append log entry",
			ReplayForm: true,
			Fields: []Field{
				numField("run_id", "Run ID", true, "the run this entry belongs to"),
				text("step_name", "Step name", true, ""),
				selectField("status", "Status", false, []string{"running", "success", "failed", "skipped"}, ""),
				textarea("output", "Output", false, ""),
			},
			Columns: []Column{col("id", "#"), col("run_id", "Run"), col("step_name", "Step"), col("status", "Status"), col("output", "Output"), col("created_at", "When")},
			Filters: []Field{selectField("status", "Status", false, []string{"running", "success", "failed", "skipped"}, ""), numField("run_id", "Run ID", false, "")},
		}
	case "sharing_and_templates":
		return PageConfig{
			Resource: resource, Title: title, Description: description, CreateLabel: "Create template",
			Fields: []Field{
				text("name", "Name", true, ""),
				textarea("description", "Description", false, ""),
				textarea("definition_json", "Definition (JSON)", false, `{"steps":[]}`),
				checkbox("is_published", "Published", ""),
			},
			Columns: []Column{col("id", "#"), col("name", "Name"), colBool("is_published", "Published"), col("downloads", "Downloads"), col("created_at", "Created")},
			Filters: []Field{selectField("is_published", "Published", false, []string{"true", "false"}, "")},
			Actions: []Action{{Key: "publish", Label: "Publish", Method: "POST", Path: "/api/agent/sharing_and_templates/{id}/publish", Body: map[string]any{"publish": true}, Confirm: "Publish this template?"}},
		}
	}
	return PageConfig{Resource: resource, Title: title, Description: description}
}

// pageResource renders a generic resource page.
func (s *Server) pageResource(cfg PageConfig) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u := middleware.UserFrom(r)
		cfg.CanCreate = true
		if cfg.Resource == "tool_catalog" && u.Role != "admin" {
			cfg.CanCreate = false
		}
		cfgJSON, _ := json.Marshal(cfg)
		pageData := struct {
			User   any
			Admin  bool
			Config PageConfig
			Cfg    template.JS
		}{User: s.svc.Me(u), Admin: u.Role == "admin", Config: cfg, Cfg: template.JS(cfgJSON)}
		_ = s.render.Page(w, "resource", pageData)
	}
}

func (s *Server) pageDashboard(w http.ResponseWriter, r *http.Request) {
	u := middleware.UserFrom(r)
	stats := s.svc.DashboardStats(u)
	recent, _ := s.svc.ListResource("task_execution", u, nil, 5, 0)
	pageData := struct {
		User   any
		Stats  map[string]any
		Recent []map[string]any
		Admin  bool
	}{User: s.svc.Me(u), Stats: stats, Recent: recent, Admin: u.Role == "admin"}
	_ = s.render.Page(w, "dashboard", pageData)
}

func (s *Server) pageRunDetail(w http.ResponseWriter, r *http.Request) {
	u := middleware.UserFrom(r)
	id, err := strconv.ParseInt(chi.URLParam(r, "id"), 10, 64)
	if err != nil {
		http.Error(w, "invalid run id", http.StatusBadRequest)
		return
	}
	pageData := struct {
		User  any
		Admin bool
		RunID int64
	}{User: s.svc.Me(u), Admin: u.Role == "admin", RunID: id}
	_ = s.render.Page(w, "run_detail", pageData)
}

func (s *Server) pageAdmin(w http.ResponseWriter, r *http.Request) {
	u := middleware.UserFrom(r)
	if u.Role != "admin" {
		http.Error(w, "forbidden", http.StatusForbidden)
		return
	}
	pageData := struct {
		User  any
		Admin bool
	}{User: s.svc.Me(u), Admin: true}
	_ = s.render.Page(w, "admin", pageData)
}

func (s *Server) pageLogin(w http.ResponseWriter, r *http.Request) {
	u := middleware.UserFrom(r)
	if u != nil {
		http.Redirect(w, r, "/", http.StatusFound)
		return
	}
	_ = s.render.Page(w, "auth", map[string]any{
		"Mode":   "login",
		"Error":  r.URL.Query().Get("error"),
		"Notice": s.svc.GetSetting("global_notice", ""),
		"User":   nil,
	})
}

func (s *Server) pageRegister(w http.ResponseWriter, r *http.Request) {
	u := middleware.UserFrom(r)
	if u != nil {
		http.Redirect(w, r, "/", http.StatusFound)
		return
	}
	_ = s.render.Page(w, "auth", map[string]any{
		"Mode":   "register",
		"Error":  r.URL.Query().Get("error"),
		"Notice": s.svc.GetSetting("global_notice", ""),
		"User":   nil,
	})
}

func (s *Server) apiLogin(w http.ResponseWriter, r *http.Request) {
	data, ok := s.readJSON(w, r)
	if !ok {
		return
	}
	username := service.StrField(data, "username")
	password := service.StrField(data, "password")
	if username == "" || password == "" {
		writeErr(w, service.BadRequest("username and password are required"))
		return
	}
	u, sess, err := s.svc.Login(username, password)
	if err != nil {
		writeErr(w, err)
		return
	}
	s.setSessionCookie(w, sess.Token)
	writeJSON(w, http.StatusOK, map[string]any{"ok": true, "user": s.svc.Me(u)})
}

func (s *Server) apiRegister(w http.ResponseWriter, r *http.Request) {
	data, ok := s.readJSON(w, r)
	if !ok {
		return
	}
	u, sess, err := s.svc.Register(
		service.StrField(data, "username"),
		service.StrField(data, "email"),
		service.StrField(data, "password"),
	)
	if err != nil {
		writeErr(w, err)
		return
	}
	s.setSessionCookie(w, sess.Token)
	writeJSON(w, http.StatusCreated, map[string]any{"ok": true, "user": s.svc.Me(u)})
}

func (s *Server) apiLogout(w http.ResponseWriter, r *http.Request) {
	if c, err := r.Cookie(s.cfg.SessionName); err == nil {
		_ = s.svc.DeleteSession(c.Value)
	}
	http.SetCookie(w, &http.Cookie{
		Name: s.cfg.SessionName, Value: "", Path: "/", HttpOnly: true, MaxAge: -1,
	})
	http.Redirect(w, r, "/login", http.StatusFound)
}

func (s *Server) setSessionCookie(w http.ResponseWriter, token string) {
	http.SetCookie(w, &http.Cookie{
		Name:     s.cfg.SessionName,
		Value:    token,
		Path:     "/",
		HttpOnly: true,
		SameSite: http.SameSiteLaxMode,
		MaxAge:   s.cfg.SessionTTL,
	})
}
