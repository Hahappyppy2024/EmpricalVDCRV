import os
import sqlite3
from flask import g

DB_PATH = os.path.join(os.path.dirname(__file__), "data", "app.db")

def get_db():
    if "db" not in g:
        os.makedirs(os.path.dirname(DB_PATH), exist_ok=True)
        g.db = sqlite3.connect(DB_PATH)
        g.db.row_factory = sqlite3.Row
        g.db.execute("PRAGMA foreign_keys = ON;")
    return g.db

def close_db(e=None):
    db = g.pop("db", None)
    if db is not None:
        db.close()
