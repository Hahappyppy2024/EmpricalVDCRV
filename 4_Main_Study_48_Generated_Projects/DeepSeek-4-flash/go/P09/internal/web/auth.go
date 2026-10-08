package web

import (
	"net/http"
	"strconv"
	"strings"

	"issuetracker/internal/models"
	"issuetracker/internal/store"
)

// ISSUE-01 — Account access -------------------------------------------------

func (a *App) pageLogin(w http.ResponseWriter, r *http.Request) {
	if userFrom(r) != nil {
		http.Redirect(w, r, "/dashboard", http.StatusSeeOther)
		return
	}
	data := a.PageData(r, "Sign in")
	if f := flashFrom(r); f != "" {
		data["Flash"] = f
	}
	a.render(w, r, "login.html", data)
}

func (a *App) pageRegister(w http.ResponseWriter, r *http.Request) {
	if userFrom(r) != nil {
		http.Redirect(w, r, "/dashboard", http.StatusSeeOther)
		return
	}
	data := a.PageData(r, "Register")
	if f := flashFrom(r); f != "" {
		data["Flash"] = f
	}
	a.render(w, r, "register.html", data)
}

func (a *App) handleLogin(w http.ResponseWriter, r *http.Request) {
	username := strings.TrimSpace(r.FormValue("username"))
	password := r.FormValue("password")
	u, err := a.Store.UserByUsername(username)
	if err != nil || !authCheck(a, u, password) {
		http.Redirect(w, r, "/login?flash="+urlEncode("Invalid username or password"), http.StatusSeeOther)
		return
	}
	if err := a.Auth.CreateSession(w, u.ID); err != nil {
		http.Error(w, "Internal error", http.StatusInternalServerError)
		return
	}
	_ = a.Store.CreateUseCaseRecord("account_access", models.UseCaseRecord{
		UserID: u.ID, Action: "login", SubjectID: u.ID, Summary: "Signed in as " + u.Username,
		Detail: "session created", Status: "ok",
	})
	http.Redirect(w, r, "/dashboard?flash="+urlEncode("Welcome back, "+u.DisplayName+"!"), http.StatusSeeOther)
}

func (a *App) handleRegister(w http.ResponseWriter, r *http.Request) {
	username := strings.TrimSpace(r.FormValue("username"))
	email := strings.TrimSpace(r.FormValue("email"))
	displayName := strings.TrimSpace(r.FormValue("display_name"))
	password := r.FormValue("password")
	if username == "" || email == "" || password == "" {
		http.Redirect(w, r, "/register?flash="+urlEncode("Username, email and password are required"), http.StatusSeeOther)
		return
	}
	if len(password) < 8 {
		http.Redirect(w, r, "/register?flash="+urlEncode("Password must be at least 8 characters"), http.StatusSeeOther)
		return
	}
	if a.Store.GetSetting("allow_registration", "true") != "true" {
		http.Redirect(w, r, "/register?flash="+urlEncode("Registration is disabled by the administrator"), http.StatusSeeOther)
		return
	}
	hash, err := hashPassword(password)
	if err != nil {
		http.Error(w, "Internal error", http.StatusInternalServerError)
		return
	}
	if displayName == "" {
		displayName = username
	}
	u, err := a.Store.CreateUser(&models.User{
		Username: username, Email: email, DisplayName: displayName, PasswordHash: hash, Role: models.RoleDeveloper,
	})
	if err != nil {
		http.Redirect(w, r, "/register?flash="+urlEncode("Username or email is already taken"), http.StatusSeeOther)
		return
	}
	if err := a.Auth.CreateSession(w, u.ID); err != nil {
		http.Error(w, "Internal error", http.StatusInternalServerError)
		return
	}
	_ = a.Store.CreateUseCaseRecord("account_access", models.UseCaseRecord{
		UserID: u.ID, Action: "register", SubjectID: u.ID, Summary: "Registered account " + u.Username,
		Detail: "role=" + u.Role, Status: "ok",
	})
	http.Redirect(w, r, "/dashboard?flash="+urlEncode("Account created. Welcome, "+u.DisplayName+"!"), http.StatusSeeOther)
}

func (a *App) handleLogout(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	a.Auth.DestroySession(w, r)
	if u != nil {
		_ = a.Store.CreateUseCaseRecord("account_access", models.UseCaseRecord{
			UserID: u.ID, Action: "logout", SubjectID: u.ID, Summary: "Signed out", Status: "ok",
		})
	}
	http.Redirect(w, r, "/login?flash="+urlEncode("You have been signed out"), http.StatusSeeOther)
}

// pageProfile is the account access page: profile form + recent account records.
func (a *App) pageProfile(w http.ResponseWriter, r *http.Request) {
	u := userFrom(r)
	records, _ := a.Store.ListUseCaseRecords("account_access", store.UseCaseRecordParams{UserID: u.ID, Limit: 20})
	data := a.PageData(r, "Account")
	data["AccountUser"] = u
	data["Records"] = records
	a.render(w, r, "profile.html", data)
}

func authCheck(a *App, u *models.User, password string) bool {
	return u != nil && u.Active && checkPassword(u.PasswordHash, password)
}

// accountAccessAPI implements GET/POST/PATCH /api/issue/account_access.
func (a *App) accountAccessAPI(w http.ResponseWriter, r *http.Request, method string) {
	u := userFrom(r)
	switch method {
	case "get":
		records, err := a.Store.ListUseCaseRecords("account_access", useCaseParams(r))
		if err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", err.Error())
			return
		}
		okJSON(w, records)
	case "post":
		action := strings.ToLower(strings.TrimSpace(r.FormValue("action")))
		if action == "" {
			var body map[string]any
			_ = jsonDecode(r, &body)
			action, _ = body["action"].(string)
		}
		switch action {
		case "profile":
			displayName := strings.TrimSpace(r.FormValue("display_name"))
			if displayName == "" {
				errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "display_name is required")
				return
			}
			if err := a.Store.UpdateUser(u.ID, "", nil, displayName); err != nil {
				errJSON(w, http.StatusInternalServerError, "internal_error", "could not update profile")
				return
			}
			_ = a.Store.CreateUseCaseRecord("account_access", models.UseCaseRecord{
				UserID: u.ID, Action: "profile.update", SubjectID: u.ID, Summary: "Updated profile", Detail: "display_name=" + displayName, Status: "ok",
			})
			okJSON(w, map[string]any{"summary": "profile updated", "user_id": u.ID})
		default:
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "action must be one of: register, login, logout, profile")
		}
	case "patch":
		id, err := strconv.ParseInt(chiURLParam(r, "id"), 10, 64)
		if err != nil {
			errJSON(w, http.StatusBadRequest, "validation_failed", "invalid id")
			return
		}
		rec, err := a.Store.UseCaseRecordByID("account_access", id)
		if err != nil {
			errJSON(w, http.StatusNotFound, "not_found", "record not found")
			return
		}
		if rec.UserID != u.ID && u.Role != models.RoleAdmin {
			errJSON(w, http.StatusForbidden, "forbidden", "you may only update your own account records")
			return
		}
		displayName := strings.TrimSpace(r.FormValue("display_name"))
		if displayName == "" {
			errJSON(w, http.StatusUnprocessableEntity, "validation_failed", "display_name is required")
			return
		}
		if err := a.Store.UpdateUser(rec.UserID, "", nil, displayName); err != nil {
			errJSON(w, http.StatusInternalServerError, "internal_error", "could not update profile")
			return
		}
		updated, _ := a.Store.UseCaseRecordByID("account_access", id)
		okJSON(w, updated)
	}
}
