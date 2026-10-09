# Project workflow notes

This file captures commands an autonomous agent should run when iterating on
the project. The application is runnable without any of these commands.

## Lint

There is no formal linter configured; rely on `python -m compileall` for a
quick syntax sweep:

```bash
python -m compileall app scripts run.py
```

## Database reset

```bash
python scripts/reset_db.py
```

## Smoke test

```bash
python scripts/init_db.py
python run.py &  # or under Windows: start python run.py in another shell
# then in another terminal:
curl -i http://127.0.0.1:5000/login
```

## Docker

```bash
docker build -t data-analytics-dashboard:p12 .
docker compose up --build
```
