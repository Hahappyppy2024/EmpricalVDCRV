package httpx

import (
	"encoding/json"
	"errors"
	"html/template"
	"net/http"
	"path"
	"strconv"
	"strings"
)

type APIError struct {
	Status  int    `json:"-"`
	Code    string `json:"code"`
	Message string `json:"message"`
	Detail  string `json:"detail,omitempty"`
}

func (e *APIError) Error() string { return e.Message }

func NewAPIError(status int, code, message string) *APIError {
	return &APIError{Status: status, Code: code, Message: message}
}

func BadRequest(msg string) *APIError     { return NewAPIError(http.StatusBadRequest, "bad_request", msg) }
func NotFound(msg string) *APIError       { return NewAPIError(http.StatusNotFound, "not_found", msg) }
func Unauthorized(msg string) *APIError   { return NewAPIError(http.StatusUnauthorized, "unauthorized", msg) }
func Forbidden(msg string) *APIError      { return NewAPIError(http.StatusForbidden, "forbidden", msg) }
func Conflict(msg string) *APIError       { return NewAPIError(http.StatusConflict, "conflict", msg) }
func Validation(msg string) *APIError     { return NewAPIError(http.StatusUnprocessableEntity, "validation", msg) }
func ServerError(msg string) *APIError    { return NewAPIError(http.StatusInternalServerError, "server_error", msg) }

func WriteJSON(w http.ResponseWriter, status int, body any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	enc := json.NewEncoder(w)
	enc.SetEscapeHTML(false)
	_ = enc.Encode(body)
}

func WriteError(w http.ResponseWriter, err error) {
	var apiErr *APIError
	if errors.As(err, &apiErr) {
		WriteJSON(w, apiErr.Status, apiErr)
		return
	}
	WriteJSON(w, http.StatusInternalServerError, &APIError{Code: "server_error", Message: err.Error()})
}

func WriteMessage(w http.ResponseWriter, status int, message string) {
	WriteJSON(w, status, map[string]string{"message": message})
}

func ParseID(raw string) (int64, error) {
	id, err := strconv.ParseInt(strings.TrimSpace(raw), 10, 64)
	if err != nil || id <= 0 {
		return 0, BadRequest("invalid id")
	}
	return id, nil
}

func SafePath(base, target string) (string, error) {
	clean := path.Clean("/" + target)
	if strings.Contains(clean, "..") {
		return "", errors.New("invalid path")
	}
	return path.Join(base, clean), nil
}

var FuncMap = template.FuncMap{
	"safeHTML": func(s string) template.HTML { return template.HTML(s) },
	"safeJS":   func(s string) template.JS { return template.JS(s) },
	"upper":    strings.ToUpper,
	"lower":    strings.ToLower,
	"title":    strings.Title,
	"contains": strings.Contains,
	"join":     strings.Join,
	"replace":  strings.ReplaceAll,
	"split":    strings.Split,
	"trim":     strings.TrimSpace,
	"yesno": func(b bool) string {
		if b {
			return "Yes"
		}
		return "No"
	},
	"fmtTime": func(t any) string {
		return formatTime(t)
	},
	"fmtDate": func(t any) string {
		return formatDate(t)
	},
	"add": func(a, b int) int { return a + b },
	"sub": func(a, b int) int { return a - b },
	"truncate": func(s string, n int) string {
		if len(s) <= n {
			return s
		}
		return s[:n] + "..."
	},
}

func formatTime(t any) string {
	if t == nil {
		return ""
	}
	switch v := t.(type) {
	case string:
		return v
	}
	return ""
}

func formatDate(t any) string {
	if t == nil {
		return ""
	}
	if v, ok := t.(string); ok {
		return v
	}
	return ""
}