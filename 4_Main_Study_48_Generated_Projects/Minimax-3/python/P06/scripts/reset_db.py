from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from app.db import Base, _engine, init_engine
from app.models import (
    Attachment,
    AuditEvent,
    Channel,
    ConnectionState,
    DirectMessage,
    ErrorLog,
    FrontendState,
    Invitation,
    LinkPreview,
    Membership,
    Message,
    SessionRecord,
    User,
    Workspace,
)


def main() -> None:
    init_engine()
    Base.metadata.drop_all(bind=_engine)
    Base.metadata.create_all(bind=_engine)
    print(f"Database at {_engine.url} reset and tables created.")


if __name__ == "__main__":
    main()
