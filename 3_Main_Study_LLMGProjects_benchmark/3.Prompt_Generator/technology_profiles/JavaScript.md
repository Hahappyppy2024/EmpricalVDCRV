# JavaScript Technology Profile

This profile is authoritative for this implementation.

- Language: JavaScript (ECMAScript modules).
- Runtime: Node.js 22 LTS.
- Web framework: Express 5.
- Database: SQLite using `better-sqlite3` through a repository/data-access layer.
- Authentication: server-side sessions identified by an HTTP-only cookie; session records are stored in SQLite.
- Browser client: server-served HTML, CSS, and vanilla JavaScript.
- Real-time transport: use the `ws` package when a use case requires WebSocket behavior; otherwise use ordinary HTTP.
- Package tooling: npm with a committed `package-lock.json` and pinned direct dependency versions.
- Configuration: environment variables documented in `.env.example`.
- Local execution: provide database reset/seed and start scripts that work without paid services or external accounts.
- Containerization: provide a Dockerfile and compose file for reproducible local execution.
