package web

import (
	"net/http"
	"strconv"
	"strings"

	"github.com/go-chi/chi/v5"

	"issuetracker/internal/models"
	"issuetracker/internal/store"
)

// ISSUE-02 — Project management ---------------------------------------------

func (a *App) pageProjects(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	projects, err := a.Store.ListVisibleProjects(u.ID)
	if err != nil {
		http.Error(w, "could not load projects", http.StatusInternalServerError)
		return
	}
	data := a.PageData(r, "Projects")
	data["Projects"] = projects
	data["IsPrivileged"] = u.Role == models.RoleMaintainer || u.Role == models.RoleAdmin
	a.render(w, r, "projects.html", data)
}

func (a *App) pageProjectNew(w http.ResponseWriter, r *http.Request) {
	data := a.PageData(r, "New project")
	a.render(w, r, "project_new.html", data)
}

func (a *App) handleProjectCreate(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	name := strings.TrimSpace(r.FormValue("name"))
	description := strings.TrimSpace(r.FormValue("description"))
	visibility := strings.TrimSpace(r.FormValue("visibility"))
	if name == "" {
		http.Redirect(w, r, "/projects/new?flash="+urlEncode("Project name is required"), http.StatusSeeOther)
		return
	}
	if visibility != models.VisibilityPublic && visibility != models.VisibilityPrivate {
		visibility = a.Store.GetSetting("default_visibility", "public")
	}
	p, err := a.Store.CreateProject(&models.Project{
		Name: name, Slug: slugify(name), Description: description, Visibility: visibility, OwnerID: u.ID,
	})
	if err == store.ErrDuplicate || (err != nil && strings.Contains(err.Error(), "UNIQUE")) {
		http.Redirect(w, r, "/projects/new?flash="+urlEncode("A project with this name already exists"), http.StatusSeeOther)
		return
	}
	if err != nil {
		http.Error(w, "could not create project", http.StatusInternalServerError)
		return
	}
	if visibility == models.VisibilityPrivate {
		_ = a.Store.AddProjectMember(p.ID, u.ID, "maintainer")
		_ = a.Store.CreateUseCaseRecord("private_projects", models.UseCaseRecord{
			UserID: u.ID, Action: "project.member", SubjectID: p.ID, Summary: "Created private project " + p.Name,
			Detail: "owner added as maintainer", Status: "ok",
		})
	}
	_ = a.Store.CreateUseCaseRecord("project_management", models.UseCaseRecord{
		UserID: u.ID, Action: "project.create", SubjectID: p.ID, Summary: "Created project " + p.Name,
		Detail: "visibility=" + p.Visibility, Status: "ok",
	})
	_ = a.Store.CreateAuditEvent(u.ID, "project.create", "projects", p.ID, "created project "+p.Name)
	http.Redirect(w, r, "/projects/"+p.Slug+"?flash="+urlEncode("Project created"), http.StatusSeeOther)
}

func (a *App) pageProject(w http.ResponseWriter, r *http.Request) {
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
	issues, _ := a.Store.SearchIssues(u.ID, store.IssueSearchParams{ProjectID: p.ID, Limit: 100})
	labels, _ := a.Store.ListLabels(p.ID)
	milestones, _ := a.Store.ListMilestones(p.ID)
	members, _ := a.Store.ListProjectMembers(p.ID)
	webhooks, _ := a.Store.ListWebhooks(p.ID)
	issuesWithLabels := make([]map[string]any, 0, len(issues))
	for i := range issues {
		ils, _ := a.Store.IssueLabels(issues[i].ID)
		issuesWithLabels = append(issuesWithLabels, map[string]any{"Issue": issues[i], "Labels": ils})
	}
	data := a.PageData(r, p.Name)
	data["Project"] = p
	data["Issues"] = issuesWithLabels
	data["Labels"] = labels
	data["Milestones"] = milestones
	data["Members"] = members
	data["Webhooks"] = webhooks
	data["CanManage"] = a.canManageProject(u, p)
	a.render(w, r, "project.html", data)
}

func (a *App) handleLabelCreate(w http.ResponseWriter, r *http.Request) {
	p := projectFrom(r)
	u := userFrom(r)
	name := strings.TrimSpace(r.FormValue("name"))
	color := strings.TrimSpace(r.FormValue("color"))
	if name == "" {
		http.Redirect(w, r, "/projects/"+p.Slug+"?flash="+urlEncode("Label name is required"), http.StatusSeeOther)
		return
	}
	if color == "" {
		color = "1f883d"
	}
	l, err := a.Store.CreateLabel(p.ID, name, color)
	if err != nil {
		http.Redirect(w, r, "/projects/"+p.Slug+"?flash="+urlEncode("Label already exists"), http.StatusSeeOther)
		return
	}
	_ = a.Store.CreateUseCaseRecord("project_management", models.UseCaseRecord{
		UserID: u.ID, Action: "label.create", SubjectID: l.ID, Summary: "Created label " + l.Name + " in " + p.Name, Status: "ok",
	})
	http.Redirect(w, r, "/projects/"+p.Slug+"?flash="+urlEncode("Label created"), http.StatusSeeOther)
}

func (a *App) handleMilestoneCreate(w http.ResponseWriter, r *http.Request) {
	p := projectFrom(r)
	u := userFrom(r)
	title := strings.TrimSpace(r.FormValue("title"))
	if title == "" {
		http.Redirect(w, r, "/projects/"+p.Slug+"?flash="+urlEncode("Milestone title is required"), http.StatusSeeOther)
		return
	}
	m, err := a.Store.CreateMilestone(p.ID, title)
	if err != nil {
		http.Error(w, "could not create milestone", http.StatusInternalServerError)
		return
	}
	_ = a.Store.CreateUseCaseRecord("project_management", models.UseCaseRecord{
		UserID: u.ID, Action: "milestone.create", SubjectID: m.ID, Summary: "Created milestone " + m.Title + " in " + p.Name, Status: "ok",
	})
	http.Redirect(w, r, "/projects/"+p.Slug+"?flash="+urlEncode("Milestone created"), http.StatusSeeOther)
}

// handleMemberAdd implements the private_projects membership workflow.
func (a *App) handleMemberAdd(w http.ResponseWriter, r *http.Request) {
	p := projectFrom(r)
	u := userFrom(r)
	username := strings.TrimSpace(r.FormValue("username"))
	role := strings.TrimSpace(r.FormValue("role"))
	if username == "" {
		http.Redirect(w, r, "/projects/"+p.Slug+"?flash="+urlEncode("Username is required"), http.StatusSeeOther)
		return
	}
	if role != "member" && role != "maintainer" {
		role = "member"
	}
	member, err := a.Store.UserByUsername(username)
	if err != nil {
		http.Redirect(w, r, "/projects/"+p.Slug+"?flash="+urlEncode("User not found"), http.StatusSeeOther)
		return
	}
	if err := a.Store.AddProjectMember(p.ID, member.ID, role); err != nil {
		http.Error(w, "could not add member", http.StatusInternalServerError)
		return
	}
	_ = a.Store.CreateUseCaseRecord("private_projects", models.UseCaseRecord{
		UserID: u.ID, Action: "project.member", SubjectID: p.ID, Summary: "Added " + member.Username + " to " + p.Name,
		Detail: "role=" + role, Status: "ok",
	})
	http.Redirect(w, r, "/projects/"+p.Slug+"?flash="+urlEncode("Member added"), http.StatusSeeOther)
}

func (a *App) handleMemberRemove(w http.ResponseWriter, r *http.Request) {
	p := projectFrom(r)
	u := userFrom(r)
	userID, err := strconv.ParseInt(chiURLParam(r, "userID"), 10, 64)
	if err != nil {
		http.NotFound(w, r)
		return
	}
	if err := a.Store.RemoveProjectMember(p.ID, userID); err != nil {
		http.Error(w, "could not remove member", http.StatusInternalServerError)
		return
	}
	_ = a.Store.CreateUseCaseRecord("private_projects", models.UseCaseRecord{
		UserID: u.ID, Action: "project.member_remove", SubjectID: p.ID, Summary: "Removed member id=" + strconv.FormatInt(userID, 10) + " from " + p.Name, Status: "ok",
	})
	http.Redirect(w, r, "/projects/"+p.Slug+"?flash="+urlEncode("Member removed"), http.StatusSeeOther)
}

func (a *App) handleVisibilityChange(w http.ResponseWriter, r *http.Request) {
	p := projectFrom(r)
	u := userFrom(r)
	visibility := strings.TrimSpace(r.FormValue("visibility"))
	if visibility != models.VisibilityPublic && visibility != models.VisibilityPrivate {
		http.Redirect(w, r, "/projects/"+p.Slug+"?flash="+urlEncode("Invalid visibility"), http.StatusSeeOther)
		return
	}
	if err := a.Store.UpdateProject(p.ID, p.Name, p.Description, visibility); err != nil {
		http.Error(w, "could not update project", http.StatusInternalServerError)
		return
	}
	_ = a.Store.CreateUseCaseRecord("project_management", models.UseCaseRecord{
		UserID: u.ID, Action: "project.visibility", SubjectID: p.ID, Summary: "Changed visibility of " + p.Name,
		Detail: "visibility=" + visibility, Status: "ok",
	})
	http.Redirect(w, r, "/projects/"+p.Slug+"?flash="+urlEncode("Visibility updated"), http.StatusSeeOther)
}

// projectManagementAPI implements the ISSUE-02 API contract.
func (a *App) projectManagementAPI(w http.ResponseWriter, r *http.Request, method string) {
	u := userFrom(r)
	switch method {
	case "get":
		records, err := a.Store.ListUseCaseRecords("project_management", useCaseParams(r))
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", err.Error())
			return
		}
		okJSON(w, records)
	case "post":
		if u.Role != models.RoleMaintainer && u.Role != models.RoleAdmin {
			errJSON(w, http.StatusForbidden, "forbidden", "project management requires the maintainer or admin role")
			return
		}
		name := strings.TrimSpace(r.FormValue("name"))
		if name == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "name is required")
			return
		}
		visibility := strings.TrimSpace(r.FormValue("visibility"))
		if visibility != models.VisibilityPublic && visibility != models.VisibilityPrivate {
			visibility = "public"
		}
		p, err := a.Store.CreateProject(&models.Project{
			Name: name, Slug: slugify(name), Description: strings.TrimSpace(r.FormValue("description")),
			Visibility: visibility, OwnerID: u.ID,
		})
		if err != nil {
			errJSON(w, http.StatusConflict, "duplicate", "a project with this name already exists")
			return
		}
		if visibility == models.VisibilityPrivate {
			_ = a.Store.AddProjectMember(p.ID, u.ID, "maintainer")
		}
		_ = a.Store.CreateUseCaseRecord("project_management", models.UseCaseRecord{
			UserID: u.ID, Action: "project.create", SubjectID: p.ID, Summary: "Created project " + p.Name,
			Detail: "visibility=" + p.Visibility, Status: "ok",
		})
		_ = a.Store.CreateAuditEvent(u.ID, "project.create", "projects", p.ID, "created project "+p.Name)
		okJSON(w, p)
	case "patch":
		if u.Role != models.RoleMaintainer && u.Role != models.RoleAdmin {
			errJSON(w, http.StatusForbidden, "forbidden", "project management requires the maintainer or admin role")
			return
		}
		id, err := idParam(r)
		if err != nil {
			errJSON(w, http.StatusBadRequest, "validation_failed", "invalid id")
			return
		}
		p, err := a.Store.ProjectByID(id)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "project not found")
			return
		}
		name := strings.TrimSpace(r.FormValue("name"))
		desc := strings.TrimSpace(r.FormValue("description"))
		vis := strings.TrimSpace(r.FormValue("visibility"))
		if name == "" {
			name = p.Name
		}
		if vis != models.VisibilityPublic && vis != models.VisibilityPrivate {
			vis = p.Visibility
		}
		if err := a.Store.UpdateProject(id, name, desc, vis); err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", "could not update project")
			return
		}
		_ = a.Store.CreateUseCaseRecord("project_management", models.UseCaseRecord{
			UserID: u.ID, Action: "project.update", SubjectID: id, Summary: "Updated project " + name,
			Detail: "visibility=" + vis, Status: "ok",
		})
		updated, _ := a.Store.ProjectByID(id)
		okJSON(w, updated)
	}
}

// privateProjectsAPI implements the ISSUE-08 API contract.
func (a *App) privateProjectsAPI(w http.ResponseWriter, r *http.Request, method string) {
	u := userFrom(r)
	switch method {
	case "get":
		records, err := a.Store.ListUseCaseRecords("private_projects", useCaseParams(r))
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
		if p.Visibility != models.VisibilityPrivate {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "membership is only meaningful for private projects")
			return
		}
		if !a.canManageProject(u, p) {
			errJSON(w, http.StatusForbidden, "forbidden", "only maintainers may grant membership")
			return
		}
		username := strings.TrimSpace(r.FormValue("username"))
		if username == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "username is required")
			return
		}
		member, err := a.Store.UserByUsername(username)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "user not found")
			return
		}
		role := strings.TrimSpace(r.FormValue("role"))
		if role != "member" && role != "maintainer" {
			role = "member"
		}
		if err := a.Store.AddProjectMember(p.ID, member.ID, role); err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", "could not add member")
			return
		}
		_ = a.Store.CreateUseCaseRecord("private_projects", models.UseCaseRecord{
			UserID: u.ID, Action: "project.member", SubjectID: p.ID, Summary: "Added " + member.Username + " to " + p.Name,
			Detail: "role=" + role, Status: "ok",
		})
		okJSON(w, map[string]any{"summary": "membership granted", "project_id": p.ID, "username": member.Username, "role": role})
	case "patch":
		if u.Role != models.RoleAdmin && u.Role != models.RoleMaintainer {
			errJSON(w, http.StatusForbidden, "forbidden", "membership updates require maintainer rights")
			return
		}
		id, err := idParam(r)
		if err != nil {
			errJSON(w, http.StatusBadRequest, "validation_failed", "invalid id")
			return
		}
		rec, err := a.Store.UseCaseRecordByID("private_projects", id)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "record not found")
			return
		}
		p, err := a.Store.ProjectByID(rec.SubjectID)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "project not found")
			return
		}
		if !a.canManageProject(u, p) {
			errJSON(w, http.StatusForbidden, "forbidden", "only maintainers may update membership")
			return
		}
		memberID, err := strconv.ParseInt(r.FormValue("user_id"), 10, 64)
		if err != nil || memberID == 0 {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "user_id is required")
			return
		}
		role := strings.TrimSpace(r.FormValue("role"))
		if role != "member" && role != "maintainer" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "role must be member or maintainer")
			return
		}
		if _, err := a.Store.UserByID(memberID); err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "user not found")
			return
		}
		if err := a.Store.AddProjectMember(p.ID, memberID, role); err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", "could not update membership")
			return
		}
		okJSON(w, map[string]any{"summary": "membership updated", "project_id": p.ID, "user_id": memberID, "role": role})
	}
}
