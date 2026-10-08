package models

// User is a platform account. Role is either "user" or "admin".
type User struct {
	ID           int64
	Username     string
	Email        string
	PasswordHash string
	Role         string
	Active       bool
	CreatedAt    string
}

// Session is a server-side persistent session identified by an HTTP-only cookie.
type Session struct {
	ID        int64
	Token     string
	UserID    int64
	CreatedAt string
	ExpiresAt string
}

// Workflow is a user-defined automation workflow (AGENT-02).
type Workflow struct {
	ID             int64
	UserID         int64
	Name           string
	Description    string
	TriggerType    string
	StepsJSON      string
	ConditionsJSON string
	Enabled        bool
	CreatedAt      string
	UpdatedAt      string
}

// Step is one step inside a workflow definition.
type Step struct {
	Name    string `json:"name"`
	Tool    string `json:"tool"`
	Command string `json:"command"`
	URL     string `json:"url"`
	Method  string `json:"method"`
	Payload string `json:"payload"`
	File    string `json:"file"`
	Secret  string `json:"secret"`
	Email   string `json:"email"`
	Fail    bool   `json:"fail"`
}

// Run is one task execution (AGENT-04).
type Run struct {
	ID         int64
	UserID     int64
	WorkflowID int64
	Trigger    string
	Status     string
	StepsJSON  string
	LogsJSON   string
	StartedAt  string
	FinishedAt string
}
