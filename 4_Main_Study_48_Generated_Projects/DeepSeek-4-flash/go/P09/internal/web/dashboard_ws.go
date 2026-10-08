package web

import (
	"context"
	"net/http"
	"strconv"
	"time"

	"github.com/coder/websocket"
	"github.com/go-chi/chi/v5"

	"issuetracker/internal/service"
	"issuetracker/internal/store"
)

// pageDashboard is the landing page after sign-in.
func (a *App) pageDashboard(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	projects, _ := a.userVisibleProjects(u)
	issues, _ := a.Store.SearchIssues(u.ID, store.IssueSearchParams{AssigneeID: u.ID, Limit: 10})
	records, _ := a.Store.ListUseCaseRecords("account_access", store.UseCaseRecordParams{UserID: u.ID, Limit: 10})
	data := a.PageData(r, "Dashboard")
	data["Projects"] = projects
	data["MyIssues"] = issues
	data["Records"] = records
	if f := flashFrom(r); f != "" {
		data["Flash"] = f
	}
	a.render(w, r, "dashboard.html", data)
}

// handleWS upgrades the connection and streams real-time issue events for a
// project room over a WebSocket.
func (a *App) handleWS(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	projectID, err := strconv.ParseInt(chi.URLParam(r, "projectID"), 10, 64)
	if err != nil {
		http.NotFound(w, r)
		return
	}
	p, err := a.Store.ProjectByID(projectID)
	if err != nil || !a.canAccessProject(u, p) {
		http.Error(w, "You do not have access to this project.", http.StatusForbidden)
		return
	}
	conn, err := websocket.Accept(w, r, &websocket.AcceptOptions{
		OriginPatterns: []string{"*"},
	})
	if err != nil {
		return
	}
	client := &service.Client{Send: make(chan []byte, 8)}
	a.Bus.Subscribe(projectID, client)
	defer func() { a.Bus.Unsubscribe(projectID, client); _ = conn.Close(websocket.StatusNormalClosure, "bye") }()

	go func() {
		for {
			select {
			case <-client.Done():
				return
			case msg := <-client.Send:
				ctx, cancel := context.WithTimeout(r.Context(), 5*time.Second)
				err := conn.Write(ctx, websocket.MessageText, msg)
				cancel()
				if err != nil {
					return
				}
			}
		}
	}()

	for {
		_, _, err := conn.Read(r.Context())
		if err != nil {
			return
		}
	}
}
