package main

import (
	"log"
	"os"
	"p09issuetracker/internal/app"
)

func main() {
	p := "./data/p09.db"
	if v := os.Getenv("DB_PATH"); v != "" {
		p = v
	}
	a, err := app.New(p, "p09_session", "./storage")
	if err != nil {
		log.Fatal(err)
	}
	defer a.Close()
	if err = a.ResetAndSeed(); err != nil {
		log.Fatal(err)
	}
	log.Println("database reset and seeded")
}
