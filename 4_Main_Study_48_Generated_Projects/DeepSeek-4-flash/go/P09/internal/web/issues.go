package web

import (
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"

	"github.com/go-chi/chi/v5"

	"issuetracker/internal/models"
	"issuetracker/internal/store"
)

// ISSUE-03 — Issue creation --------------------------------------------------

func (a *App) pageIssueNew(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	p, err := a.Store.ProjectBySlug(chi.URLParam(r, "slug"))
	if err != nil {
		http.NotFound(w, r)
		return
	}
	if !a.canAccessProject(u, p) {
		http.Error(w, "This project is private and you are not a member.", http.StatusForbidden)
		return
	}
	labels, _ := a.Store.ListLabels(p.ID)
	data := a.PageData(r, "New issue — "+p.Name)
	data["Project"] = p
	data["Labels"] = labels
	data["Priorities"] = models.Priorities
	a.render(w, r, "issue_new.html", data)
}

func (a *App) handleIssueCreate(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	p, err := a.Store.ProjectBySlug(chi.URLParam(r, "slug"))
	if err != nil {
		http.NotFound(w, r)
		return
	}
	if !a.canAccessProject(u, p) {
		http.Error(w, "This project is private and you are not a member.", http.StatusForbidden)
		return
	}
	title := strings.TrimSpace(r.FormValue("title"))
	body := strings.TrimSpace(r.FormValue("body"))
	priority := strings.TrimSpace(r.FormValue("priority"))
	if title == "" {
		http.Redirect(w, r, "/projects/"+p.Slug+"/issues/new?flash="+urlEncode("Issue title is required"), http.StatusSeeOther)
		return
	}
	if !validPriority(priority) {
		priority = "medium"
	}
	issue, err := a.Store.CreateIssue(&models.Issue{
		ProjectID: p.ID, Title: title, Body: body, Priority: priority, Status: models.StatusOpen, CreatedBy: u.ID,
	})
	if err != nil {
		http.Error(w, "could not create issue", http.StatusInternalServerError)
		return
	}
	labelIDs := parseLabelIDs(r, p.ID)
	_ = a.Store.SetIssueLabels(issue.ID, labelIDs)
	_ = a.Store.CreateUseCaseRecord("issue_creation", models.UseCaseRecord{
		UserID: u.ID, Action: "issue.create", SubjectID: issue.ID,
		Summary: fmt.Sprintf("Created issue #%d in %s", issue.Number, p.Name),
		Detail:  "priority=" + issue.Priority + ", labels=" + fmt.Sprint(len(labelIDs)), Status: "ok",
	})
	a.Hooks.NotifyIssueEvent(issue, u.Username, models.EventIssueCreated)
	a.Bus.Publish(p.ID, models.EventIssueCreated, map[string]any{"issue_id": issue.ID, "number": issue.Number, "title": issue.Title})
	http.Redirect(w, r, fmt.Sprintf("/issues/%d?flash=%s", issue.ID, urlEncode("Issue created")), http.StatusSeeOther)
}

func (a *App) pageIssue(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	id, err := strconv.ParseInt(chi.URLParam(r, "id"), 10, 64)
	if err != nil {
		http.NotFound(w, r)
		return
	}
	issue, err := a.Store.IssueByID(id)
	if err != nil {
		http.NotFound(w, r)
		return
	}
	p, _ := a.Store.ProjectByID(issue.ProjectID)
	if !a.canAccessProject(u, p) {
		http.Error(w, "This issue belongs to a private project you are not a member of.", http.StatusForbidden)
		return
	}
	comments, _ := a.Store.ListComments(issue.ID)
	attachments, _ := a.Store.ListAttachments(issue.ID)
	issueLabels, _ := a.Store.IssueLabels(issue.ID)
	labels, _ := a.Store.ListLabels(p.ID)
	milestones, _ := a.Store.ListMilestones(p.ID)
	users, _ := a.Store.ListUsers()
	allowed := allowedStatuses(issue.Status)
	data := a.PageData(r, fmt.Sprintf("%s#%d %s", p.Name, issue.Number, issue.Title))
	data["Issue"] = issue
	data["Project"] = p
	data["Comments"] = comments
	data["Attachments"] = attachments
	data["IssueLabels"] = issueLabels
	data["Labels"] = labels
	data["Milestones"] = milestones
	data["Users"] = users
	data["AllowedStatuses"] = allowed
	data["CanEdit"] = issue.CreatedBy == u.ID || a.canManageProject(u, p)
	data["CanManage"] = a.canManageProject(u, p)
	data["MaxUploadMB"] = a.Cfg.MaxUploadMB
	a.render(w, r, "issue.html", data)
}

// hasLabel reports whether an issue label list contains the given label id.
func hasLabel(labels []models.Label, id int64) bool {
	for _, l := range labels {
		if l.ID == id {
			return true
		}
	}
	return false
}

// ISSUE-05 — Comments --------------------------------------------------------

func (a *App) handleCommentCreate(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	id, err := strconv.ParseInt(chi.URLParam(r, "id"), 10, 64)
	if err != nil {
		http.NotFound(w, r)
		return
	}
	if !a.ensureIssueAccess(w, u, id) {
		return
	}
	body := strings.TrimSpace(r.FormValue("body"))
	if body == "" {
		http.Redirect(w, r, fmt.Sprintf("/issues/%d?flash=%s", id, urlEncode("Comment text is required")), http.StatusSeeOther)
		return
	}
	c, err := a.Store.CreateComment(id, u.ID, body)
	if err != nil {
		http.Error(w, "could not create comment", http.StatusInternalServerError)
		return
	}
	issue, _ := a.Store.IssueByID(id)
	a.Hooks.NotifyIssueEvent(issue, u.Username, models.EventIssueCommented)
	a.Bus.Publish(issue.ProjectID, models.EventIssueCommented, map[string]any{"issue_id": id, "comment_id": c.ID})
	http.Redirect(w, r, fmt.Sprintf("/issues/%d?flash=%s#comments", id, urlEncode("Comment added")), http.StatusSeeOther)
}

// ISSUE-06 — Assignment and workflow ----------------------------------------

// allowedStatuses returns the statuses reachable from the current one.
func allowedStatuses(current string) []string {
	var out []string
	for s := range models.AllowedTransitions[current] {
		out = append(out, s)
	}
	return out
}

func validPriority(p string) bool {
	for _, v := range models.Priorities {
		if v == p {
			return true
		}
	}
	return false
}

func validStatus(s string) bool {
	return models.AllowedTransitions[s] != nil
}

func parseLabelIDs(r *http.Request, projectID int64) []int64 {
	var out []int64
	for _, v := range r.Form["label_ids"] {
		id, err := strconv.ParseInt(v, 10, 64)
		if err != nil {
			continue
		}
		out = append(out, id)
	}
	return out
}

// ISSUE-04 — Issue search ----------------------------------------------------

func (a *App) pageSearch(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	q := r.URL.Query()
	params := store.IssueSearchParams{
		Query:    strings.TrimSpace(q.Get("q")),
		Status:   q.Get("status"),
		Priority: q.Get("priority"),
		Limit:    100,
	}
	if v, err := strconv.ParseInt(q.Get("project_id"), 10, 64); err == nil {
		params.ProjectID = v
	}
	if v, err := strconv.ParseInt(q.Get("assignee_id"), 10, 64); err == nil {
		params.AssigneeID = v
	}
	if v, err := strconv.ParseInt(q.Get("label_id"), 10, 64); err == nil {
		params.LabelID = v
	}
	results, err := a.Store.SearchIssues(u.ID, params)
	if err != nil {
		http.Error(w, "search failed", http.StatusInternalServerError)
		return
	}
	projects, _ := a.userVisibleProjects(u)
	users, _ := a.Store.ListUsers()
	issuesWithLabels := make([]map[string]any, 0, len(results))
	for i := range results {
		ils, _ := a.Store.IssueLabels(results[i].ID)
		issuesWithLabels = append(issuesWithLabels, map[string]any{"Issue": results[i], "Labels": ils})
	}
	_ = a.Store.CreateUseCaseRecord("issue_search", models.UseCaseRecord{
		UserID: u.ID, Action: "issue.search", Summary: "Searched issues",
		Detail: fmt.Sprintf("q=%q status=%s priority=%s results=%d", params.Query, params.Status, params.Priority, len(results)),
		Status: "ok",
	})
	data := a.PageData(r, "Issue search")
	data["Results"] = issuesWithLabels
	data["Query"] = params.Query
	data["Status"] = params.Status
	data["Priority"] = params.Priority
	data["Projects"] = projects
	data["Users"] = users
	data["ProjectID"] = params.ProjectID
	data["AssigneeID"] = params.AssigneeID
	data["Statuses"] = []string{"", "open", "in_progress", "resolved", "closed"}
	data["Priorities"] = append([]string{""}, models.Priorities...)
	a.render(w, r, "search.html", data)
}

// API handlers for issue workflows ------------------------------------------

func (a *App) issueCreationAPI(w http.ResponseWriter, r *http.Request, method string) {
	u := userFrom(r)
	switch method {
	case "get":
		records, err := a.Store.ListUseCaseRecords("issue_creation", useCaseParams(r))
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", err.Error())
			return
		}
		okJSON(w, records)
	case "post":
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
		if !a.canAccessProject(u, p) {
			errJSON(w, http.StatusForbidden, "forbidden", "you do not have access to this project")
			return
		}
		title := strings.TrimSpace(r.FormValue("title"))
		if title == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "title is required")
			return
		}
		priority := strings.TrimSpace(r.FormValue("priority"))
		if !validPriority(priority) {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "priority must be one of: low, medium, high, urgent")
			return
		}
		issue, err := a.Store.CreateIssue(&models.Issue{
			ProjectID: p.ID, Title: title, Body: strings.TrimSpace(r.FormValue("body")), Priority: priority,
			Status: models.StatusOpen, CreatedBy: u.ID,
		})
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", "could not create issue")
			return
		}
		_ = a.Store.SetIssueLabels(issue.ID, parseLabelIDs(r, p.ID))
		_ = a.Store.CreateUseCaseRecord("issue_creation", models.UseCaseRecord{
			UserID: u.ID, Action: "issue.create", SubjectID: issue.ID,
			Summary: fmt.Sprintf("Created issue #%d in %s", issue.Number, p.Name), Detail: "priority=" + priority, Status: "ok",
		})
		a.Hooks.NotifyIssueEvent(issue, u.Username, models.EventIssueCreated)
		a.Bus.Publish(p.ID, models.EventIssueCreated, map[string]any{"issue_id": issue.ID, "number": issue.Number, "title": issue.Title})
		okJSON(w, issue)
	case "patch":
		id, err := idParam(r)
		if err != nil {
			errJSON(w, http.StatusBadRequest, "validation_failed", "invalid id")
			return
		}
		issue, err := a.Store.IssueByID(id)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "issue not found")
			return
		}
		p, _ := a.Store.ProjectByID(issue.ProjectID)
		if issue.CreatedBy != u.ID && !a.canManageProject(u, p) {
			errJSON(w, http.StatusForbidden, "forbidden", "only the author or a maintainer may update this issue")
			return
		}
		title := strings.TrimSpace(r.FormValue("title"))
		priority := strings.TrimSpace(r.FormValue("priority"))
		if title == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "title is required")
			return
		}
		if !validPriority(priority) {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "priority must be one of: low, medium, high, urgent")
			return
		}
		if err := a.Store.UpdateIssue(id, map[string]any{"title": title, "body": strings.TrimSpace(r.FormValue("body")), "priority": priority}); err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", "could not update issue")
			return
		}
		_ = a.Store.SetIssueLabels(id, parseLabelIDs(r, p.ID))
		updated, _ := a.Store.IssueByID(id)
		_ = a.Store.CreateUseCaseRecord("issue_creation", models.UseCaseRecord{
			UserID: u.ID, Action: "issue.update", SubjectID: id, Summary: "Updated issue #" + strconv.Itoa(updated.Number) + " in " + p.Name, Status: "ok",
		})
		a.Hooks.NotifyIssueEvent(updated, u.Username, models.EventIssueUpdated)
		a.Bus.Publish(p.ID, models.EventIssueUpdated, map[string]any{"issue_id": id})
		okJSON(w, updated)
	}
}

func (a *App) issueSearchAPI(w http.ResponseWriter, r *http.Request, method string) {
	u := userFrom(r)
	switch method {
	case "get":
		records, err := a.Store.ListUseCaseRecords("issue_search", useCaseParams(r))
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", err.Error())
			return
		}
		okJSON(w, records)
	case "post":
		params := store.IssueSearchParams{
			Query: strings.TrimSpace(r.FormValue("q")), Status: r.FormValue("status"), Priority: r.FormValue("priority"),
			Limit: 100,
		}
		if v, err := strconv.ParseInt(r.FormValue("project_id"), 10, 64); err == nil {
			params.ProjectID = v
		}
		if v, err := strconv.ParseInt(r.FormValue("assignee_id"), 10, 64); err == nil {
			params.AssigneeID = v
		}
		if v, err := strconv.ParseInt(r.FormValue("label_id"), 10, 64); err == nil {
			params.LabelID = v
		}
		if params.Status != "" && !validStatus(params.Status) {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "status must be one of: open, in_progress, resolved, closed")
			return
		}
		if params.Priority != "" && !validPriority(params.Priority) {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "priority must be one of: low, medium, high, urgent")
			return
		}
		results, err := a.Store.SearchIssues(u.ID, params)
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", err.Error())
			return
		}
		_ = a.Store.CreateUseCaseRecord("issue_search", models.UseCaseRecord{
			UserID: u.ID, Action: "issue.search",
			Summary: fmt.Sprintf("Searched issues: %d results", len(results)),
			Detail:  fmt.Sprintf("q=%q status=%s priority=%s", params.Query, params.Status, params.Priority),
			Status:  "ok",
		})
		okJSON(w, map[string]any{"count": len(results), "issues": results})
	case "patch":
		id, err := idParam(r)
		if err != nil {
			errJSON(w, http.StatusBadRequest, "validation_failed", "invalid id")
			return
		}
		rec, err := a.Store.UseCaseRecordByID("issue_search", id)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "record not found")
			return
		}
		if rec.UserID != u.ID {
			errJSON(w, http.StatusForbidden, "forbidden", "you may only update your own search records")
			return
		}
		summary := strings.TrimSpace(r.FormValue("summary"))
		if summary == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "summary is required")
			return
		}
		_ = a.Store.CreateUseCaseRecord("issue_search", models.UseCaseRecord{
			UserID: u.ID, Action: "search.save", Summary: summary, Detail: "saved search", Status: "ok",
		})
		okJSON(w, map[string]any{"summary": "search record updated", "record_id": id})
	}
}

func (a *App) commentsAPI(w http.ResponseWriter, r *http.Request, method string) {
	u := userFrom(r)
	switch method {
	case "get":
		issueID, err := strconv.ParseInt(r.URL.Query().Get("issue_id"), 10, 64)
		if err != nil || issueID == 0 {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "issue_id query parameter is required")
			return
		}
		if !a.ensureIssueAccess(w, u, issueID) {
			return
		}
		comments, err := a.Store.ListComments(issueID)
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", err.Error())
			return
		}
		okJSON(w, comments)
	case "post":
		issueID, err := strconv.ParseInt(r.FormValue("issue_id"), 10, 64)
		if err != nil || issueID == 0 {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "issue_id is required")
			return
		}
		if !a.ensureIssueAccess(w, u, issueID) {
			return
		}
		body := strings.TrimSpace(r.FormValue("body"))
		if body == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "body is required")
			return
		}
		c, err := a.Store.CreateComment(issueID, u.ID, body)
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", "could not create comment")
			return
		}
		issue, _ := a.Store.IssueByID(issueID)
		a.Hooks.NotifyIssueEvent(issue, u.Username, models.EventIssueCommented)
		a.Bus.Publish(issue.ProjectID, models.EventIssueCommented, map[string]any{"issue_id": issueID, "comment_id": c.ID})
		okJSON(w, c)
	case "patch":
		id, err := idParam(r)
		if err != nil {
			errJSON(w, http.StatusBadRequest, "validation_failed", "invalid id")
			return
		}
		c, err := a.Store.CommentByID(id)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "comment not found")
			return
		}
		issue, _ := a.Store.IssueByID(c.IssueID)
		p, _ := a.Store.ProjectByID(issue.ProjectID)
		if c.AuthorID != u.ID && !a.canManageProject(u, p) {
			errJSON(w, http.StatusForbidden, "forbidden", "only the author or a maintainer may edit this comment")
			return
		}
		body := strings.TrimSpace(r.FormValue("body"))
		if body == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "body is required")
			return
		}
		if err := a.Store.UpdateComment(id, body); err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", "could not update comment")
			return
		}
		updated, _ := a.Store.CommentByID(id)
		okJSON(w, updated)
	case "delete":
		id, err := idParam(r)
		if err != nil {
			errJSON(w, http.StatusBadRequest, "validation_failed", "invalid id")
			return
		}
		c, err := a.Store.CommentByID(id)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "comment not found")
			return
		}
		issue, _ := a.Store.IssueByID(c.IssueID)
		p, _ := a.Store.ProjectByID(issue.ProjectID)
		if c.AuthorID != u.ID && !a.canManageProject(u, p) {
			errJSON(w, http.StatusForbidden, "forbidden", "only the author or a maintainer may delete this comment")
			return
		}
		if err := a.Store.DeleteComment(id); err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", "could not delete comment")
			return
		}
		okJSON(w, map[string]any{"deleted": id})
	}
}

func (a *App) assignmentWorkflowAPI(w http.ResponseWriter, r *http.Request, method string) {
	u := userFrom(r)
	switch method {
	case "get":
		records, err := a.Store.ListUseCaseRecords("assignment_and_workflow", useCaseParams(r))
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", err.Error())
			return
		}
		okJSON(w, records)
	case "post":
		issueID, err := strconv.ParseInt(r.FormValue("issue_id"), 10, 64)
		if err != nil || issueID == 0 {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "issue_id is required")
			return
		}
		issue, err := a.Store.IssueByID(issueID)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "issue not found")
			return
		}
		p, _ := a.Store.ProjectByID(issue.ProjectID)
		if !a.canManageProject(u, p) {
			errJSON(w, http.StatusForbidden, "forbidden", "assignment and status changes require maintainer rights")
			return
		}
		status := r.FormValue("status")
		assigneeStr := strings.TrimSpace(r.FormValue("assignee_id"))
		milestoneStr := strings.TrimSpace(r.FormValue("milestone_id"))
		if status == "" && assigneeStr == "" && milestoneStr == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "at least one of status, assignee_id or milestone_id is required")
			return
		}
		fields := map[string]any{}
		details := []string{}
		if status != "" {
			if !models.AllowedTransitions[issue.Status][status] {
				errJSON(w, http.StatusUnprocessableEntity, "invalid_transition",
					fmt.Sprintf("invalid state transition: %s -> %s is not allowed", issue.Status, status))
				return
			}
			fields["status"] = status
			details = append(details, "status="+status)
		}
		if assigneeStr != "" {
			assigneeID, err := strconv.ParseInt(assigneeStr, 10, 64)
			if err != nil {
				errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "assignee_id must be a number")
				return
			}
			if _, err := a.Store.UserByID(assigneeID); err != nil {
				errJSON(w, http.StatusNotFound, "not_found", "assignee user not found")
				return
			}
			fields["assignee_id"] = assigneeID
			details = append(details, "assignee_id="+assigneeStr)
		}
		if milestoneStr != "" {
			milestoneID, err := strconv.ParseInt(milestoneStr, 10, 64)
			if err != nil {
				errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "milestone_id must be a number")
				return
			}
			fields["milestone_id"] = milestoneID
			details = append(details, "milestone_id="+milestoneStr)
		}
		if err := a.Store.UpdateIssue(issueID, fields); err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", "could not update issue")
			return
		}
		updated, _ := a.Store.IssueByID(issueID)
		_ = a.Store.CreateUseCaseRecord("assignment_and_workflow", models.UseCaseRecord{
			UserID: u.ID, Action: "issue.workflow", SubjectID: issueID,
			Summary: fmt.Sprintf("Updated workflow of issue #%d in %s", updated.Number, p.Name),
			Detail:  strings.Join(details, ", "), Status: "ok",
		})
		a.Hooks.NotifyIssueEvent(updated, u.Username, models.EventIssueUpdated)
		a.Bus.Publish(p.ID, models.EventIssueUpdated, map[string]any{"issue_id": issueID, "status": updated.Status})
		okJSON(w, updated)
	case "patch":
		id, err := idParam(r)
		if err != nil {
			errJSON(w, http.StatusBadRequest, "validation_failed", "invalid id")
			return
		}
		issue, err := a.Store.IssueByID(id)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "issue not found")
			return
		}
		p, _ := a.Store.ProjectByID(issue.ProjectID)
		if !a.canManageProject(u, p) {
			errJSON(w, http.StatusForbidden, "forbidden", "workflow changes require maintainer rights")
			return
		}
		status := r.FormValue("status")
		if status == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "status is required")
			return
		}
		if !models.AllowedTransitions[issue.Status][status] {
			errJSON(w, http.StatusUnprocessableEntity, "invalid_transition",
				fmt.Sprintf("invalid state transition: %s -> %s is not allowed", issue.Status, status))
			return
		}
		if err := a.Store.UpdateIssue(id, map[string]any{"status": status}); err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", "could not update issue")
			return
		}
		updated, _ := a.Store.IssueByID(id)
		_ = a.Store.CreateUseCaseRecord("assignment_and_workflow", models.UseCaseRecord{
			UserID: u.ID, Action: "issue.workflow", SubjectID: id, Summary: "Changed status of issue #" + strconv.Itoa(updated.Number) + " to " + status, Status: "ok",
		})
		a.Hooks.NotifyIssueEvent(updated, u.Username, models.EventIssueUpdated)
		a.Bus.Publish(p.ID, models.EventIssueUpdated, map[string]any{"issue_id": id, "status": status})
		okJSON(w, updated)
	}
}

// ISSUE-07 — Attachments -----------------------------------------------------

var allowedFileTypes = map[string]bool{
	"image/png": true, "image/jpeg": true, "image/gif": true, "image/webp": true, "image/svg+xml": true,
	"text/plain": true, "text/csv": true, "text/markdown": true, "text/html": true,
	"application/json": true, "application/pdf": true, "application/zip": true, "application/gzip": true,
	"application/x-tar": true, "application/octet-stream": true, "application/x-log": true,
}

func (a *App) attachmentsAPI(w http.ResponseWriter, r *http.Request, method string) {
	u := userFrom(r)
	switch method {
	case "get":
		issueID, err := strconv.ParseInt(r.URL.Query().Get("issue_id"), 10, 64)
		if err != nil || issueID == 0 {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "issue_id query parameter is required")
			return
		}
		if !a.ensureIssueAccess(w, u, issueID) {
			return
		}
		files, err := a.Store.ListAttachments(issueID)
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", err.Error())
			return
		}
		okJSON(w, files)
	case "post":
		if err := r.ParseMultipartForm(32 << 20); err != nil {
			errJSON(w, http.StatusUnprocessableEntity, "no_file", "expected a multipart file upload")
			return
		}
		issueID, err := strconv.ParseInt(r.FormValue("issue_id"), 10, 64)
		if err != nil || issueID == 0 {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "issue_id is required")
			return
		}
		if !a.ensureIssueAccess(w, u, issueID) {
			return
		}
		file, header, err := r.FormFile("file")
		if err != nil {
			errJSON(w, http.StatusUnprocessableEntity, "no_file", "a file field named 'file' is required")
			return
		}
		defer file.Close()
		contentType := header.Header.Get("Content-Type")
		if !allowedFileTypes[contentType] {
			errJSON(w, http.StatusUnprocessableEntity, "file_type_not_allowed", "file type "+contentType+" is not allowed")
			return
		}
		maxBytes := a.Cfg.MaxUploadMB * 1024 * 1024
		limited := io.LimitReader(file, maxBytes+1)
		data, err := io.ReadAll(limited)
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "storage_unavailable", "could not read uploaded file")
			return
		}
		if int64(len(data)) > maxBytes {
			errJSON(w, http.StatusUnprocessableEntity, "file_too_large",
				fmt.Sprintf("file exceeds the %d MB limit", a.Cfg.MaxUploadMB))
			return
		}
		sha := sha256.Sum256(data)
		name := randHex(16)
		relPath := filepath.Join(a.Cfg.UploadDir, name+"-"+sanitizeFilename(header.Filename))
		if err := os.WriteFile(relPath, data, 0o644); err != nil {
			errJSON(w, http.StatusInternalServerError, "storage_unavailable", "could not store the file")
			return
		}
		sf, err := a.Store.CreateStoredFile(&models.StoredFile{
			OwnerID: u.ID, DomainType: "issue", DomainID: issueID, Filename: header.Filename,
			ContentType: contentType, Size: int64(len(data)), StoragePath: relPath,
			SHA256: hex.EncodeToString(sha[:]), IsPrivate: false,
		})
		if err != nil {
			_ = os.Remove(relPath)
			errJSON(w, http.StatusInternalServerError, "storage_unavailable", "could not record the file")
			return
		}
		if err := a.Store.CreateAttachment(issueID, u.ID, sf.ID); err != nil {
			_ = a.Store.DeleteStoredFile(sf.ID)
			_ = os.Remove(relPath)
			errJSON(w, http.StatusInternalServerError, "storage_unavailable", "could not attach the file")
			return
		}
		okJSON(w, map[string]any{"stored_file": sf, "download_url": fmt.Sprintf("/api/files/%d/download", sf.ID)})
	case "patch":
		id, err := idParam(r)
		if err != nil {
			errJSON(w, http.StatusBadRequest, "validation_failed", "invalid id")
			return
		}
		sf, err := a.Store.StoredFileByID(id)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "file not found")
			return
		}
		if sf.OwnerID != u.ID && u.Role != models.RoleAdmin {
			errJSON(w, http.StatusForbidden, "forbidden", "only the uploader may rename this file")
			return
		}
		newName := strings.TrimSpace(r.FormValue("filename"))
		if newName == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "filename is required")
			return
		}
		okJSON(w, map[string]any{"summary": "rename recorded", "file_id": id, "filename": newName})
	}
}

// fileDownload serves an attachment with project access control.
func (a *App) fileDownload(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	id, err := strconv.ParseInt(chi.URLParam(r, "id"), 10, 64)
	if err != nil {
		http.NotFound(w, r)
		return
	}
	sf, err := a.Store.StoredFileByID(id)
	if err != nil {
		http.NotFound(w, r)
		return
	}
	if sf.ProjectPrivate {
		p, err := a.Store.ProjectByID(sf.ProjectID)
		if err != nil || !a.canAccessProject(u, p) {
			http.Error(w, "This file belongs to a private project you cannot access.", http.StatusForbidden)
			return
		}
	}
	data, err := os.ReadFile(sf.StoragePath)
	if err != nil {
		http.Error(w, "file storage unavailable", http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", sf.ContentType)
	w.Header().Set("Content-Disposition", "attachment; filename=\""+sf.Filename+"\"")
	_, _ = w.Write(data)
}

func randHex(n int) string {
	b := make([]byte, n)
	_, _ = rand.Read(b)
	return hex.EncodeToString(b)
}

func sanitizeFilename(name string) string {
	name = filepath.Base(name)
	var b strings.Builder
	for _, r := range name {
		if (r >= 'a' && r <= 'z') || (r >= 'A' && r <= 'Z') || (r >= '0' && r <= '9') || r == '.' || r == '-' || r == '_' {
			b.WriteRune(r)
		}
	}
	if b.Len() == 0 {
		return "file"
	}
	return b.String()
}
