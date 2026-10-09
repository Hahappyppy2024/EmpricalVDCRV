package models

import "time"

const (
	RoleAdmin     = "admin"
	RoleMaintainer = "maintainer"
	RoleDeveloper  = "developer"
	RoleReporter   = "reporter"
	RoleMember     = "member"
)

type User struct {
	ID           int64
	Username     string
	Email        string
	FullName     string
	PasswordHash string
	Role         string
	Status       string
	Bio          string
	CreatedAt    time.Time
	UpdatedAt    time.Time
}

const (
	VisibilityPublic  = "public"
	VisibilityPrivate = "private"
)

type Project struct {
	ID          int64
	Slug        string
	Name        string
	Description string
	Visibility  string
	OwnerID     int64
	Archived    bool
	CreatedAt   time.Time
	UpdatedAt   time.Time
}

type ProjectMember struct {
	ProjectID int64
	UserID    int64
	Role      string
}

type Label struct {
	ID        int64
	ProjectID int64
	Name      string
	Color     string
}

type Milestone struct {
	ID          int64
	ProjectID   int64
	Title       string
	Description string
	DueDate     *time.Time
	State       string
}

const (
	IssueStateOpen    = "open"
	IssueStateClosed  = "closed"
	PriorityLow       = "low"
	PriorityNormal    = "normal"
	PriorityHigh      = "high"
	PriorityUrgent    = "urgent"
)

type Issue struct {
	ID          int64
	ProjectID   int64
	Number      int
	Title       string
	Body        string
	Priority    string
	State       string
	AuthorID    int64
	AssigneeID  *int64
	MilestoneID *int64
	CreatedAt   time.Time
	UpdatedAt   time.Time
	ClosedAt    *time.Time
}

type Comment struct {
	ID        int64
	IssueID   int64
	AuthorID  int64
	Body      string
	CreatedAt time.Time
	UpdatedAt time.Time
}

type Attachment struct {
	ID         int64
	IssueID    int64
	UploaderID int64
	Filename   string
	StoredPath string
	MimeType   string
	Size       int64
	CreatedAt  time.Time
}

type Webhook struct {
	ID        int64
	ProjectID int64
	URL       string
	Secret    string
	Events    string
	Active    bool
}

type WebhookDelivery struct {
	ID           int64
	WebhookID    int64
	Event        string
	Payload      string
	Status       string
	ResponseCode int
	ResponseBody string
	CreatedAt    time.Time
}

type Assignment struct {
	ID             int64
	IssueID        int64
	ActorID        int64
	OldAssignee    *int64
	NewAssignee    *int64
	OldState       string
	NewState       string
	OldMilestone   *int64
	NewMilestone   *int64
}

type PrivateAccess struct {
	ProjectID int64
	UserID    int64
	GrantedBy int64
}

type ImportExportJob struct {
	ID           int64
	UserID       int64
	Direction    string
	ProjectID    int64
	Format       string
	Status       string
	Detail       string
	ArtifactPath string
	CreatedAt    time.Time
}

type AuditEvent struct {
	ID         int64
	ActorID    *int64
	Action     string
	TargetType string
	TargetID   *int64
	Detail     string
}

type FrontendError struct {
	ID      int64
	UserID  *int64
	Kind    string
	Message string
	Context string
}

type Session struct {
	ID        string
	UserID    int64
	CSRFToken string
	CreatedAt time.Time
	ExpiresAt time.Time
	IP        string
	UA        string
}

type Setting struct {
	Key   string
	Value string
}