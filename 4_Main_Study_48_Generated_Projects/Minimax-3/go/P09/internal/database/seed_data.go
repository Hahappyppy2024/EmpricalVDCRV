package database

import (
	"context"
	"database/sql"
	"errors"
	"time"

	"golang.org/x/crypto/bcrypt"
)

var seedUsers = []struct {
	Username string
	Email    string
	FullName string
	Password string
	Role     string
	Bio      string
}{
	{"alice", "alice@example.com", "Alice Admin", "alicepass1", "admin", "Workspace owner"},
	{"bob", "bob@example.com", "Bob Maintainer", "bobpass123", "maintainer", "Maintains the platform"},
	{"carol", "carol@example.com", "Carol Developer", "carolpass1", "developer", "Builds features"},
	{"dave", "dave@example.com", "Dave Developer", "davepass1", "developer", "Backend engineer"},
	{"erin", "erin@example.com", "Erin Reporter", "erinpass1", "reporter", "Reports bugs"},
}

var seedSettings = []struct {
	Key   string
	Value string
}{
	{"workspace_name", "Issue Tracker Workspace"},
	{"allow_registration", "1"},
	{"default_priority", "normal"},
}

// SeedFn is the actual implementation so it can be called from cmd/seed too.
func SeedFn(ctx context.Context, db *sql.DB) error {
	if err := seedSettingsTable(ctx, db); err != nil {
		return err
	}
	if err := seedUsersTable(ctx, db); err != nil {
		return err
	}
	if err := seedProjectsAndIssues(ctx, db); err != nil {
		return err
	}
	return nil
}

func seedSettingsTable(ctx context.Context, db *sql.DB) error {
	for _, s := range seedSettings {
		if _, err := db.ExecContext(ctx, `INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=datetime('now')`, s.Key, s.Value); err != nil {
			return err
		}
	}
	return nil
}

func seedUsersTable(ctx context.Context, db *sql.DB) error {
	var count int
	if err := db.QueryRowContext(ctx, `SELECT COUNT(*) FROM users`).Scan(&count); err != nil {
		return err
	}
	if count > 0 {
		return nil
	}
	for _, u := range seedUsers {
		hash, err := bcrypt.GenerateFromPassword([]byte(u.Password), bcrypt.DefaultCost)
		if err != nil {
			return err
		}
		if _, err := db.ExecContext(ctx, `INSERT INTO users(username, email, full_name, password_hash, role, bio) VALUES (?,?,?,?,?,?)`, u.Username, u.Email, u.FullName, string(hash), u.Role, u.Bio); err != nil {
			return err
		}
	}
	return nil
}

func seedProjectsAndIssues(ctx context.Context, db *sql.DB) error {
	var projectCount int
	if err := db.QueryRowContext(ctx, `SELECT COUNT(*) FROM projects`).Scan(&projectCount); err != nil {
		return err
	}
	if projectCount > 0 {
		return nil
	}

	users := map[string]int64{}
	rows, err := db.QueryContext(ctx, `SELECT id, username FROM users`)
	if err != nil {
		return err
	}
	for rows.Next() {
		var id int64
		var name string
		if err := rows.Scan(&id, &name); err == nil {
			users[name] = id
		}
	}
	rows.Close()

	projects := []struct {
		Slug        string
		Name        string
		Description string
		Visibility  string
		Owner       string
		Labels      []string
		Milestones  []string
	}{
		{
			Slug:        "platform-core",
			Name:        "Platform Core",
			Description: "Core services powering the platform.",
			Visibility:  "public",
			Owner:       "bob",
			Labels:      []string{"bug", "enhancement", "good-first-issue", "documentation", "security"},
			Milestones:  []string{"v1.0", "v1.1"},
		},
		{
			Slug:        "frontend-app",
			Name:        "Frontend App",
			Description: "Browser client and design system.",
			Visibility:  "public",
			Owner:       "alice",
			Labels:      []string{"bug", "ui", "polish"},
			Milestones:  []string{"UI refresh", "Accessibility"},
		},
		{
			Slug:        "internal-roadmap",
			Name:        "Internal Roadmap",
			Description: "Private planning workspace for upcoming releases.",
			Visibility:  "private",
			Owner:       "alice",
			Labels:      []string{"planning", "research"},
			Milestones:  []string{"Q3 planning", "Q4 planning"},
		},
	}

	issueTemplates := []struct {
		Title    string
		Body     string
		Priority string
		State    string
		Labels   []string
		Assignee string
	}{
		{"Login form rejects valid credentials", "Steps to reproduce the login regression.", "high", "open", []string{"bug", "good-first-issue"}, "carol"},
		{"Improve dashboard load performance", "Profile dashboard and propose optimizations.", "normal", "open", []string{"enhancement"}, "dave"},
		{"Document webhook payload format", "Add documentation for outbound webhooks.", "low", "open", []string{"documentation"}, "erin"},
		{"Investigate memory leak in worker", "Workers leak memory after sustained load.", "urgent", "open", []string{"bug", "security"}, "dave"},
		{"Refactor attachment storage", "Move attachment storage to a swappable backend.", "normal", "closed", []string{"enhancement"}, "bob"},
		{"Migrate session store to SQLite", "Sessions are now stored in SQLite (see schema).", "normal", "closed", []string{"enhancement"}, "bob"},
	}

	for _, p := range projects {
		ownerID := users[p.Owner]
		res, err := db.ExecContext(ctx, `INSERT INTO projects(slug, name, description, visibility, owner_id) VALUES (?,?,?,?,?)`, p.Slug, p.Name, p.Description, p.Visibility, ownerID)
		if err != nil {
			return err
		}
		projectID, _ := res.LastInsertId()
		_, _ = db.ExecContext(ctx, `INSERT INTO project_members(project_id, user_id, role) VALUES (?,?, 'maintainer')`, projectID, ownerID)
		if p.Visibility == "private" {
			_, _ = db.ExecContext(ctx, `INSERT OR IGNORE INTO private_access(project_id, user_id, granted_by) VALUES (?,?,?)`, projectID, ownerID, ownerID)
		}
		for _, name := range p.Labels {
			_, _ = db.ExecContext(ctx, `INSERT INTO labels(project_id, name, color) VALUES (?,?,?)`, projectID, name, labelColor(name))
		}
		for _, title := range p.Milestones {
			_, _ = db.ExecContext(ctx, `INSERT INTO milestones(project_id, title) VALUES (?,?)`, projectID, title)
		}
		for _, tpl := range issueTemplates {
			author := ownerID
			if p.Visibility == "private" && tpl.Labels[0] == "bug" {
				author = users["alice"]
			}
			var number int
			_ = db.QueryRowContext(ctx, `SELECT COALESCE(MAX(number),0)+1 FROM issues WHERE project_id=?`, projectID).Scan(&number)
			assignee := users[tpl.Assignee]
			var assigneeArg any
			if assignee != 0 {
				assigneeArg = assignee
			}
			var closedAt any
			if tpl.State == "closed" {
				closedAt = time.Now().UTC().Format("2006-01-02 15:04:05")
			}
			res, err := db.ExecContext(ctx, `INSERT INTO issues(project_id, number, title, body, priority, state, author_id, assignee_id, closed_at) VALUES (?,?,?,?,?,?,?,?,?)`, projectID, number, tpl.Title, tpl.Body, tpl.Priority, tpl.State, author, assigneeArg, closedAt)
			if err != nil {
				return err
			}
			issueID, _ := res.LastInsertId()
			for _, lbl := range tpl.Labels {
				var labelID int64
				_ = db.QueryRowContext(ctx, `SELECT id FROM labels WHERE project_id=? AND name=?`, projectID, lbl).Scan(&labelID)
				if labelID != 0 {
					_, _ = db.ExecContext(ctx, `INSERT OR IGNORE INTO issue_labels(issue_id, label_id) VALUES (?,?)`, issueID, labelID)
				}
			}
			if tpl.State == "open" {
				_, _ = db.ExecContext(ctx, `INSERT INTO comments(issue_id, author_id, body) VALUES (?,?,?)`, issueID, author, "Initial investigation pending.")
			}
		}
		_, _ = db.ExecContext(ctx, `INSERT INTO webhooks(project_id, url, secret, events) VALUES (?,?,?,?)`, projectID, "http://127.0.0.1:65535/hook", "shared-secret", "issue.opened,issue.closed,issue.commented,issue.commented")
	}

	return nil
}

func labelColor(name string) string {
	switch name {
	case "bug":
		return "#dc2626"
	case "enhancement":
		return "#2563eb"
	case "good-first-issue":
		return "#9333ea"
	case "documentation":
		return "#0ea5e9"
	case "security":
		return "#facc15"
	case "ui", "polish":
		return "#ec4899"
	case "planning", "research":
		return "#64748b"
	}
	return "#6c757d"
}

var _ = errors.New