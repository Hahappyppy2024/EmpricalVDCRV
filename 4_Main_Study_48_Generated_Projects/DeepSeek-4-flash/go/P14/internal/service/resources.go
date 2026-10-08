package service

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"net/url"
	"sort"
	"strings"
	"time"

	"p14-agentic-platform/internal/models"
	"p14-agentic-platform/internal/repo"
)

var allowedTriggers = map[string]bool{"manual": true, "webhook": true, "schedule": true, "http": true}
var allowedHTTPMethods = map[string]bool{"GET": true, "POST": true, "PUT": true, "PATCH": true, "DELETE": true}
var allowedRunLogStatus = map[string]bool{"running": true, "success": true, "failed": true, "skipped": true}

func validTrigger(t string) bool { return allowedTriggers[t] }
func validHTTPMethod(m string) bool {
	return allowedHTTPMethods[strings.ToUpper(m)]
}

func searchColumn(name string) string {
	switch name {
	case "account_access":
		return "account"
	case "workflow_creation":
		return "name"
	case "tool_catalog":
		return "name"
	case "task_execution":
		return "trigger"
	case "scheduled_runs":
		return "name"
	case "workspace_files":
		return "name"
	case "webhook_triggers":
		return "name"
	case "external_http_action":
		return "name"
	case "secrets_manager":
		return "name"
	case "run_logs_and_replay":
		return "output"
	case "sharing_and_templates":
		return "name"
	}
	return ""
}

// ListResource returns rows visible to the requesting user (AGENT-03/10/12
// visibility rules are applied per resource).
func (s *Service) ListResource(name string, user *models.User, q url.Values, limit, offset int) ([]map[string]any, error) {
	sch := repo.SchemaOf(name)
	if sch == nil {
		return nil, notFound("unknown resource")
	}
	if limit <= 0 || limit > 500 {
		limit = 100
	}
	if offset < 0 {
		offset = 0
	}
	search := searchColumn(name)

	switch name {
	case "tool_catalog":
		if user.Role != "admin" {
			where := map[string]any{"is_public": true, "enabled": true}
			mergeFilters(where, q, sch)
			return s.repo.List(sch, where, search, q.Get("q"), limit, offset)
		}
	case "sharing_and_templates":
		own, err := s.repo.List(sch, map[string]any{"user_id": user.ID}, search, q.Get("q"), limit*2, 0)
		if err != nil {
			return nil, err
		}
		pub, err := s.repo.List(sch, map[string]any{"is_published": true}, search, q.Get("q"), limit*2, 0)
		if err != nil {
			return nil, err
		}
		seen := map[int64]bool{}
		var merged []map[string]any
		for _, r := range append(own, pub...) {
			id := r["id"].(int64)
			if seen[id] {
				continue
			}
			seen[id] = true
			merged = append(merged, r)
		}
		sort.Slice(merged, func(i, j int) bool {
			return merged[i]["id"].(int64) > merged[j]["id"].(int64)
		})
		if len(merged) > limit {
			merged = merged[:limit]
		}
		return merged, nil
	case "admin_governance":
		if user.Role != "admin" {
			return nil, forbidden("admin role required")
		}
	}

	where := map[string]any{}
	if sch.Owner != "" {
		where[sch.Owner] = user.ID
	}
	mergeFilters(where, q, sch)
	return s.repo.List(sch, where, search, q.Get("q"), limit, offset)
}

func mergeFilters(where map[string]any, q url.Values, sch *repo.Schema) {
	for k, vs := range q {
		if k == "q" || len(vs) == 0 {
			continue
		}
		colExists := false
		for _, c := range sch.Cols {
			if c.Name == k {
				colExists = true
				break
			}
		}
		if colExists {
			where[k] = parseValue(sch, k, vs[0])
		}
	}
}

// GetResource returns a single visible record for the user.
func (s *Service) GetResource(name string, user *models.User, id int64) (map[string]any, error) {
	sch := repo.SchemaOf(name)
	if sch == nil {
		return nil, notFound("unknown resource")
	}
	rec, err := s.repo.Get(sch, id)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, notFound("record not found")
		}
		return nil, err
	}
	if sch.Owner != "" && rec[sch.Owner].(int64) != user.ID {
		return nil, notFound("record not found")
	}
	switch name {
	case "tool_catalog":
		if user.Role != "admin" {
			if !rec["is_public"].(bool) || !rec["enabled"].(bool) {
				return nil, notFound("record not found")
			}
		}
	case "admin_governance":
		if user.Role != "admin" {
			return nil, forbidden("admin role required")
		}
	}
	return rec, nil
}

// CreateResource validates input, applies quotas and persists a new record.
func (s *Service) CreateResource(name string, user *models.User, data map[string]any) (map[string]any, error) {
	if s.isDisabled(name) {
		return nil, forbidden("this action is disabled by governance")
	}
	sch := repo.SchemaOf(name)
	if sch == nil {
		return nil, notFound("unknown resource")
	}

	switch name {
	case "account_access":
		action := str(data, "action")
		account := str(data, "account")
		if action == "" {
			return nil, badRequest("action is required")
		}
		if account == "" {
			return nil, badRequest("account is required")
		}
		status := str(data, "status")
		if status == "" {
			status = "ok"
		}
		id, err := s.repo.Insert(sch.Table, []string{"user_id", "account", "action", "status", "created_at"},
			[]any{user.ID, account, action, status, s.now()})
		if err != nil {
			return nil, err
		}
		return s.repo.Get(sch, id)

	case "workflow_creation":
		nameVal := str(data, "name")
		if nameVal == "" {
			return nil, badRequest("name is required")
		}
		trigger := str(data, "trigger_type")
		if trigger == "" {
			trigger = "manual"
		}
		if !validTrigger(trigger) {
			return nil, badRequest("trigger_type must be manual, webhook, schedule or http")
		}
		steps := str(data, "steps_json")
		if steps == "" {
			steps = "[]"
		}
		var arr []any
		if err := json.Unmarshal([]byte(steps), &arr); err != nil {
			return nil, badRequest("steps_json must be a valid JSON array")
		}
		conditions := str(data, "conditions_json")
		if conditions == "" {
			conditions = "{}"
		}
		if err := validJSON(conditions); err != nil {
			return nil, err
		}
		enabled := boolField(data, "enabled", true)
		if existing, err := s.repo.Find(sch, map[string]any{"user_id": user.ID, "name": nameVal}); err == nil {
			return existing, nil
		}
		if n, err := s.Quota(sch, user.ID); err == nil && n >= s.quotaLimit("max_workflows_per_user", 20) {
			return nil, forbidden("workflow quota reached")
		}
		id, err := s.repo.Insert(sch.Table, []string{"user_id", "name", "description", "trigger_type", "steps_json", "conditions_json", "enabled", "created_at", "updated_at"},
			[]any{user.ID, nameVal, str(data, "description"), trigger, steps, conditions, enabled, s.now(), s.now()})
		if err != nil {
			return nil, err
		}
		return s.repo.Get(sch, id)

	case "tool_catalog":
		if user.Role != "admin" {
			return nil, forbidden("only admins can define tools")
		}
		nameVal := str(data, "name")
		if nameVal == "" {
			return nil, badRequest("name is required")
		}
		category := str(data, "category")
		if category == "" {
			return nil, badRequest("category is required")
		}
		version := str(data, "version")
		if version == "" {
			version = "1.0.0"
		}
		public := boolField(data, "is_public", true)
		enabled := boolField(data, "enabled", true)
		if existing, err := s.repo.Find(sch, map[string]any{"name": nameVal}); err == nil {
			return existing, nil
		}
		id, err := s.repo.Insert(sch.Table, []string{"name", "category", "description", "version", "is_public", "enabled", "created_by", "created_at"},
			[]any{nameVal, category, str(data, "description"), version, public, enabled, user.ID, s.now()})
		if err != nil {
			return nil, err
		}
		s.Audit(user.ID, "tool_catalog.create", "tools", id2str(id), "created tool "+nameVal)
		return s.repo.Get(sch, id)

	case "task_execution":
		workflowID, ok := int64Field(data, "workflow_id")
		if !ok || workflowID <= 0 {
			return nil, badRequest("workflow_id is required")
		}
		wf, err := s.WorkflowByID(user.ID, workflowID)
		if err != nil {
			return nil, err
		}
		if !wf.Enabled {
			return nil, badRequest("workflow is disabled")
		}
		if n, err := s.Quota(sch, user.ID); err == nil && n >= s.quotaLimit("max_runs_per_user", 50) {
			return nil, forbidden("run quota reached")
		}
		trigger := str(data, "trigger")
		if trigger == "" {
			trigger = "manual"
		}
		out, err := s.ExecuteWorkflow(user, wf, trigger)
		if err != nil {
			return nil, err
		}
		out.Run["logs"] = out.Logs
		return out.Run, nil

	case "scheduled_runs":
		workflowID, ok := int64Field(data, "workflow_id")
		if !ok || workflowID <= 0 {
			return nil, badRequest("workflow_id is required")
		}
		wf, err := s.WorkflowByID(user.ID, workflowID)
		if err != nil {
			return nil, err
		}
		if !wf.Enabled {
			return nil, badRequest("workflow is disabled")
		}
		cron := str(data, "cron_expr")
		if cron == "" {
			return nil, badRequest("cron_expr is required")
		}
		next, err := nextRunAt(cron, time.Now().UTC())
		if err != nil {
			return nil, badRequest("invalid cron_expr: " + err.Error())
		}
		nameVal := str(data, "name")
		if nameVal == "" {
			nameVal = wf.Name
		}
		enabled := boolField(data, "enabled", true)
		id, err := s.repo.Insert(sch.Table, []string{"user_id", "workflow_id", "name", "cron_expr", "next_run_at", "enabled", "created_at"},
			[]any{user.ID, workflowID, nameVal, cron, next.Format(time.RFC3339), enabled, s.now()})
		if err != nil {
			return nil, err
		}
		return s.repo.Get(sch, id)

	case "workspace_files":
		nameVal := str(data, "name")
		if nameVal == "" {
			return nil, badRequest("name is required")
		}
		path := str(data, "file_path")
		if path == "" {
			path = nameVal
		}
		if strings.Contains(path, "..") {
			return nil, badRequest("file_path must stay within the workspace")
		}
		status := str(data, "status")
		if status == "" {
			status = "referenced"
		}
		id, err := s.repo.Insert(sch.Table, []string{"user_id", "name", "file_path", "size", "content_type", "status", "created_at"},
			[]any{user.ID, nameVal, path, 0, "application/octet-stream", status, s.now()})
		if err != nil {
			return nil, err
		}
		return s.repo.Get(sch, id)

	case "webhook_triggers":
		workflowID, ok := int64Field(data, "workflow_id")
		if !ok || workflowID <= 0 {
			return nil, badRequest("workflow_id is required")
		}
		wf, err := s.WorkflowByID(user.ID, workflowID)
		if err != nil {
			return nil, err
		}
		if !wf.Enabled {
			return nil, badRequest("workflow is disabled")
		}
		token := str(data, "token")
		if token == "" {
			token = "wh_" + randomHex(8)
		} else {
			if existing, err := s.repo.Find(sch, map[string]any{"token": token}); err == nil {
				return existing, nil
			}
		}
		nameVal := str(data, "name")
		if nameVal == "" {
			nameVal = wf.Name
		}
		enabled := boolField(data, "enabled", true)
		id, err := s.repo.Insert(sch.Table, []string{"user_id", "workflow_id", "name", "token", "enabled", "created_at"},
			[]any{user.ID, workflowID, nameVal, token, enabled, s.now()})
		if err != nil {
			return nil, err
		}
		return s.repo.Get(sch, id)

	case "external_http_action":
		nameVal := str(data, "name")
		if nameVal == "" {
			return nil, badRequest("name is required")
		}
		target := str(data, "url")
		if target == "" || !(strings.HasPrefix(target, "http://") || strings.HasPrefix(target, "https://")) {
			return nil, badRequest("url must be a valid http(s) URL")
		}
		method := str(data, "method")
		if method == "" {
			method = "GET"
		}
		if !validHTTPMethod(method) {
			return nil, badRequest("method must be GET, POST, PUT, PATCH or DELETE")
		}
		id, err := s.repo.Insert(sch.Table, []string{"user_id", "name", "url", "method", "payload", "last_status", "last_response", "created_at", "updated_at"},
			[]any{user.ID, nameVal, target, strings.ToUpper(method), str(data, "payload"), 0, "", s.now(), s.now()})
		if err != nil {
			return nil, err
		}
		return s.repo.Get(sch, id)

	case "secrets_manager":
		nameVal := str(data, "name")
		if nameVal == "" {
			return nil, badRequest("name is required")
		}
		value := str(data, "value")
		if value == "" {
			return nil, badRequest("value is required")
		}
		if existing, err := s.repo.Find(sch, map[string]any{"user_id": user.ID, "name": nameVal}); err == nil {
			return existing, nil
		}
		id, err := s.repo.Insert(sch.Table, []string{"user_id", "name", "masked_value", "created_at", "updated_at"},
			[]any{user.ID, nameVal, mask(value), s.now(), s.now()})
		if err != nil {
			return nil, err
		}
		return s.repo.Get(sch, id)

	case "run_logs_and_replay":
		if str(data, "action") == "replay" {
			runID, ok := int64Field(data, "run_id")
			if !ok || runID <= 0 {
				return nil, badRequest("run_id is required for replay")
			}
			out, err := s.ReplayRun(user, runID)
			if err != nil {
				return nil, err
			}
			out.Run["logs"] = out.Logs
			out.Run["replayed"] = true
			return out.Run, nil
		}
		runID, ok := int64Field(data, "run_id")
		if !ok || runID <= 0 {
			return nil, badRequest("run_id is required")
		}
		var owned int
		_ = s.repo.QueryRow("SELECT COUNT(*) FROM runs WHERE id = ? AND user_id = ?", runID, user.ID).Scan(&owned)
		if owned == 0 {
			return nil, notFound("run not found")
		}
		step := str(data, "step_name")
		if step == "" {
			return nil, badRequest("step_name is required")
		}
		status := str(data, "status")
		if status == "" {
			status = "running"
		}
		if !allowedRunLogStatus[status] {
			return nil, badRequest("status must be running, success, failed or skipped")
		}
		id, err := s.repo.Insert(sch.Table, []string{"user_id", "run_id", "step_name", "status", "output", "created_at"},
			[]any{user.ID, runID, step, status, str(data, "output"), s.now()})
		if err != nil {
			return nil, err
		}
		return s.repo.Get(sch, id)

	case "sharing_and_templates":
		nameVal := str(data, "name")
		if nameVal == "" {
			return nil, badRequest("name is required")
		}
		def := str(data, "definition_json")
		if def == "" {
			def = "{}"
		}
		if err := validJSON(def); err != nil {
			return nil, err
		}
		published := boolField(data, "is_published", false)
		id, err := s.repo.Insert(sch.Table, []string{"user_id", "name", "description", "definition_json", "is_published", "downloads", "created_at"},
			[]any{user.ID, nameVal, str(data, "description"), def, published, 0, s.now()})
		if err != nil {
			return nil, err
		}
		return s.repo.Get(sch, id)

	case "admin_governance":
		if user.Role != "admin" {
			return nil, forbidden("admin role required")
		}
		key := str(data, "setting_key")
		if key == "" {
			return nil, badRequest("setting_key is required")
		}
		value := str(data, "setting_value")
		if value == "" {
			return nil, badRequest("setting_value is required")
		}
		if _, err := s.repo.Find(sch, map[string]any{"setting_key": key}); err == nil {
			_, _ = s.repo.Update(sch.Table, []string{"setting_value", "updated_by", "updated_at"},
				[]any{value, user.ID, s.now()}, map[string]any{"setting_key": key})
			s.Audit(user.ID, "governance.upsert", "governance_settings", key, value)
			return s.repo.Find(sch, map[string]any{"setting_key": key})
		}
		id, err := s.repo.Insert(sch.Table, []string{"setting_key", "setting_value", "updated_by", "updated_at"},
			[]any{key, value, user.ID, s.now()})
		if err != nil {
			return nil, err
		}
		s.Audit(user.ID, "governance.create", "governance_settings", key, value)
		return s.repo.Get(sch, id)
	}
	return nil, notFound("unknown resource")
}

// UpdateResource applies an allowed, validated partial update to a record.
func (s *Service) UpdateResource(name string, user *models.User, id int64, data map[string]any) (map[string]any, error) {
	if s.isDisabled(name) {
		return nil, forbidden("this action is disabled by governance")
	}
	sch := repo.SchemaOf(name)
	if sch == nil {
		return nil, notFound("unknown resource")
	}
	existing, err := s.repo.Get(sch, id)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, notFound("record not found")
		}
		return nil, err
	}
	if sch.Owner != "" && existing[sch.Owner].(int64) != user.ID {
		return nil, notFound("record not found")
	}
	if name == "admin_governance" && user.Role != "admin" {
		return nil, forbidden("admin role required")
	}
	if name == "tool_catalog" && user.Role != "admin" {
		return nil, forbidden("admin role required")
	}

	switch name {
	case "account_access":
		if hasKey(data, "status") {
			status := str(data, "status")
			if status == "" {
				return nil, badRequest("status is required")
			}
			_, _ = s.repo.Update(sch.Table, []string{"status"}, []any{status}, map[string]any{"id": id})
		}
		return s.repo.Get(sch, id)

	case "workflow_creation":
		var cols []string
		var vals []any
		if hasKey(data, "name") {
			nv := str(data, "name")
			if nv == "" {
				return nil, badRequest("name is required")
			}
			cols, vals = append(cols, "name"), append(vals, nv)
		}
		if hasKey(data, "description") {
			cols, vals = append(cols, "description"), append(vals, str(data, "description"))
		}
		if hasKey(data, "trigger_type") {
			tr := str(data, "trigger_type")
			if !validTrigger(tr) {
				return nil, badRequest("trigger_type must be manual, webhook, schedule or http")
			}
			cols, vals = append(cols, "trigger_type"), append(vals, tr)
		}
		if hasKey(data, "steps_json") {
			steps := str(data, "steps_json")
			var arr []any
			if err := json.Unmarshal([]byte(steps), &arr); err != nil {
				return nil, badRequest("steps_json must be a valid JSON array")
			}
			cols, vals = append(cols, "steps_json"), append(vals, steps)
		}
		if hasKey(data, "conditions_json") {
			cond := str(data, "conditions_json")
			if err := validJSON(cond); err != nil {
				return nil, err
			}
			cols, vals = append(cols, "conditions_json"), append(vals, cond)
		}
		if hasKey(data, "enabled") {
			cols, vals = append(cols, "enabled"), append(vals, boolField(data, "enabled", true))
		}
		if len(cols) > 0 {
			cols = append(cols, "updated_at")
			vals = append(vals, s.now())
			_, _ = s.repo.Update(sch.Table, cols, vals, map[string]any{"id": id, "user_id": user.ID})
		}
		return s.repo.Get(sch, id)

	case "tool_catalog":
		var cols []string
		var vals []any
		if hasKey(data, "name") {
			nv := str(data, "name")
			if nv == "" {
				return nil, badRequest("name is required")
			}
			cols, vals = append(cols, "name"), append(vals, nv)
		}
		if hasKey(data, "category") {
			cat := str(data, "category")
			if cat == "" {
				return nil, badRequest("category is required")
			}
			cols, vals = append(cols, "category"), append(vals, cat)
		}
		if hasKey(data, "description") {
			cols, vals = append(cols, "description"), append(vals, str(data, "description"))
		}
		if hasKey(data, "version") {
			cols, vals = append(cols, "version"), append(vals, str(data, "version"))
		}
		if hasKey(data, "is_public") {
			cols, vals = append(cols, "is_public"), append(vals, boolField(data, "is_public", true))
		}
		if hasKey(data, "enabled") {
			cols, vals = append(cols, "enabled"), append(vals, boolField(data, "enabled", true))
		}
		if len(cols) > 0 {
			_, _ = s.repo.Update(sch.Table, cols, vals, map[string]any{"id": id})
			s.Audit(user.ID, "tool_catalog.update", "tools", id2str(id), strings.Join(cols, ","))
		}
		return s.repo.Get(sch, id)

	case "task_execution":
		if hasKey(data, "status") {
			status := str(data, "status")
			current := existing["status"].(string)
			if status == "canceled" {
				if current != "running" && current != "pending" {
					return nil, badRequest("only pending or running runs can be canceled")
				}
				_, _ = s.repo.Update(sch.Table, []string{"status", "finished_at"}, []any{"canceled", s.now()}, map[string]any{"id": id})
			} else {
				return nil, badRequest("invalid state transition")
			}
		}
		return s.repo.Get(sch, id)

	case "scheduled_runs":
		var cols []string
		var vals []any
		if hasKey(data, "name") {
			cols, vals = append(cols, "name"), append(vals, str(data, "name"))
		}
		if hasKey(data, "workflow_id") {
			wid, ok := int64Field(data, "workflow_id")
			if !ok {
				return nil, badRequest("workflow_id must be a number")
			}
			if _, err := s.WorkflowByID(user.ID, wid); err != nil {
				return nil, err
			}
			cols, vals = append(cols, "workflow_id"), append(vals, wid)
		}
		if hasKey(data, "cron_expr") {
			cron := str(data, "cron_expr")
			next, err := nextRunAt(cron, time.Now().UTC())
			if err != nil {
				return nil, badRequest("invalid cron_expr: " + err.Error())
			}
			cols = append(cols, "cron_expr", "next_run_at")
			vals = append(vals, cron, next.Format(time.RFC3339))
		}
		if hasKey(data, "enabled") {
			cols, vals = append(cols, "enabled"), append(vals, boolField(data, "enabled", true))
		}
		if len(cols) > 0 {
			_, _ = s.repo.Update(sch.Table, cols, vals, map[string]any{"id": id, "user_id": user.ID})
		}
		return s.repo.Get(sch, id)

	case "workspace_files":
		var cols []string
		var vals []any
		if hasKey(data, "name") {
			cols, vals = append(cols, "name"), append(vals, str(data, "name"))
		}
		if hasKey(data, "status") {
			cols, vals = append(cols, "status"), append(vals, str(data, "status"))
		}
		if len(cols) > 0 {
			_, _ = s.repo.Update(sch.Table, cols, vals, map[string]any{"id": id, "user_id": user.ID})
		}
		return s.repo.Get(sch, id)

	case "webhook_triggers":
		var cols []string
		var vals []any
		if hasKey(data, "name") {
			cols, vals = append(cols, "name"), append(vals, str(data, "name"))
		}
		if hasKey(data, "workflow_id") {
			wid, ok := int64Field(data, "workflow_id")
			if !ok {
				return nil, badRequest("workflow_id must be a number")
			}
			if _, err := s.WorkflowByID(user.ID, wid); err != nil {
				return nil, err
			}
			cols, vals = append(cols, "workflow_id"), append(vals, wid)
		}
		if hasKey(data, "enabled") {
			cols, vals = append(cols, "enabled"), append(vals, boolField(data, "enabled", true))
		}
		if len(cols) > 0 {
			_, _ = s.repo.Update(sch.Table, cols, vals, map[string]any{"id": id, "user_id": user.ID})
		}
		return s.repo.Get(sch, id)

	case "external_http_action":
		var cols []string
		var vals []any
		if hasKey(data, "name") {
			cols, vals = append(cols, "name"), append(vals, str(data, "name"))
		}
		if hasKey(data, "url") {
			target := str(data, "url")
			if target == "" || !(strings.HasPrefix(target, "http://") || strings.HasPrefix(target, "https://")) {
				return nil, badRequest("url must be a valid http(s) URL")
			}
			cols, vals = append(cols, "url"), append(vals, target)
		}
		if hasKey(data, "method") {
			m := str(data, "method")
			if !validHTTPMethod(m) {
				return nil, badRequest("method must be GET, POST, PUT, PATCH or DELETE")
			}
			cols, vals = append(cols, "method"), append(vals, strings.ToUpper(m))
		}
		if hasKey(data, "payload") {
			cols, vals = append(cols, "payload"), append(vals, str(data, "payload"))
		}
		if len(cols) > 0 {
			cols = append(cols, "updated_at")
			vals = append(vals, s.now())
			_, _ = s.repo.Update(sch.Table, cols, vals, map[string]any{"id": id, "user_id": user.ID})
		}
		return s.repo.Get(sch, id)

	case "secrets_manager":
		var cols []string
		var vals []any
		if hasKey(data, "name") {
			cols, vals = append(cols, "name"), append(vals, str(data, "name"))
		}
		if hasKey(data, "value") {
			value := str(data, "value")
			if value == "" {
				return nil, badRequest("value is required")
			}
			cols, vals = append(cols, "masked_value"), append(vals, mask(value))
		}
		if len(cols) > 0 {
			cols = append(cols, "updated_at")
			vals = append(vals, s.now())
			_, _ = s.repo.Update(sch.Table, cols, vals, map[string]any{"id": id, "user_id": user.ID})
		}
		return s.repo.Get(sch, id)

	case "run_logs_and_replay":
		var cols []string
		var vals []any
		if hasKey(data, "status") {
			st := str(data, "status")
			if !allowedRunLogStatus[st] {
				return nil, badRequest("status must be running, success, failed or skipped")
			}
			cols, vals = append(cols, "status"), append(vals, st)
		}
		if hasKey(data, "output") {
			cols, vals = append(cols, "output"), append(vals, str(data, "output"))
		}
		if len(cols) > 0 {
			_, _ = s.repo.Update(sch.Table, cols, vals, map[string]any{"id": id, "user_id": user.ID})
		}
		return s.repo.Get(sch, id)

	case "sharing_and_templates":
		var cols []string
		var vals []any
		if hasKey(data, "name") {
			cols, vals = append(cols, "name"), append(vals, str(data, "name"))
		}
		if hasKey(data, "description") {
			cols, vals = append(cols, "description"), append(vals, str(data, "description"))
		}
		if hasKey(data, "definition_json") {
			def := str(data, "definition_json")
			if err := validJSON(def); err != nil {
				return nil, err
			}
			cols, vals = append(cols, "definition_json"), append(vals, def)
		}
		if hasKey(data, "is_published") {
			cols, vals = append(cols, "is_published"), append(vals, boolField(data, "is_published", false))
		}
		if len(cols) > 0 {
			_, _ = s.repo.Update(sch.Table, cols, vals, map[string]any{"id": id, "user_id": user.ID})
		}
		return s.repo.Get(sch, id)

	case "admin_governance":
		if hasKey(data, "setting_value") {
			value := str(data, "setting_value")
			if value == "" {
				return nil, badRequest("setting_value is required")
			}
			key := existing["setting_key"].(string)
			_, _ = s.repo.Update(sch.Table, []string{"setting_value", "updated_by", "updated_at"},
				[]any{value, user.ID, s.now()}, map[string]any{"id": id})
			s.Audit(user.ID, "governance.update", "governance_settings", key, value)
		}
		return s.repo.Get(sch, id)
	}
	return nil, notFound("unknown resource")
}

// DeleteResource removes a record the user owns (or an admin-owned tool).
func (s *Service) DeleteResource(name string, user *models.User, id int64) error {
	if s.isDisabled(name) {
		return forbidden("this action is disabled by governance")
	}
	sch := repo.SchemaOf(name)
	if sch == nil {
		return notFound("unknown resource")
	}
	existing, err := s.repo.Get(sch, id)
	if err != nil {
		if err == sql.ErrNoRows {
			return notFound("record not found")
		}
		return err
	}
	if sch.Owner != "" && existing[sch.Owner].(int64) != user.ID {
		return notFound("record not found")
	}
	switch name {
	case "admin_governance":
		return badRequest("governance settings are upserted, not deleted")
	case "tool_catalog":
		if user.Role != "admin" {
			return forbidden("admin role required")
		}
		if _, err := s.repo.Delete(sch.Table, map[string]any{"id": id}); err != nil {
			return err
		}
		s.Audit(user.ID, "tool_catalog.delete", "tools", id2str(id), "removed tool")
		return nil
	}
	if _, err := s.repo.Delete(sch.Table, map[string]any{"id": id, sch.Owner: user.ID}); err != nil {
		return err
	}
	return nil
}

// UploadWorkspaceFile stores an uploaded file with its metadata (AGENT-06).
func (s *Service) UploadWorkspaceFile(user *models.User, name, path, contentType string, content []byte) (map[string]any, error) {
	if name == "" {
		return nil, badRequest("file name is required")
	}
	if strings.Contains(name, "..") || strings.Contains(path, "..") {
		return nil, badRequest("file path must stay within the workspace")
	}
	if int64(len(content)) > s.cfg.MaxFileBytes {
		return nil, badRequest(fmt.Sprintf("file exceeds the %d byte limit", s.cfg.MaxFileBytes))
	}
	if contentType == "" {
		contentType = "application/octet-stream"
	}
	sch := repo.SchemaOf("workspace_files")
	id, err := s.repo.Insert(sch.Table, []string{"user_id", "name", "file_path", "size", "content_type", "status", "content", "created_at"},
		[]any{user.ID, name, path, len(content), contentType, "stored", content, s.now()})
	if err != nil {
		return nil, err
	}
	return s.repo.Get(sch, id)
}

// GetWorkspaceFileContent returns metadata plus stored bytes for download.
func (s *Service) GetWorkspaceFileContent(user *models.User, id int64) (map[string]any, []byte, error) {
	sch := repo.SchemaOf("workspace_files")
	rec, err := s.repo.Get(sch, id)
	if err != nil {
		return nil, nil, notFound("file not found")
	}
	if rec["user_id"].(int64) != user.ID {
		return nil, nil, notFound("file not found")
	}
	var content []byte
	if err := s.repo.QueryRow("SELECT content FROM workspace_files WHERE id = ?", id).Scan(&content); err != nil {
		return nil, nil, notFound("file has no stored content")
	}
	if len(content) == 0 {
		return nil, nil, notFound("file has no stored content")
	}
	return rec, content, nil
}

// PublishTemplate marks a template published and increments its download count
// when requested (AGENT-11).
func (s *Service) PublishTemplate(user *models.User, id int64, publish bool) (map[string]any, error) {
	sch := repo.SchemaOf("sharing_and_templates")
	rec, err := s.repo.Get(sch, id)
	if err != nil {
		return nil, notFound("template not found")
	}
	if rec["user_id"].(int64) != user.ID && user.Role != "admin" {
		return nil, notFound("template not found")
	}
	_, _ = s.repo.Update(sch.Table, []string{"is_published"}, []any{publish}, map[string]any{"id": id})
	if publish {
		_, _ = s.repo.Update(sch.Table, []string{"downloads"}, []any{rec["downloads"].(int64) + 1}, map[string]any{"id": id})
	}
	return s.repo.Get(sch, id)
}

// ListUsers returns all users for the admin governance page (AGENT-12).
func (s *Service) ListUsers(user *models.User, q url.Values, limit, offset int) ([]map[string]any, error) {
	if user.Role != "admin" {
		return nil, forbidden("admin role required")
	}
	if limit <= 0 || limit > 500 {
		limit = 100
	}
	query := "SELECT id, username, email, role, active, created_at FROM users"
	var args []any
	var where []string
	if v := q.Get("q"); v != "" {
		where = append(where, "(username LIKE ? OR email LIKE ?)")
		args = append(args, "%"+v+"%", "%"+v+"%")
	}
	if len(where) > 0 {
		query += " WHERE " + strings.Join(where, " AND ")
	}
	query += " ORDER BY id DESC LIMIT ? OFFSET ?"
	args = append(args, limit, offset)
	rows, err := s.repo.Query(query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []map[string]any
	for rows.Next() {
		var id int64
		var username, email, role, created string
		var active int64
		if err := rows.Scan(&id, &username, &email, &role, &active, &created); err != nil {
			return nil, err
		}
		out = append(out, map[string]any{
			"id": id, "username": username, "email": email, "role": role,
			"active": active != 0, "created_at": created,
		})
	}
	return out, rows.Err()
}

// UpdateUserAdmin lets admins enable/disable accounts or change roles (AGENT-12).
func (s *Service) UpdateUserAdmin(user *models.User, targetID int64, data map[string]any) (map[string]any, error) {
	if user.Role != "admin" {
		return nil, forbidden("admin role required")
	}
	if targetID == user.ID {
		return nil, badRequest("cannot modify your own governance profile here")
	}
	target, err := s.UserByID(targetID)
	if err != nil {
		return nil, err
	}
	var cols []string
	var vals []any
	if hasKey(data, "active") {
		cols, vals = append(cols, "active"), append(vals, boolField(data, "active", true))
	}
	if hasKey(data, "role") {
		role := str(data, "role")
		if role != "user" && role != "admin" {
			return nil, badRequest("role must be user or admin")
		}
		cols, vals = append(cols, "role"), append(vals, role)
	}
	if len(cols) > 0 {
		_, _ = s.repo.Update("users", cols, vals, map[string]any{"id": targetID})
	}
	s.Audit(user.ID, "user.update", "users", id2str(targetID), strings.Join(cols, ",")+" username="+target.Username)
	updated, err := s.UserByID(targetID)
	if err != nil {
		return nil, err
	}
	return s.Me(updated), nil
}

// ListAudit returns audit events for the admin page (AGENT-12).
func (s *Service) ListAudit(user *models.User, q url.Values, limit, offset int) ([]map[string]any, error) {
	if user.Role != "admin" {
		return nil, forbidden("admin role required")
	}
	if limit <= 0 || limit > 500 {
		limit = 100
	}
	query := "SELECT id, actor_id, action, entity_type, entity_id, detail, created_at FROM audit_events"
	var args []any
	if v := q.Get("q"); v != "" {
		query += " WHERE action LIKE ? OR detail LIKE ? OR entity_type LIKE ?"
		args = append(args, "%"+v+"%", "%"+v+"%", "%"+v+"%")
	}
	query += " ORDER BY id DESC LIMIT ? OFFSET ?"
	args = append(args, limit, offset)
	rows, err := s.repo.Query(query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []map[string]any
	for rows.Next() {
		var id, actor int64
		var action, entity, eid, detail, created string
		if err := rows.Scan(&id, &actor, &action, &entity, &eid, &detail, &created); err != nil {
			return nil, err
		}
		out = append(out, map[string]any{
			"id": id, "actor_id": actor, "action": action, "entity_type": entity,
			"entity_id": eid, "detail": detail, "created_at": created,
		})
	}
	return out, rows.Err()
}

// Me returns the signed-in user profile.
func (s *Service) Me(user *models.User) map[string]any {
	return map[string]any{
		"id": user.ID, "username": user.Username, "email": user.Email,
		"role": user.Role, "active": user.Active, "created_at": user.CreatedAt,
	}
}

// DashboardStats aggregates counts for the dashboard.
func (s *Service) DashboardStats(user *models.User) map[string]any {
	out := map[string]any{}
	for _, name := range repo.KnownResources() {
		sch := repo.SchemaOf(name)
		where := map[string]any{}
		if sch.Owner != "" {
			where[sch.Owner] = user.ID
		}
		if name == "tool_catalog" && user.Role != "admin" {
			where = map[string]any{"is_public": true, "enabled": true}
		}
		if name == "admin_governance" && user.Role != "admin" {
			continue
		}
		n, err := s.repo.Count(sch, where)
		if err != nil {
			n = 0
		}
		out[name] = n
	}
	out["global_notice"] = s.GetSetting("global_notice", "")
	return out
}

var _ = models.User{}
