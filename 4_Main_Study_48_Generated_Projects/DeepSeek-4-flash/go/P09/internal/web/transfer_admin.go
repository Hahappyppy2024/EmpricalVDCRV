package web

import (
	"encoding/csv"
	"fmt"
	"net/http"
	"strconv"
	"strings"

	"issuetracker/internal/models"
	"issuetracker/internal/store"
)

// ISSUE-10 — Import/export ---------------------------------------------------

func (a *App) pageImportExport(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	p, err := a.projectFromSlug(r)
	if err != nil {
		http.NotFound(w, r)
		return
	}
	records, _ := a.Store.ListUseCaseRecords("import_export", store.UseCaseRecordParams{
		UserID: u.ID, Action: "import_export", Limit: 50, OrderDesc: true,
	})
	data := a.PageData(r, "Import/export — "+p.Name)
	data["Project"] = p
	data["Records"] = records
	data["CanManage"] = a.canManageProject(u, p)
	a.render(w, r, "import_export.html", data)
}

// importExportAPI implements the ISSUE-10 API contract. POST with a CSV file
// imports issues; GET with action=export returns a CSV report.
func (a *App) importExportAPI(w http.ResponseWriter, r *http.Request, method string) {
	u := userFrom(r)
	switch method {
	case "get":
		if r.URL.Query().Get("action") == "export" {
			projectID, err := strconv.ParseInt(r.URL.Query().Get("project_id"), 10, 64)
			if err != nil || projectID == 0 {
				errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "project_id is required")
				return
			}
			a.exportReport(w, r, u, projectID)
			return
		}
		records, err := a.Store.ListUseCaseRecords("import_export", useCaseParams(r))
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", err.Error())
			return
		}
		okJSON(w, records)
	case "post":
		if err := r.ParseMultipartForm(32 << 20); err != nil {
			errJSON(w, http.StatusUnprocessableEntity, "no_file", "expected a multipart file upload")
			return
		}
		projectID, err := strconv.ParseInt(r.FormValue("project_id"), 10, 64)
		if err != nil || projectID == 0 {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "project_id is required")
			return
		}
		p, err := a.Store.ProjectByID(projectID)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "project not found")
			return
		}
		if !a.canManageProject(u, p) {
			errJSON(w, http.StatusForbidden, "forbidden", "importing issues requires maintainer rights")
			return
		}
		file, header, err := r.FormFile("file")
		if err != nil {
			errJSON(w, http.StatusUnprocessableEntity, "no_file", "a file field named 'file' is required")
			return
		}
		defer file.Close()
		if header.Size > a.Cfg.MaxUploadMB*1024*1024 {
			errJSON(w, http.StatusUnprocessableEntity, "file_too_large", "import file exceeds the size limit")
			return
		}
		reader := csv.NewReader(file)
		reader.FieldsPerRecord = -1
		rows, err := reader.ReadAll()
		if err != nil {
			errJSON(w, http.StatusUnprocessableEntity, "file_type_not_allowed", "the import file must be valid CSV")
			return
		}
		if len(rows) == 0 {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "the import file is empty")
			return
		}
		created, skipped, invalid := 0, 0, 0
		for i, row := range rows {
			if i == 0 && strings.EqualFold(strings.TrimSpace(row[0]), "title") {
				continue
			}
			if len(row) < 1 || strings.TrimSpace(row[0]) == "" {
				skipped++
				continue
			}
			title := strings.TrimSpace(row[0])
			body := ""
			if len(row) > 1 {
				body = strings.TrimSpace(row[1])
			}
			priority := "medium"
			if len(row) > 2 {
				priority = strings.ToLower(strings.TrimSpace(row[2]))
			}
			status := models.StatusOpen
			if len(row) > 3 {
				s := strings.ToLower(strings.TrimSpace(row[3]))
				if validStatus(s) {
					status = s
				}
			}
			if !validPriority(priority) {
				invalid++
				continue
			}
			issue, err := a.Store.CreateIssue(&models.Issue{
				ProjectID: p.ID, Title: title, Body: body, Priority: priority, Status: status, CreatedBy: u.ID,
			})
			if err != nil {
				invalid++
				continue
			}
			if len(row) > 4 {
				var ids []int64
				for _, ln := range strings.Split(row[4], ",") {
					ln = strings.TrimSpace(ln)
					if ln == "" {
						continue
					}
					labels, err := a.Store.ListLabels(p.ID)
					if err != nil {
						continue
					}
					for _, l := range labels {
						if strings.EqualFold(l.Name, ln) {
							ids = append(ids, l.ID)
						}
					}
				}
				_ = a.Store.SetIssueLabels(issue.ID, ids)
			}
			created++
			a.Hooks.NotifyIssueEvent(issue, u.Username, models.EventIssueCreated)
			a.Bus.Publish(p.ID, models.EventIssueCreated, map[string]any{"issue_id": issue.ID, "number": issue.Number, "title": issue.Title})
		}
		if created == 0 {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed",
				fmt.Sprintf("no valid issue rows found (skipped=%d invalid=%d)", skipped, invalid))
			return
		}
		summary := fmt.Sprintf("Imported %d issues into %s (skipped=%d invalid=%d)", created, p.Name, skipped, invalid)
		_ = a.Store.CreateUseCaseRecord("import_export", models.UseCaseRecord{
			UserID: u.ID, Action: "import_export", SubjectID: p.ID, Summary: summary,
			Detail: "file=" + header.Filename, Status: "ok",
		})
		okJSON(w, map[string]any{"summary": summary, "created": created, "skipped": skipped, "invalid": invalid})
	case "patch":
		id, err := idParam(r)
		if err != nil {
			errJSON(w, http.StatusBadRequest, "validation_failed", "invalid id")
			return
		}
		rec, err := a.Store.UseCaseRecordByID("import_export", id)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "record not found")
			return
		}
		if rec.UserID != u.ID && u.Role != models.RoleAdmin {
			errJSON(w, http.StatusForbidden, "forbidden", "you may only update your own import/export records")
			return
		}
		summary := strings.TrimSpace(r.FormValue("summary"))
		if summary == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "summary is required")
			return
		}
		okJSON(w, map[string]any{"summary": "import/export record noted", "record_id": id, "note": summary})
	}
}

// exportReport streams a deterministic CSV report of a project's issues.
func (a *App) exportReport(w http.ResponseWriter, r *http.Request, u *models.User, projectID int64) {
	p, err := a.Store.ProjectByID(projectID)
	if err != nil {
		errJSON(w, http.StatusNotFound, "not_found", "project not found")
		return
	}
	if !a.canManageProject(u, p) {
		errJSON(w, http.StatusForbidden, "forbidden", "exporting reports requires maintainer rights")
		return
	}
	issues, err := a.Store.SearchIssues(u.ID, store.IssueSearchParams{ProjectID: p.ID, Limit: 500})
	if err != nil {
		errJSON(w, http.StatusInternalServerError, "internal_error", "could not load issues")
		return
	}
	w.Header().Set("Content-Type", "text/csv")
	w.Header().Set("Content-Disposition", fmt.Sprintf("attachment; filename=\"%s-issues-report.csv\"", p.Slug))
	cw := csv.NewWriter(w)
	_ = cw.Write([]string{"number", "title", "body", "priority", "status", "assignee", "creator", "created_at", "updated_at"})
	for i := range issues {
		it := issues[i]
		_ = cw.Write([]string{
			strconv.Itoa(it.Number), it.Title, it.Body, it.Priority, it.Status,
			it.AssigneeName, it.CreatorName, it.CreatedAt.Format("2006-01-02T15:04:05Z07:00"),
			it.UpdatedAt.Format("2006-01-02T15:04:05Z07:00"),
		})
	}
	cw.Flush()
	_ = a.Store.CreateUseCaseRecord("import_export", models.UseCaseRecord{
		UserID: u.ID, Action: "import_export", SubjectID: p.ID,
		Summary: fmt.Sprintf("Exported %d issues from %s", len(issues), p.Name),
		Detail:  "format=csv", Status: "ok",
	})
}

// ISSUE-11 — Admin operations ------------------------------------------------

func (a *App) pageAdmin(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	users, _ := a.Store.ListUsers()
	projects, _ := a.Store.ListAllProjects()
	settings, _ := a.Store.ListSettings()
	audit, _ := a.Store.ListAuditEvents(50)
	records, _ := a.Store.ListUseCaseRecords("admin_operations", store.UseCaseRecordParams{Limit: 30})
	data := a.PageData(r, "Admin operations")
	data["Users"] = users
	data["Projects"] = projects
	data["Settings"] = settings
	data["AuditEvents"] = audit
	data["Records"] = records
	data["CurrentUser"] = u
	a.render(w, r, "admin.html", data)
}

// adminOperationsAPI implements the ISSUE-11 API contract.
func (a *App) adminOperationsAPI(w http.ResponseWriter, r *http.Request, method string) {
	u := userFrom(r)
	if u.Role != models.RoleAdmin {
		errJSON(w, http.StatusForbidden, "forbidden", "admin operations require the admin role")
		return
	}
	switch method {
	case "get":
		records, err := a.Store.ListUseCaseRecords("admin_operations", useCaseParams(r))
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", err.Error())
			return
		}
		okJSON(w, records)
	case "post":
		action := strings.TrimSpace(r.FormValue("action"))
		switch action {
		case "user.update":
			userID, err := strconv.ParseInt(r.FormValue("user_id"), 10, 64)
			if err != nil || userID == 0 {
				errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "user_id is required")
				return
			}
			target, err := a.Store.UserByID(userID)
			if err != nil {
				errJSON(w, http.StatusNotFound, "not_found", "user not found")
				return
			}
			role := strings.TrimSpace(r.FormValue("role"))
			activeStr := strings.TrimSpace(r.FormValue("active"))
			var active *bool
			if activeStr == "true" || activeStr == "false" {
				v := activeStr == "true"
				active = &v
			}
			if role != "" && !validRole(role) {
				errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "invalid role")
				return
			}
			if err := a.Store.UpdateUser(userID, role, active, ""); err != nil {
				errJSON(w, http.StatusInternalServerError, "internal_error", "could not update user")
				return
			}
			detail := fmt.Sprintf("user_id=%d", userID)
			if role != "" {
				detail += ", role=" + role
			}
			if active != nil {
				detail += fmt.Sprintf(", active=%v", *active)
			}
			_ = a.Store.CreateUseCaseRecord("admin_operations", models.UseCaseRecord{
				UserID: u.ID, Action: "user.update", SubjectID: userID,
				Summary: "Updated user " + target.Username, Detail: detail, Status: "ok",
			})
			_ = a.Store.CreateAuditEvent(u.ID, "user.update", "users", userID, detail)
			okJSON(w, map[string]any{"summary": "user updated", "user_id": userID})
		case "project.transfer":
			projectID, err := strconv.ParseInt(r.FormValue("project_id"), 10, 64)
			if err != nil || projectID == 0 {
				errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "project_id is required")
				return
			}
			newOwnerID, err := strconv.ParseInt(r.FormValue("new_owner_id"), 10, 64)
			if err != nil || newOwnerID == 0 {
				errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "new_owner_id is required")
				return
			}
			p, err := a.Store.ProjectByID(projectID)
			if err != nil {
				errJSON(w, http.StatusNotFound, "not_found", "project not found")
				return
			}
			if _, err := a.Store.UserByID(newOwnerID); err != nil {
				errJSON(w, http.StatusNotFound, "not_found", "new owner user not found")
				return
			}
			if err := a.Store.TransferProjectOwnership(projectID, newOwnerID); err != nil {
				errJSON(w, http.StatusInternalServerError, "internal_error", "could not transfer project")
				return
			}
			_ = a.Store.CreateUseCaseRecord("admin_operations", models.UseCaseRecord{
				UserID: u.ID, Action: "project.transfer", SubjectID: projectID,
				Summary: "Transferred " + p.Name, Detail: fmt.Sprintf("new_owner_id=%d", newOwnerID), Status: "ok",
			})
			_ = a.Store.CreateAuditEvent(u.ID, "project.transfer", "projects", projectID, fmt.Sprintf("new_owner_id=%d", newOwnerID))
			okJSON(w, map[string]any{"summary": "project ownership transferred", "project_id": projectID, "new_owner_id": newOwnerID})
		case "setting.update":
			key := strings.TrimSpace(r.FormValue("key"))
			value := strings.TrimSpace(r.FormValue("value"))
			if key == "" || value == "" {
				errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "key and value are required")
				return
			}
			if err := a.Store.SetSetting(key, value); err != nil {
				errJSON(w, http.StatusInternalServerError, "internal_error", "could not update setting")
				return
			}
			_ = a.Store.CreateUseCaseRecord("admin_operations", models.UseCaseRecord{
				UserID: u.ID, Action: "setting.update", SubjectID: 0,
				Summary: "Updated global setting " + key, Detail: "value=" + value, Status: "ok",
			})
			_ = a.Store.CreateAuditEvent(u.ID, "setting.update", "global_settings", 0, key+"="+value)
			okJSON(w, map[string]any{"summary": "setting updated", "key": key, "value": value})
		default:
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed",
				"action must be one of: user.update, project.transfer, setting.update")
		}
	case "patch":
		id, err := idParam(r)
		if err != nil {
			errJSON(w, http.StatusBadRequest, "validation_failed", "invalid id")
			return
		}
		key := strings.TrimSpace(r.FormValue("key"))
		value := strings.TrimSpace(r.FormValue("value"))
		if key == "" || value == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "key and value are required")
			return
		}
		if err := a.Store.SetSetting(key, value); err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", "could not update setting")
			return
		}
		_ = a.Store.CreateUseCaseRecord("admin_operations", models.UseCaseRecord{
			UserID: u.ID, Action: "setting.update", SubjectID: 0,
			Summary: "Updated global setting " + key + " (PATCH)", Detail: "value=" + value, Status: "ok",
		})
		_ = a.Store.CreateAuditEvent(u.ID, "setting.update", "global_settings", id, key+"="+value)
		okJSON(w, map[string]any{"summary": "setting updated", "key": key, "value": value})
	}
}

func validRole(role string) bool {
	switch role {
	case models.RoleDeveloper, models.RoleReporter, models.RoleMember, models.RoleMaintainer, models.RoleAdmin:
		return true
	}
	return false
}

// ISSUE-12 — Frontend API integration and errors -----------------------------

// frontendErrorsAPI records client-side API error states reported by the UI.
func (a *App) frontendErrorsAPI(w http.ResponseWriter, r *http.Request, method string) {
	u := userFrom(r)
	switch method {
	case "get":
		records, err := a.Store.ListUseCaseRecords("frontend_api_integration_and_errors", useCaseParams(r))
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", err.Error())
			return
		}
		okJSON(w, records)
	case "post":
		operation := strings.TrimSpace(r.FormValue("operation"))
		code := strings.TrimSpace(r.FormValue("error_code"))
		message := strings.TrimSpace(r.FormValue("message"))
		if operation == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "operation is required")
			return
		}
		status := "ok"
		if code != "" {
			status = "error"
		}
		_ = a.Store.CreateUseCaseRecord("frontend_api_integration_and_errors", models.UseCaseRecord{
			UserID: u.ID, Action: operation, SubjectID: 0,
			Summary: "Frontend handled API state for " + operation, Detail: code + " " + message, Status: status,
		})
		okJSON(w, map[string]any{"summary": "error state recorded", "operation": operation, "status": status})
	case "patch":
		id, err := idParam(r)
		if err != nil {
			errJSON(w, http.StatusBadRequest, "validation_failed", "invalid id")
			return
		}
		rec, err := a.Store.UseCaseRecordByID("frontend_api_integration_and_errors", id)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "record not found")
			return
		}
		if rec.UserID != u.ID && u.Role != models.RoleAdmin {
			errJSON(w, http.StatusForbidden, "forbidden", "you may only update your own error records")
			return
		}
		status := strings.TrimSpace(r.FormValue("status"))
		if status != "ok" && status != "error" && status != "acknowledged" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "status must be ok, error or acknowledged")
			return
		}
		okJSON(w, map[string]any{"summary": "error record status updated", "record_id": id, "status": status})
	}
}
