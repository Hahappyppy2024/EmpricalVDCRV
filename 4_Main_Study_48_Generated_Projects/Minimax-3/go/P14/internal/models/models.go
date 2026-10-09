package models

import "time"

type User struct {
	ID           string
	Email        string
	DisplayName  string
	PasswordHash string
	Role         string
	Status       string
	CreatedAt    time.Time
}

type Session struct {
	ID        string
	UserID    string
	CreatedAt time.Time
	ExpiresAt time.Time
}

type AccountAccess struct {
	ID        string
	UserID    string
	Action    string
	Status    string
	Detail    string
	CreatedAt time.Time
}

type WorkflowStep struct {
	Seq    int    `json:"seq"`
	Name   string `json:"name"`
	Tool   string `json:"tool"`
	Config map[string]string `json:"config"`
}

type Workflow struct {
	ID          string
	UserID      string
	Name        string
	Description string
	TriggerKind string
	CronExpr    string
	Steps       []map[string]any
	Status      string
	CreatedAt   time.Time
	UpdatedAt   time.Time
}

type Tool struct {
	ID          string
	Name        string
	Kind        string
	Description string
	Default     map[string]string
	Enabled     bool
	CreatedAt   time.Time
}

type Run struct {
	ID         string
	UserID     string
	WorkflowID string
	Trigger    string
	Status     string
	StartedAt  *time.Time
	FinishedAt *time.Time
	Detail     string
	Steps      []RunStep
	CreatedAt  time.Time
}

type RunStep struct {
	ID         string
	RunID      string
	Seq        int
	Name       string
	ToolID     string
	Status     string
	StartedAt  *time.Time
	FinishedAt *time.Time
	Output     string
	Error      string
}

type Schedule struct {
	ID         string
	UserID     string
	WorkflowID string
	CronExpr   string
	NextRunAt  time.Time
	Enabled    bool
	CreatedAt  time.Time
}

type WorkspaceFile struct {
	ID         string
	UserID     string
	WorkflowID string
	Path       string
	Size       int
	MimeType   string
	Content    string
	CreatedAt  time.Time
	UpdatedAt  time.Time
}

type StoredFile struct {
	ID          string
	UserID      string
	WorkspaceID string
	Filename    string
	StoredPath  string
	Size        int
	MimeType    string
	CreatedAt   time.Time
}

type Webhook struct {
	ID          string
	UserID      string
	WorkflowID  string
	Token       string
	Description string
	Revoked     bool
	CreatedAt   time.Time
}

type ExternalHTTPCall struct {
	ID         string
	UserID     string
	WorkflowID string
	URL        string
	Method     string
	Payload    string
	StatusCode int
	Response   string
	Error      string
	CreatedAt  time.Time
}

type Secret struct {
	ID        string
	UserID    string
	Name      string
	Masked    string
	Cipher    string
	Revoked   bool
	CreatedAt time.Time
}

type RunLog struct {
	ID        string
	UserID    string
	RunID     string
	Level     string
	Message   string
	CreatedAt time.Time
}

type Template struct {
	ID          string
	UserID      string
	WorkflowID  string
	Name        string
	Description string
	Body        map[string]any
	Visibility  string
	CreatedAt   time.Time
}

type AdminSetting struct {
	ID        string
	Key       string
	Value     string
	UpdatedBy string
	UpdatedAt time.Time
}

type AuditEvent struct {
	ID        string
	ActorID   string
	Action    string
	Target    string
	Detail    string
	CreatedAt time.Time
}