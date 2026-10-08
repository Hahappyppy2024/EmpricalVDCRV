// Command issuetracker is the P09 Issue Tracking System web application.
package main

import (
	"flag"
	"log"
	"net/http"
	"time"

	"issuetracker/internal/config"
	"issuetracker/internal/database"
	"issuetracker/internal/web"
)

func main() {
	cfg := config.Load()

	reset := flag.Bool("reset", false, "drop all tables and re-seed the database")
	flag.Parse()

	db, err := database.Open(cfg.DBPath)
	if err != nil {
		log.Fatalf("database: %v", err)
	}
	defer db.Close()

	if *reset {
		if err := database.Reset(db); err != nil {
			log.Fatalf("reset: %v", err)
		}
		if err := database.ApplySchema(db); err != nil {
			log.Fatalf("schema: %v", err)
		}
		if err := database.Seed(db, cfg.AppURL); err != nil {
			log.Fatalf("seed: %v", err)
		}
		log.Printf("database reset and seeded at %s", cfg.DBPath)
		return
	}

	app, err := web.New(cfg, db)
	if err != nil {
		log.Fatalf("web: %v", err)
	}

	addr := ":" + cfg.Port
	server := &http.Server{
		Addr:              addr,
		Handler:           app.Routes(),
		ReadHeaderTimeout: 10 * time.Second,
	}
	log.Printf("P09 Issue Tracking System listening on %s (APP_URL=%s)", addr, cfg.AppURL)
	if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
		log.Fatalf("server: %v", err)
	}
}
