package runner

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/anomalyco/p14-workflow-automation/internal/auth"
	"github.com/anomalyco/p14-workflow-automation/internal/config"
	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/anomalyco/p14-workflow-automation/internal/repo"
)

// Event represents a single log/event emitted during a workflow run.
type Event struct {
	RunID    string    `json:"run_id"`
	Level    string    `json:"level"`
	Message  string    `json:"message"`
	Time     time.Time `json:"time"`
	Sequence int64     `json:"seq"`
}

// Bus is a process-local subscriber queue for run events.
type Bus struct {
	mu          sync.Mutex
	subscribers map[string][]chan Event
	seq         int64
}

func NewBus() *Bus {
	return &Bus{subscribers: map[string][]chan Event{}}
}

func (b *Bus) Subscribe(runID string) (<-chan Event, func()) {
	ch := make(chan Event, 256)
	b.mu.Lock()
	b.subscribers[runID] = append(b.subscribers[runID], ch)
	b.mu.Unlock()
	cancel := func() {
		b.mu.Lock()
		list := b.subscribers[runID]
		out := list[:0]
		for _, c := range list {
			if c != ch {
				out = append(out, c)
			}
		}
		b.subscribers[runID] = out
		b.mu.Unlock()
		close(ch)
	}
	return ch, cancel
}

func (b *Bus) Publish(e Event) {
	b.mu.Lock()
	b.seq++
	e.Sequence = b.seq
	subs := append([]chan Event(nil), b.subscribers[e.RunID]...)
	b.mu.Unlock()
	for _, ch := range subs {
		select {
		case ch <- e:
		default:
		}
	}
}

// Runner executes workflow steps against a local sandbox.
type Runner struct {
	bus      *Bus
	cfg      *config.Config
	runs     *repo.Runs
	logs     *repo.RunLogs
	files    *repo.Files
	tools    *repo.Tools
	workflow *repo.Workflows
	http     *http.Client
}

func New(cfg *config.Config, bus *Bus, runs *repo.Runs, logs *repo.RunLogs, files *repo.Files, tools *repo.Tools, workflow *repo.Workflows) *Runner {
	return &Runner{
		bus:      bus,
		cfg:      cfg,
		runs:     runs,
		logs:     logs,
		files:    files,
		tools:    tools,
		workflow: workflow,
		http:     &http.Client{Timeout: cfg.HTTPFetchTimeout},
	}
}

func (r *Runner) Execute(parent context.Context, runID, userID, workflowID string) {
	ctx, cancel := context.WithTimeout(parent, 60*time.Second)
	defer cancel()

	if err := r.runs.SetStatus(ctx, runID, "running", "running"); err != nil {
		return
	}
	r.emit(ctx, userID, runID, "info", "run started")

	wf, err := r.workflow.ByID(ctx, workflowID)
	if err != nil || wf == nil {
		r.fail(ctx, userID, runID, "workflow not found")
		return
	}

	if len(wf.Steps) == 0 {
		r.fail(ctx, userID, runID, "workflow has no steps")
		return
	}

	for _, raw := range wf.Steps {
		seq := toInt(raw["seq"])
		name, _ := raw["name"].(string)
		toolName, _ := raw["tool"].(string)
		cfgMap := map[string]string{}
		cfgAny, _ := raw["config"].(map[string]any)
		for k, v := range cfgAny {
			cfgMap[k] = fmt.Sprint(v)
		}
		tool, err := r.tools.ByName(ctx, toolName)
		if err != nil || tool == nil {
			r.failStep(ctx, userID, runID, seq, name, "", fmt.Sprintf("tool %q not found", toolName))
			r.fail(ctx, userID, runID, "missing tool")
			return
		}
		if !tool.Enabled {
			r.failStep(ctx, userID, runID, seq, name, tool.ID, "tool disabled")
			r.fail(ctx, userID, runID, "tool disabled")
			return
		}
		stepID, err := r.runs.AddStep(ctx, runID, seq, name, tool.ID)
		if err != nil {
			r.fail(ctx, userID, runID, "failed to register step")
			return
		}
		_ = r.runs.StepStart(ctx, stepID)
		r.emit(ctx, userID, runID, "info", fmt.Sprintf("step %d start: %s (%s)", seq, name, tool.Kind))
		output, runErr := r.dispatch(ctx, userID, wf.ID, tool, cfgMap)
		if runErr != nil {
			_ = r.runs.StepFinish(ctx, stepID, "failed", output, runErr.Error())
			r.emit(ctx, userID, runID, "error", fmt.Sprintf("step %d failed: %v", seq, runErr))
			r.fail(ctx, userID, runID, fmt.Sprintf("step %d failed", seq))
			return
		}
		_ = r.runs.StepFinish(ctx, stepID, "success", output, "")
		r.emit(ctx, userID, runID, "info", fmt.Sprintf("step %d ok: %s", seq, truncate(output, 200)))
		time.Sleep(10 * time.Millisecond)
	}

	_ = r.runs.SetStatus(ctx, runID, "success", "all steps completed")
	r.emit(ctx, userID, runID, "info", "run completed successfully")
}

func (r *Runner) dispatch(ctx context.Context, userID, wfID string, tool *models.Tool, cfg map[string]string) (string, error) {
	switch tool.Kind {
	case "log":
		level := cfg["level"]
		if level == "" {
			level = "info"
		}
		msg := cfg["message"]
		if msg == "" {
			msg = "log step"
		}
		return msg, nil
	case "delay":
		ms, _ := strconv.Atoi(cfg["ms"])
		if ms < 0 {
			ms = 0
		}
		if ms > 5000 {
			ms = 5000
		}
		time.Sleep(time.Duration(ms) * time.Millisecond)
		return fmt.Sprintf("delayed %dms", ms), nil
	case "shell":
		if os.Getenv("P14_ALLOW_SHELL") != "true" {
			return "", errors.New("shell tool disabled (set P14_ALLOW_SHELL=true)")
		}
		cmd := cfg["command"]
		if cmd == "" {
			return "", errors.New("missing command")
		}
		parts := strings.Fields(cmd)
		if len(parts) == 0 {
			return "", errors.New("empty command")
		}
		c := exec.CommandContext(ctx, parts[0], parts[1:]...)
		var buf bytes.Buffer
		c.Stdout = &buf
		c.Stderr = &buf
		if err := c.Run(); err != nil {
			return buf.String(), err
		}
		return buf.String(), nil
	case "file_write":
		path := cfg["path"]
		content := cfg["content"]
		if path == "" {
			return "", errors.New("missing path")
		}
		if err := os.MkdirAll(filepath.Join(r.cfg.WorkspaceRoot, userID, filepath.Dir(path)), 0o755); err != nil {
			return "", err
		}
		full := filepath.Join(r.cfg.WorkspaceRoot, userID, path)
		if err := os.WriteFile(full, []byte(content), 0o644); err != nil {
			return "", err
		}
		// mirror into workspace_files table
		existing, _ := r.files.ByPath(ctx, userID, path)
		if existing == nil {
			if _, err := r.files.Create(ctx, userID, wfID, path, content, "text/plain"); err != nil {
				return "", err
			}
		} else {
			if err := r.files.Update(ctx, existing.ID, content); err != nil {
				return "", err
			}
		}
		return fmt.Sprintf("wrote %d bytes to %s", len(content), path), nil
	case "file_read":
		path := cfg["path"]
		if path == "" {
			return "", errors.New("missing path")
		}
		full := filepath.Join(r.cfg.WorkspaceRoot, userID, path)
		b, err := os.ReadFile(full)
		if err != nil {
			return "", err
		}
		return string(b), nil
	case "http":
		url := cfg["url"]
		method := strings.ToUpper(cfg["method"])
		if method == "" {
			method = "GET"
		}
		payload := cfg["payload"]
		if url == "" {
			return "", errors.New("missing url")
		}
		var body io.Reader
		if payload != "" && method != "GET" && method != "HEAD" {
			body = bytes.NewReader([]byte(payload))
		}
		req, err := http.NewRequestWithContext(ctx, method, url, body)
		if err != nil {
			return "", err
		}
		if body != nil {
			req.Header.Set("Content-Type", "application/json")
		}
		req.Header.Set("User-Agent", "p14-runner/1.0")
		resp, err := r.http.Do(req)
		if err != nil {
			return "", err
		}
		defer resp.Body.Close()
		raw, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		return fmt.Sprintf("%d %s: %s", resp.StatusCode, resp.Status, string(raw)), nil
	}
	return "", fmt.Errorf("unsupported tool kind %q", tool.Kind)
}

func (r *Runner) emit(ctx context.Context, userID, runID, level, msg string) {
	r.bus.Publish(Event{RunID: runID, Level: level, Message: msg, Time: time.Now().UTC()})
	_, _ = r.logs.Append(ctx, userID, runID, level, msg)
}

func (r *Runner) fail(ctx context.Context, userID, runID, reason string) {
	_ = r.runs.SetStatus(ctx, runID, "failed", reason)
	r.emit(ctx, userID, runID, "error", "run failed: "+reason)
}

func (r *Runner) failStep(ctx context.Context, userID, runID string, seq int, name, toolID, msg string) {
	r.emit(ctx, userID, runID, "error", fmt.Sprintf("step %d aborted: %s", seq, msg))
}

func toInt(v any) int {
	switch t := v.(type) {
	case float64:
		return int(t)
	case int:
		return t
	case string:
		n, _ := strconv.Atoi(t)
		return n
	}
	return 0
}

func truncate(s string, n int) string {
	if len(s) <= n {
		return s
	}
	return s[:n] + "..."
}

func init() {
	// ensure Bus mutex is usable
	_ = json.Marshal
	_ = auth.RandomToken
}