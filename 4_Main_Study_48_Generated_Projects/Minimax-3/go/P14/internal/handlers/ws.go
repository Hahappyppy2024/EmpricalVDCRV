package handlers

import (
	"context"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/anomalyco/p14-workflow-automation/internal/runner"
	"github.com/coder/websocket"
)

func (d *Deps) RunStreamWS(w http.ResponseWriter, r *http.Request) {
	runID := r.URL.Query().Get("run_id")
	if runID == "" {
		runID = r.PathValue("id")
	}
	if runID == "" {
		http.Error(w, "missing run id", http.StatusBadRequest)
		return
	}
	conn, err := websocket.Accept(w, r, &websocket.AcceptOptions{
		InsecureSkipVerify: true,
	})
	if err != nil {
		return
	}
	defer conn.Close(websocket.StatusNormalClosure, "done")
	ctx, cancel := context.WithTimeout(r.Context(), 2*time.Minute)
	defer cancel()
	ch, unsub := d.Bus.Subscribe(runID)
	defer unsub()
	pingTicker := time.NewTicker(20 * time.Second)
	defer pingTicker.Stop()
	greet := []byte(`{"seq":0,"run_id":"` + runID + `","level":"info","message":"connected","time":"` + time.Now().UTC().Format(time.RFC3339Nano) + `"}`)
	_ = conn.Write(ctx, websocket.MessageText, greet)
	done := ctx.Done()
	for {
		select {
		case <-done:
			return
		case ev, ok := <-ch:
			if !ok {
				return
			}
			payload := []byte(formatEvent(ev))
			if err := conn.Write(ctx, websocket.MessageText, payload); err != nil {
				return
			}
		case <-pingTicker.C:
			if err := conn.Ping(ctx); err != nil {
				return
			}
		}
	}
}

func formatEvent(ev runner.Event) string {
	var b strings.Builder
	b.WriteString(`{"seq":`)
	b.WriteString(strconv.FormatInt(ev.Sequence, 10))
	b.WriteString(`,"run_id":"`)
	b.WriteString(ev.RunID)
	b.WriteString(`","level":"`)
	b.WriteString(ev.Level)
	b.WriteString(`","message":`)
	b.WriteString(jsonQuote(ev.Message))
	b.WriteString(`,"time":"`)
	b.WriteString(ev.Time.Format(time.RFC3339Nano))
	b.WriteString(`"}`)
	return b.String()
}

func jsonQuote(s string) string {
	var b strings.Builder
	b.WriteByte('"')
	for _, r := range s {
		switch r {
		case '\\', '"':
			b.WriteByte('\\')
			b.WriteRune(r)
		case '\n':
			b.WriteString(`\n`)
		case '\r':
			b.WriteString(`\r`)
		case '\t':
			b.WriteString(`\t`)
		default:
			b.WriteRune(r)
		}
	}
	b.WriteByte('"')
	return b.String()
}