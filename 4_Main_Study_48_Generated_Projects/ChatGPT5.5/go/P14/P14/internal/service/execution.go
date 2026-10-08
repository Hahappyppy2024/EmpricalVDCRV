package service

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"

	"p14-agentic-platform/internal/models"
	"p14-agentic-platform/internal/repo"
)

// RunOutcome wraps a completed run record plus its step-by-step logs.
type RunOutcome struct {
	Run  map[string]any   `json:"run"`
	Logs []map[string]any `json:"logs"`
}

// ExecuteWorkflow runs every step of a workflow deterministically and records
// the run plus one run_log row per step (AGENT-04, AGENT-10).
func (s *Service) ExecuteWorkflow(user *models.User, wf *models.Workflow, trigger string) (*RunOutcome, error) {
	steps, err := parseSteps(wf.StepsJSON)
	if err != nil {
		return nil, badRequest("workflow steps are invalid")
	}

	started := s.now()
	runID, err := s.repo.Insert("runs", []string{"user_id", "workflow_id", "trigger", "status", "steps_json", "logs_json", "started_at", "finished_at"},
		[]any{user.ID, wf.ID, trigger, "running", wf.StepsJSON, "[]", started, ""})
	if err != nil {
		return nil, err
	}

	var results []map[string]any
	failed := false
	for _, st := range steps {
		if s.cfg.StepDelayMs > 0 {
			time.Sleep(time.Duration(s.cfg.StepDelayMs) * time.Millisecond)
		}
		stStatus := "success"
		var out string
		if failed {
			stStatus = "skipped"
			out = "skipped: a previous step failed"
		} else {
			out, err = s.runStep(user, wf, st)
			if err != nil {
				stStatus = "failed"
				out = fmt.Sprintf("step error: %s", err)
				failed = true
			} else if st.Fail {
				stStatus = "failed"
				out += "\n[error] step configured to fail deterministically"
				failed = true
			}
		}
		_, _ = s.repo.Insert("run_logs", []string{"user_id", "run_id", "step_name", "status", "output", "created_at"},
			[]any{user.ID, runID, st.Name, stStatus, out, s.now()})
		results = append(results, map[string]any{
			"name":       st.Name,
			"status":     stStatus,
			"output":     out,
			"created_at": s.now(),
		})
	}

	runStatus := "success"
	if failed {
		runStatus = "failed"
	}
	logsJSON, _ := json.Marshal(results)
	_, _ = s.repo.Update("runs", []string{"status", "logs_json", "finished_at"},
		[]any{runStatus, string(logsJSON), s.now()}, map[string]any{"id": runID})

	runRec, err := s.repo.Get(repo.SchemaOf("task_execution"), runID)
	if err != nil {
		return nil, err
	}
	return &RunOutcome{Run: runRec, Logs: results}, nil
}

func parseSteps(j string) ([]models.Step, error) {
	if strings.TrimSpace(j) == "" {
		return nil, nil
	}
	var steps []models.Step
	if err := json.Unmarshal([]byte(j), &steps); err != nil {
		return nil, err
	}
	return steps, nil
}

// runStep simulates one workflow step using a deterministic local adapter.
func (s *Service) runStep(user *models.User, wf *models.Workflow, st models.Step) (string, error) {
	switch strings.ToLower(st.Tool) {
	case "shell", "command", "run":
		return fmt.Sprintf("[runner] executing command %q\nstdout:\n  %s\n  %s",
			st.Command, deterministicLine(st.Name), deterministicLine(st.Command)), nil
	case "http":
		return s.httpStep(user, st)
	case "file-io", "file":
		size := deterministicInt(st.File) % 4096
		return fmt.Sprintf("[file-io] scoped workspace file %q (%d bytes)", st.File, size), nil
	case "secret-lookup", "secret":
		secret, err := s.findSecret(user.ID, st.Secret)
		if err != nil {
			return "", err
		}
		return fmt.Sprintf("[secret-lookup] resolved %q => %s", st.Secret, secret["masked_value"].(string)), nil
	case "email", "notify":
		to := st.Email
		if to == "" {
			to = "ops@p14.local"
		}
		return fmt.Sprintf("[notify] deterministic email notification queued to %s", to), nil
	default:
		return fmt.Sprintf("[step] %s completed", st.Name), nil
	}
}

func (s *Service) httpStep(user *models.User, st models.Step) (string, error) {
	target := st.URL
	if target == "" {
		action, err := s.findHTTPAction(user.ID, st.Name)
		if err != nil {
			return "", err
		}
		target = action["url"].(string)
	}
	method := strings.ToUpper(st.Method)
	if method == "" {
		method = "GET"
	}
	client := &http.Client{Timeout: 5 * time.Second}
	var body io.Reader
	if st.Payload != "" {
		body = strings.NewReader(st.Payload)
	}
	req, err := http.NewRequest(method, target, body)
	if err != nil {
		return "", fmt.Errorf("invalid request target: %w", err)
	}
	resp, err := client.Do(req)
	if err != nil {
		return "", fmt.Errorf("HTTP call to %s failed: %v", target, err)
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(io.LimitReader(resp.Body, 16*1024))
	if len(raw) > 2000 {
		raw = raw[:2000]
	}
	return fmt.Sprintf("[http] %s %s -> %d\n%s", method, target, resp.StatusCode, string(raw)), nil
}

func (s *Service) findSecret(userID int64, name string) (map[string]any, error) {
	rec, err := s.repo.Find(repo.SchemaOf("secrets_manager"), map[string]any{"user_id": userID, "name": name})
	if err != nil {
		return nil, fmt.Errorf("secret %q not found", name)
	}
	return rec, nil
}

func (s *Service) findHTTPAction(userID int64, name string) (map[string]any, error) {
	rec, err := s.repo.Find(repo.SchemaOf("external_http_action"), map[string]any{"user_id": userID, "name": name})
	if err != nil {
		return nil, fmt.Errorf("http action %q not found", name)
	}
	return rec, nil
}

// deterministicLine returns a stable, reproducible pseudo-output line.
func deterministicLine(seed string) string {
	h := sha256.Sum256([]byte(seed))
	return "out " + hex.EncodeToString(h[:4])
}

func deterministicInt(seed string) int {
	h := sha256.Sum256([]byte(seed))
	return int(h[0])<<8 | int(h[1])
}

// ReplayRun re-executes the workflow that produced an existing run (AGENT-10).
func (s *Service) ReplayRun(user *models.User, runID int64) (*RunOutcome, error) {
	var workflowID int64
	err := s.repo.QueryRow("SELECT workflow_id FROM runs WHERE id = ? AND user_id = ?", runID, user.ID).Scan(&workflowID)
	if err != nil {
		return nil, notFound("run not found")
	}
	wf, err := s.WorkflowByID(user.ID, workflowID)
	if err != nil {
		return nil, err
	}
	return s.ExecuteWorkflow(user, wf, "replay")
}

// TriggerWebhook executes the workflow attached to a public webhook token (AGENT-07).
func (s *Service) TriggerWebhook(token string) (*RunOutcome, error) {
	var userID, workflowID int64
	err := s.repo.QueryRow("SELECT user_id, workflow_id FROM webhooks WHERE token = ? AND enabled = 1", token).
		Scan(&userID, &workflowID)
	if err != nil {
		return nil, notFound("webhook not found or disabled")
	}
	owner, err := s.UserByID(userID)
	if err != nil {
		return nil, err
	}
	wf, err := s.WorkflowByID(owner.ID, workflowID)
	if err != nil {
		return nil, err
	}
	return s.ExecuteWorkflow(owner, wf, "webhook")
}

// RunDetail returns a run record and its ordered run_log rows (AGENT-10).
func (s *Service) RunDetail(user *models.User, runID int64) (map[string]any, []map[string]any, error) {
	rec, err := s.repo.Get(repo.SchemaOf("task_execution"), runID)
	if err != nil {
		return nil, nil, notFound("run not found")
	}
	if rec["user_id"].(int64) != user.ID {
		return nil, nil, notFound("run not found")
	}
	rows, err := s.repo.Query("SELECT id, user_id, run_id, step_name, status, output, created_at FROM run_logs WHERE run_id = ? ORDER BY id", runID)
	if err != nil {
		return nil, nil, err
	}
	defer rows.Close()
	var logs []map[string]any
	for rows.Next() {
		var id, uid, rid int64
		var step, status, output, created string
		if err := rows.Scan(&id, &uid, &rid, &step, &status, &output, &created); err != nil {
			return nil, nil, err
		}
		logs = append(logs, map[string]any{
			"id": id, "user_id": uid, "run_id": rid, "step_name": step,
			"status": status, "output": output, "created_at": created,
		})
	}
	return rec, logs, rows.Err()
}

// ExecuteHTTPAction calls a configured external endpoint with a bounded timeout
// and records the deterministic result (AGENT-08).
func (s *Service) ExecuteHTTPAction(user *models.User, id int64) (map[string]any, error) {
	rec, err := s.repo.Get(repo.SchemaOf("external_http_action"), id)
	if err != nil {
		return nil, notFound("http action not found")
	}
	if rec["user_id"].(int64) != user.ID {
		return nil, notFound("http action not found")
	}
	client := &http.Client{Timeout: 5 * time.Second}
	method := rec["method"].(string)
	var body io.Reader
	if payload, _ := rec["payload"].(string); payload != "" {
		body = strings.NewReader(payload)
	}
	req, err := http.NewRequest(method, rec["url"].(string), body)
	if err != nil {
		return nil, badRequest("invalid action url")
	}
	resp, err := client.Do(req)
	status := 0
	response := ""
	if err != nil {
		response = fmt.Sprintf("request failed: %v", err)
	} else {
		defer resp.Body.Close()
		status = resp.StatusCode
		raw, _ := io.ReadAll(io.LimitReader(resp.Body, 16*1024))
		if len(raw) > 2000 {
			raw = raw[:2000]
		}
		response = string(raw)
	}
	_, _ = s.repo.Update("http_actions", []string{"last_status", "last_response", "updated_at"},
		[]any{status, response, s.now()}, map[string]any{"id": id})
	s.Audit(user.ID, "http_action.execute", "http_actions", id2str(id), fmt.Sprintf("method=%s status=%d", method, status))
	return s.repo.Get(repo.SchemaOf("external_http_action"), id)
}
