from __future__ import annotations

import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from werkzeug.security import generate_password_hash

from app.db import Base, SessionLocal, _engine, init_engine
from app.models import (
    Channel,
    Invitation,
    Membership,
    Message,
    User,
    Workspace,
    DirectMessage,
    Attachment,
    LinkPreview,
)


def main() -> None:
    init_engine()
    Base.metadata.drop_all(_engine)
    Base.metadata.create_all(_engine)

    with SessionLocal() as session:
        admin = _create_user(
            session,
            email="admin@example.com",
            username="admin",
            display_name="Site Administrator",
            password="AdminPass123!",
            is_admin=True,
        )
        alice = _create_user(
            session,
            email="alice@example.com",
            username="alice",
            display_name="Alice Anderson",
            password="AlicePass123!",
        )
        bob = _create_user(
            session,
            email="bob@example.com",
            username="bob",
            display_name="Bob Brown",
            password="BobPass123!",
        )
        carol = _create_user(
            session,
            email="carol@example.com",
            username="carol",
            display_name="Carol Carter",
            password="CarolPass123!",
        )

        ws = Workspace(
            name="acme",
            description="Workspace aligned with the synthetic Zulip-style benchmark.",
            owner_id=admin.id,
        )
        session.add(ws)
        session.flush()

        general = Channel(
            workspace_id=ws.id,
            name="general",
            description="Default team chat channel.",
            is_private=False,
            topic="general",
        )
        random = Channel(
            workspace_id=ws.id,
            name="random",
            description="Off-topic chatter.",
            is_private=False,
            topic="random",
        )
        engineering = Channel(
            workspace_id=ws.id,
            name="engineering",
            description="Engineering coordination.",
            is_private=True,
            topic="engineering",
        )
        session.add_all([general, random, engineering])
        session.flush()

        session.add_all(
            [
                Membership(user_id=admin.id, workspace_id=ws.id, role="owner", invited_by=admin.id),
                Membership(user_id=alice.id, workspace_id=ws.id, role="admin", invited_by=admin.id),
                Membership(user_id=bob.id, workspace_id=ws.id, role="member", invited_by=admin.id),
                Membership(user_id=carol.id, workspace_id=ws.id, role="guest", invited_by=admin.id),
            ]
        )

        now = datetime.now(timezone.utc)
        msg1 = Message(
            channel_id=general.id,
            sender_id=alice.id,
            body="Welcome to the team chat, everyone!",
            topic="general",
            delivery_state="delivered",
            created_at=now - timedelta(hours=2),
            updated_at=now - timedelta(hours=2),
        )
        msg2 = Message(
            channel_id=general.id,
            sender_id=bob.id,
            body="Glad to be here. Looking forward to the project demo.",
            topic="general",
            delivery_state="delivered",
            created_at=now - timedelta(hours=1, minutes=45),
            updated_at=now - timedelta(hours=1, minutes=45),
        )
        msg3 = Message(
            channel_id=general.id,
            sender_id=carol.id,
            body="Hi folks, joining from the customer success team.",
            topic="general",
            delivery_state="delivered",
            created_at=now - timedelta(hours=1),
            updated_at=now - timedelta(hours=1),
        )
        msg4 = Message(
            channel_id=engineering.id,
            sender_id=alice.id,
            body="Standup at 10:00 in the engineering channel.",
            topic="engineering",
            delivery_state="delivered",
            created_at=now - timedelta(minutes=30),
            updated_at=now - timedelta(minutes=30),
        )
        session.add_all([msg1, msg2, msg3, msg4])
        session.flush()

        dm_alice_bob = DirectMessage(
            thread_key=f"dm:{min(alice.id, bob.id)}-{max(alice.id, bob.id)}",
            sender_id=alice.id,
            recipient_id=bob.id,
            body="Bob, can you review the migration plan I shared?",
            read=True,
        )
        dm_bob_alice = DirectMessage(
            thread_key=f"dm:{min(alice.id, bob.id)}-{max(alice.id, bob.id)}",
            sender_id=bob.id,
            recipient_id=alice.id,
            body="Yes, will review this afternoon.",
            read=True,
        )
        dm_alice_carol = DirectMessage(
            thread_key=f"dm:{min(alice.id, carol.id)}-{max(alice.id, carol.id)}",
            sender_id=alice.id,
            recipient_id=carol.id,
            body="Welcome aboard Carol!",
            read=False,
        )
        session.add_all([dm_alice_bob, dm_bob_alice, dm_alice_carol])

        invitation = Invitation(
            workspace_id=ws.id,
            email="dave@example.com",
            token="seed-invite-token-dave",
            proposed_role="member",
            status="pending",
            invited_by=admin.id,
        )
        session.add(invitation)

        preview = LinkPreview(
            url="https://example.com/",
            title="Example Domain",
            description="Reserved for documentation examples.",
            image_url=None,
            site_name="Example",
            requested_by=alice.id,
        )
        session.add(preview)

        attachment = Attachment(
            stored_filename="seed_readme.txt",
            original_filename="welcome.txt",
            content_type="text/plain",
            size_bytes=120,
            owner_id=alice.id,
            message_id=msg1.id,
        )
        session.add(attachment)

        session.commit()

    storage = Path(ROOT / "storage")
    storage.mkdir(parents=True, exist_ok=True)
    (storage / "seed_readme.txt").write_text(
        "Welcome to the real-time team chat sample attachment.", encoding="utf-8"
    )

    print("Seed complete.")
    print("Seed accounts (password noted after username):")
    print("  admin@example.com / admin (AdminPass123!)")
    print("  alice@example.com / alice (AlicePass123!)")
    print("  bob@example.com / bob (BobPass123!)")
    print("  carol@example.com / carol (CarolPass123!)")


def _create_user(session, *, email, username, display_name, password, is_admin=False):
    user = User(
        email=email.lower(),
        username=username,
        display_name=display_name,
        password_hash=generate_password_hash(password),
        is_admin=is_admin,
        avatar_seed=username,
    )
    session.add(user)
    session.flush()
    return user


if __name__ == "__main__":
    main()
