# Go Technology Profile

This profile is authoritative for this implementation.

- Language and runtime: Go 1.24.
- Web framework/router: `github.com/go-chi/chi/v5` with the standard `net/http` server.
- Database: SQLite through the pure-Go `modernc.org/sqlite` driver using a repository/data-access layer; do not require CGO.
- Authentication: server-side sessions identified by an HTTP-only cookie; persistent session records are stored in SQLite.
- Browser client: server-served HTML templates, CSS, and vanilla JavaScript.
- Real-time transport: use `github.com/coder/websocket` when a use case requires WebSocket behavior; otherwise use ordinary HTTP.
- Dependency tooling: Go modules with committed `go.mod` and `go.sum`.
- Configuration: environment variables documented in `.env.example`.
- Local execution: provide database reset/seed and start commands that work without paid services or external accounts.
- Containerization: provide a multi-stage Dockerfile and compose file for reproducible local execution.
