package handler

import (
	"io"
	"net/http"
	"strconv"

	"github.com/go-chi/chi/v5"

	"p14-agentic-platform/internal/middleware"
	"p14-agentic-platform/internal/service"
)

func (s *Server) apiMe(w http.ResponseWriter, r *http.Request) {
	u := middleware.UserFrom(r)
	writeJSON(w, http.StatusOK, map[string]any{"user": s.svc.Me(u)})
}

func (s *Server) apiList(w http.ResponseWriter, r *http.Request) {
	resource := chi.URLParam(r, "resource")
	limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))
	offset, _ := strconv.Atoi(r.URL.Query().Get("offset"))
	items, err := s.svc.ListResource(resource, middleware.UserFrom(r), r.URL.Query(), limit, offset)
	if err != nil {
		writeErr(w, err)
		return
	}
	if items == nil {
		items = []map[string]any{}
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "total": len(items)})
}

func (s *Server) apiGet(w http.ResponseWriter, r *http.Request) {
	id, ok := idParam(r)
	if !ok {
		writeErr(w, service.BadRequest("invalid record id"))
		return
	}
	rec, err := s.svc.GetResource(chi.URLParam(r, "resource"), middleware.UserFrom(r), id)
	if err != nil {
		writeErr(w, err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"item": rec})
}

func (s *Server) apiCreate(w http.ResponseWriter, r *http.Request) {
	resource := chi.URLParam(r, "resource")

	// Multipart upload for workspace files (AGENT-06).
	if resource == "workspace_files" && isMultipart(r) {
		rec, err := s.uploadFile(w, r)
		if err != nil {
			writeErr(w, err)
			return
		}
		writeJSON(w, http.StatusCreated, map[string]any{"item": rec})
		return
	}

	data, ok := s.readJSON(w, r)
	if !ok {
		return
	}

	rec, err := s.svc.CreateResource(resource, middleware.UserFrom(r), data)
	if err != nil {
		writeErr(w, err)
		return
	}
	writeJSON(w, http.StatusCreated, map[string]any{"item": rec})
}

func (s *Server) apiUpdate(w http.ResponseWriter, r *http.Request) {
	id, ok := idParam(r)
	if !ok {
		writeErr(w, service.BadRequest("invalid record id"))
		return
	}
	data, ok := s.readJSON(w, r)
	if !ok {
		return
	}
	rec, err := s.svc.UpdateResource(chi.URLParam(r, "resource"), middleware.UserFrom(r), id, data)
	if err != nil {
		writeErr(w, err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"item": rec})
}

func (s *Server) apiDelete(w http.ResponseWriter, r *http.Request) {
	id, ok := idParam(r)
	if !ok {
		writeErr(w, service.BadRequest("invalid record id"))
		return
	}
	if err := s.svc.DeleteResource(chi.URLParam(r, "resource"), middleware.UserFrom(r), id); err != nil {
		writeErr(w, err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true})
}

func (s *Server) apiExecuteHTTPAction(w http.ResponseWriter, r *http.Request) {
	if chi.URLParam(r, "resource") != "external_http_action" {
		writeErr(w, service.NotFound("unknown action"))
		return
	}
	id, ok := idParam(r)
	if !ok {
		writeErr(w, service.BadRequest("invalid action id"))
		return
	}
	rec, err := s.svc.ExecuteHTTPAction(middleware.UserFrom(r), id)
	if err != nil {
		writeErr(w, err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"item": rec})
}

func (s *Server) apiReplayRun(w http.ResponseWriter, r *http.Request) {
	res := chi.URLParam(r, "resource")
	if res != "task_execution" && res != "run_logs_and_replay" {
		writeErr(w, service.NotFound("unknown action"))
		return
	}
	id, ok := idParam(r)
	if !ok {
		writeErr(w, service.BadRequest("invalid run id"))
		return
	}
	out, err := s.svc.ReplayRun(middleware.UserFrom(r), id)
	if err != nil {
		writeErr(w, err)
		return
	}
	out.Run["logs"] = out.Logs
	writeJSON(w, http.StatusOK, map[string]any{"item": out.Run})
}

func (s *Server) apiPublishTemplate(w http.ResponseWriter, r *http.Request) {
	if chi.URLParam(r, "resource") != "sharing_and_templates" {
		writeErr(w, service.NotFound("unknown action"))
		return
	}
	id, ok := idParam(r)
	if !ok {
		writeErr(w, service.BadRequest("invalid template id"))
		return
	}
	data, ok := s.readJSON(w, r)
	if !ok {
		return
	}
	publish := false
	if v, exists := data["publish"]; exists {
		if b, isBool := v.(bool); isBool {
			publish = b
		}
	}
	rec, err := s.svc.PublishTemplate(middleware.UserFrom(r), id, publish)
	if err != nil {
		writeErr(w, err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"item": rec})
}

func (s *Server) apiDownloadFile(w http.ResponseWriter, r *http.Request) {
	if chi.URLParam(r, "resource") != "workspace_files" {
		writeErr(w, service.NotFound("unknown action"))
		return
	}
	id, ok := idParam(r)
	if !ok {
		writeErr(w, service.BadRequest("invalid file id"))
		return
	}
	rec, content, err := s.svc.GetWorkspaceFileContent(middleware.UserFrom(r), id)
	if err != nil {
		writeErr(w, err)
		return
	}
	name, _ := rec["name"].(string)
	ct, _ := rec["content_type"].(string)
	w.Header().Set("Content-Type", ct)
	w.Header().Set("Content-Disposition", "attachment; filename=\""+name+"\"")
	_, _ = w.Write(content)
}

func (s *Server) apiWebhookTrigger(w http.ResponseWriter, r *http.Request) {
	out, err := s.svc.TriggerWebhook(chi.URLParam(r, "token"))
	if err != nil {
		writeErr(w, err)
		return
	}
	out.Run["logs"] = out.Logs
	writeJSON(w, http.StatusOK, map[string]any{"item": out.Run})
}

// --- admin governance endpoints (AGENT-12) ----------------------------------

func (s *Server) apiAdminUsers(w http.ResponseWriter, r *http.Request) {
	if chi.URLParam(r, "resource") != "admin_governance" {
		writeErr(w, service.NotFound("unknown resource"))
		return
	}
	limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))
	offset, _ := strconv.Atoi(r.URL.Query().Get("offset"))
	items, err := s.svc.ListUsers(middleware.UserFrom(r), r.URL.Query(), limit, offset)
	if err != nil {
		writeErr(w, err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "total": len(items)})
}

func (s *Server) apiAdminAudit(w http.ResponseWriter, r *http.Request) {
	if chi.URLParam(r, "resource") != "admin_governance" {
		writeErr(w, service.NotFound("unknown resource"))
		return
	}
	limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))
	offset, _ := strconv.Atoi(r.URL.Query().Get("offset"))
	items, err := s.svc.ListAudit(middleware.UserFrom(r), r.URL.Query(), limit, offset)
	if err != nil {
		writeErr(w, err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items, "total": len(items)})
}

func (s *Server) apiAdminUpdateUser(w http.ResponseWriter, r *http.Request) {
	if chi.URLParam(r, "resource") != "admin_governance" {
		writeErr(w, service.NotFound("unknown resource"))
		return
	}
	id, ok := idParam(r)
	if !ok {
		writeErr(w, service.BadRequest("invalid user id"))
		return
	}
	data, ok := s.readJSON(w, r)
	if !ok {
		return
	}
	rec, err := s.svc.UpdateUserAdmin(middleware.UserFrom(r), id, data)
	if err != nil {
		writeErr(w, err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"item": rec})
}

func (s *Server) apiRunScheduler(w http.ResponseWriter, r *http.Request) {
	if chi.URLParam(r, "resource") != "admin_governance" {
		writeErr(w, service.NotFound("unknown resource"))
		return
	}
	ran, err := s.svc.RunDueSchedules()
	if err != nil {
		writeErr(w, err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true, "ran": ran})
}

// --- workspace file upload (AGENT-06) ---------------------------------------

func isMultipart(r *http.Request) bool {
	return len(r.Header.Get("Content-Type")) >= 19 && r.Header.Get("Content-Type")[:19] == "multipart/form-data"
}

func (s *Server) uploadFile(w http.ResponseWriter, r *http.Request) (map[string]any, error) {
	if err := r.ParseMultipartForm(4 << 20); err != nil {
		return nil, service.BadRequest("unable to parse multipart form")
	}
	file, header, err := r.FormFile("file")
	if err != nil {
		return nil, service.BadRequest("a multipart file field named 'file' is required")
	}
	defer file.Close()
	content := make([]byte, 0, 4096)
	buf := make([]byte, 64*1024)
	for {
		n, err := file.Read(buf)
		content = append(content, buf[:n]...)
		if int64(len(content)) > s.cfg.MaxFileBytes {
			return nil, service.BadRequest("file exceeds the configured size limit")
		}
		if err == io.EOF {
			break
		}
		if err != nil {
			return nil, service.BadRequest("unable to read uploaded file")
		}
	}
	name := header.Filename
	path := r.FormValue("path")
	if path == "" {
		path = name
	}
	ct := header.Header.Get("Content-Type")
	return s.svc.UploadWorkspaceFile(middleware.UserFrom(r), name, path, ct, content)
}
