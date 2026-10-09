package templates

import (
	"embed"
	"fmt"
	"html/template"
	"io"
	"io/fs"
	"net/http"
	"strings"
)

//go:embed all:files
var files embed.FS

type Engine struct {
	pages map[string]string
}

func New() (*Engine, error) {
	root, err := fs.Sub(files, "files")
	if err != nil {
		return nil, err
	}
	pages := map[string]string{}
	err = fs.WalkDir(root, ".", func(p string, d fs.DirEntry, walkErr error) error {
		if walkErr != nil {
			return walkErr
		}
		if d.IsDir() {
			return nil
		}
		if !strings.HasSuffix(p, ".html") {
			return nil
		}
		data, err := fs.ReadFile(root, p)
		if err != nil {
			return err
		}
		pages[p] = string(data)
		return nil
	})
	if err != nil {
		return nil, err
	}
	return &Engine{pages: pages}, nil
}

func (e *Engine) Render(w http.ResponseWriter, page string, data any) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	if data == nil {
		data = map[string]any{}
	}
	if m, ok := data.(map[string]any); ok {
		m["Page"] = page
	}
	if err := e.execute(page, data, w); err != nil {
		http.Error(w, "render error: "+err.Error(), http.StatusInternalServerError)
	}
}

func (e *Engine) execute(page string, data any, w http.ResponseWriter) error {
	tpl := template.New("layout.html").Funcs(funcMap)
	for name, body := range e.pages {
		if !isPartial(name) {
			continue
		}
		if _, err := tpl.New(name).Parse(body); err != nil {
			return fmt.Errorf("parse %s: %w", name, err)
		}
	}
	if body, ok := e.pages["layout.html"]; ok {
		if _, err := tpl.Parse(body); err != nil {
			return fmt.Errorf("parse layout: %w", err)
		}
	}
	if body, ok := e.pages[page]; ok {
		if _, err := tpl.New(page).Parse(body); err != nil {
			return fmt.Errorf("parse %s: %w", page, err)
		}
	}
	return tpl.ExecuteTemplate(w, "layout.html", data)
}

func (e *Engine) RenderInline(w io.Writer, page string, data any) error {
	if data == nil {
		data = map[string]any{}
	}
	if m, ok := data.(map[string]any); ok {
		m["Page"] = page
	}
	tpl := template.New("layout.html").Funcs(funcMap)
	for name, body := range e.pages {
		if !isPartial(name) {
			continue
		}
		if _, err := tpl.New(name).Parse(body); err != nil {
			return err
		}
	}
	if body, ok := e.pages["layout.html"]; ok {
		if _, err := tpl.Parse(body); err != nil {
			return err
		}
	}
	if body, ok := e.pages[page]; ok {
		if _, err := tpl.New(page).Parse(body); err != nil {
			return err
		}
	}
	return tpl.ExecuteTemplate(w, "layout.html", data)
}

func isPartial(name string) bool {
	if !strings.HasPrefix(name, "partials/") {
		return false
	}
	if name == "layout.html" {
		return false
	}
	return true
}