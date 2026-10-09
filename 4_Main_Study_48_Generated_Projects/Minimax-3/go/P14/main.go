package main

import (
	"context"
	"flag"
	"log"
	"net/http"
	"os"
	"os/signal"
	"path/filepath"
	"syscall"
	"time"

	"github.com/anomalyco/p14-workflow-automation/internal/auth"
	"github.com/anomalyco/p14-workflow-automation/internal/config"
	"github.com/anomalyco/p14-workflow-automation/internal/database"
	"github.com/anomalyco/p14-workflow-automation/internal/handlers"
	"github.com/anomalyco/p14-workflow-automation/internal/middleware"
	"github.com/anomalyco/p14-workflow-automation/internal/render"
	"github.com/anomalyco/p14-workflow-automation/internal/repo"
	"github.com/anomalyco/p14-workflow-automation/internal/runner"
	"github.com/go-chi/chi/v5"
)

func main() {
	reset := flag.Bool("reset", false, "wipe and reseed the database before starting")
	flag.Parse()

	if *reset {
		// Clean any prior local data using best-effort env defaults.
		guess := func(key, def string) string {
			if v := os.Getenv(key); v != "" {
				return v
			}
			return def
		}
		dbPath := guess("DATABASE_PATH", "data/app.db")
		wsRoot := guess("WORKSPACE_ROOT", "data/workspaces")
		upRoot := guess("UPLOAD_ROOT", "data/uploads")
		_ = os.Remove(dbPath)
		_ = os.RemoveAll(filepath.Dir(dbPath))
		_ = os.RemoveAll(wsRoot)
		_ = os.RemoveAll(upRoot)
	}

	cfg, err := config.Load()
	if err != nil {
		log.Fatalf("config: %v", err)
	}

	db, err := database.Open(cfg.DatabasePath)
	if err != nil {
		log.Fatalf("database: %v", err)
	}
	defer db.Close()

	authSvc := auth.NewService(db, cfg.SessionSecret, cfg.SessionLifetime)

	if err := database.SeedDB(context.Background(), db, authSvc); err != nil {
		log.Fatalf("seed: %v", err)
	}

	r, err := render.New()
	if err != nil {
		log.Fatalf("render: %v", err)
	}

	bus := runner.NewBus()
	deps := &handlers.Deps{
		Cfg:       cfg,
		DB:        db,
		Auth:      authSvc,
		Users:     &repo.Users{DB: db},
		AccessLog: &repo.AccessLog{DB: db},
		Workflows: &repo.Workflows{DB: db},
		Tools:     &repo.Tools{DB: db},
		Runs:      &repo.Runs{DB: db},
		Schedules: &repo.Schedules{DB: db},
		Files:     &repo.Files{DB: db},
		Webhooks:  &repo.Webhooks{DB: db},
		HTTPA:     &repo.HTTPActions{DB: db},
		Secrets:   &repo.Secrets{DB: db},
		Logs:      &repo.RunLogs{DB: db},
		Templates: &repo.Templates{DB: db},
		Admin:     &repo.Admin{DB: db},
		Audit:     &repo.Audit{DB: db},
		Render:    r,
		Runner:    runner.New(cfg, bus, &repo.Runs{DB: db}, &repo.RunLogs{DB: db}, &repo.Files{DB: db}, &repo.Tools{DB: db}, &repo.Workflows{DB: db}),
		Bus:       bus,
	}

	router := buildRouter(cfg, deps, authSvc)

	srv := &http.Server{
		Addr:              cfg.HTTPAddr,
		Handler:           router,
		ReadHeaderTimeout: 10 * time.Second,
	}

	// background session cleanup
	go func() {
		ticker := time.NewTicker(time.Hour)
		defer ticker.Stop()
		for range ticker.C {
			if err := authSvc.Cleanup(context.Background()); err != nil {
				log.Printf("session cleanup: %v", err)
			}
		}
	}()

	go func() {
		log.Printf("P14 listening on %s", cfg.HTTPAddr)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("listen: %v", err)
		}
	}()

	stop := make(chan os.Signal, 1)
	signal.Notify(stop, os.Interrupt, syscall.SIGTERM)
	<-stop
	log.Println("shutting down")
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	_ = srv.Shutdown(ctx)
}

func buildRouter(cfg *config.Config, d *handlers.Deps, authSvc *auth.Service) http.Handler {
	r := chi.NewRouter()

	r.Use(middleware.Recoverer)

	staticDir := http.Dir("./web/static")
	r.Handle("/static/*", http.StripPrefix("/static/", http.FileServer(staticDir)))

	// public API
	r.Get("/api/health", d.Health)
	r.Post("/api/echo", d.Echo)
	r.Post("/api/agent/account_access/register", d.AccountRegisterPost)
	r.Post("/api/agent/account_access/sign_in", d.AccountSignInPost)
	r.Get("/api/agent/account_access", d.AccountHome)
	// public webhook (token-based, no session)
	r.Post("/api/webhooks/{token}", d.WebhookPublicInvoke)
	r.Get("/api/runs/{id}/stream", d.RunStreamWS)
	r.Get("/ws/runs/{id}", d.RunStreamWS)

	// public pages
	r.Get("/", d.Home)
	r.Get("/account_access/sign_in", d.AccountSignInForm)
	r.Get("/account_access/register", d.AccountRegisterForm)
	r.Post("/account_access/sign_in", d.AccountSignInPost)
	r.Post("/account_access/register", d.AccountRegisterPost)
	r.Post("/account_access/sign_out", d.AccountSignOutPost)
	r.Post("/account_access/reset", d.AccountResetPost)

	// session-required pages
	r.Group(func(r chi.Router) {
		r.Use(middleware.Auth(authSvc, d.Users, false))

		r.Get("/dashboard", d.Dashboard)
		r.Get("/account_access", d.AccountHome)

		r.Get("/workflow_creation", d.WorkflowsPage)
		r.Post("/workflow_creation", d.WorkflowsPage)
		r.Post("/workflow_creation/{id}/run", d.WorkflowRunPost)
		r.Post("/workflow_creation/{id}/archive", d.WorkflowArchivePost)

		r.Get("/tool_catalog", d.ToolCatalogPage)
		r.Post("/tool_catalog", d.ToolCatalogPage)
		r.Post("/tool_catalog/{id}/toggle", d.ToolTogglePost)

		r.Get("/task_execution", d.TaskExecutionPage)
		r.Post("/task_execution", d.TaskExecutionPage)

		r.Get("/scheduled_runs", d.SchedulesPage)
		r.Post("/scheduled_runs", d.SchedulesPage)
		r.Post("/scheduled_runs/{id}/toggle", d.ScheduleTogglePost)
		r.Post("/scheduled_runs/{id}/delete", d.ScheduleDeletePost)

		r.Get("/workspace_files", d.WorkspacePage)
		r.Post("/workspace_files", d.WorkspacePage)
		r.Post("/workspace_files/upload", d.WorkspaceUploadPost)
		r.Get("/workspace_files/view", d.WorkspaceViewGet)
		r.Post("/workspace_files/{id}/delete", d.WorkspaceDeletePost)

		r.Get("/webhook_triggers", d.WebhooksPage)
		r.Post("/webhook_triggers", d.WebhooksPage)
		r.Post("/webhook_triggers/{id}/revoke", d.WebhookRevokePost)
		r.Post("/webhooks/invoke", d.WebhookInvokePost)

		r.Get("/external_http_action", d.HTTPPage)
		r.Post("/external_http_action", d.HTTPPage)

		r.Get("/secrets_manager", d.SecretsPage)
		r.Post("/secrets_manager", d.SecretsPage)
		r.Post("/secrets_manager/{id}/revoke", d.SecretRevokePost)

		r.Get("/run_logs_and_replay", d.RunLogsPage)
		r.Post("/run_logs_and_replay/replay", d.RunLogsPage)

		r.Get("/sharing_and_templates", d.TemplatesPage)
		r.Post("/sharing_and_templates", d.TemplatesPage)
		r.Post("/sharing_and_templates/{id}/visibility", d.TemplateVisibilityPost)
		r.Post("/sharing_and_templates/{id}/delete", d.TemplateDeletePost)

		// admin-only
		r.Group(func(r chi.Router) {
			r.Use(middleware.RequireRole("admin"))
			r.Get("/admin_governance", d.AdminPage)
			r.Post("/admin_governance/{key}/update", d.AdminUpdateSetting)
			r.Post("/admin_governance/users/{id}/toggle_status", d.AdminUserToggleStatus)
			r.Post("/admin_governance/users/{id}/toggle_role", d.AdminUserToggleRole)
		})
	})

	r.NotFound(func(w http.ResponseWriter, req *http.Request) {
		http.Error(w, "not found", http.StatusNotFound)
	})

	return r
}