package repo

import (
	"context"
	"database/sql"
	"strings"

	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/google/uuid"
)

type Tools struct{ DB *sql.DB }

func (r *Tools) Create(ctx context.Context, name, kind, desc, defJSON string, enabled bool) (*models.Tool, error) {
	id := uuid.NewString()
	if _, err := r.DB.ExecContext(ctx,
		`INSERT INTO tool_catalog (id, name, kind, description, default_json, enabled) VALUES (?, ?, ?, ?, ?, ?)`,
		id, name, kind, desc, defJSON, boolToInt(enabled)); err != nil {
		return nil, err
	}
	return r.ByID(ctx, id)
}

func (r *Tools) ByID(ctx context.Context, id string) (*models.Tool, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, name, kind, description, default_json, enabled, created_at FROM tool_catalog WHERE id = ?`, id)
	return scanTool(row)
}

func (r *Tools) ByName(ctx context.Context, name string) (*models.Tool, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, name, kind, description, default_json, enabled, created_at FROM tool_catalog WHERE name = ?`, name)
	return scanTool(row)
}

func (r *Tools) List(ctx context.Context, search string, enabledOnly bool) ([]models.Tool, error) {
	q := `SELECT id, name, kind, description, default_json, enabled, created_at FROM tool_catalog`
	args := []any{}
	var where []string
	if search != "" {
		where = append(where, "(LOWER(name) LIKE ? OR LOWER(description) LIKE ?)")
		args = append(args, "%"+strings.ToLower(search)+"%", "%"+strings.ToLower(search)+"%")
	}
	if enabledOnly {
		where = append(where, "enabled = 1")
	}
	if len(where) > 0 {
		q += " WHERE " + strings.Join(where, " AND ")
	}
	q += " ORDER BY name"
	rows, err := r.DB.QueryContext(ctx, q, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Tool
	for rows.Next() {
		t := models.Tool{}
		var defJSON string
		var enabled int
		if err := rows.Scan(&t.ID, &t.Name, &t.Kind, &t.Description, &defJSON, &enabled, &t.CreatedAt); err != nil {
			return nil, err
		}
		t.Enabled = enabled == 1
		t.Default = decodeMap(defJSON)
		out = append(out, t)
	}
	return out, rows.Err()
}

func (r *Tools) Update(ctx context.Context, id, name, kind, desc, defJSON string, enabled bool) error {
	_, err := r.DB.ExecContext(ctx,
		`UPDATE tool_catalog SET name = ?, kind = ?, description = ?, default_json = ?, enabled = ? WHERE id = ?`,
		name, kind, desc, defJSON, boolToInt(enabled), id)
	return err
}

func scanTool(row *sql.Row) (*models.Tool, error) {
	t := &models.Tool{}
	var defJSON string
	var enabled int
	if err := row.Scan(&t.ID, &t.Name, &t.Kind, &t.Description, &defJSON, &enabled, &t.CreatedAt); err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, err
	}
	t.Enabled = enabled == 1
	t.Default = decodeMap(defJSON)
	return t, nil
}

func boolToInt(b bool) int {
	if b {
		return 1
	}
	return 0
}