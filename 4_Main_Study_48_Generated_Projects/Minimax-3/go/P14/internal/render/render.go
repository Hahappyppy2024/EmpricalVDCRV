package render

import (
	"embed"
	"errors"
	"fmt"
	"html/template"
	"io"
	"io/fs"
	"net/http"
	"path"
	"strings"
)

// Renderer renders html templates that live alongside this package.
// It supports a layout pattern: pages in pages/*.html can `{{define "content"}}`.
type Renderer struct {
	templates map[string]*template.Template
}

//go:embed all:templates
var tplFS embed.FS

func New() (*Renderer, error) {
	baseRaw, err := fs.ReadFile(tplFS, "templates/base.html")
	if err != nil {
		return nil, fmt.Errorf("read base: %w", err)
	}
	headerRaw, err := fs.ReadFile(tplFS, "templates/header.html")
	if err != nil {
		return nil, fmt.Errorf("read header: %w", err)
	}
	footerRaw, err := fs.ReadFile(tplFS, "templates/footer.html")
	if err != nil {
		return nil, fmt.Errorf("read footer: %w", err)
	}
	funcs := template.FuncMap{
		"safeJS":   func(s string) template.JS { return template.JS(s) },
		"safeHTML": func(s string) template.HTML { return template.HTML(s) },
		"join":     strings.Join,
		"upper":    strings.ToUpper,
		"lower":    strings.ToLower,
		"trim":     strings.TrimSpace,
		"yesno":    func(b bool) string { if b { return "yes" }; return "no" },
		"mask": func(s string) string {
			if len(s) <= 4 {
				return strings.Repeat("*", len(s))
			}
			return s[:2] + strings.Repeat("*", len(s)-4) + s[len(s)-2:]
		},
		"short": func(s string, n int) string {
			if len(s) <= n {
				return s
			}
			return s[:n] + "…"
		},
	}
	pages := map[string]*template.Template{}
	entries, err := fs.ReadDir(tplFS, "templates/pages")
	if err != nil {
		return nil, fmt.Errorf("read pages: %w", err)
	}
	for _, e := range entries {
		if e.IsDir() || !strings.HasSuffix(e.Name(), ".html") {
			continue
		}
		name := strings.TrimSuffix(e.Name(), ".html")
		pageRaw, err := fs.ReadFile(tplFS, "templates/pages/"+e.Name())
		if err != nil {
			return nil, fmt.Errorf("read page %s: %w", e.Name(), err)
		}
		// Each page gets a fresh template with base/header/footer clones
		// so its {{define "content"}} is isolated.
		tpl, err := template.New(name).Funcs(funcs).Parse(string(baseRaw))
		if err != nil {
			return nil, fmt.Errorf("parse base for %s: %w", name, err)
		}
		if _, err := tpl.Parse(string(headerRaw)); err != nil {
			return nil, fmt.Errorf("parse header for %s: %w", name, err)
		}
		if _, err := tpl.Parse(string(footerRaw)); err != nil {
			return nil, fmt.Errorf("parse footer for %s: %w", name, err)
		}
		if _, err := tpl.Parse(string(pageRaw)); err != nil {
			return nil, fmt.Errorf("parse page %s: %w", name, err)
		}
		pages[name] = tpl
	}
	return &Renderer{templates: pages}, nil
}

func (r *Renderer) Render(w http.ResponseWriter, name string, data any) {
	tpl, ok := r.templates[name]
	if !ok {
		http.Error(w, "template not found: "+name, http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	if err := tpl.ExecuteTemplate(w, "base", data); err != nil && !errors.Is(err, io.EOF) {
		http.Error(w, err.Error(), http.StatusInternalServerError)
	}
}

// RenderPartial renders a partial template without the base layout.
func (r *Renderer) RenderPartial(w http.ResponseWriter, name string, data any) {
	tpl, ok := r.templates[name]
	if !ok {
		http.Error(w, "template not found: "+name, http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	if err := tpl.Execute(w, data); err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
	}
}

func SafePath(p string) string {
	return path.Clean("/" + p)
}