package attachments

import (
	"context"
	"crypto/sha256"
	"database/sql"
	"encoding/hex"
	"fmt"
	"io"
	"mime/multipart"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"

	"github.com/go-chi/chi/v5"

	"github.com/anomaly/p09/internal/auth"
	"github.com/anomaly/p09/internal/httpx"
	"github.com/anomaly/p09/internal/models"
	"github.com/anomaly/p09/internal/realtime"
	"github.com/anomaly/p09/internal/templates"
)

type Service struct {
	DB        *sql.DB
	UploadDir string
	MaxSize   int64
	Hub       *realtime.Hub
}

func NewService(db *sql.DB, uploadDir string, maxSize int64, hub *realtime.Hub) *Service {
	return &Service{DB: db, UploadDir: uploadDir, MaxSize: maxSize, Hub: hub}
}

var allowedExt = map[string]bool{
	".txt": true, ".log": true, ".md": true, ".json": true,
	".png": true, ".jpg": true, ".jpeg": true, ".gif": true, ".svg": true,
	".pdf": true, ".csv": true,
}

type Attachment struct {
	ID           int64  `json:"id"`
	IssueID      int64  `json:"issue_id"`
	UploaderID   int64  `json:"uploader_id"`
	UploaderName string `json:"uploader_username"`
	Filename     string `json:"filename"`
	StoredPath   string `json:"-"`
	MimeType     string `json:"mime_type"`
	Size         int64  `json:"size"`
	CreatedAt    string `json:"created_at"`
}

func mimeFor(ext string) string {
	switch ext {
	case ".txt", ".log":
		return "text/plain"
	case ".md":
		return "text/markdown"
	case ".json":
		return "application/json"
	case ".png":
		return "image/png"
	case ".jpg", ".jpeg":
		return "image/jpeg"
	case ".gif":
		return "image/gif"
	case ".svg":
		return "image/svg+xml"
	case ".pdf":
		return "application/pdf"
	case ".csv":
		return "text/csv"
	}
	return "application/octet-stream"
}

func (s *Service) Store(ctx context.Context, issueID, uploaderID int64, fh *multipart.FileHeader) (*Attachment, error) {
	if fh.Size == 0 {
		return nil, httpx.BadRequest("empty file")
	}
	if fh.Size > s.MaxSize {
		return nil, httpx.BadRequest(fmt.Sprintf("file exceeds %d bytes", s.MaxSize))
	}
	ext := strings.ToLower(filepath.Ext(fh.Filename))
	if !allowedExt[ext] {
		return nil, httpx.BadRequest("unsupported file type: " + ext)
	}
	if strings.Contains(fh.Filename, "..") || strings.ContainsAny(fh.Filename, "/\\") {
		return nil, httpx.BadRequest("invalid filename")
	}
	src, err := fh.Open()
	if err != nil {
		return nil, err
	}
	defer src.Close()
	hash := sha256.New()
	if _, err := io.Copy(hash, src); err != nil {
		return nil, err
	}
	sum := hex.EncodeToString(hash.Sum(nil))[:16]
	cleanName := sanitizeFilename(fh.Filename)
	stored := filepath.Join(s.UploadDir, fmt.Sprintf("issue-%d-%s-%s", issueID, sum, cleanName))
	dst, err := os.OpenFile(stored, os.O_CREATE|os.O_WRONLY|os.O_EXCL, 0o644)
	if err != nil {
		dst, err = os.Create(stored)
		if err != nil {
			return nil, err
		}
	}
	defer dst.Close()
	src.Seek(0, 0)
	if _, err := io.Copy(dst, src); err != nil {
		_ = os.Remove(stored)
		return nil, err
	}
	mime := mimeFor(ext)
	res, err := s.DB.ExecContext(ctx, `INSERT INTO attachments(issue_id, uploader_id, filename, stored_path, mime_type, size) VALUES (?,?,?,?,?,?)`, issueID, uploaderID, fh.Filename, stored, mime, fh.Size)
	if err != nil {
		_ = os.Remove(stored)
		return nil, err
	}
	id, _ := res.LastInsertId()
	if s.Hub != nil {
		s.Hub.Publish(realtime.Event{Event: "issue.attachment", Message: "attachment uploaded: " + fh.Filename, IssueID: issueID, ActorID: uploaderID})
	}
	return s.Get(ctx, id)
}

func sanitizeFilename(name string) string {
	name = filepath.Base(name)
	name = strings.ReplaceAll(name, " ", "_")
	if len(name) > 80 {
		name = name[:80]
	}
	return name
}

func (s *Service) Get(ctx context.Context, id int64) (*Attachment, error) {
	row := s.DB.QueryRowContext(ctx, `SELECT a.id, a.issue_id, a.uploader_id, u.username, a.filename, a.stored_path, a.mime_type, a.size, a.created_at FROM attachments a JOIN users u ON u.id=a.uploader_id WHERE a.id=?`, id)
	a := &Attachment{}
	if err := row.Scan(&a.ID, &a.IssueID, &a.UploaderID, &a.UploaderName, &a.Filename, &a.StoredPath, &a.MimeType, &a.Size, &a.CreatedAt); err != nil {
		return nil, httpx.NotFound("attachment not found")
	}
	return a, nil
}

type Handler struct {
	Service *Service
}

func NewHandler(s *Service) *Handler { return &Handler{Service: s} }

func (h *Handler) Routes(r chi.Router, tpl *templates.Engine) {
	r.Get("/api/issue/attachments", h.list)
	r.Post("/api/issue/attachments/{issueID}", h.upload)
	r.Get("/api/issue/attachments/{id}/download", h.download)
}

func (h *Handler) list(w http.ResponseWriter, r *http.Request) {
	issueID, err := httpx.ParseID(r.URL.Query().Get("issue_id"))
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	rows, err := h.Service.DB.QueryContext(r.Context(), `SELECT a.id, a.issue_id, a.uploader_id, u.username, a.filename, a.mime_type, a.size, a.created_at FROM attachments a JOIN users u ON u.id=a.uploader_id WHERE a.issue_id=? ORDER BY a.id`, issueID)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	defer rows.Close()
	var out []Attachment
	for rows.Next() {
		a := Attachment{}
		if err := rows.Scan(&a.ID, &a.IssueID, &a.UploaderID, &a.UploaderName, &a.Filename, &a.MimeType, &a.Size, &a.CreatedAt); err != nil {
			httpx.WriteError(w, err)
			return
		}
		out = append(out, a)
	}
	httpx.WriteJSON(w, http.StatusOK, out)
}

func (h *Handler) upload(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		httpx.WriteError(w, httpx.Unauthorized("authentication required"))
		return
	}
	issueID, err := httpx.ParseID(chi.URLParam(r, "issueID"))
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	if err := r.ParseMultipartForm(h.Service.MaxSize); err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid multipart form"))
		return
	}
	fh, err := retrieveFile(r, "file")
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	a, err := h.Service.Store(r.Context(), issueID, u.ID, fh)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	var slug string
	var number int
	_ = h.Service.DB.QueryRowContext(r.Context(), `SELECT p.slug, i.number FROM issues i JOIN projects p ON p.id=i.project_id WHERE i.id=?`, issueID).Scan(&slug, &number)
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/projects/"+slug+"/issues/"+strconv.Itoa(number), http.StatusSeeOther)
		return
	}
	httpx.WriteJSON(w, http.StatusCreated, a)
}

func retrieveFile(r *http.Request, field string) (*multipart.FileHeader, error) {
	if r.MultipartForm != nil {
		if fhs := r.MultipartForm.File[field]; len(fhs) > 0 {
			return fhs[0], nil
		}
	}
	return nil, httpx.BadRequest("file required")
}

func (h *Handler) download(w http.ResponseWriter, r *http.Request) {
	id, err := httpx.ParseID(chi.URLParam(r, "id"))
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	a, err := h.Service.Get(r.Context(), id)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	u, _ := auth.UserFromContext(r.Context())
	if u == nil {
		var visibility string
		_ = h.Service.DB.QueryRowContext(r.Context(), `SELECT p.visibility FROM issues i JOIN projects p ON p.id=i.project_id WHERE i.id=?`, a.IssueID).Scan(&visibility)
		if visibility == "private" {
			httpx.WriteError(w, httpx.Unauthorized("authentication required"))
			return
		}
	} else if !canAccessAttachment(r.Context(), h.Service.DB, u, a) {
		httpx.WriteError(w, httpx.Forbidden("not allowed"))
		return
	}
	w.Header().Set("Content-Type", a.MimeType)
	w.Header().Set("Content-Disposition", `attachment; filename="`+a.Filename+`"`)
	http.ServeFile(w, r, a.StoredPath)
}

func canAccessAttachment(ctx context.Context, db *sql.DB, u *models.User, a *Attachment) bool {
	if u == nil {
		return false
	}
	if u.ID == a.UploaderID {
		return true
	}
	var ownerID int64
	var visibility string
	_ = db.QueryRowContext(ctx, `SELECT p.visibility, p.owner_id FROM issues i JOIN projects p ON p.id=i.project_id WHERE i.id=?`, a.IssueID).Scan(&visibility, &ownerID)
	if visibility == "public" {
		return true
	}
	if ownerID == u.ID {
		return true
	}
	if u.Role == "admin" {
		return true
	}
	var memberCount int
	_ = db.QueryRowContext(ctx, `SELECT COUNT(*) FROM project_members WHERE project_id=(SELECT project_id FROM issues WHERE id=?) AND user_id=?`, a.IssueID, u.ID).Scan(&memberCount)
	if memberCount > 0 {
		return true
	}
	var access int
	_ = db.QueryRowContext(ctx, `SELECT COUNT(*) FROM private_access WHERE project_id=(SELECT project_id FROM issues WHERE id=?) AND user_id=?`, a.IssueID, u.ID).Scan(&access)
	if access > 0 {
		return true
	}
	return false
}