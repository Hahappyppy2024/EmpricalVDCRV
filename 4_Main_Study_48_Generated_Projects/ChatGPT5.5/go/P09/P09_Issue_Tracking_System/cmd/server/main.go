package main

import (
	"log"
	"net/http"
	"os"

	"p09issuetracker/internal/app"
)

func env(k, d string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return d
}
func main() {
	a, err := app.New(env("DB_PATH", "./data/p09.db"), env("SESSION_COOKIE", "p09_session"), "./storage")
	if err != nil {
		log.Fatal(err)
	}
	defer a.Close()
	log.Printf("P09 listening on %s", env("APP_ADDR", ":8080"))
	log.Fatal(http.ListenAndServe(env("APP_ADDR", ":8080"), a.Router()))
}
