package store

import (
	"database/sql"
	"errors"
	"fmt"
	"strings"

	"issuetracker/internal/models"
)

// Stored files ---------------------------------------------------------------

func (s *Store) CreateStoredFile(f *models.StoredFile) (*models.StoredFile, error) {
	res, err := s.DB.Exec(`INSERT INTO stored_files(owner_id,domain_type,domain_id,filename,content_type,size,storage_path,sha256,is_private,created_at)
		VALUES(?,?,?,?,?,?,?,?,?,?)`, f.OwnerID, f.DomainType, f.DomainID, f.Filename, f.ContentType, f.Size, f.StoragePath, f.SHA256, b2i(f.IsPrivate), Now())
	if err != nil {
		return nil, err
	}
	id, _ := res.LastInsertId()
	return s.StoredFileByID(id)
}

func (s *Store) StoredFileByID(id int64) (*models.StoredFile, error) {
	row := s.DB.QueryRow(`SELECT f.id,f.owner_id,u.username,f.domain_type,f.domain_id,f.filename,f.content_type,f.size,f.storage_path,f.sha256,f.is_private,f.created_at,
		p.id,p.slug,p.visibility
		FROM stored_files f
		JOIN users u ON u.id=f.owner_id
		LEFT JOIN issues i ON i.id=f.domain_id
		LEFT JOIN projects p ON p.id=i.project_id
		WHERE f.id=?`, id)
	var f models.StoredFile
	var isPriv int
	var created string
	var projectID, projectSlug, projectVis sql.NullString
	err := row.Scan(&f.ID, &f.OwnerID, &f.OwnerName, &f.DomainType, &f.DomainID, &f.Filename, &f.ContentType, &f.Size,
		&f.StoragePath, &f.SHA256, &isPriv, &created, &projectID, &projectSlug, &projectVis)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, ErrNotFound
		}
		return nil, err
	}
	f.IsPrivate = isPriv == 1
	f.CreatedAt = parseTime(created)
	if projectID.Valid && projectID.String != "" {
		var pid int64
		if _, err := fmt.Sscan(projectID.String, &pid); err == nil {
			f.ProjectID = pid
		}
	}
	if projectSlug.Valid {
		f.ProjectSlug = projectSlug.String
	}
	f.ProjectPrivate = projectVis.Valid && projectVis.String == models.VisibilityPrivate
	return &f, nil
}

func (s *Store) ListStoredFiles(domainType string, domainID int64) ([]models.StoredFile, error) {
	rows, err := s.DB.Query(`SELECT id,owner_id,domain_type,domain_id,filename,content_type,size,storage_path,sha256,is_private,created_at
		FROM stored_files WHERE domain_type=? AND domain_id=? ORDER BY id`, domainType, domainID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.StoredFile
	for rows.Next() {
		var f models.StoredFile
		var isPriv int
		var created string
		if err := rows.Scan(&f.ID, &f.OwnerID, &f.DomainType, &f.DomainID, &f.Filename, &f.ContentType, &f.Size,
			&f.StoragePath, &f.SHA256, &isPriv, &created); err != nil {
			return nil, err
		}
		f.IsPrivate = isPriv == 1
		f.CreatedAt = parseTime(created)
		out = append(out, f)
	}
	return out, rows.Err()
}

func (s *Store) DeleteStoredFile(id int64) error {
	_, err := s.DB.Exec(`DELETE FROM stored_files WHERE id=?`, id)
	return err
}

// Attachments ----------------------------------------------------------------

func (s *Store) CreateAttachment(issueID, uploaderID, fileID int64) error {
	_, err := s.DB.Exec(`INSERT INTO attachments(issue_id,uploader_id,stored_file_id,created_at) VALUES(?,?,?,?)`,
		issueID, uploaderID, fileID, Now())
	return err
}

func (s *Store) ListAttachments(issueID int64) ([]models.StoredFile, error) {
	rows, err := s.DB.Query(`SELECT f.id,f.owner_id,u.username,f.domain_type,f.domain_id,f.filename,f.content_type,f.size,f.storage_path,f.sha256,f.is_private,f.created_at
		FROM attachments a JOIN stored_files f ON f.id=a.stored_file_id JOIN users u ON u.id=f.owner_id
		WHERE a.issue_id=? ORDER BY a.id`, issueID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.StoredFile
	for rows.Next() {
		var f models.StoredFile
		var isPriv int
		var created string
		if err := rows.Scan(&f.ID, &f.OwnerID, &f.OwnerName, &f.DomainType, &f.DomainID, &f.Filename, &f.ContentType,
			&f.Size, &f.StoragePath, &f.SHA256, &isPriv, &created); err != nil {
			return nil, err
		}
		f.IsPrivate = isPriv == 1
		f.CreatedAt = parseTime(created)
		out = append(out, f)
	}
	return out, rows.Err()
}

// Webhooks -------------------------------------------------------------------

func (s *Store) CreateWebhook(w *models.Webhook) (*models.Webhook, error) {
	res, err := s.DB.Exec(`INSERT INTO webhooks(project_id,created_by,url,secret,active,events,created_at)
		VALUES(?,?,?,?,?,?,?)`, w.ProjectID, w.CreatedBy, w.URL, w.Secret, b2i(w.Active), strings.Join(w.Events, ","), Now())
	if err != nil {
		return nil, err
	}
	id, _ := res.LastInsertId()
	return s.WebhookByID(id)
}

func (s *Store) WebhookByID(id int64) (*models.Webhook, error) {
	row := s.DB.QueryRow(`SELECT w.id,w.project_id,p.name,w.created_by,w.url,w.secret,w.active,w.events,w.created_at
		FROM webhooks w JOIN projects p ON p.id=w.project_id WHERE w.id=?`, id)
	return scanWebhook(row)
}

func (s *Store) ListWebhooks(projectID int64) ([]models.Webhook, error) {
	rows, err := s.DB.Query(`SELECT w.id,w.project_id,p.name,w.created_by,w.url,w.secret,w.active,w.events,w.created_at
		FROM webhooks w JOIN projects p ON p.id=w.project_id WHERE w.project_id=? ORDER BY w.id`, projectID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Webhook
	for rows.Next() {
		w, err := scanWebhook(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, *w)
	}
	return out, rows.Err()
}

// ListAllWebhooks returns every configured webhook across projects.
func (s *Store) ListAllWebhooks() ([]models.Webhook, error) {
	rows, err := s.DB.Query(`SELECT w.id,w.project_id,p.name,w.created_by,w.url,w.secret,w.active,w.events,w.created_at
		FROM webhooks w JOIN projects p ON p.id=w.project_id ORDER BY w.id`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Webhook
	for rows.Next() {
		w, err := scanWebhook(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, *w)
	}
	return out, rows.Err()
}

func (s *Store) UpdateWebhook(id int64, url, secret string, active *bool, events []string) error {
	cur, err := s.WebhookByID(id)
	if err != nil {
		return err
	}
	if url != "" {
		cur.URL = url
	}
	if secret != "" {
		cur.Secret = secret
	}
	if active != nil {
		cur.Active = *active
	}
	if len(events) > 0 {
		cur.Events = events
	}
	_, err = s.DB.Exec(`UPDATE webhooks SET url=?,secret=?,active=?,events=? WHERE id=?`,
		cur.URL, cur.Secret, b2i(cur.Active), strings.Join(cur.Events, ","), id)
	return err
}

func (s *Store) DeleteWebhook(id int64) error {
	res, err := s.DB.Exec(`DELETE FROM webhooks WHERE id=?`, id)
	if err != nil {
		return err
	}
	if n, _ := res.RowsAffected(); n == 0 {
		return ErrNotFound
	}
	return nil
}

// ActiveWebhooksFor returns active webhooks of a project subscribed to event.
func (s *Store) ActiveWebhooksFor(projectID int64, event string) ([]models.Webhook, error) {
	rows, err := s.DB.Query(`SELECT w.id,w.project_id,p.name,w.created_by,w.url,w.secret,w.active,w.events,w.created_at
		FROM webhooks w JOIN projects p ON p.id=w.project_id
		WHERE w.project_id=? AND w.active=1`, projectID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Webhook
	for rows.Next() {
		w, err := scanWebhook(rows)
		if err != nil {
			return nil, err
		}
		for _, ev := range w.Events {
			if ev == event {
				out = append(out, *w)
				break
			}
		}
	}
	return out, rows.Err()
}

func scanWebhook(r scanner) (*models.Webhook, error) {
	var w models.Webhook
	var active int
	var events, created string
	err := r.Scan(&w.ID, &w.ProjectID, &w.ProjectName, &w.CreatedBy, &w.URL, &w.Secret, &active, &events, &created)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, ErrNotFound
		}
		return nil, err
	}
	w.Active = active == 1
	w.Events = strings.Split(events, ",")
	w.CreatedAt = parseTime(created)
	return &w, nil
}

// Webhook deliveries ---------------------------------------------------------

func (s *Store) CreateDelivery(webhookID int64, event, payload, status, response string, attempts int) (*models.WebhookDelivery, error) {
	res, err := s.DB.Exec(`INSERT INTO webhook_deliveries(webhook_id,event,payload,status,attempts,response,created_at)
		VALUES(?,?,?,?,?,?,?)`, webhookID, event, payload, status, attempts, response, Now())
	if err != nil {
		return nil, err
	}
	id, _ := res.LastInsertId()
	return s.DeliveryByID(id)
}

func (s *Store) DeliveryByID(id int64) (*models.WebhookDelivery, error) {
	var d models.WebhookDelivery
	var created string
	err := s.DB.QueryRow(`SELECT d.id,d.webhook_id,w.url,d.event,d.payload,d.status,d.attempts,d.response,d.created_at
		FROM webhook_deliveries d JOIN webhooks w ON w.id=d.webhook_id WHERE d.id=?`, id).
		Scan(&d.ID, &d.WebhookID, &d.WebhookURL, &d.Event, &d.Payload, &d.Status, &d.Attempts, &d.Response, &created)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, ErrNotFound
		}
		return nil, err
	}
	d.CreatedAt = parseTime(created)
	return &d, nil
}

func (s *Store) ListDeliveries(webhookID int64, limit int) ([]models.WebhookDelivery, error) {
	if limit <= 0 || limit > 100 {
		limit = 50
	}
	rows, err := s.DB.Query(`SELECT d.id,d.webhook_id,w.url,d.event,d.payload,d.status,d.attempts,d.response,d.created_at
		FROM webhook_deliveries d JOIN webhooks w ON w.id=d.webhook_id WHERE d.webhook_id=? ORDER BY d.id DESC LIMIT ?`, webhookID, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.WebhookDelivery
	for rows.Next() {
		var d models.WebhookDelivery
		var created string
		if err := rows.Scan(&d.ID, &d.WebhookID, &d.WebhookURL, &d.Event, &d.Payload, &d.Status, &d.Attempts, &d.Response, &created); err != nil {
			return nil, err
		}
		d.CreatedAt = parseTime(created)
		out = append(out, d)
	}
	return out, rows.Err()
}

// Audit events ---------------------------------------------------------------

func (s *Store) CreateAuditEvent(actorID int64, action, entityType string, entityID int64, detail string) error {
	_, err := s.DB.Exec(`INSERT INTO audit_events(actor_id,action,entity_type,entity_id,detail,created_at)
		VALUES(?,?,?,?,?,?)`, actorID, action, entityType, entityID, detail, Now())
	return err
}

func (s *Store) ListAuditEvents(limit int) ([]models.AuditEvent, error) {
	if limit <= 0 || limit > 500 {
		limit = 100
	}
	rows, err := s.DB.Query(`SELECT a.id,a.actor_id,u.username,a.action,a.entity_type,a.entity_id,a.detail,a.created_at
		FROM audit_events a JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT ?`, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.AuditEvent
	for rows.Next() {
		var a models.AuditEvent
		var created string
		if err := rows.Scan(&a.ID, &a.ActorID, &a.ActorName, &a.Action, &a.EntityType, &a.EntityID, &a.Detail, &created); err != nil {
			return nil, err
		}
		a.CreatedAt = parseTime(created)
		out = append(out, a)
	}
	return out, rows.Err()
}

// Use-case workflow records --------------------------------------------------

// UseCaseRecordParams filters use-case records.
type UseCaseRecordParams struct {
	UserID    int64
	Action    string
	Limit     int
	OrderDesc bool
}

// ListUseCaseRecords lists records of a use-case entity table, newest first.
func (s *Store) ListUseCaseRecords(table string, p UseCaseRecordParams) ([]models.UseCaseRecord, error) {
	allowed := map[string]bool{
		"account_access": true, "project_management": true, "issue_creation": true,
		"issue_search": true, "assignment_and_workflow": true, "private_projects": true,
		"import_export": true, "admin_operations": true, "frontend_api_integration_and_errors": true,
	}
	if !allowed[table] {
		return nil, errors.New("unknown use-case table")
	}
	where := []string{"1=1"}
	args := []any{}
	if p.UserID != 0 {
		where = append(where, "r.user_id=?")
		args = append(args, p.UserID)
	}
	if p.Action != "" {
		where = append(where, "r.action=?")
		args = append(args, p.Action)
	}
	order := "ASC"
	if p.OrderDesc {
		order = "DESC"
	}
	limit := p.Limit
	if limit <= 0 || limit > 500 {
		limit = 100
	}
	q := `SELECT r.id,r.user_id,u.username,r.action,r.subject_id,r.summary,r.detail,r.status,r.created_at
		FROM ` + table + ` r JOIN users u ON u.id=r.user_id WHERE ` + strings.Join(where, " AND ") +
		` ORDER BY r.id ` + order + ` LIMIT ` + fmt.Sprint(limit)
	rows, err := s.DB.Query(q, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.UseCaseRecord
	for rows.Next() {
		var r models.UseCaseRecord
		var created string
		if err := rows.Scan(&r.ID, &r.UserID, &r.UserName, &r.Action, &r.SubjectID, &r.Summary, &r.Detail, &r.Status, &created); err != nil {
			return nil, err
		}
		r.CreatedAt = parseTime(created)
		out = append(out, r)
	}
	return out, rows.Err()
}

// CreateUseCaseRecord inserts a workflow record into a use-case table.
func (s *Store) CreateUseCaseRecord(table string, r models.UseCaseRecord) error {
	allowed := map[string]bool{
		"account_access": true, "project_management": true, "issue_creation": true,
		"issue_search": true, "assignment_and_workflow": true, "private_projects": true,
		"import_export": true, "admin_operations": true, "frontend_api_integration_and_errors": true,
	}
	if !allowed[table] {
		return errors.New("unknown use-case table")
	}
	if r.Status == "" {
		r.Status = "ok"
	}
	_, err := s.DB.Exec(`INSERT INTO `+table+`(user_id,action,subject_id,summary,detail,status,created_at)
		VALUES(?,?,?,?,?,?,?)`, r.UserID, r.Action, r.SubjectID, r.Summary, r.Detail, r.Status, Now())
	return err
}

// UseCaseRecordByID fetches a single record from a use-case table.
func (s *Store) UseCaseRecordByID(table string, id int64) (*models.UseCaseRecord, error) {
	allowed := map[string]bool{
		"account_access": true, "project_management": true, "issue_creation": true,
		"issue_search": true, "assignment_and_workflow": true, "private_projects": true,
		"import_export": true, "admin_operations": true, "frontend_api_integration_and_errors": true,
	}
	if !allowed[table] {
		return nil, errors.New("unknown use-case table")
	}
	var r models.UseCaseRecord
	var created string
	err := s.DB.QueryRow(`SELECT r.id,r.user_id,u.username,r.action,r.subject_id,r.summary,r.detail,r.status,r.created_at
		FROM `+table+` r JOIN users u ON u.id=r.user_id WHERE r.id=?`, id).
		Scan(&r.ID, &r.UserID, &r.UserName, &r.Action, &r.SubjectID, &r.Summary, &r.Detail, &r.Status, &created)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, ErrNotFound
		}
		return nil, err
	}
	r.CreatedAt = parseTime(created)
	return &r, nil
}
