package templates

import (
	"crypto/rand"
	"encoding/hex"
	"html/template"
	"strings"

	"github.com/anomaly/p09/internal/httpx"
)

var funcMap = template.FuncMap{
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
		if t == nil {
			return ""
		}
		if v, ok := t.(string); ok {
			return v
		}
		return ""
	},
	"fmtDate": func(t any) string {
		if t == nil {
			return ""
		}
		if v, ok := t.(string); ok {
			return v
		}
		return ""
	},
	"add": func(a, b int) int { return a + b },
	"sub": func(a, b int) int { return a - b },
	"truncate": func(s string, n int) string {
		if len(s) <= n {
			return s
		}
		return s[:n] + "..."
	},
	"defaultStr": func(v any, def string) string {
		if v == nil {
			return def
		}
		if s, ok := v.(string); ok && s == "" {
			return def
		}
		return ""
	},
	"renderFlash": func(kind, msg string) template.HTML {
		return template.HTML(`<div class="flash flash-` + kind + `"><span>` + msg + `</span><button class="flash-close" type="button" data-dismiss-flash aria-label="Dismiss">&times;</button></div>`)
	},
	"empty": func(v any) bool {
		if v == nil {
			return true
		}
		switch x := v.(type) {
		case string:
			return x == ""
		case []any:
			return len(x) == 0
		default:
			return false
		}
	},
	"csrf": func(token string) template.HTML {
		return template.HTML(`<input type="hidden" name="_csrf" value="` + token + `">`)
	},
	"randomToken": func(n int) string {
		b := make([]byte, n)
		_, _ = rand.Read(b)
		return hex.EncodeToString(b)
	},
}

var _ = httpx.BadRequest