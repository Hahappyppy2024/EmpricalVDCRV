package database

import (
	"database/sql"
	"fmt"

	"golang.org/x/crypto/bcrypt"
)

// Seed deterministically populates the database with the actors, roles,
// relationships, workflow states and dependencies required by the acceptance
// criteria. Every seed user has the password "password123".
func Seed(db *sql.DB, appURL string) error {
	tx, err := db.Begin()
	if err != nil {
		return err
	}
	defer func() { _ = tx.Rollback() }()

	hash := func(pw string) string {
		h, err := bcrypt.GenerateFromPassword([]byte(pw), bcrypt.DefaultCost)
		if err != nil {
			panic(fmt.Sprintf("bcrypt: %v", err))
		}
		return string(h)
	}
	now := func() string { return "2026-01-15T09:00:00Z" }

	insertUser := func(username, email, displayName, role string) int64 {
		res, err := tx.Exec(`INSERT INTO users(username,email,display_name,password_hash,role,active,created_at,updated_at)
			VALUES(?,?,?,?,?,1,?,?)`, username, email, displayName, hash("password123"), role, now(), now())
		if err != nil {
			panic(fmt.Sprintf("seed user %s: %v", username, err))
		}
		id, _ := res.LastInsertId()
		return id
	}

	adminID := insertUser("admin", "admin@example.com", "Admin User", "admin")
	maintainerID := insertUser("maintainer", "maintainer@example.com", "Grace Maintainer", "maintainer")
	devID := insertUser("dev", "dev@example.com", "Dev Developer", "developer")
	reporterID := insertUser("reporter", "reporter@example.com", "Rita Reporter", "reporter")
	memberID := insertUser("member", "member@example.com", "Mike Member", "member")
	outsiderID := insertUser("outsider", "outsider@example.com", "Oscar Outsider", "developer")

	insertProject := func(name, slug, desc, visibility string, owner int64) int64 {
		res, err := tx.Exec(`INSERT INTO projects(name,slug,description,visibility,owner_id,created_at,updated_at)
			VALUES(?,?,?,?,?,?,?)`, name, slug, desc, visibility, owner, now(), now())
		if err != nil {
			panic(fmt.Sprintf("seed project %s: %v", slug, err))
		}
		id, _ := res.LastInsertId()
		return id
	}

	webID := insertProject("Web Platform", "web-platform", "Public web platform issue tracking", "public", maintainerID)
	mobID := insertProject("Mobile App", "mobile-app", "Mobile application issue tracking", "public", maintainerID)
	secID := insertProject("Secret Sauce", "secret-sauce", "Private internal project", "private", maintainerID)

	member := func(projectID, userID int64, role string) {
		if _, err := tx.Exec(`INSERT INTO project_members(project_id,user_id,role) VALUES(?,?,?)`, projectID, userID, role); err != nil {
			panic(fmt.Sprintf("seed member: %v", err))
		}
	}
	member(secID, memberID, "member")
	member(secID, devID, "member")
	member(secID, maintainerID, "maintainer")

	insertLabel := func(projectID int64, name, color string) int64 {
		res, err := tx.Exec(`INSERT INTO labels(project_id,name,color) VALUES(?,?,?)`, projectID, name, color)
		if err != nil {
			panic(fmt.Sprintf("seed label %s: %v", name, err))
		}
		id, _ := res.LastInsertId()
		return id
	}
	webBug := insertLabel(webID, "bug", "d73a4a")
	webFeature := insertLabel(webID, "feature", "1f883d")
	webEnh := insertLabel(webID, "enhancement", "a2eeef")
	webDoc := insertLabel(webID, "documentation", "0969da")
	webUrgent := insertLabel(webID, "urgent", "b60205")
	mobBug := insertLabel(mobID, "bug", "d73a4a")
	mobFeature := insertLabel(mobID, "feature", "1f883d")
	secInternal := insertLabel(secID, "internal", "5319e7")
	secSecurity := insertLabel(secID, "security", "d73a4a")

	insertMilestone := func(projectID int64, title string, open bool) int64 {
		res, err := tx.Exec(`INSERT INTO milestones(project_id,title,is_open,created_at) VALUES(?,?,?,?)`, projectID, title, b2i(open), now())
		if err != nil {
			panic(fmt.Sprintf("seed milestone %s: %v", title, err))
		}
		id, _ := res.LastInsertId()
		return id
	}
	webV10 := insertMilestone(webID, "v1.0", true)
	webV11 := insertMilestone(webID, "v1.1", true)
	mobMVP := insertMilestone(mobID, "MVP", true)
	secQ3 := insertMilestone(secID, "Q3 Audit", true)

	insertIssue := func(projectID int64, number int, title, body, priority, status string, creator, assignee int64, milestone *int64) int64 {
		var mid any
		if milestone != nil {
			mid = *milestone
		}
		var aid any
		if assignee != 0 {
			aid = assignee
		}
		res, err := tx.Exec(`INSERT INTO issues(project_id,number,title,body,priority,status,milestone_id,created_by,assignee_id,created_at,updated_at)
			VALUES(?,?,?,?,?,?,?,?,?,?,?)`, projectID, number, title, body, priority, status, mid, creator, aid, now(), now())
		if err != nil {
			panic(fmt.Sprintf("seed issue %d: %v", number, err))
		}
		id, _ := res.LastInsertId()
		return id
	}
	issueLabels := func(issueID int64, labelIDs ...int64) {
		for _, l := range labelIDs {
			if _, err := tx.Exec(`INSERT INTO issue_labels(issue_id,label_id) VALUES(?,?)`, issueID, l); err != nil {
				panic(fmt.Sprintf("seed issue label: %v", err))
			}
		}
	}

	web1 := insertIssue(webID, 1, "Login page crashes on Safari", "The login form throws a JS error on Safari 17 and redirects to a blank page.", "urgent", "open", devID, devID, nil)
	issueLabels(web1, webBug, webUrgent)
	web2 := insertIssue(webID, 2, "Add CSV export button", "Add an export button to the issues listing page that downloads a CSV report.", "medium", "open", reporterID, 0, &webV11)
	issueLabels(web2, webFeature)
	web3 := insertIssue(webID, 3, "Dark mode support", "Add dark mode to the web UI.", "low", "in_progress", devID, maintainerID, &webV11)
	issueLabels(web3, webEnh)
	web4 := insertIssue(webID, 4, "Update installation docs", "Rewrite the installation section of the README.", "medium", "resolved", reporterID, 0, &webV10)
	issueLabels(web4, webDoc)
	web5 := insertIssue(webID, 5, "Fix rate limit for API", "The API rate limiter rejects legitimate requests after 10 req/min.", "high", "closed", maintainerID, devID, &webV10)
	issueLabels(web5, webBug)

	mob1 := insertIssue(mobID, 1, "Crash on Android 12", "App crashes on startup on Android 12 devices.", "high", "open", devID, devID, &mobMVP)
	issueLabels(mob1, mobBug)
	mob2 := insertIssue(mobID, 2, "Add offline mode", "Support caching of issues for offline reading.", "low", "open", reporterID, 0, nil)
	issueLabels(mob2, mobFeature)

	sec1 := insertIssue(secID, 1, "Internal security review checklist", "Run the quarterly security review; results are confidential.", "urgent", "open", maintainerID, memberID, &secQ3)
	issueLabels(sec1, secInternal, secSecurity)

	insertComment := func(issueID, author int64, body, ts string) {
		if _, err := tx.Exec(`INSERT INTO comments(issue_id,author_id,body,created_at,updated_at) VALUES(?,?,?,?,?)`, issueID, author, body, ts, ts); err != nil {
			panic(fmt.Sprintf("seed comment: %v", err))
		}
	}
	insertComment(web1, devID, "Reproduced on Safari 17.1, attaching a console log.", "2026-01-16T10:05:00Z")
	insertComment(web1, memberID, "Looks related to the SameSite cookie setting.", "2026-01-16T11:20:00Z")
	insertComment(web2, maintainerID, "Good idea, added to the v1.1 backlog.", "2026-01-17T09:00:00Z")
	insertComment(mob1, devID, "Investigating the startup crash now.", "2026-01-18T14:30:00Z")
	insertComment(sec1, memberID, "Checklist drafted, ready for review.", "2026-01-19T08:15:00Z")

	insertWebhook := func(projectID, createdBy int64, url, secret string, active bool, events ...string) int64 {
		var evs string
		for i, e := range events {
			if i > 0 {
				evs += ","
			}
			evs += e
		}
		res, err := tx.Exec(`INSERT INTO webhooks(project_id,created_by,url,secret,active,events,created_at) VALUES(?,?,?,?,?,?,?)`,
			projectID, createdBy, url, secret, b2i(active), evs, now())
		if err != nil {
			panic(fmt.Sprintf("seed webhook: %v", err))
		}
		id, _ := res.LastInsertId()
		return id
	}
	insertWebhook(webID, maintainerID, appURL+"/api/issue/webhooks/listener", "seed-webhook-secret", true,
		"issue.created", "issue.updated", "issue.commented")
	insertWebhook(mobID, maintainerID, appURL+"/api/issue/webhooks/listener", "seed-webhook-secret-2", false, "issue.created")

	settings := map[string]string{
		"site_name":          "Issue Tracker",
		"allow_registration": "true",
		"max_upload_mb":      "5",
		"webhook_retries":    "3",
		"default_visibility": "public",
	}
	for k, v := range settings {
		if _, err := tx.Exec(`INSERT INTO global_settings(key,value) VALUES(?,?)`, k, v); err != nil {
			panic(fmt.Sprintf("seed setting %s: %v", k, err))
		}
	}

	audit := func(actor int64, action, entityType string, entityID int64, detail, ts string) {
		if _, err := tx.Exec(`INSERT INTO audit_events(actor_id,action,entity_type,entity_id,detail,created_at) VALUES(?,?,?,?,?,?)`,
			actor, action, entityType, entityID, detail, ts); err != nil {
			panic(fmt.Sprintf("seed audit: %v", err))
		}
	}
	audit(adminID, "user.create", "users", devID, "created seed user 'dev'", "2026-01-15T09:10:00Z")
	audit(maintainerID, "project.create", "projects", webID, "created seed project 'web-platform'", "2026-01-15T09:20:00Z")

	useCaseRecord := func(table string, userID int64, action string, subjectID int64, summary, detail, status, ts string) {
		q := fmt.Sprintf(`INSERT INTO %s(user_id,action,subject_id,summary,detail,status,created_at) VALUES(?,?,?,?,?,?,?)`, table)
		if _, err := tx.Exec(q, userID, action, subjectID, summary, detail, status, ts); err != nil {
			panic(fmt.Sprintf("seed %s: %v", table, err))
		}
	}
	useCaseRecord("account_access", maintainerID, "login", maintainerID, "Signed in as maintainer", "session token issued", "ok", "2026-01-16T08:00:00Z")
	useCaseRecord("project_management", maintainerID, "project.create", webID, "Created project web-platform", "visibility=public", "ok", "2026-01-15T09:20:00Z")
	useCaseRecord("issue_creation", devID, "issue.create", web1, "Created issue #1 in web-platform", "priority=urgent", "ok", "2026-01-15T10:00:00Z")
	useCaseRecord("issue_search", memberID, "issue.search", 0, "Searched issues", "q=crash, status=open", "ok", "2026-01-16T12:00:00Z")
	useCaseRecord("assignment_and_workflow", maintainerID, "issue.assign", web3, "Assigned issue #3 to maintainer", "status=in_progress", "ok", "2026-01-17T15:00:00Z")
	useCaseRecord("private_projects", memberID, "project.member", secID, "Joined private project secret-sauce", "role=member", "ok", "2026-01-15T09:30:00Z")
	useCaseRecord("import_export", maintainerID, "export.report", webID, "Exported issues report", "format=csv", "ok", "2026-01-18T10:00:00Z")
	useCaseRecord("admin_operations", adminID, "setting.update", 0, "Updated global setting max_upload_mb", "value=5", "ok", "2026-01-15T09:40:00Z")
	useCaseRecord("frontend_api_integration_and_errors", devID, "api.error", 0, "Handled validation error on issue form", "code=validation_failed", "ok", "2026-01-16T13:00:00Z")

	if err := tx.Commit(); err != nil {
		return err
	}
	fmt.Printf("Seeded database: users=[admin maintainer dev reporter member outsider] projects=[web-platform mobile-app secret-sauce] (password for all: password123)\n")
	_ = outsiderID
	return nil
}

func b2i(b bool) int {
	if b {
		return 1
	}
	return 0
}
