package app

import (
	"context"
	"crypto/rand"
	"crypto/sha256"
	"database/sql"
	"encoding/csv"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"github.com/go-chi/chi/v5"
	_ "modernc.org/sqlite"
)

type App struct {
	DB         *sql.DB
	CookieName string
	StorageDir string
}

type apiError struct {
	Error struct {
		Code    string            `json:"code"`
		Message string            `json:"message"`
		Fields  map[string]string `json:"fields,omitempty"`
	} `json:"error"`
}

type actor struct {
	ID    int64
	Role  string
	Email string
}

func New(dbPath, cookieName, storageDir string) (*App, error) {
	if cookieName == "" {
		cookieName = "p09_session"
	}
	if storageDir == "" {
		storageDir = "./storage"
	}
	if err := os.MkdirAll(filepath.Dir(dbPath), 0755); err != nil {
		return nil, err
	}
	if err := os.MkdirAll(storageDir, 0755); err != nil {
		return nil, err
	}
	db, err := sql.Open("sqlite", dbPath)
	if err != nil {
		return nil, err
	}
	db.SetMaxOpenConns(1)
	if _, err = db.Exec(`PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;`); err != nil {
		db.Close()
		return nil, err
	}
	return &App{DB: db, CookieName: cookieName, StorageDir: storageDir}, nil
}

func (a *App) Close() error { return a.DB.Close() }

func schema() string {
	return `
DROP TABLE IF EXISTS notifications; DROP TABLE IF EXISTS watchers; DROP TABLE IF EXISTS webhook_deliveries; DROP TABLE IF EXISTS webhooks; DROP TABLE IF EXISTS imports; DROP TABLE IF EXISTS attachments; DROP TABLE IF EXISTS comments; DROP TABLE IF EXISTS issue_labels; DROP TABLE IF EXISTS issues; DROP TABLE IF EXISTS workflow_transitions; DROP TABLE IF EXISTS project_members; DROP TABLE IF EXISTS projects; DROP TABLE IF EXISTS labels; DROP TABLE IF EXISTS audit_events; DROP TABLE IF EXISTS password_resets; DROP TABLE IF EXISTS sessions; DROP TABLE IF EXISTS users;
CREATE TABLE users(id INTEGER PRIMARY KEY AUTOINCREMENT,email TEXT NOT NULL UNIQUE,password_hash TEXT NOT NULL,role TEXT NOT NULL CHECK(role IN('member','admin')),active INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL);
CREATE TABLE sessions(token TEXT PRIMARY KEY,user_id INTEGER NOT NULL,expires_at TEXT NOT NULL,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE password_resets(token TEXT PRIMARY KEY,user_id INTEGER NOT NULL,expires_at TEXT NOT NULL,used INTEGER NOT NULL DEFAULT 0,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE projects(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,description TEXT NOT NULL DEFAULT '',is_private INTEGER NOT NULL DEFAULT 0,owner_id INTEGER NOT NULL,version INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL,FOREIGN KEY(owner_id) REFERENCES users(id));
CREATE TABLE project_members(project_id INTEGER NOT NULL,user_id INTEGER NOT NULL,member_role TEXT NOT NULL DEFAULT 'member',PRIMARY KEY(project_id,user_id),FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE labels(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL UNIQUE,color TEXT NOT NULL DEFAULT '#777777');
CREATE TABLE issues(id INTEGER PRIMARY KEY AUTOINCREMENT,project_id INTEGER NOT NULL,title TEXT NOT NULL,description TEXT NOT NULL DEFAULT '',issue_type TEXT NOT NULL DEFAULT 'task',priority TEXT NOT NULL DEFAULT 'medium',status TEXT NOT NULL DEFAULT 'open',assignee_id INTEGER,reporter_id INTEGER NOT NULL,version INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL,FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,FOREIGN KEY(assignee_id) REFERENCES users(id),FOREIGN KEY(reporter_id) REFERENCES users(id));
CREATE TABLE issue_labels(issue_id INTEGER NOT NULL,label_id INTEGER NOT NULL,PRIMARY KEY(issue_id,label_id),FOREIGN KEY(issue_id) REFERENCES issues(id) ON DELETE CASCADE,FOREIGN KEY(label_id) REFERENCES labels(id));
CREATE TABLE workflow_transitions(id INTEGER PRIMARY KEY AUTOINCREMENT,project_id INTEGER NOT NULL,from_status TEXT NOT NULL,to_status TEXT NOT NULL,UNIQUE(project_id,from_status,to_status),FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE);
CREATE TABLE comments(id INTEGER PRIMARY KEY AUTOINCREMENT,issue_id INTEGER NOT NULL,author_id INTEGER NOT NULL,body TEXT NOT NULL,version INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL,FOREIGN KEY(issue_id) REFERENCES issues(id) ON DELETE CASCADE,FOREIGN KEY(author_id) REFERENCES users(id));
CREATE TABLE attachments(id INTEGER PRIMARY KEY AUTOINCREMENT,issue_id INTEGER NOT NULL,uploader_id INTEGER NOT NULL,filename TEXT NOT NULL,stored_name TEXT NOT NULL,mime_type TEXT NOT NULL,size INTEGER NOT NULL,created_at TEXT NOT NULL,FOREIGN KEY(issue_id) REFERENCES issues(id) ON DELETE CASCADE,FOREIGN KEY(uploader_id) REFERENCES users(id));
CREATE TABLE webhooks(id INTEGER PRIMARY KEY AUTOINCREMENT,project_id INTEGER NOT NULL,url TEXT NOT NULL,secret TEXT NOT NULL,active INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL,FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE);
CREATE TABLE webhook_deliveries(id INTEGER PRIMARY KEY AUTOINCREMENT,webhook_id INTEGER NOT NULL,event TEXT NOT NULL,status_code INTEGER NOT NULL,payload TEXT NOT NULL,created_at TEXT NOT NULL,FOREIGN KEY(webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE);
CREATE TABLE imports(id INTEGER PRIMARY KEY AUTOINCREMENT,project_id INTEGER NOT NULL,created_by INTEGER NOT NULL,imported_count INTEGER NOT NULL,status TEXT NOT NULL,created_at TEXT NOT NULL,FOREIGN KEY(project_id) REFERENCES projects(id),FOREIGN KEY(created_by) REFERENCES users(id));
CREATE TABLE watchers(issue_id INTEGER NOT NULL,user_id INTEGER NOT NULL,PRIMARY KEY(issue_id,user_id),FOREIGN KEY(issue_id) REFERENCES issues(id) ON DELETE CASCADE,FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,issue_id INTEGER,message TEXT NOT NULL,read_at TEXT,created_at TEXT NOT NULL,FOREIGN KEY(user_id) REFERENCES users(id),FOREIGN KEY(issue_id) REFERENCES issues(id));
CREATE TABLE audit_events(id INTEGER PRIMARY KEY AUTOINCREMENT,actor_id INTEGER,action TEXT NOT NULL,target_type TEXT NOT NULL,target_id INTEGER,details TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL,FOREIGN KEY(actor_id) REFERENCES users(id));
`
}

func hashPassword(s string) string { x := sha256.Sum256([]byte(s)); return hex.EncodeToString(x[:]) }
func token() string                { b := make([]byte, 24); _, _ = rand.Read(b); return hex.EncodeToString(b) }
func now() string                  { return time.Now().UTC().Format(time.RFC3339Nano) }

func (a *App) ResetAndSeed() error {
	if _, err := a.DB.Exec(schema()); err != nil {
		return err
	}
	tx, err := a.DB.Begin()
	if err != nil {
		return err
	}
	defer tx.Rollback()
	users := []struct{ email, pass, role string }{{"admin@example.test", "AdminPass123!", "admin"}, {"owner@example.test", "OwnerPass123!", "member"}, {"member@example.test", "MemberPass123!", "member"}, {"outsider@example.test", "OutPass123!", "member"}}
	for _, u := range users {
		if _, err = tx.Exec(`INSERT INTO users(email,password_hash,role,created_at) VALUES(?,?,?,?)`, u.email, hashPassword(u.pass), u.role, now()); err != nil {
			return err
		}
	}
	if _, err = tx.Exec(`INSERT INTO projects(name,description,is_private,owner_id,created_at) VALUES('Alpha','Seed private project',1,2,?)`, now()); err != nil {
		return err
	}
	if _, err = tx.Exec(`INSERT INTO project_members(project_id,user_id,member_role) VALUES(1,2,'owner'),(1,3,'member')`); err != nil {
		return err
	}
	if _, err = tx.Exec(`INSERT INTO labels(name,color) VALUES('bug','#d73a4a'),('feature','#0e8a16')`); err != nil {
		return err
	}
	if _, err = tx.Exec(`INSERT INTO issues(project_id,title,description,issue_type,priority,status,assignee_id,reporter_id,created_at) VALUES(1,'Seed issue','seed','bug','high','open',3,2,?)`, now()); err != nil {
		return err
	}
	if _, err = tx.Exec(`INSERT INTO issue_labels(issue_id,label_id) VALUES(1,1)`); err != nil {
		return err
	}
	if _, err = tx.Exec(`INSERT INTO workflow_transitions(project_id,from_status,to_status) VALUES(1,'open','in_progress'),(1,'in_progress','closed'),(1,'open','closed')`); err != nil {
		return err
	}
	if _, err = tx.Exec(`INSERT INTO comments(issue_id,author_id,body,created_at) VALUES(1,3,'Seed comment',?)`, now()); err != nil {
		return err
	}
	if _, err = tx.Exec(`INSERT INTO watchers(issue_id,user_id) VALUES(1,3)`); err != nil {
		return err
	}
	if _, err = tx.Exec(`INSERT INTO notifications(user_id,issue_id,message,created_at) VALUES(3,1,'Seed notification',?)`, now()); err != nil {
		return err
	}
	return tx.Commit()
}

func (a *App) Router() http.Handler {
	r := chi.NewRouter()
	r.Get("/", a.home)
	r.Get("/login", a.loginPage)
	r.Get("/issues", a.issuesPage)
	r.Get("/admin", a.adminPage)
	r.Route("/api", func(r chi.Router) {
		r.Post("/auth/register", a.register)
		r.Post("/auth/login", a.login)
		r.Post("/auth/logout", a.logout)
		r.Post("/auth/password-reset-requests", a.passwordResetRequest)
		r.Post("/auth/password-resets", a.passwordReset)
		r.Group(func(r chi.Router) {
			r.Use(a.auth)
			r.Post("/projects", a.createProject)
			r.Get("/projects", a.listProjects)
			r.Get("/projects/{projectId}", a.getProject)
			r.Patch("/projects/{projectId}", a.patchProject)
			r.Post("/projects/{projectId}/members", a.addMember)
			r.Patch("/projects/{projectId}/members/{userId}", a.patchMember)
			r.Delete("/projects/{projectId}/members/{userId}", a.deleteMember)
			r.Post("/projects/{projectId}/issues", a.createIssue)
			r.Get("/issues/{issueId}", a.getIssue)
			r.Patch("/issues/{issueId}", a.patchIssue)
			r.Get("/issues", a.searchIssues)
			r.Get("/issues/{issueId}/comments", a.listComments)
			r.Post("/issues/{issueId}/comments", a.createComment)
			r.Patch("/comments/{commentId}", a.patchComment)
			r.Delete("/comments/{commentId}", a.deleteComment)
			r.Put("/issues/{issueId}/assignee", a.assignIssue)
			r.Post("/issues/{issueId}/transitions", a.transitionIssue)
			r.Post("/issues/{issueId}/attachments", a.createAttachment)
			r.Get("/attachments/{attachmentId}/content", a.attachmentContent)
			r.Delete("/attachments/{attachmentId}", a.deleteAttachment)
			r.Get("/projects/{projectId}/webhooks", a.listWebhooks)
			r.Post("/projects/{projectId}/webhooks", a.createWebhook)
			r.Post("/webhooks/{webhookId}/rotate-secret", a.rotateWebhook)
			r.Get("/webhooks/{webhookId}/deliveries", a.webhookDeliveries)
			r.Post("/projects/{projectId}/imports", a.importIssues)
			r.Get("/projects/{projectId}/issues.csv", a.exportIssues)
			r.Post("/issues/{issueId}/watchers/me", a.watchIssue)
			r.Delete("/issues/{issueId}/watchers/me", a.unwatchIssue)
			r.Get("/notifications", a.notifications)
			r.Post("/notifications/{notificationId}/read", a.readNotification)
			r.Get("/admin/users", a.adminUsers)
			r.Patch("/admin/users/{userId}", a.adminPatchUser)
			r.Get("/admin/audit-events", a.adminAudit)
			r.Post("/admin/labels", a.adminCreateLabel)
		})
	})
	return r
}

type ctxKey string

const actorKey ctxKey = "actor"

func (a *App) auth(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		c, err := r.Cookie(a.CookieName)
		if err != nil {
			fail(w, 401, "unauthenticated", "authentication required", nil)
			return
		}
		var u actor
		var exp string
		err = a.DB.QueryRow(`SELECT u.id,u.role,u.email,s.expires_at FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.token=? AND u.active=1`, c.Value).Scan(&u.ID, &u.Role, &u.Email, &exp)
		if err != nil {
			fail(w, 401, "unauthenticated", "invalid session", nil)
			return
		}
		t, _ := time.Parse(time.RFC3339Nano, exp)
		if time.Now().After(t) {
			fail(w, 401, "unauthenticated", "expired session", nil)
			return
		}
		next.ServeHTTP(w, r.WithContext(context.WithValue(r.Context(), actorKey, u)))
	})
}
func who(r *http.Request) actor { return r.Context().Value(actorKey).(actor) }
func jsonOut(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}
func fail(w http.ResponseWriter, status int, code, msg string, fields map[string]string) {
	var e apiError
	e.Error.Code = code
	e.Error.Message = msg
	e.Error.Fields = fields
	jsonOut(w, status, e)
}
func decode(r *http.Request, dst any) error {
	dec := json.NewDecoder(io.LimitReader(r.Body, 1<<20))
	dec.DisallowUnknownFields()
	return dec.Decode(dst)
}
func idParam(r *http.Request, name string) (int64, error) {
	return strconv.ParseInt(chi.URLParam(r, name), 10, 64)
}
func (a *App) audit(actorID int64, action, target string, id int64, details string) {
	_, _ = a.DB.Exec(`INSERT INTO audit_events(actor_id,action,target_type,target_id,details,created_at) VALUES(?,?,?,?,?,?)`, actorID, action, target, id, details, now())
}
func (a *App) isMember(projectID, userID int64) bool {
	var n int
	_ = a.DB.QueryRow(`SELECT COUNT(*) FROM project_members WHERE project_id=? AND user_id=?`, projectID, userID).Scan(&n)
	return n > 0
}
func (a *App) isOwnerOrAdmin(projectID int64, u actor) bool {
	if u.Role == "admin" {
		return true
	}
	var owner int64
	if a.DB.QueryRow(`SELECT owner_id FROM projects WHERE id=?`, projectID).Scan(&owner) != nil {
		return false
	}
	return owner == u.ID
}
func (a *App) projectForIssue(issueID int64) (int64, error) {
	var p int64
	err := a.DB.QueryRow(`SELECT project_id FROM issues WHERE id=?`, issueID).Scan(&p)
	return p, err
}
func (a *App) requireIssueMember(w http.ResponseWriter, r *http.Request, issueID int64) (int64, bool) {
	p, err := a.projectForIssue(issueID)
	if errors.Is(err, sql.ErrNoRows) {
		fail(w, 404, "not_found", "issue not found", nil)
		return 0, false
	}
	if err != nil {
		fail(w, 500, "internal_error", "operation failed", nil)
		return 0, false
	}
	u := who(r)
	if u.Role != "admin" && !a.isMember(p, u.ID) {
		fail(w, 403, "forbidden", "project membership required", nil)
		return 0, false
	}
	return p, true
}

func (a *App) home(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html")
	fmt.Fprint(w, `<!doctype html><title>P09 Issue Tracker</title><h1>P09 Issue Tracking System</h1><nav><a href="/login">Login</a> <a href="/issues">Issue Search</a> <a href="/admin">Admin</a></nav>`)
}
func (a *App) loginPage(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html")
	fmt.Fprint(w, `<!doctype html><title>Login</title><h1>Login</h1><form id="login"><input name="email"><input name="password" type="password"><button>Login</button></form>`)
}
func (a *App) issuesPage(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html")
	fmt.Fprint(w, `<!doctype html><title>Issues</title><h1>Issue Search</h1><input id="q"><button>Search</button><div id="results"></div>`)
}
func (a *App) adminPage(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html")
	fmt.Fprint(w, `<!doctype html><title>Admin</title><h1>Admin Governance</h1>`)
}

func (a *App) register(w http.ResponseWriter, r *http.Request) {
	var in struct{ Email, Password string }
	if decode(r, &in) != nil || !strings.Contains(in.Email, "@") || len(in.Password) < 8 {
		fail(w, 422, "validation_error", "invalid registration", map[string]string{"email": "valid email required", "password": "minimum 8 characters"})
		return
	}
	res, err := a.DB.Exec(`INSERT INTO users(email,password_hash,role,created_at) VALUES(?,?,'member',?)`, strings.ToLower(in.Email), hashPassword(in.Password), now())
	if err != nil {
		fail(w, 409, "conflict", "email already registered", nil)
		return
	}
	id, _ := res.LastInsertId()
	jsonOut(w, 201, map[string]any{"id": id, "email": strings.ToLower(in.Email), "role": "member"})
}
func (a *App) login(w http.ResponseWriter, r *http.Request) {
	var in struct{ Email, Password string }
	if decode(r, &in) != nil {
		fail(w, 422, "validation_error", "invalid credentials payload", nil)
		return
	}
	var id int64
	var role, hash string
	err := a.DB.QueryRow(`SELECT id,role,password_hash FROM users WHERE email=? AND active=1`, strings.ToLower(in.Email)).Scan(&id, &role, &hash)
	if err != nil || hash != hashPassword(in.Password) {
		fail(w, 401, "invalid_credentials", "invalid email or password", nil)
		return
	}
	t := token()
	exp := time.Now().UTC().Add(24 * time.Hour)
	_, _ = a.DB.Exec(`INSERT INTO sessions(token,user_id,expires_at) VALUES(?,?,?)`, t, id, exp.Format(time.RFC3339Nano))
	http.SetCookie(w, &http.Cookie{Name: a.CookieName, Value: t, Path: "/", HttpOnly: true, SameSite: http.SameSiteLaxMode, Expires: exp})
	jsonOut(w, 200, map[string]any{"user": map[string]any{"id": id, "email": in.Email, "role": role}})
}
func (a *App) logout(w http.ResponseWriter, r *http.Request) {
	if c, err := r.Cookie(a.CookieName); err == nil {
		_, _ = a.DB.Exec(`DELETE FROM sessions WHERE token=?`, c.Value)
	}
	http.SetCookie(w, &http.Cookie{Name: a.CookieName, Value: "", Path: "/", MaxAge: -1, HttpOnly: true})
	jsonOut(w, 200, map[string]bool{"ok": true})
}
func (a *App) passwordResetRequest(w http.ResponseWriter, r *http.Request) {
	var in struct{ Email string }
	if decode(r, &in) != nil || !strings.Contains(in.Email, "@") {
		fail(w, 422, "validation_error", "valid email required", nil)
		return
	}
	var id int64
	if a.DB.QueryRow(`SELECT id FROM users WHERE email=?`, strings.ToLower(in.Email)).Scan(&id) == nil {
		t := token()
		_, _ = a.DB.Exec(`INSERT INTO password_resets(token,user_id,expires_at) VALUES(?,?,?)`, t, id, time.Now().UTC().Add(time.Hour).Format(time.RFC3339Nano))
	}
	jsonOut(w, 200, map[string]string{"message": "If the account exists, a reset request was created."})
}
func (a *App) passwordReset(w http.ResponseWriter, r *http.Request) {
	var in struct{ Token, Password string }
	if decode(r, &in) != nil || len(in.Password) < 8 {
		fail(w, 422, "validation_error", "invalid reset payload", nil)
		return
	}
	var uid int64
	var exp string
	var used int
	err := a.DB.QueryRow(`SELECT user_id,expires_at,used FROM password_resets WHERE token=?`, in.Token).Scan(&uid, &exp, &used)
	t, _ := time.Parse(time.RFC3339Nano, exp)
	if err != nil || used == 1 || time.Now().After(t) {
		fail(w, 409, "invalid_state", "reset token invalid or expired", nil)
		return
	}
	tx, _ := a.DB.Begin()
	defer tx.Rollback()
	_, err = tx.Exec(`UPDATE users SET password_hash=? WHERE id=?`, hashPassword(in.Password), uid)
	if err == nil {
		_, err = tx.Exec(`UPDATE password_resets SET used=1 WHERE token=?`, in.Token)
	}
	if err != nil {
		fail(w, 500, "internal_error", "operation failed", nil)
		return
	}
	_ = tx.Commit()
	jsonOut(w, 200, map[string]bool{"ok": true})
}

func (a *App) createProject(w http.ResponseWriter, r *http.Request) {
	u := who(r)
	var in struct {
		Name, Description string
		Private           bool
	}
	if decode(r, &in) != nil || strings.TrimSpace(in.Name) == "" {
		fail(w, 422, "validation_error", "project name required", map[string]string{"name": "required"})
		return
	}
	tx, _ := a.DB.Begin()
	defer tx.Rollback()
	res, err := tx.Exec(`INSERT INTO projects(name,description,is_private,owner_id,created_at) VALUES(?,?,?,?,?)`, in.Name, in.Description, boolInt(in.Private), u.ID, now())
	if err != nil {
		fail(w, 500, "internal_error", "operation failed", nil)
		return
	}
	id, _ := res.LastInsertId()
	_, err = tx.Exec(`INSERT INTO project_members(project_id,user_id,member_role) VALUES(?,?,'owner')`, id, u.ID)
	if err != nil {
		fail(w, 500, "internal_error", "operation failed", nil)
		return
	}
	_, _ = tx.Exec(`INSERT INTO workflow_transitions(project_id,from_status,to_status) VALUES(?,'open','in_progress'),(?,'in_progress','closed'),(?,'open','closed')`, id, id, id)
	_ = tx.Commit()
	a.audit(u.ID, "project.create", "project", id, "")
	jsonOut(w, 201, map[string]any{"id": id, "name": in.Name, "private": in.Private, "ownerId": u.ID, "version": 1})
}
func boolInt(v bool) int {
	if v {
		return 1
	}
	return 0
}
func (a *App) listProjects(w http.ResponseWriter, r *http.Request) {
	u := who(r)
	rows, err := a.DB.Query(`SELECT p.id,p.name,p.description,p.is_private,p.owner_id,p.version FROM projects p WHERE p.is_private=0 OR p.owner_id=? OR EXISTS(SELECT 1 FROM project_members m WHERE m.project_id=p.id AND m.user_id=?) ORDER BY p.id LIMIT 100`, u.ID, u.ID)
	if err != nil {
		fail(w, 500, "internal_error", "operation failed", nil)
		return
	}
	defer rows.Close()
	out := []map[string]any{}
	for rows.Next() {
		var id, owner, ver int64
		var name, desc string
		var priv int
		_ = rows.Scan(&id, &name, &desc, &priv, &owner, &ver)
		out = append(out, map[string]any{"id": id, "name": name, "description": desc, "private": priv == 1, "ownerId": owner, "version": ver})
	}
	jsonOut(w, 200, map[string]any{"items": out})
}
func (a *App) getProject(w http.ResponseWriter, r *http.Request) {
	id, err := idParam(r, "projectId")
	if err != nil {
		fail(w, 404, "not_found", "project not found", nil)
		return
	}
	u := who(r)
	var name, desc string
	var priv int
	var owner, ver int64
	err = a.DB.QueryRow(`SELECT name,description,is_private,owner_id,version FROM projects WHERE id=?`, id).Scan(&name, &desc, &priv, &owner, &ver)
	if errors.Is(err, sql.ErrNoRows) {
		fail(w, 404, "not_found", "project not found", nil)
		return
	}
	if priv == 1 && u.Role != "admin" && !a.isMember(id, u.ID) {
		fail(w, 403, "forbidden", "private project", nil)
		return
	}
	jsonOut(w, 200, map[string]any{"id": id, "name": name, "description": desc, "private": priv == 1, "ownerId": owner, "version": ver})
}
func (a *App) patchProject(w http.ResponseWriter, r *http.Request) {
	id, _ := idParam(r, "projectId")
	u := who(r)
	if !a.isOwnerOrAdmin(id, u) {
		fail(w, 403, "forbidden", "owner required", nil)
		return
	}
	var in struct {
		Name, Description string
		Private           *bool
		Version           int64
	}
	if decode(r, &in) != nil || in.Version < 1 {
		fail(w, 422, "validation_error", "version required", nil)
		return
	}
	var cur int64
	if a.DB.QueryRow(`SELECT version FROM projects WHERE id=?`, id).Scan(&cur) != nil {
		fail(w, 404, "not_found", "project not found", nil)
		return
	}
	if cur != in.Version {
		fail(w, 409, "stale_version", "stale project version", nil)
		return
	}
	if in.Name == "" {
		_ = a.DB.QueryRow(`SELECT name FROM projects WHERE id=?`, id).Scan(&in.Name)
	}
	if in.Description == "" {
		_ = a.DB.QueryRow(`SELECT description FROM projects WHERE id=?`, id).Scan(&in.Description)
	}
	var p int
	_ = a.DB.QueryRow(`SELECT is_private FROM projects WHERE id=?`, id).Scan(&p)
	if in.Private != nil {
		p = boolInt(*in.Private)
	}
	_, _ = a.DB.Exec(`UPDATE projects SET name=?,description=?,is_private=?,version=version+1 WHERE id=?`, in.Name, in.Description, p, id)
	a.audit(u.ID, "project.update", "project", id, "")
	a.getProject(w, r)
}
func (a *App) addMember(w http.ResponseWriter, r *http.Request) {
	pid, _ := idParam(r, "projectId")
	u := who(r)
	if !a.isOwnerOrAdmin(pid, u) {
		fail(w, 403, "forbidden", "owner required", nil)
		return
	}
	var in struct {
		UserID int64
		Role   string
	}
	if decode(r, &in) != nil || in.UserID < 1 {
		fail(w, 422, "validation_error", "userId required", nil)
		return
	}
	if in.Role == "" {
		in.Role = "member"
	}
	_, err := a.DB.Exec(`INSERT INTO project_members(project_id,user_id,member_role) VALUES(?,?,?)`, pid, in.UserID, in.Role)
	if err != nil {
		fail(w, 409, "conflict", "member already exists or invalid", nil)
		return
	}
	a.audit(u.ID, "member.add", "project", pid, fmt.Sprint(in.UserID))
	jsonOut(w, 201, map[string]any{"projectId": pid, "userId": in.UserID, "role": in.Role})
}
func (a *App) patchMember(w http.ResponseWriter, r *http.Request) {
	pid, _ := idParam(r, "projectId")
	uid, _ := idParam(r, "userId")
	u := who(r)
	if !a.isOwnerOrAdmin(pid, u) {
		fail(w, 403, "forbidden", "owner required", nil)
		return
	}
	var in struct{ Role string }
	if decode(r, &in) != nil || in.Role == "" {
		fail(w, 422, "validation_error", "role required", nil)
		return
	}
	res, _ := a.DB.Exec(`UPDATE project_members SET member_role=? WHERE project_id=? AND user_id=?`, in.Role, pid, uid)
	n, _ := res.RowsAffected()
	if n == 0 {
		fail(w, 404, "not_found", "member not found", nil)
		return
	}
	jsonOut(w, 200, map[string]any{"projectId": pid, "userId": uid, "role": in.Role})
}
func (a *App) deleteMember(w http.ResponseWriter, r *http.Request) {
	pid, _ := idParam(r, "projectId")
	uid, _ := idParam(r, "userId")
	u := who(r)
	if !a.isOwnerOrAdmin(pid, u) {
		fail(w, 403, "forbidden", "owner required", nil)
		return
	}
	var owner int64
	_ = a.DB.QueryRow(`SELECT owner_id FROM projects WHERE id=?`, pid).Scan(&owner)
	if owner == uid {
		fail(w, 409, "invalid_state", "cannot remove project owner", nil)
		return
	}
	res, _ := a.DB.Exec(`DELETE FROM project_members WHERE project_id=? AND user_id=?`, pid, uid)
	n, _ := res.RowsAffected()
	if n == 0 {
		fail(w, 404, "not_found", "member not found", nil)
		return
	}
	w.WriteHeader(204)
}

func (a *App) createIssue(w http.ResponseWriter, r *http.Request) {
	pid, _ := idParam(r, "projectId")
	u := who(r)
	if u.Role != "admin" && !a.isMember(pid, u.ID) {
		fail(w, 403, "forbidden", "membership required", nil)
		return
	}
	var in struct {
		Title, Description, Type, Priority string
		Labels                             []int64
	}
	if decode(r, &in) != nil || strings.TrimSpace(in.Title) == "" {
		fail(w, 422, "validation_error", "title required", nil)
		return
	}
	if in.Type == "" {
		in.Type = "task"
	}
	if in.Priority == "" {
		in.Priority = "medium"
	}
	tx, _ := a.DB.Begin()
	defer tx.Rollback()
	res, err := tx.Exec(`INSERT INTO issues(project_id,title,description,issue_type,priority,status,reporter_id,created_at) VALUES(?,?,?,?,?,'open',?,?)`, pid, in.Title, in.Description, in.Type, in.Priority, u.ID, now())
	if err != nil {
		fail(w, 404, "not_found", "project not found", nil)
		return
	}
	id, _ := res.LastInsertId()
	for _, lid := range in.Labels {
		if _, err = tx.Exec(`INSERT INTO issue_labels(issue_id,label_id) VALUES(?,?)`, id, lid); err != nil {
			fail(w, 422, "validation_error", "invalid label", nil)
			return
		}
	}
	_ = tx.Commit()
	a.notifyWatchers(id, u.ID, "Issue created: "+in.Title)
	jsonOut(w, 201, map[string]any{"id": id, "projectId": pid, "title": in.Title, "status": "open", "version": 1})
}
func (a *App) getIssue(w http.ResponseWriter, r *http.Request) {
	id, _ := idParam(r, "issueId")
	if _, ok := a.requireIssueMember(w, r, id); !ok {
		return
	}
	var pid, reporter, ver int64
	var assignee sql.NullInt64
	var title, desc, typ, priority, status string
	err := a.DB.QueryRow(`SELECT project_id,title,description,issue_type,priority,status,assignee_id,reporter_id,version FROM issues WHERE id=?`, id).Scan(&pid, &title, &desc, &typ, &priority, &status, &assignee, &reporter, &ver)
	if err != nil {
		fail(w, 404, "not_found", "issue not found", nil)
		return
	}
	jsonOut(w, 200, map[string]any{"id": id, "projectId": pid, "title": title, "description": desc, "type": typ, "priority": priority, "status": status, "assigneeId": nullableInt(assignee), "reporterId": reporter, "version": ver})
}
func nullableInt(n sql.NullInt64) any {
	if n.Valid {
		return n.Int64
	}
	return nil
}
func (a *App) patchIssue(w http.ResponseWriter, r *http.Request) {
	id, _ := idParam(r, "issueId")
	if _, ok := a.requireIssueMember(w, r, id); !ok {
		return
	}
	var in struct {
		Title, Description, Priority string
		Version                      int64
	}
	if decode(r, &in) != nil || in.Version < 1 {
		fail(w, 422, "validation_error", "version required", nil)
		return
	}
	var cur int64
	var title, desc, pri string
	_ = a.DB.QueryRow(`SELECT version,title,description,priority FROM issues WHERE id=?`, id).Scan(&cur, &title, &desc, &pri)
	if cur != in.Version {
		fail(w, 409, "stale_version", "stale issue version", nil)
		return
	}
	if in.Title != "" {
		title = in.Title
	}
	if in.Description != "" {
		desc = in.Description
	}
	if in.Priority != "" {
		pri = in.Priority
	}
	_, _ = a.DB.Exec(`UPDATE issues SET title=?,description=?,priority=?,version=version+1 WHERE id=?`, title, desc, pri, id)
	a.getIssue(w, r)
}
func (a *App) searchIssues(w http.ResponseWriter, r *http.Request) {
	u := who(r)
	page := 1
	if p, _ := strconv.Atoi(r.URL.Query().Get("page")); p > 0 {
		page = p
	}
	if page > 1000 {
		fail(w, 422, "validation_error", "page out of range", nil)
		return
	}
	q := "%" + r.URL.Query().Get("q") + "%"
	status := r.URL.Query().Get("status")
	projectID := r.URL.Query().Get("projectId")
	assignee := r.URL.Query().Get("assigneeId")
	label := r.URL.Query().Get("label")
	args := []any{u.ID, u.ID, q, q}
	where := `(p.is_private=0 OR p.owner_id=? OR EXISTS(SELECT 1 FROM project_members pm WHERE pm.project_id=p.id AND pm.user_id=?)) AND (i.title LIKE ? OR i.description LIKE ?)`
	if status != "" {
		where += " AND i.status=?"
		args = append(args, status)
	}
	if projectID != "" {
		where += " AND i.project_id=?"
		args = append(args, projectID)
	}
	if assignee != "" {
		where += " AND i.assignee_id=?"
		args = append(args, assignee)
	}
	if label != "" {
		where += ` AND EXISTS(SELECT 1 FROM issue_labels il JOIN labels l ON l.id=il.label_id WHERE il.issue_id=i.id AND l.name=?)`
		args = append(args, label)
	}
	args = append(args, (page-1)*50)
	rows, err := a.DB.Query(`SELECT i.id,i.project_id,i.title,i.status,i.priority FROM issues i JOIN projects p ON p.id=i.project_id WHERE `+where+` ORDER BY i.id LIMIT 50 OFFSET ?`, args...)
	if err != nil {
		fail(w, 500, "internal_error", "operation failed", nil)
		return
	}
	defer rows.Close()
	items := []map[string]any{}
	for rows.Next() {
		var id, pid int64
		var title, st, pri string
		_ = rows.Scan(&id, &pid, &title, &st, &pri)
		items = append(items, map[string]any{"id": id, "projectId": pid, "title": title, "status": st, "priority": pri})
	}
	jsonOut(w, 200, map[string]any{"items": items, "page": page})
}

func (a *App) listComments(w http.ResponseWriter, r *http.Request) {
	iid, _ := idParam(r, "issueId")
	if _, ok := a.requireIssueMember(w, r, iid); !ok {
		return
	}
	rows, _ := a.DB.Query(`SELECT id,author_id,body,version FROM comments WHERE issue_id=? ORDER BY id`, iid)
	defer rows.Close()
	items := []map[string]any{}
	for rows.Next() {
		var id, aid, v int64
		var b string
		_ = rows.Scan(&id, &aid, &b, &v)
		items = append(items, map[string]any{"id": id, "authorId": aid, "body": b, "version": v})
	}
	jsonOut(w, 200, map[string]any{"items": items})
}
func (a *App) createComment(w http.ResponseWriter, r *http.Request) {
	iid, _ := idParam(r, "issueId")
	if _, ok := a.requireIssueMember(w, r, iid); !ok {
		return
	}
	u := who(r)
	var in struct{ Body string }
	if decode(r, &in) != nil || strings.TrimSpace(in.Body) == "" {
		fail(w, 422, "validation_error", "body required", nil)
		return
	}
	res, _ := a.DB.Exec(`INSERT INTO comments(issue_id,author_id,body,created_at) VALUES(?,?,?,?)`, iid, u.ID, in.Body, now())
	id, _ := res.LastInsertId()
	a.notifyWatchers(iid, u.ID, "New comment")
	jsonOut(w, 201, map[string]any{"id": id, "issueId": iid, "authorId": u.ID, "body": in.Body, "version": 1})
}
func (a *App) patchComment(w http.ResponseWriter, r *http.Request) {
	id, _ := idParam(r, "commentId")
	u := who(r)
	var iid, author, cur int64
	if a.DB.QueryRow(`SELECT issue_id,author_id,version FROM comments WHERE id=?`, id).Scan(&iid, &author, &cur) != nil {
		fail(w, 404, "not_found", "comment not found", nil)
		return
	}
	if _, ok := a.requireIssueMember(w, r, iid); !ok {
		return
	}
	if author != u.ID && u.Role != "admin" {
		fail(w, 403, "forbidden", "comment author required", nil)
		return
	}
	var in struct {
		Body    string
		Version int64
	}
	if decode(r, &in) != nil || in.Body == "" || in.Version < 1 {
		fail(w, 422, "validation_error", "body and version required", nil)
		return
	}
	if cur != in.Version {
		fail(w, 409, "stale_version", "stale comment version", nil)
		return
	}
	_, _ = a.DB.Exec(`UPDATE comments SET body=?,version=version+1 WHERE id=?`, in.Body, id)
	jsonOut(w, 200, map[string]any{"id": id, "body": in.Body, "version": cur + 1})
}
func (a *App) deleteComment(w http.ResponseWriter, r *http.Request) {
	id, _ := idParam(r, "commentId")
	u := who(r)
	var iid, author int64
	if a.DB.QueryRow(`SELECT issue_id,author_id FROM comments WHERE id=?`, id).Scan(&iid, &author) != nil {
		fail(w, 404, "not_found", "comment not found", nil)
		return
	}
	if _, ok := a.requireIssueMember(w, r, iid); !ok {
		return
	}
	if author != u.ID && u.Role != "admin" {
		fail(w, 403, "forbidden", "comment author required", nil)
		return
	}
	_, _ = a.DB.Exec(`DELETE FROM comments WHERE id=?`, id)
	w.WriteHeader(204)
}
func (a *App) assignIssue(w http.ResponseWriter, r *http.Request) {
	iid, _ := idParam(r, "issueId")
	pid, ok := a.requireIssueMember(w, r, iid)
	if !ok {
		return
	}
	var in struct{ AssigneeID int64 }
	if decode(r, &in) != nil || in.AssigneeID < 1 {
		fail(w, 422, "validation_error", "assigneeId required", nil)
		return
	}
	if !a.isMember(pid, in.AssigneeID) {
		fail(w, 409, "invalid_state", "assignee is not project member", nil)
		return
	}
	_, _ = a.DB.Exec(`UPDATE issues SET assignee_id=?,version=version+1 WHERE id=?`, in.AssigneeID, iid)
	jsonOut(w, 200, map[string]any{"issueId": iid, "assigneeId": in.AssigneeID})
}
func (a *App) transitionIssue(w http.ResponseWriter, r *http.Request) {
	iid, _ := idParam(r, "issueId")
	pid, ok := a.requireIssueMember(w, r, iid)
	if !ok {
		return
	}
	var in struct{ To string }
	if decode(r, &in) != nil || in.To == "" {
		fail(w, 422, "validation_error", "to required", nil)
		return
	}
	var cur string
	_ = a.DB.QueryRow(`SELECT status FROM issues WHERE id=?`, iid).Scan(&cur)
	var n int
	_ = a.DB.QueryRow(`SELECT COUNT(*) FROM workflow_transitions WHERE project_id=? AND from_status=? AND to_status=?`, pid, cur, in.To).Scan(&n)
	if n == 0 {
		fail(w, 409, "invalid_state", "transition not allowed", nil)
		return
	}
	_, _ = a.DB.Exec(`UPDATE issues SET status=?,version=version+1 WHERE id=?`, in.To, iid)
	a.notifyWatchers(iid, who(r).ID, "Issue status changed to "+in.To)
	jsonOut(w, 200, map[string]any{"issueId": iid, "status": in.To})
}

func (a *App) createAttachment(w http.ResponseWriter, r *http.Request) {
	iid, _ := idParam(r, "issueId")
	if _, ok := a.requireIssueMember(w, r, iid); !ok {
		return
	}
	u := who(r)
	if err := r.ParseMultipartForm(2 << 20); err != nil {
		fail(w, 422, "validation_error", "multipart form required", nil)
		return
	}
	f, h, err := r.FormFile("file")
	if err != nil {
		fail(w, 422, "validation_error", "file required", nil)
		return
	}
	defer f.Close()
	clean := filepath.Base(h.Filename)
	if clean == "." || clean == "" {
		fail(w, 422, "validation_error", "invalid filename", nil)
		return
	}
	stored := token() + ".bin"
	dst, err := os.Create(filepath.Join(a.StorageDir, stored))
	if err != nil {
		fail(w, 500, "internal_error", "operation failed", nil)
		return
	}
	n, err := io.Copy(dst, io.LimitReader(f, 2<<20))
	_ = dst.Close()
	if err != nil {
		fail(w, 500, "internal_error", "operation failed", nil)
		return
	}
	res, _ := a.DB.Exec(`INSERT INTO attachments(issue_id,uploader_id,filename,stored_name,mime_type,size,created_at) VALUES(?,?,?,?,?,?,?)`, iid, u.ID, clean, stored, h.Header.Get("Content-Type"), n, now())
	id, _ := res.LastInsertId()
	jsonOut(w, 201, map[string]any{"id": id, "filename": clean, "size": n})
}
func (a *App) attachmentContent(w http.ResponseWriter, r *http.Request) {
	id, _ := idParam(r, "attachmentId")
	var iid int64
	var name, stored, mime string
	if a.DB.QueryRow(`SELECT issue_id,filename,stored_name,mime_type FROM attachments WHERE id=?`, id).Scan(&iid, &name, &stored, &mime) != nil {
		fail(w, 404, "not_found", "attachment not found", nil)
		return
	}
	if _, ok := a.requireIssueMember(w, r, iid); !ok {
		return
	}
	b, err := os.ReadFile(filepath.Join(a.StorageDir, stored))
	if err != nil {
		fail(w, 404, "not_found", "attachment content missing", nil)
		return
	}
	w.Header().Set("Content-Type", mime)
	w.Header().Set("Content-Disposition", fmt.Sprintf(`attachment; filename=%q`, name))
	w.WriteHeader(200)
	_, _ = w.Write(b)
}
func (a *App) deleteAttachment(w http.ResponseWriter, r *http.Request) {
	id, _ := idParam(r, "attachmentId")
	var iid, uploader int64
	var stored string
	if a.DB.QueryRow(`SELECT issue_id,uploader_id,stored_name FROM attachments WHERE id=?`, id).Scan(&iid, &uploader, &stored) != nil {
		fail(w, 404, "not_found", "attachment not found", nil)
		return
	}
	_, ok := a.requireIssueMember(w, r, iid)
	if !ok {
		return
	}
	u := who(r)
	pid, _ := a.projectForIssue(iid)
	if uploader != u.ID && !a.isOwnerOrAdmin(pid, u) {
		fail(w, 403, "forbidden", "uploader or owner required", nil)
		return
	}
	_, _ = a.DB.Exec(`DELETE FROM attachments WHERE id=?`, id)
	_ = os.Remove(filepath.Join(a.StorageDir, stored))
	w.WriteHeader(204)
}

func (a *App) listWebhooks(w http.ResponseWriter, r *http.Request) {
	pid, _ := idParam(r, "projectId")
	u := who(r)
	if !a.isOwnerOrAdmin(pid, u) {
		fail(w, 403, "forbidden", "owner required", nil)
		return
	}
	rows, _ := a.DB.Query(`SELECT id,url,active,created_at FROM webhooks WHERE project_id=? ORDER BY id`, pid)
	defer rows.Close()
	items := []map[string]any{}
	for rows.Next() {
		var id int64
		var url, created string
		var active int
		_ = rows.Scan(&id, &url, &active, &created)
		items = append(items, map[string]any{"id": id, "url": url, "active": active == 1, "createdAt": created})
	}
	jsonOut(w, 200, map[string]any{"items": items})
}
func (a *App) createWebhook(w http.ResponseWriter, r *http.Request) {
	pid, _ := idParam(r, "projectId")
	u := who(r)
	if !a.isOwnerOrAdmin(pid, u) {
		fail(w, 403, "forbidden", "owner required", nil)
		return
	}
	var in struct{ URL string }
	if decode(r, &in) != nil || !(strings.HasPrefix(in.URL, "http://") || strings.HasPrefix(in.URL, "https://")) {
		fail(w, 422, "validation_error", "http(s) URL required", nil)
		return
	}
	sec := token()
	res, _ := a.DB.Exec(`INSERT INTO webhooks(project_id,url,secret,created_at) VALUES(?,?,?,?)`, pid, in.URL, sec, now())
	id, _ := res.LastInsertId()
	jsonOut(w, 201, map[string]any{"id": id, "url": in.URL, "secret": sec, "active": true})
}
func (a *App) rotateWebhook(w http.ResponseWriter, r *http.Request) {
	id, _ := idParam(r, "webhookId")
	u := who(r)
	var pid int64
	if a.DB.QueryRow(`SELECT project_id FROM webhooks WHERE id=?`, id).Scan(&pid) != nil {
		fail(w, 404, "not_found", "webhook not found", nil)
		return
	}
	if !a.isOwnerOrAdmin(pid, u) {
		fail(w, 403, "forbidden", "owner required", nil)
		return
	}
	sec := token()
	_, _ = a.DB.Exec(`UPDATE webhooks SET secret=? WHERE id=?`, sec, id)
	jsonOut(w, 200, map[string]any{"id": id, "secret": sec})
}
func (a *App) webhookDeliveries(w http.ResponseWriter, r *http.Request) {
	id, _ := idParam(r, "webhookId")
	u := who(r)
	var pid int64
	if a.DB.QueryRow(`SELECT project_id FROM webhooks WHERE id=?`, id).Scan(&pid) != nil {
		fail(w, 404, "not_found", "webhook not found", nil)
		return
	}
	if !a.isOwnerOrAdmin(pid, u) {
		fail(w, 403, "forbidden", "owner required", nil)
		return
	}
	rows, _ := a.DB.Query(`SELECT id,event,status_code,created_at FROM webhook_deliveries WHERE webhook_id=? ORDER BY id DESC LIMIT 100`, id)
	defer rows.Close()
	items := []map[string]any{}
	for rows.Next() {
		var did int64
		var ev, created string
		var status int
		_ = rows.Scan(&did, &ev, &status, &created)
		items = append(items, map[string]any{"id": did, "event": ev, "statusCode": status, "createdAt": created})
	}
	jsonOut(w, 200, map[string]any{"items": items})
}

func (a *App) importIssues(w http.ResponseWriter, r *http.Request) {
	pid, _ := idParam(r, "projectId")
	u := who(r)
	if !a.isOwnerOrAdmin(pid, u) {
		fail(w, 403, "forbidden", "owner required", nil)
		return
	}
	var in struct {
		Issues []struct{ Title, Description, Priority string }
	}
	if decode(r, &in) != nil || len(in.Issues) == 0 || len(in.Issues) > 100 {
		fail(w, 422, "validation_error", "issues array required (1..100)", nil)
		return
	}
	tx, _ := a.DB.Begin()
	defer tx.Rollback()
	count := 0
	for _, it := range in.Issues {
		if strings.TrimSpace(it.Title) == "" {
			fail(w, 422, "validation_error", "each issue requires title", nil)
			return
		}
		pri := it.Priority
		if pri == "" {
			pri = "medium"
		}
		_, err := tx.Exec(`INSERT INTO issues(project_id,title,description,issue_type,priority,status,reporter_id,created_at) VALUES(?,?,?,'task',?,'open',?,?)`, pid, it.Title, it.Description, pri, u.ID, now())
		if err != nil {
			fail(w, 500, "internal_error", "operation failed", nil)
			return
		}
		count++
	}
	res, _ := tx.Exec(`INSERT INTO imports(project_id,created_by,imported_count,status,created_at) VALUES(?,?,?,'completed',?)`, pid, u.ID, count, now())
	job, _ := res.LastInsertId()
	_ = tx.Commit()
	jsonOut(w, 201, map[string]any{"id": job, "status": "completed", "importedCount": count})
}
func (a *App) exportIssues(w http.ResponseWriter, r *http.Request) {
	pid, _ := idParam(r, "projectId")
	u := who(r)
	if !a.isOwnerOrAdmin(pid, u) {
		fail(w, 403, "forbidden", "owner required", nil)
		return
	}
	rows, err := a.DB.Query(`SELECT id,title,description,issue_type,priority,status FROM issues WHERE project_id=? ORDER BY id`, pid)
	if err != nil {
		fail(w, 500, "internal_error", "operation failed", nil)
		return
	}
	defer rows.Close()
	w.Header().Set("Content-Type", "text/csv")
	w.Header().Set("Content-Disposition", `attachment; filename="issues.csv"`)
	cw := csv.NewWriter(w)
	_ = cw.Write([]string{"id", "title", "description", "type", "priority", "status"})
	for rows.Next() {
		var id int64
		var t, d, typ, p, s string
		_ = rows.Scan(&id, &t, &d, &typ, &p, &s)
		_ = cw.Write([]string{strconv.FormatInt(id, 10), t, d, typ, p, s})
	}
	cw.Flush()
}

func (a *App) adminUsers(w http.ResponseWriter, r *http.Request) {
	u := who(r)
	if u.Role != "admin" {
		fail(w, 403, "forbidden", "admin required", nil)
		return
	}
	rows, _ := a.DB.Query(`SELECT id,email,role,active FROM users ORDER BY id LIMIT 100`)
	defer rows.Close()
	items := []map[string]any{}
	for rows.Next() {
		var id int64
		var email, role string
		var active int
		_ = rows.Scan(&id, &email, &role, &active)
		items = append(items, map[string]any{"id": id, "email": email, "role": role, "active": active == 1})
	}
	jsonOut(w, 200, map[string]any{"items": items})
}
func (a *App) adminPatchUser(w http.ResponseWriter, r *http.Request) {
	u := who(r)
	if u.Role != "admin" {
		fail(w, 403, "forbidden", "admin required", nil)
		return
	}
	id, _ := idParam(r, "userId")
	var in struct {
		Active *bool
		Role   string
	}
	if decode(r, &in) != nil {
		fail(w, 422, "validation_error", "invalid payload", nil)
		return
	}
	var n int
	if a.DB.QueryRow(`SELECT COUNT(*) FROM users WHERE id=?`, id).Scan(&n); n == 0 {
		fail(w, 404, "not_found", "user not found", nil)
		return
	}
	if in.Active != nil {
		_, _ = a.DB.Exec(`UPDATE users SET active=? WHERE id=?`, boolInt(*in.Active), id)
	}
	if in.Role != "" {
		if in.Role != "member" && in.Role != "admin" {
			fail(w, 422, "validation_error", "invalid role", nil)
			return
		}
		_, _ = a.DB.Exec(`UPDATE users SET role=? WHERE id=?`, in.Role, id)
	}
	a.audit(u.ID, "user.update", "user", id, "")
	jsonOut(w, 200, map[string]any{"id": id, "updated": true})
}
func (a *App) adminAudit(w http.ResponseWriter, r *http.Request) {
	u := who(r)
	if u.Role != "admin" {
		fail(w, 403, "forbidden", "admin required", nil)
		return
	}
	rows, _ := a.DB.Query(`SELECT id,actor_id,action,target_type,target_id,details,created_at FROM audit_events ORDER BY id DESC LIMIT 100`)
	defer rows.Close()
	items := []map[string]any{}
	for rows.Next() {
		var id int64
		var actorID, targetID sql.NullInt64
		var action, target, details, created string
		_ = rows.Scan(&id, &actorID, &action, &target, &targetID, &details, &created)
		items = append(items, map[string]any{"id": id, "actorId": nullableInt(actorID), "action": action, "targetType": target, "targetId": nullableInt(targetID), "details": details, "createdAt": created})
	}
	jsonOut(w, 200, map[string]any{"items": items})
}
func (a *App) adminCreateLabel(w http.ResponseWriter, r *http.Request) {
	u := who(r)
	if u.Role != "admin" {
		fail(w, 403, "forbidden", "admin required", nil)
		return
	}
	var in struct{ Name, Color string }
	if decode(r, &in) != nil || strings.TrimSpace(in.Name) == "" {
		fail(w, 422, "validation_error", "name required", nil)
		return
	}
	if in.Color == "" {
		in.Color = "#777777"
	}
	res, err := a.DB.Exec(`INSERT INTO labels(name,color) VALUES(?,?)`, in.Name, in.Color)
	if err != nil {
		fail(w, 409, "conflict", "label already exists", nil)
		return
	}
	id, _ := res.LastInsertId()
	a.audit(u.ID, "label.create", "label", id, "")
	jsonOut(w, 201, map[string]any{"id": id, "name": in.Name, "color": in.Color})
}

func (a *App) watchIssue(w http.ResponseWriter, r *http.Request) {
	iid, _ := idParam(r, "issueId")
	if _, ok := a.requireIssueMember(w, r, iid); !ok {
		return
	}
	u := who(r)
	_, err := a.DB.Exec(`INSERT INTO watchers(issue_id,user_id) VALUES(?,?)`, iid, u.ID)
	if err != nil {
		fail(w, 409, "conflict", "already watching", nil)
		return
	}
	jsonOut(w, 201, map[string]any{"issueId": iid, "watching": true})
}
func (a *App) unwatchIssue(w http.ResponseWriter, r *http.Request) {
	iid, _ := idParam(r, "issueId")
	if _, ok := a.requireIssueMember(w, r, iid); !ok {
		return
	}
	u := who(r)
	res, _ := a.DB.Exec(`DELETE FROM watchers WHERE issue_id=? AND user_id=?`, iid, u.ID)
	n, _ := res.RowsAffected()
	if n == 0 {
		fail(w, 404, "not_found", "watcher not found", nil)
		return
	}
	w.WriteHeader(204)
}
func (a *App) notifyWatchers(issueID, exclude int64, message string) {
	rows, err := a.DB.Query(`SELECT user_id FROM watchers WHERE issue_id=? AND user_id<>? ORDER BY user_id`, issueID, exclude)
	if err != nil {
		return
	}
	defer rows.Close()
	ids := []int64{}
	for rows.Next() {
		var id int64
		_ = rows.Scan(&id)
		ids = append(ids, id)
	}
	for _, id := range ids {
		_, _ = a.DB.Exec(`INSERT INTO notifications(user_id,issue_id,message,created_at) VALUES(?,?,?,?)`, id, issueID, message, now())
	}
}
func (a *App) notifications(w http.ResponseWriter, r *http.Request) {
	u := who(r)
	rows, _ := a.DB.Query(`SELECT id,issue_id,message,read_at,created_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 100`, u.ID)
	defer rows.Close()
	items := []map[string]any{}
	for rows.Next() {
		var id int64
		var iid sql.NullInt64
		var msg, created string
		var read sql.NullString
		_ = rows.Scan(&id, &iid, &msg, &read, &created)
		items = append(items, map[string]any{"id": id, "issueId": nullableInt(iid), "message": msg, "read": read.Valid, "createdAt": created})
	}
	jsonOut(w, 200, map[string]any{"items": items})
}
func (a *App) readNotification(w http.ResponseWriter, r *http.Request) {
	id, _ := idParam(r, "notificationId")
	u := who(r)
	res, _ := a.DB.Exec(`UPDATE notifications SET read_at=? WHERE id=? AND user_id=?`, now(), id, u.ID)
	n, _ := res.RowsAffected()
	if n == 0 {
		fail(w, 404, "not_found", "notification not found", nil)
		return
	}
	jsonOut(w, 200, map[string]any{"id": id, "read": true})
}
