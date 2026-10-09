"""Flask extension singletons kept here to avoid circular imports."""
from __future__ import annotations

from flask_sqlalchemy import SQLAlchemy
from flask_sock import Sock

db = SQLAlchemy()
sock = Sock()
