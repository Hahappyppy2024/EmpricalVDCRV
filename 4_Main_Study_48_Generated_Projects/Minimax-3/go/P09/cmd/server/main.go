package main

import (
	"context"
	"database/sql"
	"errors"
	"log"
	"net/http"
	"os"
	"os/signal"
	"path/filepath"
	"syscall"
	"time"

	"github.com/go-chi/chi/v5"
	"github.com/go-chi/chi/v5/middleware"

	"github.com/anomaly/p09/internal/auth"
	"github.com/anomaly/p09/internal/config"
	"github.com/anomaly/p09/internal/database"
	"github.com/anomaly/p09/internal/httpx"
	"github.com/anomaly/p09/internal/modules/account"
	"github.com/anomaly/p09/internal/modules/admin"
	"github.com/anomaly/p09/internal/modules/assignment"
	"github.com/anomaly/p09/internal/modules/attachments"
	"github.com/anomaly/p09/internal/modules/comments"
	"github.com/anomaly/p09/internal/modules/frontend_errors"
	"github.com/anomaly/p09/internal/modules/importexport"
	"github.com/anomaly/p09/internal/modules/issues"
	"github.com/anomaly/p09/internal/modules/private"
	"github.com/anomaly/p09/internal/modules/projects"
	"github.com/anomaly/p09/internal/modules/search"
	"github.com/anomaly/p09/internal/modules/webhooks"
	"github.com/anomaly/p09/internal/realtime"
	"github.com/anomaly/p09/internal/templates"
)

func main() {
	cfg, err := config.Load()
	if err != nil {
		log.Fatalf("load config: %v", err)
	}
	db, err := database.Open(cfg.DBPath)
	if err != nil {
		log.Fatalf("open db: %v", err)
	}
	defer db.Close()
	if err := database.Migrate(context.Background(), db); err != nil {
		log.Fatalf("migrate: %v", err)
	}
	if os.Getenv("APP_SKIP_SEED") == "" {
		if err := database.Seed(context.Background(), db); err != nil {
			log.Printf("seed warning: %v", err)
		}
	}

	authSvc := auth.NewService(db, cfg.CookieName, time.Duration(cfg.SessionTTL)*time.Second)
	mw := auth.NewMiddleware(authSvc)
	tpl, err := templates.New()
	if err != nil {
		log.Fatalf("templates: %v", err)
	}
	hub := realtime.NewHub()

	accountHandler := account.NewHandler(db, authSvc)
	projectsService := projects.NewService(db)
	projectsHandler := projects.NewHandler(projectsService, hub)
	issuesService := issues.NewService(db)
	issuesHandler := issues.NewHandler(issuesService, hub)
	searchService := search.NewService(db, hub)
	searchHandler := search.NewHandler(searchService)
	commentsService := comments.NewService(db, hub)
	commentsHandler := comments.NewHandler(commentsService)
	assignmentService := assignment.NewService(db, hub)
	assignmentHandler := assignment.NewHandler(assignmentService)
	attachmentsService := attachments.NewService(db, cfg.UploadDir, cfg.UploadLimit, hub)
	attachmentsHandler := attachments.NewHandler(attachmentsService)
	privateService := private.NewService(db, hub)
	privateHandler := private.NewHandler(privateService)
	webhooksService := webhooks.NewService(db, hub)
	webhooksHandler := webhooks.NewHandler(webhooksService)
	exportDir := filepath.Join(cfg.DataDir, "exports")
	_ = os.MkdirAll(exportDir, 0o755)
	importService := importexport.NewService(db, exportDir)
	importHandler := importexport.NewHandler(importService)
	adminService := admin.NewService(db)
	adminHandler := admin.NewHandler(adminService)
	feService := frontend_errors.NewService(db, hub)
	feHandler := frontend_errors.NewHandler(feService)

	r := chi.NewRouter()
	r.Use(middleware.RequestID)
	r.Use(middleware.RealIP)
	r.Use(middleware.Logger)
	r.Use(middleware.Recoverer)
	r.Use(securityHeaders)
	r.Use(mw.Authenticate)
	r.Use(auth.CSRFProtect)
	r.Use(csrfExemptForReadOnly)

	staticDir, _ := os.Getwd()
	r.Handle("/static/*", http.StripPrefix("/static/", http.FileServer(http.Dir(filepath.Join(staticDir, "web", "static")))))
	r.Get("/", func(w http.ResponseWriter, req *http.Request) {
		u, _ := auth.UserFromContext(req.Context())
		stats := map[string]int{}
		if n, err := countProjects(req.Context(), db); err == nil {
			stats["projects"] = n
		}
		if n, err := countIssues(req.Context(), db); err == nil {
			stats["issues"] = n
		}
		if n, err := countUsers(req.Context(), db); err == nil {
			stats["users"] = n
		}
		if n, err := countComments(req.Context(), db); err == nil {
			stats["comments"] = n
		}
		tpl.Render(w, "home.html", map[string]any{
			"User":      u,
			"Stats":     stats,
			"CSRFToken": auth.CSRFToken(req.Context()),
		})
	})
	r.Get("/login", func(w http.ResponseWriter, req *http.Request) {
		u, _ := auth.UserFromContext(req.Context())
		if u != nil {
			http.Redirect(w, req, "/", http.StatusSeeOther)
			return
		}
		accountHandler.LoginPage(w, req, tpl)
	})
	r.Get("/register", func(w http.ResponseWriter, req *http.Request) {
		u, _ := auth.UserFromContext(req.Context())
		if u != nil {
			http.Redirect(w, req, "/", http.StatusSeeOther)
			return
		}
		accountHandler.RegisterPage(w, req, tpl)
	})
	r.Get("/search", func(w http.ResponseWriter, req *http.Request) {
		searchHandler.SearchPage(w, req, tpl)
	})
	r.Get("/admin", func(w http.ResponseWriter, req *http.Request) {
		adminHandler.Page(w, req, tpl)
	})
	r.Get("/frontend-errors", func(w http.ResponseWriter, req *http.Request) {
		feHandler.Page(w, req, tpl)
	})
	r.Get("/import-export", func(w http.ResponseWriter, req *http.Request) {
		importHandler.Page(w, req, tpl)
	})

	accountHandler.Routes(r, tpl)
	projectsHandler.Routes(r, tpl)
	issuesHandler.Routes(r, tpl)
	commentsHandler.Routes(r, tpl)
	assignmentHandler.Routes(r, tpl)
	attachmentsHandler.Routes(r, tpl)
	privateHandler.Routes(r, tpl)
	webhooksHandler.Routes(r, tpl)
	importHandler.Routes(r, tpl)
	adminHandler.Routes(r, tpl)
	feHandler.Routes(r, tpl)
	searchHandler.Routes(r, tpl)

	r.NotFound(func(w http.ResponseWriter, req *http.Request) {
		tpl.Render(w, "error.html", map[string]any{"Message": "Page not found", "User": authUserOrNil(req.Context())})
	})

	srv := &http.Server{
		Addr:              cfg.Addr,
		Handler:           r,
		ReadHeaderTimeout: 15 * time.Second,
	}

	go func() {
		log.Printf("listening on %s", cfg.Addr)
		if err := srv.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			log.Fatalf("server error: %v", err)
		}
	}()

	stop := make(chan os.Signal, 1)
	signal.Notify(stop, syscall.SIGINT, syscall.SIGTERM)
	<-stop
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	if err := srv.Shutdown(ctx); err != nil {
		log.Printf("shutdown error: %v", err)
	}
}

func securityHeaders(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("X-Frame-Options", "DENY")
		w.Header().Set("X-Content-Type-Options", "nosniff")
		w.Header().Set("Referrer-Policy", "no-referrer")
		next.ServeHTTP(w, r)
	})
}

func csrfExemptForReadOnly(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet || r.Method == http.MethodHead || r.Method == http.MethodOptions {
			next.ServeHTTP(w, r)
			return
		}
		next.ServeHTTP(w, r)
	})
}

func authUserOrNil(ctx context.Context) any {
	u, ok := auth.UserFromContext(ctx)
	if !ok {
		return nil
	}
	return u
}

func countProjects(ctx context.Context, db *sql.DB) (int, error) {
	var n int
	err := db.QueryRowContext(ctx, `SELECT COUNT(*) FROM projects`).Scan(&n)
	return n, err
}
func countIssues(ctx context.Context, db *sql.DB) (int, error) {
	var n int
	err := db.QueryRowContext(ctx, `SELECT COUNT(*) FROM issues`).Scan(&n)
	return n, err
}
func countUsers(ctx context.Context, db *sql.DB) (int, error) {
	var n int
	err := db.QueryRowContext(ctx, `SELECT COUNT(*) FROM users`).Scan(&n)
	return n, err
}
func countComments(ctx context.Context, db *sql.DB) (int, error) {
	var n int
	err := db.QueryRowContext(ctx, `SELECT COUNT(*) FROM comments`).Scan(&n)
	return n, err
}

var _ = httpx.BadRequest