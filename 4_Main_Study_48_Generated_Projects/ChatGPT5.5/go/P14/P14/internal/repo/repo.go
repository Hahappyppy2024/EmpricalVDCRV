package repo

import (
	"database/sql"
	"fmt"
	"strings"
)

// Col describes a column for the generic scanner.
type Col struct {
	Name string
	Typ  string // "text", "int", "bool"
}

// Schema describes one resource table. Owner is the ownership column name
// (e.g. "user_id") or "" for shared tables (tool catalog, governance settings).
type Schema struct {
	Table string
	Cols  []Col
	Owner string
}

// Repo is the data-access layer. All SQL is parameterized; table and column
// names always come from the fixed schema registry (never from user input).
type Repo struct {
	db *sql.DB
}

func New(db *sql.DB) *Repo { return &Repo{db: db} }

var schemas = map[string]*Schema{
	"account_access": {
		Table: "account_accesses",
		Cols: []Col{
			{Name: "user_id", Typ: "int"},
			{Name: "account", Typ: "text"},
			{Name: "action", Typ: "text"},
			{Name: "status", Typ: "text"},
			{Name: "created_at", Typ: "text"},
		},
		Owner: "user_id",
	},
	"workflow_creation": {
		Table: "workflows",
		Cols: []Col{
			{Name: "user_id", Typ: "int"},
			{Name: "name", Typ: "text"},
			{Name: "description", Typ: "text"},
			{Name: "trigger_type", Typ: "text"},
			{Name: "steps_json", Typ: "text"},
			{Name: "conditions_json", Typ: "text"},
			{Name: "enabled", Typ: "bool"},
			{Name: "created_at", Typ: "text"},
			{Name: "updated_at", Typ: "text"},
		},
		Owner: "user_id",
	},
	"tool_catalog": {
		Table: "tools",
		Cols: []Col{
			{Name: "name", Typ: "text"},
			{Name: "category", Typ: "text"},
			{Name: "description", Typ: "text"},
			{Name: "version", Typ: "text"},
			{Name: "is_public", Typ: "bool"},
			{Name: "enabled", Typ: "bool"},
			{Name: "created_by", Typ: "int"},
			{Name: "created_at", Typ: "text"},
		},
		Owner: "",
	},
	"task_execution": {
		Table: "runs",
		Cols: []Col{
			{Name: "user_id", Typ: "int"},
			{Name: "workflow_id", Typ: "int"},
			{Name: "trigger", Typ: "text"},
			{Name: "status", Typ: "text"},
			{Name: "steps_json", Typ: "text"},
			{Name: "logs_json", Typ: "text"},
			{Name: "started_at", Typ: "text"},
			{Name: "finished_at", Typ: "text"},
		},
		Owner: "user_id",
	},
	"scheduled_runs": {
		Table: "schedules",
		Cols: []Col{
			{Name: "user_id", Typ: "int"},
			{Name: "workflow_id", Typ: "int"},
			{Name: "name", Typ: "text"},
			{Name: "cron_expr", Typ: "text"},
			{Name: "next_run_at", Typ: "text"},
			{Name: "enabled", Typ: "bool"},
			{Name: "created_at", Typ: "text"},
		},
		Owner: "user_id",
	},
	"workspace_files": {
		Table: "workspace_files",
		Cols: []Col{
			{Name: "user_id", Typ: "int"},
			{Name: "name", Typ: "text"},
			{Name: "file_path", Typ: "text"},
			{Name: "size", Typ: "int"},
			{Name: "content_type", Typ: "text"},
			{Name: "status", Typ: "text"},
			{Name: "created_at", Typ: "text"},
		},
		Owner: "user_id",
	},
	"webhook_triggers": {
		Table: "webhooks",
		Cols: []Col{
			{Name: "user_id", Typ: "int"},
			{Name: "workflow_id", Typ: "int"},
			{Name: "name", Typ: "text"},
			{Name: "token", Typ: "text"},
			{Name: "enabled", Typ: "bool"},
			{Name: "created_at", Typ: "text"},
		},
		Owner: "user_id",
	},
	"external_http_action": {
		Table: "http_actions",
		Cols: []Col{
			{Name: "user_id", Typ: "int"},
			{Name: "name", Typ: "text"},
			{Name: "url", Typ: "text"},
			{Name: "method", Typ: "text"},
			{Name: "payload", Typ: "text"},
			{Name: "last_status", Typ: "int"},
			{Name: "last_response", Typ: "text"},
			{Name: "created_at", Typ: "text"},
			{Name: "updated_at", Typ: "text"},
		},
		Owner: "user_id",
	},
	"secrets_manager": {
		Table: "secrets",
		Cols: []Col{
			{Name: "user_id", Typ: "int"},
			{Name: "name", Typ: "text"},
			{Name: "masked_value", Typ: "text"},
			{Name: "created_at", Typ: "text"},
			{Name: "updated_at", Typ: "text"},
		},
		Owner: "user_id",
	},
	"run_logs_and_replay": {
		Table: "run_logs",
		Cols: []Col{
			{Name: "user_id", Typ: "int"},
			{Name: "run_id", Typ: "int"},
			{Name: "step_name", Typ: "text"},
			{Name: "status", Typ: "text"},
			{Name: "output", Typ: "text"},
			{Name: "created_at", Typ: "text"},
		},
		Owner: "user_id",
	},
	"sharing_and_templates": {
		Table: "templates",
		Cols: []Col{
			{Name: "user_id", Typ: "int"},
			{Name: "name", Typ: "text"},
			{Name: "description", Typ: "text"},
			{Name: "definition_json", Typ: "text"},
			{Name: "is_published", Typ: "bool"},
			{Name: "downloads", Typ: "int"},
			{Name: "created_at", Typ: "text"},
		},
		Owner: "user_id",
	},
	"admin_governance": {
		Table: "governance_settings",
		Cols: []Col{
			{Name: "setting_key", Typ: "text"},
			{Name: "setting_value", Typ: "text"},
			{Name: "updated_by", Typ: "int"},
			{Name: "updated_at", Typ: "text"},
		},
		Owner: "",
	},
}

// SchemaOf returns the fixed schema for a resource name, or nil if unknown.
func SchemaOf(resource string) *Schema {
	return schemas[resource]
}

// KnownResources returns every supported resource name.
func KnownResources() []string {
	names := make([]string, 0, len(schemas))
	for name := range schemas {
		names = append(names, name)
	}
	return names
}

// List returns rows matching every entry in where (AND), optionally filtered by
// a LIKE search on searchCol. Results are ordered newest first.
func (r *Repo) List(sch *Schema, where map[string]any, searchCol, q string, limit, offset int) ([]map[string]any, error) {
	var clauses []string
	var args []any
	for k, v := range where {
		clauses = append(clauses, k+" = ?")
		args = append(args, v)
	}
	if q != "" && searchCol != "" {
		clauses = append(clauses, searchCol+" LIKE ?")
		args = append(args, "%"+q+"%")
	}
	whereSQL := ""
	if len(clauses) > 0 {
		whereSQL = " WHERE " + strings.Join(clauses, " AND ")
	}
	types := []Col{{Name: "id", Typ: "int"}}
	types = append(types, sch.Cols...)
	cols := make([]string, len(types))
	for i, c := range types {
		cols[i] = c.Name
	}
	query := fmt.Sprintf("SELECT %s FROM %s%s ORDER BY id DESC LIMIT ? OFFSET ?",
		strings.Join(cols, ", "), sch.Table, whereSQL)
	args = append(args, limit, offset)
	rows, err := r.db.Query(query, args...)
	if err != nil {
		return nil, err
	}
	return scan(rows, types)
}

// Get returns a single row by id.
func (r *Repo) Get(sch *Schema, id int64) (map[string]any, error) {
	types := []Col{{Name: "id", Typ: "int"}}
	types = append(types, sch.Cols...)
	cols := make([]string, len(types))
	for i, c := range types {
		cols[i] = c.Name
	}
	query := fmt.Sprintf("SELECT %s FROM %s WHERE id = ?", strings.Join(cols, ", "), sch.Table)
	rows, err := r.db.Query(query, id)
	if err != nil {
		return nil, err
	}
	items, err := scan(rows, types)
	if err != nil {
		return nil, err
	}
	if len(items) == 0 {
		return nil, sql.ErrNoRows
	}
	return items[0], nil
}

// Find returns the first row matching all where clauses, or sql.ErrNoRows.
func (r *Repo) Find(sch *Schema, where map[string]any) (map[string]any, error) {
	items, err := r.List(sch, where, "", "", 1, 0)
	if err != nil {
		return nil, err
	}
	if len(items) == 0 {
		return nil, sql.ErrNoRows
	}
	return items[0], nil
}

// Count returns the number of rows matching all where clauses.
func (r *Repo) Count(sch *Schema, where map[string]any) (int, error) {
	var clauses []string
	var args []any
	for k, v := range where {
		clauses = append(clauses, k+" = ?")
		args = append(args, v)
	}
	whereSQL := ""
	if len(clauses) > 0 {
		whereSQL = " WHERE " + strings.Join(clauses, " AND ")
	}
	query := fmt.Sprintf("SELECT COUNT(*) FROM %s%s", sch.Table, whereSQL)
	var n int
	if err := r.db.QueryRow(query, args...).Scan(&n); err != nil {
		return 0, err
	}
	return n, nil
}

// Insert inserts one row and returns the new id.
func (r *Repo) Insert(table string, cols []string, vals []any) (int64, error) {
	marks := make([]string, len(cols))
	for i := range marks {
		marks[i] = "?"
	}
	query := fmt.Sprintf("INSERT INTO %s(%s) VALUES(%s)",
		table, strings.Join(cols, ", "), strings.Join(marks, ", "))
	res, err := r.db.Exec(query, vals...)
	if err != nil {
		return 0, err
	}
	return res.LastInsertId()
}

// Update sets the given columns for all rows matching every where clause and
// returns the number of affected rows.
func (r *Repo) Update(table string, setCols []string, setVals []any, where map[string]any) (int64, error) {
	var sets []string
	for _, c := range setCols {
		sets = append(sets, c+" = ?")
	}
	var clauses []string
	for k, v := range where {
		clauses = append(clauses, k+" = ?")
		setVals = append(setVals, v)
	}
	query := fmt.Sprintf("UPDATE %s SET %s WHERE %s",
		table, strings.Join(sets, ", "), strings.Join(clauses, " AND "))
	res, err := r.db.Exec(query, setVals...)
	if err != nil {
		return 0, err
	}
	return res.RowsAffected()
}

// Delete removes all rows matching every where clause and returns affected count.
func (r *Repo) Delete(table string, where map[string]any) (int64, error) {
	var clauses []string
	var args []any
	for k, v := range where {
		clauses = append(clauses, k+" = ?")
		args = append(args, v)
	}
	query := fmt.Sprintf("DELETE FROM %s WHERE %s", table, strings.Join(clauses, " AND "))
	res, err := r.db.Exec(query, args...)
	if err != nil {
		return 0, err
	}
	return res.RowsAffected()
}

// Query exposes raw querying for special resources (workspace files, admin views).
func (r *Repo) Query(query string, args ...any) (*sql.Rows, error) {
	return r.db.Query(query, args...)
}

// QueryRow exposes a single-row query for special resources.
func (r *Repo) QueryRow(query string, args ...any) *sql.Row {
	return r.db.QueryRow(query, args...)
}

// Exec runs a raw statement for special resources.
func (r *Repo) Exec(query string, args ...any) (sql.Result, error) {
	return r.db.Exec(query, args...)
}

func scan(rows *sql.Rows, types []Col) ([]map[string]any, error) {
	defer rows.Close()
	var out []map[string]any
	for rows.Next() {
		dests := make([]any, len(types))
		ptrs := make([]any, len(types))
		for i, c := range types {
			switch c.Typ {
			case "int":
				ptrs[i] = new(int64)
			case "bool":
				ptrs[i] = new(int64)
			default:
				ptrs[i] = new(string)
			}
			dests[i] = ptrs[i]
		}
		if err := rows.Scan(dests...); err != nil {
			return nil, err
		}
		m := make(map[string]any, len(types))
		for i, c := range types {
			switch c.Typ {
			case "int":
				m[c.Name] = *ptrs[i].(*int64)
			case "bool":
				m[c.Name] = *ptrs[i].(*int64) != 0
			default:
				m[c.Name] = *ptrs[i].(*string)
			}
		}
		out = append(out, m)
	}
	return out, rows.Err()
}
