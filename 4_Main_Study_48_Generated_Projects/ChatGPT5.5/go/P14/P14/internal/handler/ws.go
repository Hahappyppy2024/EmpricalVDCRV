package handler

import (
	"fmt"
	"net/http"
	"strconv"
	"time"

	"github.com/coder/websocket"

	"github.com/go-chi/chi/v5"

	"p14-agentic-platform/internal/middleware"
	"p14-agentic-platform/internal/service"
)

// wsRunLogs streams a run's step logs over a WebSocket connection, replaying
// existing log entries with a small delay to simulate live output (AGENT-10).
func (s *Server) wsRunLogs(w http.ResponseWriter, r *http.Request) {
	user := middleware.UserFrom(r)
	if user == nil {
		writeErr(w, service.Unauthorized("not signed in"))
		return
	}
	id, err := strconv.ParseInt(chi.URLParam(r, "id"), 10, 64)
	if err != nil {
		writeErr(w, service.BadRequest("invalid run id"))
		return
	}
	run, logs, err := s.svc.RunDetail(user, id)
	if err != nil {
		writeErr(w, err)
		return
	}

	conn, err := websocket.Accept(w, r, &websocket.AcceptOptions{
		InsecureSkipVerify: true,
	})
	if err != nil {
		return
	}
	defer conn.Close(websocket.StatusNormalClosure, "done")

	status, _ := run["status"].(string)
	_ = conn.Write(r.Context(), websocket.MessageText, []byte(fmt.Sprintf("[run %d] status: %s", id, status)))

	for _, lg := range logs {
		step, _ := lg["step_name"].(string)
		st, _ := lg["status"].(string)
		output, _ := lg["output"].(string)
		line := fmt.Sprintf("[%s] %s\n%s", st, step, output)
		if err := conn.Write(r.Context(), websocket.MessageText, []byte(line)); err != nil {
			return
		}
		time.Sleep(80 * time.Millisecond)
	}
	_ = conn.Write(r.Context(), websocket.MessageText, []byte("__end__"))
}
