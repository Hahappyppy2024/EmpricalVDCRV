package database

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"

	"github.com/anomalyco/p14-workflow-automation/internal/auth"
	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/google/uuid"
)

// SeedDB populates deterministic fixtures covering every use case.
func SeedDB(ctx context.Context, db *sql.DB, svc *auth.Service) error {
	if err := clearAll(ctx, db); err != nil {
		return err
	}

	// users
	adminID := uuid.NewString()
	userID := uuid.NewString()
	adminHash, err := auth.HashPassword("admin123")
	if err != nil {
		return err
	}
	userHash, err := auth.HashPassword("user123")
	if err != nil {
		return err
	}
	if _, err := db.ExecContext(ctx,
		`INSERT INTO users (id, email, display_name, password_hash, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')`,
		adminID, "admin@p14.local", "Platform Admin", adminHash); err != nil {
		return err
	}
	if _, err := db.ExecContext(ctx,
		`INSERT INTO users (id, email, display_name, password_hash, role, status) VALUES (?, ?, ?, ?, 'user', 'active')`,
		userID, "alice@p14.local", "Alice Engineer", userHash); err != nil {
		return err
	}

	// admin governance settings
	for _, kv := range []struct{ k, v string }{
		{"quota.workflows_per_user", "50"},
		{"quota.runs_per_day", "200"},
		{"feature.sharing_enabled", "true"},
		{"feature.webhooks_enabled", "true"},
		{"feature.schedules_enabled", "true"},
		{"maintenance.message", ""},
	} {
		if _, err := db.ExecContext(ctx,
			`INSERT INTO admin_governance (id, key, value, updated_by) VALUES (?, ?, ?, ?)`,
			uuid.NewString(), kv.k, kv.v, adminID); err != nil {
			return err
		}
	}

	// tool catalog (seed)
	type toolSeed struct {
		Name, Kind, Description, Default string
	}
	tools := []toolSeed{
		{"http_get", "http", "Issue an outbound HTTP GET", `{"url":"http://localhost:8080/api/health","method":"GET","payload":""}`},
		{"http_post_json", "http", "POST JSON to a configured URL", `{"url":"http://localhost:8080/api/echo","method":"POST","payload":"{\"hello\":\"world\"}"}`},
		{"shell_echo", "shell", "Run /bin/echo via a sandboxed shell helper", `{"command":"echo hello"}`},
		{"write_file", "file_write", "Write text into the workspace", `{"path":"notes/out.txt","content":"hello"}`},
		{"read_file", "file_read", "Read a workspace text file", `{"path":"notes/out.txt"}`},
		{"delay", "delay", "Sleep for N milliseconds", `{"ms":"200"}`},
		{"log_message", "log", "Emit a structured log line", `{"level":"info","message":"step ran"}`},
	}
	toolIDs := map[string]string{}
	for _, t := range tools {
		tid := uuid.NewString()
		toolIDs[t.Name] = tid
		if _, err := db.ExecContext(ctx,
			`INSERT INTO tool_catalog (id, name, kind, description, default_json, enabled) VALUES (?, ?, ?, ?, ?, 1)`,
			tid, t.Name, t.Kind, t.Description, t.Default); err != nil {
			return err
		}
	}

	// workflow creation seed
	wfID := uuid.NewString()
	steps := []models.WorkflowStep{
		{Seq: 1, Name: "log start", Tool: "log_message", Config: map[string]string{"level": "info", "message": "starting"}},
		{Seq: 2, Name: "delay", Tool: "delay", Config: map[string]string{"ms": "120"}},
		{Seq: 3, Name: "write output", Tool: "write_file", Config: map[string]string{"path": "notes/out.txt", "content": "produced by p14"}},
		{Seq: 4, Name: "http probe", Tool: "http_get", Config: map[string]string{"url": "http://localhost:8080/api/health", "method": "GET"}},
	}
	stepsJSON, _ := json.Marshal(steps)
	if _, err := db.ExecContext(ctx,
		`INSERT INTO workflow_creation (id, user_id, name, description, trigger_kind, cron_expr, steps_json, status) VALUES (?, ?, ?, ?, 'manual', '', ?, 'active')`,
		wfID, userID, "Daily Status Sync", "Sample workflow that exercises every seed step", string(stepsJSON)); err != nil {
		return err
	}

	for _, name := range []string{"log_message", "delay", "write_file", "http_get"} {
		if _, err := db.ExecContext(ctx,
			`INSERT INTO workflow_tools (workflow_id, tool_id) VALUES (?, ?)`,
			wfID, toolIDs[name]); err != nil {
			return err
		}
	}

	// workspace files for alice
	files := []struct {
		Path, Content string
	}{
		{"notes/README.md", "# Alice's workspace\nThis is a deterministic seed file."},
		{"notes/out.txt", "produced by p14\n"},
	}
	for _, f := range files {
		if _, err := db.ExecContext(ctx,
			`INSERT INTO workspace_files (id, user_id, workflow_id, path, size, mime_type, content) VALUES (?, ?, ?, ?, ?, 'text/plain', ?)`,
			uuid.NewString(), userID, wfID, f.Path, len(f.Content), f.Content); err != nil {
			return err
		}
	}

	// schedule
	if _, err := db.ExecContext(ctx,
		`INSERT INTO scheduled_runs (id, user_id, workflow_id, cron_expr, next_run_at, enabled) VALUES (?, ?, ?, '0 9 * * *', datetime('now','+1 day'), 1)`,
		uuid.NewString(), userID, wfID); err != nil {
		return err
	}

	// webhook trigger
	tok, err := auth.RandomToken(18)
	if err != nil {
		return err
	}
	if _, err := db.ExecContext(ctx,
		`INSERT INTO webhook_triggers (id, user_id, workflow_id, token, description) VALUES (?, ?, ?, ?, ?)`,
		uuid.NewString(), userID, wfID, tok, "Inbound trigger for daily sync"); err != nil {
		return err
	}

	// secrets (encrypted demo)
	plainSecret := "shh-this-is-a-token-1234567890"
	cipher, err := svc.Encrypt(plainSecret)
	if err != nil {
		return err
	}
	masked := auth.MaskSecret(plainSecret)
	if _, err := db.ExecContext(ctx,
		`INSERT INTO secrets_manager (id, user_id, name, masked, cipher) VALUES (?, ?, 'OUTBOUND_TOKEN', ?, ?)`,
		uuid.NewString(), userID, masked, cipher); err != nil {
		return err
	}

	// shared template (visible to her + admins)
	body := map[string]any{
		"name":        "Daily Status Sync",
		"trigger":     "manual",
		"steps":       steps,
		"description": "Shared template published from the seed workflow",
	}
	bodyJSON, _ := json.Marshal(body)
	if _, err := db.ExecContext(ctx,
		`INSERT INTO sharing_and_templates (id, user_id, workflow_id, name, description, body_json, visibility) VALUES (?, ?, ?, 'Daily Sync Template', 'Reusable status sync template', ?, 'shared')`,
		uuid.NewString(), userID, wfID, string(bodyJSON)); err != nil {
		return err
	}

	// a finished run with replayable log lines (for run logs and replay)
	runID := uuid.NewString()
	if _, err := db.ExecContext(ctx,
		`INSERT INTO task_execution (id, user_id, workflow_id, trigger, status, started_at, finished_at, detail) VALUES (?, ?, ?, 'manual', 'success', datetime('now','-1 hour'), datetime('now','-59 minutes'), 'Seed run completed')`,
		runID, userID, wfID); err != nil {
		return err
	}
	for i, msg := range []string{"workflow started", "step 1 ok", "step 2 ok", "step 3 ok", "step 4 ok", "workflow finished"} {
		if _, err := db.ExecContext(ctx,
			`INSERT INTO run_logs_and_replay (id, user_id, run_id, level, message) VALUES (?, ?, ?, 'info', ?)`,
			uuid.NewString(), userID, runID, fmt.Sprintf("%s (#%d)", msg, i+1)); err != nil {
			return err
		}
	}

	return nil
}

func clearAll(ctx context.Context, db *sql.DB) error {
	tables := []string{
		"audit_events",
		"admin_governance",
		"sharing_and_templates",
		"run_logs_and_replay",
		"secrets_manager",
		"external_http_action",
		"webhook_triggers",
		"stored_files",
		"workspace_files",
		"scheduled_runs",
		"task_steps",
		"task_execution",
		"workflow_tools",
		"workflow_creation",
		"tool_catalog",
		"account_access",
		"sessions",
		"users",
	}
	for _, t := range tables {
		if _, err := db.ExecContext(ctx, "DELETE FROM "+t); err != nil {
			return err
		}
	}
	return nil
}