package main

import (
	"context"
	"flag"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"p14-agentic-platform/internal/config"
	"p14-agentic-platform/internal/db"
	"p14-agentic-platform/internal/handler"
	"p14-agentic-platform/internal/repo"
	"p14-agentic-platform/internal/service"
)

func main() {
	seedFlag := flag.Bool("seed", false, "reset the database and load deterministic seed fixtures")
	versionFlag := flag.Bool("version", false, "print version and exit")
	flag.Parse()

	if *versionFlag {
		fmt.Println("p14-agentic-platform v1.0.0")
		return
	}

	cfg := config.Load()
	if os.Getenv("SEED_DB") == "true" {
		*seedFlag = true
	}

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	database, err := db.Open(cfg.DBPath)
	if err != nil {
		log.Fatalf("database: %v", err)
	}
	defer database.Close()

	if *seedFlag {
		log.Printf("db: resetting database at %s", cfg.DBPath)
		if err := db.Reset(database); err != nil {
			log.Fatalf("db reset: %v", err)
		}
		log.Printf("db: schema migrated")
	} else if err := db.Migrate(database); err != nil {
		log.Fatalf("db migrate: %v", err)
	}

	if err := db.Seed(database, cfg.WebBaseURL); err != nil {
		log.Fatalf("db seed: %v", err)
	}

	svc := service.New(cfg, repo.New(database))
	srv := handler.New(cfg, svc)

	go svc.SchedulerLoop(ctx)

	server := &http.Server{
		Addr:              cfg.Addr,
		Handler:           srv.Routes(),
		ReadHeaderTimeout: 5 * time.Second,
	}

	go func() {
		log.Printf("p14-agentic-platform listening on %s (db=%s)", cfg.Addr, cfg.DBPath)
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("http server: %v", err)
		}
	}()

	<-ctx.Done()
	log.Printf("shutting down")
	shutdownCtx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	_ = server.Shutdown(shutdownCtx)
}
