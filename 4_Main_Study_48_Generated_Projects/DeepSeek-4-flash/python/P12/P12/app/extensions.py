"""SQLAlchemy engine/session bootstrap and shared declarative base."""
from sqlalchemy import create_engine
from sqlalchemy.orm import declarative_base, sessionmaker

engine = None
SessionLocal = None
Base = declarative_base()


def init_db_engine(database_path: str) -> None:
    """Create the engine and session factory for the given SQLite file."""
    global engine, SessionLocal
    engine = create_engine(
        f"sqlite:///{database_path}",
        connect_args={"check_same_thread": False},
    )
    SessionLocal = sessionmaker(bind=engine, autoflush=False, expire_on_commit=False)
