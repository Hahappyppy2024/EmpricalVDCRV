package web

import (
	"fmt"
	"net/http"
	"strconv"
	"strings"

	"issuetracker/internal/models"
)

// ISSUE-09 — Webhooks --------------------------------------------------------

func (a *App) pageWebhooks(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	p, err := a.projectFromSlug(r)
	if err != nil {
		http.NotFound(w, r)
		return
	}
	webhooks, _ := a.Store.ListWebhooks(p.ID)
	type row struct {
		Webhook    models.Webhook
		Deliveries []models.WebhookDelivery
	}
	rows := make([]row, 0, len(webhooks))
	for i := range webhooks {
		deliveries, _ := a.Store.ListDeliveries(webhooks[i].ID, 10)
		rows = append(rows, row{Webhook: webhooks[i], Deliveries: deliveries})
	}
	data := a.PageData(r, "Webhooks — "+p.Name)
	data["Project"] = p
	data["Rows"] = rows
	data["CanManage"] = a.canManageProject(u, p)
	data["AllEvents"] = []string{models.EventIssueCreated, models.EventIssueUpdated, models.EventIssueCommented}
	a.render(w, r, "webhooks.html", data)
}

func (a *App) handleWebhookCreate(w http.ResponseWriter, r *http.Request) {
	p := projectFrom(r)
	u := userFrom(r)
	url := strings.TrimSpace(r.FormValue("url"))
	secret := strings.TrimSpace(r.FormValue("secret"))
	if url == "" {
		http.Redirect(w, r, "/projects/"+p.Slug+"/webhooks?flash="+urlEncode("Webhook URL is required"), http.StatusSeeOther)
		return
	}
	if !strings.HasPrefix(url, "http://") && !strings.HasPrefix(url, "https://") {
		http.Redirect(w, r, "/projects/"+p.Slug+"/webhooks?flash="+urlEncode("Webhook URL must start with http:// or https://"), http.StatusSeeOther)
		return
	}
	if secret == "" {
		secret = randHex(16)
	}
	events := eventsFromForm(r)
	if len(events) == 0 {
		http.Redirect(w, r, "/projects/"+p.Slug+"/webhooks?flash="+urlEncode("Select at least one event"), http.StatusSeeOther)
		return
	}
	h, err := a.Store.CreateWebhook(&models.Webhook{
		ProjectID: p.ID, CreatedBy: u.ID, URL: url, Secret: secret, Active: true, Events: events,
	})
	if err != nil {
		http.Error(w, "could not create webhook", http.StatusInternalServerError)
		return
	}
	_ = h
	http.Redirect(w, r, "/projects/"+p.Slug+"/webhooks?flash="+urlEncode("Webhook created"), http.StatusSeeOther)
}

func eventsFromForm(r *http.Request) []string {
	var out []string
	for _, e := range r.Form["events"] {
		switch e {
		case models.EventIssueCreated, models.EventIssueUpdated, models.EventIssueCommented:
			out = append(out, e)
		}
	}
	return out
}

// webhooksAPI implements the ISSUE-09 API contract.
func (a *App) webhooksAPI(w http.ResponseWriter, r *http.Request, method string) {
	u := userFrom(r)
	switch method {
	case "get":
		if projectID, err := strconv.ParseInt(r.URL.Query().Get("project_id"), 10, 64); err == nil && projectID != 0 {
			p, err := a.Store.ProjectByID(projectID)
			if err != nil {
				errJSON(w, http.StatusNotFound, "not_found", "project not found")
				return
			}
			if !a.canAccessProject(u, p) {
				errJSON(w, http.StatusForbidden, "forbidden", "you do not have access to this project")
				return
			}
			hooks, _ := a.Store.ListWebhooks(projectID)
			type hookRow struct {
				Webhook    models.Webhook           `json:"webhook"`
				Deliveries []models.WebhookDelivery `json:"deliveries"`
			}
			rows := make([]hookRow, 0, len(hooks))
			for i := range hooks {
				deliveries, _ := a.Store.ListDeliveries(hooks[i].ID, 10)
				rows = append(rows, hookRow{Webhook: hooks[i], Deliveries: deliveries})
			}
			okJSON(w, rows)
			return
		}
		hooks, err := a.Store.ListAllWebhooks()
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", err.Error())
			return
		}
		okJSON(w, hooks)
	case "post":
		projectID, err := strconv.ParseInt(r.FormValue("project_id"), 10, 64)
		if err != nil || projectID == 0 {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "project_id is required")
			return
		}
		p, err := a.Store.ProjectByID(projectID)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "project not found")
			return
		}
		if !a.canManageProject(u, p) {
			errJSON(w, http.StatusForbidden, "forbidden", "webhook configuration requires maintainer rights")
			return
		}
		url := strings.TrimSpace(r.FormValue("url"))
		if url == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "url is required")
			return
		}
		if !strings.HasPrefix(url, "http://") && !strings.HasPrefix(url, "https://") {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "url must start with http:// or https://")
			return
		}
		secret := strings.TrimSpace(r.FormValue("secret"))
		if secret == "" {
			secret = randHex(16)
		}
		events := eventsFromForm(r)
		if len(events) == 0 {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "at least one event is required")
			return
		}
		h, err := a.Store.CreateWebhook(&models.Webhook{
			ProjectID: p.ID, CreatedBy: u.ID, URL: url, Secret: secret, Active: true, Events: events,
		})
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", "could not create webhook")
			return
		}
		okJSON(w, h)
	case "patch":
		id, err := idParam(r)
		if err != nil {
			errJSON(w, http.StatusBadRequest, "validation_failed", "invalid id")
			return
		}
		h, err := a.Store.WebhookByID(id)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "webhook not found")
			return
		}
		p, _ := a.Store.ProjectByID(h.ProjectID)
		if !a.canManageProject(u, p) {
			errJSON(w, http.StatusForbidden, "forbidden", "webhook configuration requires maintainer rights")
			return
		}
		url := strings.TrimSpace(r.FormValue("url"))
		secret := strings.TrimSpace(r.FormValue("secret"))
		var active *bool
		switch r.FormValue("active") {
		case "true":
			v := true
			active = &v
		case "false":
			v := false
			active = &v
		}
		events := eventsFromForm(r)
		if url != "" && !strings.HasPrefix(url, "http://") && !strings.HasPrefix(url, "https://") {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "url must start with http:// or https://")
			return
		}
		if err := a.Store.UpdateWebhook(id, url, secret, active, events); err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", "could not update webhook")
			return
		}
		detail := "url=" + h.URL
		if active != nil {
			detail += fmt.Sprintf(", active=%v", *active)
		}
		if secret != "" {
			detail += ", secret=rotated"
		}
		updated, _ := a.Store.WebhookByID(id)
		okJSON(w, updated)
	}
}
