package httpx

import (
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"net/http"
)

func RandomToken(n int) string {
	b := make([]byte, n)
	if _, err := rand.Read(b); err != nil {
		return "fallback-token"
	}
	return hex.EncodeToString(b)
}

func ReadJSON(r *http.Request, v any) error {
	dec := json.NewDecoder(r.Body)
	dec.DisallowUnknownFields()
	return dec.Decode(v)
}