package web

import (
	"context"
	"encoding/json"
	"net/http"
	"net/url"
	"strconv"
	"strings"

	"github.com/go-chi/chi/v5"

	"issuetracker/internal/auth"
	"issuetracker/internal/models"
	"issuetracker/internal/store"
)

// Small shared helpers -------------------------------------------------------

func urlEncode(s string) string { return url.QueryEscape(s) }

// slugify converts a project name into a URL-safe slug.
func slugify(name string) string {
	s := strings.ToLower(strings.TrimSpace(name))
	var b strings.Builder
	lastDash := false
	for _, r := range s {
		switch {
		case r >= 'a' && r <= 'z', r >= '0' && r <= '9':
			b.WriteRune(r)
			lastDash = false
		case r == ' ' || r == '-' || r == '_':
			if !lastDash && b.Len() > 0 {
				b.WriteByte('-')
				lastDash = true
			}
		}
	}
	out := strings.TrimSuffix(b.String(), "-")
	if out == "" {
		out = "project"
	}
	return out
}

func hashPassword(pw string) (string, error) { return auth.HashPassword(pw) }

func checkPassword(hash, pw string) bool { return auth.CheckPassword(hash, pw) }

// jsonDecode decodes a JSON request body into v, failing silently when the
// body is not JSON (form posts are the primary client).
func jsonDecode(r *http.Request, v any) error {
	if r.Body == nil {
		return nil
	}
	return json.NewDecoder(r.Body).Decode(v)
}

func chiURLParam(r *http.Request, name string) string { return chi.URLParam(r, name) }

// idParam parses the {id} route parameter.
func idParam(r *http.Request) (int64, error) {
	return strconv.ParseInt(chi.URLParam(r, "id"), 10, 64)
}

// useCaseParams builds record list filters from query parameters.
func useCaseParams(r *http.Request) store.UseCaseRecordParams {
	q := r.URL.Query()
	p := store.UseCaseRecordParams{Action: q.Get("action")}
	if v, err := strconv.ParseInt(q.Get("user_id"), 10, 64); err == nil {
		p.UserID = v
	}
	if v, err := strconv.Atoi(q.Get("limit")); err == nil {
		p.Limit = v
	}
	p.OrderDesc = q.Get("order") != "asc"
	return p
}

// mustUseCaseTable validates the table name against the allow-list.
func validUseCaseTable(t string) bool {
	switch t {
	case "account_access", "project_management", "issue_creation", "issue_search",
		"assignment_and_workflow", "private_projects", "import_export",
		"admin_operations", "frontend_api_integration_and_errors":
		return true
	}
	return false
}

// userVisibleProjects returns the projects visible to the current user.
func (a *App) userVisibleProjects(u *models.User) ([]models.Project, error) {
	return a.Store.ListVisibleProjects(u.ID)
}

// canManageProject reports whether the user has maintainer rights on a project.
func (a *App) canManageProject(u *models.User, p *models.Project) bool {
	if u.Role == models.RoleAdmin || u.Role == models.RoleMaintainer {
		return true
	}
	if p.OwnerID == u.ID {
		return true
	}
	return a.Store.MemberRoleInProject(p.ID, u.ID) == "maintainer"
}

// canAccessProject reports whether the user may view the project.
func (a *App) canAccessProject(u *models.User, p *models.Project) bool {
	if p.Visibility == models.VisibilityPublic {
		return true
	}
	if p.OwnerID == u.ID {
		return true
	}
	return a.Store.IsProjectMember(p.ID, u.ID)
}

// requireProjectAccess gates a page on project visibility.
func (a *App) requireProjectAccess(next http.HandlerFunc) http.HandlerFunc {
	return a.requireAuth(func(w http.ResponseWriter, r *http.Request) {
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
		next(w, r)
	})
}

// requireProjectManage gates a page/action on maintainer rights.
func (a *App) requireProjectManage(next http.HandlerFunc) http.HandlerFunc {
	return a.requireAuth(func(w http.ResponseWriter, r *http.Request) {
		u := userFrom(r)
		p, err := a.Store.ProjectBySlug(chi.URLParam(r, "slug"))
		if err != nil {
			http.NotFound(w, r)
			return
		}
		if !a.canManageProject(u, p) {
			http.Error(w, "You need maintainer rights for this project.", http.StatusForbidden)
			return
		}
		r = withProject(r, p)
		next(w, r)
	})
}

type projKey int

const ctxProject projKey = 1

func withProject(r *http.Request, p *models.Project) *http.Request {
	ctx := context.WithValue(r.Context(), ctxProject, p)
	return r.WithContext(ctx)
}

func projectFrom(r *http.Request) *models.Project {
	p, _ := r.Context().Value(ctxProject).(*models.Project)
	return p
}

// projectSlug returns the slug from URL param or context.
func (a *App) projectFromSlug(r *http.Request) (*models.Project, error) {
	if p := projectFrom(r); p != nil {
		return p, nil
	}
	return a.Store.ProjectBySlug(chi.URLParam(r, "slug"))
}

// projectCanAccessForIssue checks project access and 403s on failure.
func (a *App) ensureIssueAccess(w http.ResponseWriter, u *models.User, issueID int64) bool {
	it, err := a.Store.IssueByID(issueID)
	if err != nil {
		errJSON(w, http.StatusNotFound, "not_found", "issue not found")
		return false
	}
	p, err := a.Store.ProjectByID(it.ProjectID)
	if err != nil {
		errJSON(w, http.StatusNotFound, "not_found", "project not found")
		return false
	}
	if !a.canAccessProject(u, p) {
		errJSON(w, http.StatusForbidden, "forbidden", "you do not have access to this project")
		return false
	}
	return true
}
