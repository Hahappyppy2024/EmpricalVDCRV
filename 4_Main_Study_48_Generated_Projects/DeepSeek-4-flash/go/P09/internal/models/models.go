// Package models defines the persistent domain entities and the use-case
// workflow record entities of the Issue Tracking System.
package models

import "time"

// Roles used across the application.
const (
	RoleDeveloper  = "developer"
	RoleReporter   = "reporter"
	RoleMember     = "member"
	RoleMaintainer = "maintainer"
	RoleAdmin      = "admin"
)

// Issue statuses and the allowed workflow transitions.
const (
	StatusOpen       = "open"
	StatusInProgress = "in_progress"
	StatusResolved   = "resolved"
	StatusClosed     = "closed"
)

// AllowedTransitions maps a current status to the statuses it may move to.
var AllowedTransitions = map[string]map[string]bool{
	StatusOpen:       {StatusInProgress: true, StatusResolved: true, StatusClosed: true},
	StatusInProgress: {StatusOpen: true, StatusResolved: true, StatusClosed: true},
	StatusResolved:   {StatusOpen: true, StatusClosed: true},
	StatusClosed:     {StatusOpen: true},
}

// Priorities for issues.
var Priorities = []string{"low", "medium", "high", "urgent"}

// Visibility values for projects.
const (
	VisibilityPublic  = "public"
	VisibilityPrivate = "private"
)

// User is an account with a role.
type User struct {
	ID           int64     `json:"id"`
	Username     string    `json:"username"`
	Email        string    `json:"email"`
	DisplayName  string    `json:"display_name"`
	PasswordHash string    `json:"-"`
	Role         string    `json:"role"`
	Active       bool      `json:"active"`
	CreatedAt    time.Time `json:"created_at"`
	UpdatedAt    time.Time `json:"updated_at"`
}

// Session is a persisted server-side session record.
type Session struct {
	ID        int64     `json:"id"`
	Token     string    `json:"token"`
	UserID    int64     `json:"user_id"`
	CreatedAt time.Time `json:"created_at"`
	ExpiresAt time.Time `json:"expires_at"`
}

// Project is a tracked project (public or private).
type Project struct {
	ID          int64     `json:"id"`
	Name        string    `json:"name"`
	Slug        string    `json:"slug"`
	Description string    `json:"description"`
	Visibility  string    `json:"visibility"`
	OwnerID     int64     `json:"owner_id"`
	OwnerName   string    `json:"owner_name"`
	CreatedAt   time.Time `json:"created_at"`
	UpdatedAt   time.Time `json:"updated_at"`
}

// Label belongs to a project.
type Label struct {
	ID        int64  `json:"id"`
	ProjectID int64  `json:"project_id"`
	Name      string `json:"name"`
	Color     string `json:"color"`
}

// Milestone belongs to a project.
type Milestone struct {
	ID        int64     `json:"id"`
	ProjectID int64     `json:"project_id"`
	Title     string    `json:"title"`
	IsOpen    bool      `json:"is_open"`
	CreatedAt time.Time `json:"created_at"`
}

// Issue is a tracked issue inside a project.
type Issue struct {
	ID           int64     `json:"id"`
	ProjectID    int64     `json:"project_id"`
	ProjectSlug  string    `json:"project_slug"`
	Number       int       `json:"number"`
	Title        string    `json:"title"`
	Body         string    `json:"body"`
	Priority     string    `json:"priority"`
	Status       string    `json:"status"`
	MilestoneID  *int64    `json:"milestone_id"`
	CreatedBy    int64     `json:"created_by"`
	CreatorName  string    `json:"creator_name"`
	AssigneeID   *int64    `json:"assignee_id"`
	AssigneeName string    `json:"assignee_name"`
	CreatedAt    time.Time `json:"created_at"`
	UpdatedAt    time.Time `json:"updated_at"`
}

// Comment is a user comment on an issue.
type Comment struct {
	ID         int64     `json:"id"`
	IssueID    int64     `json:"issue_id"`
	AuthorID   int64     `json:"author_id"`
	AuthorName string    `json:"author_name"`
	Body       string    `json:"body"`
	CreatedAt  time.Time `json:"created_at"`
	UpdatedAt  time.Time `json:"updated_at"`
}

// StoredFile is the metadata record of a stored file with ownership links.
type StoredFile struct {
	ID             int64     `json:"id"`
	OwnerID        int64     `json:"owner_id"`
	DomainType     string    `json:"domain_type"`
	DomainID       int64     `json:"domain_id"`
	Filename       string    `json:"filename"`
	ContentType    string    `json:"content_type"`
	Size           int64     `json:"size"`
	StoragePath    string    `json:"-"`
	SHA256         string    `json:"sha256"`
	IsPrivate      bool      `json:"is_private"`
	CreatedAt      time.Time `json:"created_at"`
	OwnerName      string    `json:"owner_name"`
	ProjectSlug    string    `json:"project_slug"`
	ProjectID      int64     `json:"project_id"`
	ProjectPrivate bool      `json:"project_private"`
}

// Webhook is a configured outbound webhook with secret and event mask.
type Webhook struct {
	ID          int64     `json:"id"`
	ProjectID   int64     `json:"project_id"`
	ProjectName string    `json:"project_name"`
	CreatedBy   int64     `json:"created_by"`
	URL         string    `json:"url"`
	Secret      string    `json:"secret,omitempty"`
	Active      bool      `json:"active"`
	Events      []string  `json:"events"`
	CreatedAt   time.Time `json:"created_at"`
}

// WebhookEvent names.
const (
	EventIssueCreated   = "issue.created"
	EventIssueUpdated   = "issue.updated"
	EventIssueCommented = "issue.commented"
)

// WebhookDelivery is the deterministic delivery record for one event.
type WebhookDelivery struct {
	ID         int64     `json:"id"`
	WebhookID  int64     `json:"webhook_id"`
	WebhookURL string    `json:"webhook_url"`
	Event      string    `json:"event"`
	Payload    string    `json:"payload"`
	Status     string    `json:"status"` // delivered | failed
	Attempts   int       `json:"attempts"`
	Response   string    `json:"response"`
	CreatedAt  time.Time `json:"created_at"`
}

// AuditEvent is an auditable privileged-operation record.
type AuditEvent struct {
	ID         int64     `json:"id"`
	ActorID    int64     `json:"actor_id"`
	ActorName  string    `json:"actor_name"`
	Action     string    `json:"action"`
	EntityType string    `json:"entity_type"`
	EntityID   int64     `json:"entity_id"`
	Detail     string    `json:"detail"`
	CreatedAt  time.Time `json:"created_at"`
}

// UseCaseRecord is the shared shape of the per-use-case workflow records
// (account_access, project_management, issue_creation, issue_search,
// assignment_and_workflow, private_projects, admin_operations,
// frontend_api_integration_and_errors, import_export). The domain tables hold
// authoritative data; these tables persist the required use-case entities.
type UseCaseRecord struct {
	ID        int64     `json:"id"`
	UserID    int64     `json:"user_id"`
	UserName  string    `json:"user_name"`
	Action    string    `json:"action"`
	SubjectID int64     `json:"subject_id,omitempty"`
	Summary   string    `json:"summary"`
	Detail    string    `json:"detail"`
	Status    string    `json:"status"`
	CreatedAt time.Time `json:"created_at"`
}

// GlobalSetting is a key/value configuration entry editable by admins.
type GlobalSetting struct {
	Key   string `json:"key"`
	Value string `json:"value"`
}
