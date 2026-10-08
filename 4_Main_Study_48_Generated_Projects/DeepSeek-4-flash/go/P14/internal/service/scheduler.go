package service

import (
	"context"
	"fmt"
	"log"
	"strings"
	"time"
)

// RunDueSchedules executes every enabled schedule whose next_run_at has passed
// and advances the schedule to its next occurrence (AGENT-05).
func (s *Service) RunDueSchedules() (int, error) {
	now := time.Now().UTC()
	rows, err := s.repo.Query("SELECT id, user_id, workflow_id, cron_expr FROM schedules WHERE enabled = 1 AND next_run_at <= ?", now.Format(time.RFC3339))
	if err != nil {
		return 0, err
	}
	type due struct {
		id, userID, workflowID int64
		cron                   string
	}
	var dueItems []due
	for rows.Next() {
		var d due
		if err := rows.Scan(&d.id, &d.userID, &d.workflowID, &d.cron); err != nil {
			rows.Close()
			return 0, err
		}
		dueItems = append(dueItems, d)
	}
	rows.Close()
	if err := rows.Err(); err != nil {
		return 0, err
	}

	ran := 0
	for _, d := range dueItems {
		owner, err := s.UserByID(d.userID)
		if err != nil {
			continue
		}
		wf, err := s.WorkflowByID(owner.ID, d.workflowID)
		if err != nil {
			continue
		}
		if _, err := s.ExecuteWorkflow(owner, wf, "schedule"); err != nil {
			log.Printf("scheduler: workflow %d failed: %v", d.workflowID, err)
			continue
		}
		if next, err := nextRunAt(d.cron, now); err == nil {
			_, _ = s.repo.Update("schedules", []string{"next_run_at"}, []any{next.Format(time.RFC3339)}, map[string]any{"id": d.id})
		}
		ran++
	}
	if ran > 0 {
		log.Printf("scheduler: executed %d due schedule(s)", ran)
	}
	return ran, nil
}

// SchedulerLoop ticks periodically and runs due schedules until ctx is done.
func (s *Service) SchedulerLoop(ctx context.Context) {
	tick := time.Duration(s.cfg.SchedulerTickMs) * time.Millisecond
	log.Printf("scheduler: starting loop, tick %s", tick)
	for {
		select {
		case <-ctx.Done():
			log.Printf("scheduler: stopping")
			return
		case <-time.After(tick):
			if _, err := s.RunDueSchedules(); err != nil {
				log.Printf("scheduler: error: %v", err)
			}
		}
	}
}

// nextRunAt computes the next occurrence of a 5-field cron expression from the
// given time. Minute and hour fields support "*", "*/N", "N" and comma lists.
// Day/month/weekday fields must be "*".
func nextRunAt(expr string, from time.Time) (time.Time, error) {
	fields := strings.Fields(expr)
	if len(fields) != 5 {
		return time.Time{}, fmt.Errorf("cron must have 5 fields")
	}
	minuteSet, err := parseCronField(fields[0], 0, 59)
	if err != nil {
		return time.Time{}, err
	}
	hourSet, err := parseCronField(fields[1], 0, 23)
	if err != nil {
		return time.Time{}, err
	}
	for _, f := range fields[2:] {
		if f != "*" {
			return time.Time{}, fmt.Errorf("day/month/weekday fields must be '*' in this adapter")
		}
	}
	t := from.Truncate(time.Minute).Add(time.Minute)
	for i := 0; i < 60*24*31; i++ {
		if minuteSet[t.Minute()] && hourSet[t.Hour()] {
			return t, nil
		}
		t = t.Add(time.Minute)
	}
	return time.Time{}, fmt.Errorf("no future occurrence within 31 days")
}

func parseCronField(f string, min, max int) (map[int]bool, error) {
	set := map[int]bool{}
	for _, p := range strings.Split(f, ",") {
		switch {
		case p == "*":
			for i := min; i <= max; i++ {
				set[i] = true
			}
		case strings.HasPrefix(p, "*/"):
			var n int
			if _, err := fmt.Sscanf(p[2:], "%d", &n); err != nil || n < 1 {
				return nil, fmt.Errorf("invalid step field %q", p)
			}
			for i := min; i <= max; i++ {
				if i%n == 0 {
					set[i] = true
				}
			}
		default:
			var n int
			if _, err := fmt.Sscanf(p, "%d", &n); err != nil {
				return nil, fmt.Errorf("invalid field %q", p)
			}
			if n < min || n > max {
				return nil, fmt.Errorf("value %d out of range [%d,%d]", n, min, max)
			}
			set[n] = true
		}
	}
	return set, nil
}
