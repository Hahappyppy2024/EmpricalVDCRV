package db

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"log"
	"time"

	"golang.org/x/crypto/bcrypt"
)

// Seed populates deterministic fixtures for every actor, role, workflow state
// and dependency required by the acceptance criteria. Seeding is skipped when
// users already exist so it is safe to run on every startup.
func Seed(database *sql.DB, webBaseURL string) error {
	var count int
	if err := database.QueryRow("SELECT COUNT(*) FROM users").Scan(&count); err != nil {
		return err
	}
	if count > 0 {
		log.Println("db: seed skipped, users already present")
		return nil
	}

	hash := func(pw string) string {
		b, err := bcrypt.GenerateFromPassword([]byte(pw), bcrypt.DefaultCost)
		if err != nil {
			panic(err)
		}
		return string(b)
	}

	now := time.Now().UTC()

	// --- Users -------------------------------------------------------------
	adminID := insertUser(database, "admin", "admin@p14.local", hash("admin123"), "admin", true, now)
	aliceID := insertUser(database, "alice", "alice@p14.local", hash("alice123"), "user", true, now)
	bobID := insertUser(database, "bob", "bob@p14.local", hash("bob123"), "user", true, now)

	// --- Tool catalog (AGENT-03) -------------------------------------------
	insertTool(database, "shell-runner", "runtime", "Run shell commands in a sandboxed runner", "1.2.0", true, true, adminID, now)
	insertTool(database, "http-client", "integration", "Call external HTTP endpoints with bounded options", "2.0.1", true, true, adminID, now)
	insertTool(database, "file-io", "storage", "Read and write scoped workspace files", "1.0.0", true, true, adminID, now)
	insertTool(database, "secret-lookup", "security", "Resolve masked secrets for workflow actions", "0.9.0", true, true, adminID, now)
	insertTool(database, "notify-email", "notification", "Queue deterministic email notifications", "1.1.0", true, true, adminID, now)
	insertTool(database, "private-scanner", "scanner", "Internal-only vulnerability scanner", "3.0.0", false, true, adminID, now)

	// --- Workflows (AGENT-02) ----------------------------------------------
	deploySteps := []any{
		map[string]any{"name": "checkout repo", "tool": "shell", "command": "git clone --depth=1 . repo"},
		map[string]any{"name": "install deps", "tool": "shell", "command": "go mod download"},
		map[string]any{"name": "run tests", "tool": "shell", "command": "go test ./..."},
		map[string]any{"name": "build artifact", "tool": "shell", "command": "go build -o p14-web ./cmd"},
		map[string]any{"name": "deploy notification", "tool": "http", "url": webBaseURL + "/internal/echo", "method": "POST", "payload": `{"event":"deploy"}`},
	}
	deployStepsJSON := mustJSON(deploySteps)

	backupSteps := []any{
		map[string]any{"name": "snapshot data", "tool": "shell", "command": "pg_dump p14 > backup.sql"},
		map[string]any{"name": "store artifact", "tool": "file-io", "file": "backup/backup.sql"},
		map[string]any{"name": "notify ops", "tool": "email", "email": "ops@p14.local"},
	}
	backupStepsJSON := mustJSON(backupSteps)

	webhookSteps := []any{
		map[string]any{"name": "resolve event", "tool": "shell", "command": "echo event received"},
		map[string]any{"name": "run build", "tool": "shell", "command": "make build"},
		map[string]any{"name": "publish", "tool": "shell", "command": "make publish"},
	}
	webhookStepsJSON := mustJSON(webhookSteps)

	healthSteps := []any{
		map[string]any{"name": "probe endpoint", "tool": "http", "url": webBaseURL + "/internal/echo", "method": "GET"},
		map[string]any{"name": "report status", "tool": "email", "email": "health@p14.local"},
	}
	healthStepsJSON := mustJSON(healthSteps)

	aliceWfDeploy := insertWorkflow(database, aliceID, "Deploy web app", "Build, test and deploy the web application", "manual", deployStepsJSON, `{"branch":"main"}`, true, now)
	aliceWfBackup := insertWorkflow(database, aliceID, "Nightly backup", "Snapshots the data store every night", "schedule", backupStepsJSON, `{"env":"prod"}`, true, now)
	aliceWfWebhook := insertWorkflow(database, aliceID, "Webhook build", "Builds triggered by inbound webhooks", "webhook", webhookStepsJSON, `{}`, true, now)
	aliceWfHealth := insertWorkflow(database, aliceID, "API health check", "Probes the internal echo service", "http", healthStepsJSON, `{}`, true, now)

	bobDemoSteps := mustJSON([]any{
		map[string]any{"name": "clone project", "tool": "shell", "command": "git clone demo"},
		map[string]any{"name": "run linter", "tool": "shell", "command": "golangci-lint run", "fail": true},
	})
	insertWorkflow(database, bobID, "Bob's demo pipeline", "Demonstrates a failing linter step", "manual", bobDemoSteps, `{}`, true, now)

	// --- Runs (AGENT-04) ----------------------------------------------------
	nowT := now.Format(time.RFC3339)
	insertRun(database, aliceID, aliceWfDeploy, "manual", "success",
		deployStepsJSON,
		`[{"name":"checkout repo","status":"success","output":"cloned repository"},{"name":"install deps","status":"success","output":"dependencies installed"},{"name":"run tests","status":"success","output":"42 tests passed"},{"name":"build artifact","status":"success","output":"artifact p14-web built"},{"name":"deploy notification","status":"success","output":"notification queued"}]`,
		nowT, now.Add(-30*time.Minute).Format(time.RFC3339))

	failedSteps := mustJSON([]any{
		map[string]any{"name": "checkout repo", "tool": "shell", "command": "git clone ."},
		map[string]any{"name": "run tests", "tool": "shell", "command": "go test ./...", "fail": true},
	})
	insertRun(database, aliceID, aliceWfDeploy, "manual", "failed",
		failedSteps,
		`[{"name":"checkout repo","status":"success","output":"cloned repository"},{"name":"run tests","status":"failed","output":"3 tests failed"}]`,
		nowT, now.Add(-2*time.Hour).Format(time.RFC3339))

	insertRun(database, aliceID, aliceWfWebhook, "webhook", "success",
		webhookStepsJSON,
		`[{"name":"resolve event","status":"success","output":"event received"},{"name":"run build","status":"success","output":"build ok"},{"name":"publish","status":"success","output":"published"}]`,
		nowT, now.Add(-1*time.Hour).Format(time.RFC3339))

	// --- Run logs (AGENT-10) ------------------------------------------------
	insertRunLog(database, aliceID, 1, "checkout repo", "success", "cloned repository\nsha 3f9c2a11", now)
	insertRunLog(database, aliceID, 1, "install deps", "success", "dependencies installed\n2.4s", now)
	insertRunLog(database, aliceID, 1, "run tests", "success", "42 tests passed\n0.9s", now)
	insertRunLog(database, aliceID, 1, "build artifact", "success", "artifact p14-web built", now)
	insertRunLog(database, aliceID, 1, "deploy notification", "success", "notification queued", now)
	insertRunLog(database, aliceID, 2, "checkout repo", "success", "cloned repository", now.Add(-2*time.Hour))
	insertRunLog(database, aliceID, 2, "run tests", "failed", "3 tests failed\nFAIL  TestIntegration", now.Add(-2*time.Hour))

	// --- Schedules (AGENT-05) ------------------------------------------------
	insertSchedule(database, aliceID, aliceWfBackup, "Nightly backup", "0 2 * * *", now.Add(2*time.Hour).Format(time.RFC3339), true, now)
	insertSchedule(database, aliceID, aliceWfHealth, "Demo every 5 minutes", "*/5 * * * *", now.Add(5*time.Minute).Format(time.RFC3339), false, now)

	// --- Webhooks (AGENT-07) --------------------------------------------------
	insertWebhook(database, aliceID, aliceWfWebhook, "Deploy webhook", "wh_deploy_alice", true, now)
	insertWebhook(database, bobID, 5, "Bob hook", "wh_demo_bob", true, now)

	// --- External HTTP actions (AGENT-08) --------------------------------------
	insertHTTPAction(database, aliceID, "Deploy notification", webBaseURL+"/internal/echo", "POST", `{"event":"deploy","version":"1.4.2"}`, 200, `{"ok":true,"method":"POST","payload":{"event":"deploy","version":"1.4.2"}}`, now)
	insertHTTPAction(database, bobID, "Health ping", webBaseURL+"/internal/echo", "GET", "", 200, `{"ok":true,"method":"GET"}`, now)

	// --- Secrets (AGENT-09) -----------------------------------------------------
	insertSecret(database, aliceID, "DEPLOY_TOKEN", "dpl•••••••••••", now)
	insertSecret(database, aliceID, "DATABASE_URL", "pos•••••••••••••", now)
	insertSecret(database, bobID, "PERSONAL_API_KEY", "key•••••••••••", now)

	// --- Workspace files (AGENT-06) ----------------------------------------------
	insertWorkspaceFile(database, aliceID, "notes.md", "notes.md", int64(len("P14 workspace seed note.")), "text/markdown", "stored", []byte("P14 workspace seed note."), now)
	insertWorkspaceFile(database, aliceID, "config.json", "config/config.json", 30, "application/json", "stored", []byte(`{"region":"eu-west-1"}`), now)
	insertWorkspaceFile(database, bobID, "bob.txt", "bob.txt", 8, "text/plain", "stored", []byte("bob data"), now)

	// --- Templates (AGENT-11) -----------------------------------------------------
	insertTemplate(database, aliceID, "CI Build Pipeline", "Reusable build pipeline with tests and publish", mustJSON(map[string]any{
		"steps": []any{
			map[string]any{"name": "install", "tool": "shell", "command": "go mod download"},
			map[string]any{"name": "test", "tool": "shell", "command": "go test ./..."},
			map[string]any{"name": "publish", "tool": "http", "url": webBaseURL + "/internal/echo", "method": "POST"},
		},
	}), true, 12, now)
	insertTemplate(database, bobID, "Bob private template", "Not yet published", `{"steps":[]}`, false, 0, now)

	// --- Governance settings (AGENT-12) ---------------------------------------------
	insertSetting(database, "allow_registration", "true", adminID, now)
	insertSetting(database, "max_workflows_per_user", "20", adminID, now)
	insertSetting(database, "max_runs_per_user", "50", adminID, now)
	insertSetting(database, "disabled_actions", "[]", adminID, now)
	insertSetting(database, "global_notice", "Welcome to the P14 Workflow Automation / Agentic Task Platform.", adminID, now)
	insertSetting(database, "max_file_bytes", "5242880", adminID, now)

	// --- Audit events (AGENT-12) -------------------------------------------------------
	insertAudit(database, adminID, "tool_catalog.seed", "tools", fmt.Sprint(6), "seeded 6 catalog tools", now)
	insertAudit(database, adminID, "governance.upsert", "governance_settings", "disabled_actions", "initial governance defaults", now)

	// --- Account access records (AGENT-01) ------------------------------------------------
	insertAccountAccess(database, aliceID, "alice", "signin", "ok", now.Add(-2*time.Hour))
	insertAccountAccess(database, aliceID, "alice", "signin", "rejected", now.Add(-90*time.Minute))
	insertAccountAccess(database, adminID, "admin", "signin", "ok", now.Add(-1*time.Hour))

	log.Printf("db: seeded %d users, %d tools, %d workflows, %d runs, %d settings", 3, 6, 5, 3, 6)
	return nil
}

func mustJSON(v any) string {
	b, err := json.Marshal(v)
	if err != nil {
		panic(err)
	}
	return string(b)
}

func insertUser(db *sql.DB, username, email, hash, role string, active bool, now time.Time) int64 {
	res, err := db.Exec("INSERT INTO users(username, email, password_hash, role, active, created_at) VALUES(?,?,?,?,?,?)",
		username, email, hash, role, active, now.Format(time.RFC3339))
	if err != nil {
		panic(err)
	}
	id, _ := res.LastInsertId()
	return id
}

func insertTool(db *sql.DB, name, category, desc, version string, public, enabled bool, createdBy int64, now time.Time) {
	_, err := db.Exec("INSERT INTO tools(name, category, description, version, is_public, enabled, created_by, created_at) VALUES(?,?,?,?,?,?,?,?)",
		name, category, desc, version, public, enabled, createdBy, now.Format(time.RFC3339))
	if err != nil {
		panic(err)
	}
}

func insertWorkflow(db *sql.DB, userID int64, name, desc, trigger, steps, conditions string, enabled bool, now time.Time) int64 {
	res, err := db.Exec("INSERT INTO workflows(user_id, name, description, trigger_type, steps_json, conditions_json, enabled, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?)",
		userID, name, desc, trigger, steps, conditions, enabled, now.Format(time.RFC3339), now.Format(time.RFC3339))
	if err != nil {
		panic(err)
	}
	id, _ := res.LastInsertId()
	return id
}

func insertRun(db *sql.DB, userID, workflowID int64, trigger, status, steps, logs, started, finished string) {
	_, err := db.Exec("INSERT INTO runs(user_id, workflow_id, trigger, status, steps_json, logs_json, started_at, finished_at) VALUES(?,?,?,?,?,?,?,?)",
		userID, workflowID, trigger, status, steps, logs, started, finished)
	if err != nil {
		panic(err)
	}
}

func insertRunLog(db *sql.DB, userID, runID int64, step, status, output string, now time.Time) {
	_, err := db.Exec("INSERT INTO run_logs(user_id, run_id, step_name, status, output, created_at) VALUES(?,?,?,?,?,?)",
		userID, runID, step, status, output, now.Format(time.RFC3339))
	if err != nil {
		panic(err)
	}
}

func insertSchedule(db *sql.DB, userID, workflowID int64, name, cron, next string, enabled bool, now time.Time) {
	_, err := db.Exec("INSERT INTO schedules(user_id, workflow_id, name, cron_expr, next_run_at, enabled, created_at) VALUES(?,?,?,?,?,?,?)",
		userID, workflowID, name, cron, next, enabled, now.Format(time.RFC3339))
	if err != nil {
		panic(err)
	}
}

func insertWebhook(db *sql.DB, userID, workflowID int64, name, token string, enabled bool, now time.Time) {
	_, err := db.Exec("INSERT INTO webhooks(user_id, workflow_id, name, token, enabled, created_at) VALUES(?,?,?,?,?,?)",
		userID, workflowID, name, token, enabled, now.Format(time.RFC3339))
	if err != nil {
		panic(err)
	}
}

func insertHTTPAction(db *sql.DB, userID int64, name, url, method, payload string, status int, response string, now time.Time) {
	_, err := db.Exec("INSERT INTO http_actions(user_id, name, url, method, payload, last_status, last_response, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?)",
		userID, name, url, method, payload, status, response, now.Format(time.RFC3339), now.Format(time.RFC3339))
	if err != nil {
		panic(err)
	}
}

func insertSecret(db *sql.DB, userID int64, name, masked string, now time.Time) {
	_, err := db.Exec("INSERT INTO secrets(user_id, name, masked_value, created_at, updated_at) VALUES(?,?,?,?,?)",
		userID, name, masked, now.Format(time.RFC3339), now.Format(time.RFC3339))
	if err != nil {
		panic(err)
	}
}

func insertWorkspaceFile(db *sql.DB, userID int64, name, path string, size int64, ct, status string, content []byte, now time.Time) {
	_, err := db.Exec("INSERT INTO workspace_files(user_id, name, file_path, size, content_type, status, content, created_at) VALUES(?,?,?,?,?,?,?,?)",
		userID, name, path, size, ct, status, content, now.Format(time.RFC3339))
	if err != nil {
		panic(err)
	}
}

func insertTemplate(db *sql.DB, userID int64, name, desc, def string, published bool, downloads int64, now time.Time) {
	_, err := db.Exec("INSERT INTO templates(user_id, name, description, definition_json, is_published, downloads, created_at) VALUES(?,?,?,?,?,?,?)",
		userID, name, desc, def, published, downloads, now.Format(time.RFC3339))
	if err != nil {
		panic(err)
	}
}

func insertSetting(db *sql.DB, key, value string, updatedBy int64, now time.Time) {
	_, err := db.Exec("INSERT INTO governance_settings(setting_key, setting_value, updated_by, updated_at) VALUES(?,?,?,?)",
		key, value, updatedBy, now.Format(time.RFC3339))
	if err != nil {
		panic(err)
	}
}

func insertAudit(db *sql.DB, actor int64, action, entity, id, detail string, now time.Time) {
	_, err := db.Exec("INSERT INTO audit_events(actor_id, action, entity_type, entity_id, detail, created_at) VALUES(?,?,?,?,?,?)",
		actor, action, entity, id, detail, now.Format(time.RFC3339))
	if err != nil {
		panic(err)
	}
}

func insertAccountAccess(db *sql.DB, userID int64, account, action, status string, now time.Time) {
	_, err := db.Exec("INSERT INTO account_accesses(user_id, account, action, status, created_at) VALUES(?,?,?,?,?)",
		userID, account, action, status, now.Format(time.RFC3339))
	if err != nil {
		panic(err)
	}
}
