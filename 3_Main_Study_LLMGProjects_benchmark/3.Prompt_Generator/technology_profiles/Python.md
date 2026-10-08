# Python Technology Profile

This profile is authoritative for this implementation.

- Language and runtime: Python 3.12.
- Web framework: Flask 3.
- Database: SQLite through SQLAlchemy 2 using a repository/data-access layer.
- Authentication: server-side sessions identified by an HTTP-only cookie; persistent session records are stored in SQLite.
- Browser client: server-served HTML, CSS, and vanilla JavaScript.
- Real-time transport: use Flask-Sock when a use case requires WebSocket behavior; otherwise use ordinary HTTP.
- Dependency tooling: a pinned `requirements.txt` or lock file with documented virtual-environment commands.
- Configuration: environment variables documented in `.env.example`.
- Local execution: provide database reset/seed and start commands that work without paid services or external accounts.
- Containerization: provide a Dockerfile and compose file for reproducible local execution.
