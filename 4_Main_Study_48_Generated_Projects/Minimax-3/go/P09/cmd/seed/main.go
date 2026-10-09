package main

import (
	"context"
	"flag"
	"fmt"
	"log"
	"os"
	"path/filepath"

	"github.com/anomaly/p09/internal/config"
	"github.com/anomaly/p09/internal/database"
)

func main() {
	reset := flag.Bool("reset", false, "delete existing data before seeding")
	flag.Parse()

	cfg, err := config.Load()
	if err != nil {
		log.Fatalf("load config: %v", err)
	}
	if *reset {
		if err := os.Remove(cfg.DBPath); err != nil && !os.IsNotExist(err) {
			log.Fatalf("remove db: %v", err)
		}
		for _, ext := range []string{"-shm", "-wal"} {
			_ = os.Remove(cfg.DBPath + ext)
		}
		fmt.Println("database file removed (if present)")
	}
	if err := os.MkdirAll(filepath.Dir(cfg.DBPath), 0o755); err != nil {
		log.Fatalf("mkdir: %v", err)
	}
	db, err := database.Open(cfg.DBPath)
	if err != nil {
		log.Fatalf("open db: %v", err)
	}
	defer db.Close()
	if err := database.Migrate(context.Background(), db); err != nil {
		log.Fatalf("migrate: %v", err)
	}
	if err := database.Seed(context.Background(), db); err != nil {
		log.Fatalf("seed: %v", err)
	}
	fmt.Println("seed complete:", cfg.DBPath)
}