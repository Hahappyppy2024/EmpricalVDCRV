package web

import (
	"embed"
	"fmt"
	"html/template"
	"io"
	"io/fs"
	"net/http"
	"sync"
)

//go:embed all:templates all:static
var Assets embed.FS

// Renderer parses the shared base template together with a page template on
// every request and executes the "base" definition. Results are cached.
type Renderer struct {
	funcs template.FuncMap
	mu    sync.Mutex
	cache map[string]*template.Template
}

func NewRenderer() *Renderer {
	return &Renderer{
		funcs: template.FuncMap{
			"lower": func(s string) string {
				return template.HTMLEscapeString(s)
			},
		},
		cache: map[string]*template.Template{},
	}
}

// Page renders base.html + <name>.html to the writer with the given data.
func (r *Renderer) Page(w io.Writer, name string, data any) error {
	r.mu.Lock()
	t, ok := r.cache[name]
	r.mu.Unlock()
	if !ok {
		parsed, err := template.New("base").Funcs(r.funcs).ParseFS(Assets, "templates/base.html", "templates/"+name+".html")
		if err != nil {
			return fmt.Errorf("parse page %s: %w", name, err)
		}
		r.mu.Lock()
		r.cache[name] = parsed
		r.mu.Unlock()
		t = parsed
	}
	if err := t.ExecuteTemplate(w, "base", data); err != nil {
		return fmt.Errorf("render page %s: %w", name, err)
	}
	return nil
}

// Static serves the embedded /static directory.
func Static() http.Handler {
	sub, err := fs.Sub(Assets, "static")
	if err != nil {
		panic(err)
	}
	return http.FileServer(http.FS(sub))
}
