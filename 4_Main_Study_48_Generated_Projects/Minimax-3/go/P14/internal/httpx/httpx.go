package httpx

import (
	"encoding/json"
	"errors"
	"net/http"
)

type ErrorBody struct {
	OK    bool   `json:"ok"`
	Error string `json:"error"`
	Field string `json:"field,omitempty"`
}

func WriteJSON(w http.ResponseWriter, status int, body any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(body)
}

func WriteError(w http.ResponseWriter, status int, msg, field string) {
	WriteJSON(w, status, ErrorBody{OK: false, Error: msg, Field: field})
}

func WriteOK(w http.ResponseWriter, body any) {
	if body == nil {
		WriteJSON(w, http.StatusOK, map[string]any{"ok": true})
		return
	}
	if m, ok := body.(map[string]any); ok {
		m["ok"] = true
		WriteJSON(w, http.StatusOK, m)
		return
	}
	WriteJSON(w, http.StatusOK, map[string]any{"ok": true, "data": body})
}

var ErrUnauthorized = errors.New("unauthorized")
var ErrForbidden = errors.New("forbidden")
var ErrNotFound = errors.New("not found")
var ErrValidation = errors.New("validation failed")