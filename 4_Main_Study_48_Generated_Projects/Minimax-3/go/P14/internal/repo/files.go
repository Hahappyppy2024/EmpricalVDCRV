package repo

import (
	"context"
	"database/sql"
	"errors"
	"time"

	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/google/uuid"
)

type Files struct{ DB *sql.DB }

func (r *Files) Create(ctx context.Context, userID, wfID, path, content, mime string) (*models.WorkspaceFile, error) {
	id := uuid.NewString()
	now := time.Now().UTC().Format(time.RFC3339)
	if _, err := r.DB.ExecContext(ctx,
		`INSERT INTO workspace_files (id, user_id, workflow_id, path, size, mime_type, content, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		id, userID, nullable(wfID), path, len(content), mime, content, now, now); err != nil {
		return nil, err
	}
	return r.ByID(ctx, id)
}

func (r *Files) ByID(ctx context.Context, id string) (*models.WorkspaceFile, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, user_id, COALESCE(workflow_id,''), path, size, mime_type, content, created_at, updated_at FROM workspace_files WHERE id = ?`, id)
	return scanFile(row)
}

func (r *Files) ByPath(ctx context.Context, userID, path string) (*models.WorkspaceFile, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, user_id, COALESCE(workflow_id,''), path, size, mime_type, content, created_at, updated_at FROM workspace_files WHERE user_id = ? AND path = ?`, userID, path)
	return scanFile(row)
}

func (r *Files) ListByUser(ctx context.Context, userID string) ([]models.WorkspaceFile, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, user_id, COALESCE(workflow_id,''), path, size, mime_type, content, created_at, updated_at FROM workspace_files WHERE user_id = ? ORDER BY path`, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.WorkspaceFile
	for rows.Next() {
		f, err := scanFileRows(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, *f)
	}
	return out, rows.Err()
}

func (r *Files) Update(ctx context.Context, id, content string) error {
	now := time.Now().UTC().Format(time.RFC3339)
	_, err := r.DB.ExecContext(ctx,
		`UPDATE workspace_files SET content = ?, size = ?, updated_at = ? WHERE id = ?`,
		content, len(content), now, id)
	return err
}

func (r *Files) Delete(ctx context.Context, id, userID string) error {
	res, err := r.DB.ExecContext(ctx, `DELETE FROM workspace_files WHERE id = ? AND user_id = ?`, id, userID)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("not found")
	}
	return nil
}

func (r *Files) StoreUpload(ctx context.Context, userID, workspaceID, filename, stored, mime string, size int) (*models.StoredFile, error) {
	id := uuid.NewString()
	if _, err := r.DB.ExecContext(ctx,
		`INSERT INTO stored_files (id, user_id, workspace_id, filename, stored_path, size, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?)`,
		id, userID, nullable(workspaceID), filename, stored, size, mime); err != nil {
		return nil, err
	}
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, user_id, COALESCE(workspace_id,''), filename, stored_path, size, mime_type, created_at FROM stored_files WHERE id = ?`, id)
	s := &models.StoredFile{}
	if err := row.Scan(&s.ID, &s.UserID, &s.WorkspaceID, &s.Filename, &s.StoredPath, &s.Size, &s.MimeType, &s.CreatedAt); err != nil {
		return nil, err
	}
	return s, nil
}

func (r *Files) Stored(ctx context.Context, userID string) ([]models.StoredFile, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, user_id, COALESCE(workspace_id,''), filename, stored_path, size, mime_type, created_at FROM stored_files WHERE user_id = ? ORDER BY created_at DESC`, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.StoredFile
	for rows.Next() {
		s := models.StoredFile{}
		if err := rows.Scan(&s.ID, &s.UserID, &s.WorkspaceID, &s.Filename, &s.StoredPath, &s.Size, &s.MimeType, &s.CreatedAt); err != nil {
			return nil, err
		}
		out = append(out, s)
	}
	return out, rows.Err()
}

func scanFile(row *sql.Row) (*models.WorkspaceFile, error) {
	f := &models.WorkspaceFile{}
	if err := row.Scan(&f.ID, &f.UserID, &f.WorkflowID, &f.Path, &f.Size, &f.MimeType, &f.Content, &f.CreatedAt, &f.UpdatedAt); err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, err
	}
	return f, nil
}

func scanFileRows(rows *sql.Rows) (*models.WorkspaceFile, error) {
	f := &models.WorkspaceFile{}
	if err := rows.Scan(&f.ID, &f.UserID, &f.WorkflowID, &f.Path, &f.Size, &f.MimeType, &f.Content, &f.CreatedAt, &f.UpdatedAt); err != nil {
		return nil, err
	}
	return f, nil
}

func nullable(v string) any {
	if v == "" {
		return nil
	}
	return v
}