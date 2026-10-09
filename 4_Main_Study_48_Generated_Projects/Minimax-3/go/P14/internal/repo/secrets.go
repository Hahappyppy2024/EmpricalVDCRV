package repo

import (
	"context"
	"database/sql"
	"errors"

	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/google/uuid"
)

type Secrets struct{ DB *sql.DB }

func (r *Secrets) Create(ctx context.Context, userID, name, masked, cipher string) (*models.Secret, error) {
	id := uuid.NewString()
	if _, err := r.DB.ExecContext(ctx,
		`INSERT INTO secrets_manager (id, user_id, name, masked, cipher) VALUES (?, ?, ?, ?, ?)`,
		id, userID, name, masked, cipher); err != nil {
		return nil, err
	}
	return r.ByID(ctx, id)
}

func (r *Secrets) ByID(ctx context.Context, id string) (*models.Secret, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, user_id, name, masked, cipher, revoked, created_at FROM secrets_manager WHERE id = ?`, id)
	return scanSecret(row)
}

func (r *Secrets) ByName(ctx context.Context, userID, name string) (*models.Secret, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, user_id, name, masked, cipher, revoked, created_at FROM secrets_manager WHERE user_id = ? AND name = ?`, userID, name)
	return scanSecret(row)
}

func (r *Secrets) ListByUser(ctx context.Context, userID string) ([]models.Secret, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, user_id, name, masked, cipher, revoked, created_at FROM secrets_manager WHERE user_id = ? ORDER BY name`, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Secret
	for rows.Next() {
		s, err := scanSecretRows(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, *s)
	}
	return out, rows.Err()
}

func (r *Secrets) ListAll(ctx context.Context) ([]models.Secret, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, user_id, name, masked, cipher, revoked, created_at FROM secrets_manager ORDER BY created_at DESC`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Secret
	for rows.Next() {
		s, err := scanSecretRows(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, *s)
	}
	return out, rows.Err()
}

func (r *Secrets) Revoke(ctx context.Context, id, userID string) error {
	res, err := r.DB.ExecContext(ctx, `UPDATE secrets_manager SET revoked = 1 WHERE id = ? AND user_id = ?`, id, userID)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("not found")
	}
	return nil
}

func scanSecret(row *sql.Row) (*models.Secret, error) {
	s := &models.Secret{}
	var revoked int
	if err := row.Scan(&s.ID, &s.UserID, &s.Name, &s.Masked, &s.Cipher, &revoked, &s.CreatedAt); err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, err
	}
	s.Revoked = revoked == 1
	return s, nil
}

func scanSecretRows(rows *sql.Rows) (*models.Secret, error) {
	s := &models.Secret{}
	var revoked int
	if err := rows.Scan(&s.ID, &s.UserID, &s.Name, &s.Masked, &s.Cipher, &revoked, &s.CreatedAt); err != nil {
		return nil, err
	}
	s.Revoked = revoked == 1
	return s, nil
}