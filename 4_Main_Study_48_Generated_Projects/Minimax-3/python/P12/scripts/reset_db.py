"""Reset and re-seed the local SQLite database."""
from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from scripts.init_db import init_database  # noqa: E402

if __name__ == "__main__":
    init_database(reset=True)
    print("Database reset and seeded successfully.")
