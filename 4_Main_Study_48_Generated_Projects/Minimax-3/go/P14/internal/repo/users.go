package repo

import (
	"context"
	"database/sql"
	"errors"
	"strings"

	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/google/uuid"
)

type Users struct{ DB *sql.DB }

func (r *Users) ByEmail(ctx context.Context, email string) (*models.User, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, email, display_name, password_hash, role, status, created_at FROM users WHERE email = ?`, email)
	u := &models.User{}
	if err := row.Scan(&u.ID, &u.Email, &u.DisplayName, &u.PasswordHash, &u.Role, &u.Status, &u.CreatedAt); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, nil
		}
		return nil, err
	}
	return u, nil
}

func (r *Users) ByID(ctx context.Context, id string) (*models.User, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, email, display_name, password_hash, role, status, created_at FROM users WHERE id = ?`, id)
	u := &models.User{}
	if err := row.Scan(&u.ID, &u.Email, &u.DisplayName, &u.PasswordHash, &u.Role, &u.Status, &u.CreatedAt); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, nil
		}
		return nil, err
	}
	return u, nil
}

func (r *Users) Create(ctx context.Context, email, name, hash, role string) (*models.User, error) {
	id := uuid.NewString()
	role = strings.ToLower(role)
	if role != "admin" {
		role = "user"
	}
	if _, err := r.DB.ExecContext(ctx,
		`INSERT INTO users (id, email, display_name, password_hash, role, status) VALUES (?, ?, ?, ?, ?, 'active')`,
		id, strings.ToLower(email), name, hash, role); err != nil {
		return nil, err
	}
	return r.ByID(ctx, id)
}

func (r *Users) List(ctx context.Context) ([]models.User, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, email, display_name, password_hash, role, status, created_at FROM users ORDER BY created_at DESC`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.User
	for rows.Next() {
		u := models.User{}
		if err := rows.Scan(&u.ID, &u.Email, &u.DisplayName, &u.PasswordHash, &u.Role, &u.Status, &u.CreatedAt); err != nil {
			return nil, err
		}
		out = append(out, u)
	}
	return out, rows.Err()
}

func (r *Users) UpdateStatus(ctx context.Context, id, status string) error {
	if status != "active" && status != "disabled" {
		return errors.New("invalid status")
	}
	_, err := r.DB.ExecContext(ctx, `UPDATE users SET status = ? WHERE id = ?`, status, id)
	return err
}

func (r *Users) UpdateRole(ctx context.Context, id, role string) error {
	if role != "admin" && role != "user" {
		return errors.New("invalid role")
	}
	_, err := r.DB.ExecContext(ctx, `UPDATE users SET role = ? WHERE id = ?`, role, id)
	return err
}