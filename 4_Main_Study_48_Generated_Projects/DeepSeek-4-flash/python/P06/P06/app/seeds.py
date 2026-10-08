"""Deterministic seed fixtures for every actor, role, relationship and
workflow state required by the acceptance criteria.

Run with:  flask seed   (or)   flask reset-db
"""

from __future__ import annotations

import datetime as dt

from .adapters import mailbox, storage
from .extensions import db
from .models import (
    DELIVERY_READ,
    DELIVERY_SENT,
    ROLE_ADMIN,
    ROLE_MEMBER,
    ROLE_OWNER,
    VISIBILITY_PRIVATE,
    VISIBILITY_PUBLIC,
    Attachment,
    AuditEvent,
    AuthSession,
    Channel,
    ChannelMember,
    ConnectionEvent,
    DeliveryRecord,
    DirectMessage,
    DirectMessageThread,
    ErrorRecord,
    FrontendEvent,
    Invitation,
    LinkPreview,
    Membership,
    Message,
    SavedSearch,
    StoredFile,
    Topic,
    User,
    Workspace,
    utcnow,
)


def _t(day: int, hour: int, minute: int = 0, second: int = 0) -> dt.datetime:
    return dt.datetime(2026, 1, day, hour, minute, second)


def _seed_users() -> dict[str, User]:
    data = [
        ("owner", "owner@example.com", "Workspace Owner", ROLE_OWNER),
        ("admin", "admin@example.com", "Admin User", ROLE_ADMIN),
        ("member", "member@example.com", "Megan Member", ROLE_MEMBER),
        ("alice", "alice@example.com", "Alice Anderson", ROLE_MEMBER),
        ("bob", "bob@example.com", "Bob Baker", ROLE_MEMBER),
        ("carol", "carol@example.com", "Carol Chen", ROLE_MEMBER),
    ]
    users = {}
    for username, email, full_name, role in data:
        user = User(
            username=username,
            email=email,
            full_name=full_name,
            role=role,
            status="active",
            created_at=_t(1, 8, 0),
        )
        user.set_password("password123")
        db.session.add(user)
        users[username] = user
    db.session.flush()
    return users


def _seed_workspace(users: dict[str, User]) -> Workspace:
    workspace = Workspace(
        name="Acme Corp",
        slug="acme",
        description="Official workspace for the Acme Corp team.",
        owner_id=users["owner"].id,
        created_at=_t(1, 9, 0),
    )
    db.session.add(workspace)
    db.session.flush()

    memberships = [
        (users["owner"], ROLE_OWNER, _t(1, 9, 0)),
        (users["admin"], ROLE_ADMIN, _t(1, 9, 5)),
        (users["member"], ROLE_MEMBER, _t(1, 9, 10)),
        (users["alice"], ROLE_MEMBER, _t(1, 9, 15)),
        (users["bob"], ROLE_MEMBER, _t(1, 9, 20)),
    ]
    for user, role, joined in memberships:
        db.session.add(
            Membership(
                workspace_id=workspace.id,
                user_id=user.id,
                role=role,
                joined_at=joined,
            )
        )
    db.session.flush()
    return workspace


def _seed_channels(workspace: Workspace, users: dict[str, User]) -> dict[str, Channel]:
    specs = [
        ("general", "General discussion for everyone", VISIBILITY_PUBLIC, users["owner"], False),
        ("announcements", "Official team announcements", VISIBILITY_PUBLIC, users["admin"], False),
        ("random", "Off-topic fun", VISIBILITY_PUBLIC, users["member"], False),
        ("secret", "Private strategy room", VISIBILITY_PRIVATE, users["owner"], False),
        ("archive-me", "Archived example channel", VISIBILITY_PUBLIC, users["owner"], True),
    ]
    channels = {}
    for index, (name, description, visibility, creator, archived) in enumerate(specs):
        channel = Channel(
            workspace_id=workspace.id,
            name=name,
            description=description,
            visibility=visibility,
            created_by_id=creator.id,
            archived=archived,
            created_at=_t(1, 10, index),
        )
        db.session.add(channel)
        channels[name] = channel
    db.session.flush()

    db.session.add(
        ChannelMember(channel_id=channels["secret"].id, user_id=users["alice"].id, added_at=_t(1, 10, 30))
    )
    db.session.add(
        ChannelMember(channel_id=channels["secret"].id, user_id=users["bob"].id, added_at=_t(1, 10, 31))
    )
    db.session.flush()
    return channels


def _seed_topics(channels: dict[str, Channel], users: dict[str, User]) -> dict[str, Topic]:
    topics = {
        "welcome": Topic(
            channel_id=channels["general"].id,
            name="welcome",
            created_by_id=users["owner"].id,
            created_at=_t(1, 11, 0),
        ),
        "releases": Topic(
            channel_id=channels["announcements"].id,
            name="releases",
            created_by_id=users["admin"].id,
            created_at=_t(1, 11, 5),
        ),
        "random-talk": Topic(
            channel_id=channels["random"].id,
            name="random-talk",
            created_by_id=users["member"].id,
            created_at=_t(1, 11, 10),
        ),
    }
    db.session.add_all(topics.values())
    db.session.flush()
    return topics


def _seed_messages(
    workspace: Workspace, channels: dict[str, Channel], topics: dict[str, Topic], users: dict[str, User]
) -> dict[int, Message]:
    seed_msgs = [
        (channels["general"], topics["welcome"], users["owner"], "Welcome to the Acme workspace! Say hi in this topic.", _t(1, 12, 0), "seed-msg-1"),
        (channels["general"], topics["welcome"], users["member"], "Hi everyone! Glad to be here.", _t(1, 12, 5), "seed-msg-2"),
        (channels["general"], topics["welcome"], users["alice"], "Hello from Alice. Ready to collaborate.", _t(1, 12, 10), "seed-msg-3"),
        (channels["general"], topics["welcome"], users["bob"], "Hey team! The onboarding docs look great.", _t(1, 12, 15), "seed-msg-4"),
        (channels["announcements"], topics["releases"], users["admin"], "Release 2.0 ships Friday. Please review the changelog.", _t(1, 12, 30), "seed-msg-5"),
        (channels["announcements"], topics["releases"], users["owner"], "Thanks! Please share feedback in the releases topic.", _t(1, 12, 32), "seed-msg-6"),
        (channels["random"], topics["random-talk"], users["member"], "Who is joining the team lunch on Friday?", _t(1, 12, 40), "seed-msg-7"),
        (channels["random"], topics["random-talk"], users["alice"], "Count me in! https://example.com has a great menu.", _t(1, 12, 42), "seed-msg-8"),
        (channels["secret"], None, users["alice"], "Draft proposal for the new pricing page is in the shared folder.", _t(1, 12, 50), "seed-msg-9"),
        (channels["secret"], None, users["bob"], "Received, I will review it before the board meeting.", _t(1, 12, 52), "seed-msg-10"),
        (channels["general"], topics["welcome"], users["owner"], "Remember the launch plan: website at https://www.python.org", _t(1, 13, 0), "seed-msg-11"),
    ]
    messages = {}
    for channel, topic, sender, body, created, client_id in seed_msgs:
        message = Message(
            workspace_id=workspace.id,
            channel_id=channel.id,
            topic_id=topic.id if topic else None,
            sender_id=sender.id,
            body=body,
            client_msg_id=client_id,
            created_at=created,
            updated_at=created,
        )
        db.session.add(message)
        messages[message.client_msg_id] = message
    db.session.flush()
    return messages


def _seed_dms(users: dict[str, User]) -> dict:
    thread = DirectMessageThread(
        user_a_id=users["alice"].id,
        user_b_id=users["bob"].id,
        created_at=_t(1, 14, 0),
    )
    db.session.add(thread)
    db.session.flush()
    dms = [
        DirectMessage(
            thread_id=thread.id, sender_id=users["alice"].id,
            body="Hi Bob, are you free to review the draft?", client_msg_id="seed-dm-1",
            created_at=_t(1, 14, 1), updated_at=_t(1, 14, 1), delivered=True,
        ),
        DirectMessage(
            thread_id=thread.id, sender_id=users["bob"].id,
            body="Sure, send it over and I will look this afternoon.", client_msg_id="seed-dm-2",
            created_at=_t(1, 14, 2), updated_at=_t(1, 14, 2), delivered=True,
        ),
    ]
    db.session.add_all(dms)
    db.session.flush()
    return {"thread": thread, "dms": dms}


def _seed_attachments(
    workspace: Workspace, channels: dict[str, Channel], messages: dict[int, Message], users: dict[str, User]
) -> None:
    content = b"Hello! This is a seeded welcome attachment for the Acme workspace."
    result = storage.save("welcome.txt", content)
    stored = StoredFile(
        stored_name=result["stored_name"],
        original_name="welcome.txt",
        sha256=result["sha256"],
        size=result["size"],
        content_type="text/plain",
    )
    db.session.add(stored)
    db.session.flush()
    attachment = Attachment(
        owner_id=users["owner"].id,
        stored_file_id=stored.id,
        filename="welcome.txt",
        content_type="text/plain",
        size=result["size"],
        message_id=messages["seed-msg-1"].id,
        is_private=False,
        created_at=_t(1, 15, 0),
    )
    db.session.add(attachment)
    db.session.flush()

    private_content = b"Private notes only for the owner."
    private_result = storage.save("private-notes.txt", private_content)
    private_stored = StoredFile(
        stored_name=private_result["stored_name"],
        original_name="private-notes.txt",
        sha256=private_result["sha256"],
        size=private_result["size"],
        content_type="text/plain",
    )
    db.session.add(private_stored)
    db.session.flush()
    private_attachment = Attachment(
        owner_id=users["owner"].id,
        stored_file_id=private_stored.id,
        filename="private-notes.txt",
        content_type="text/plain",
        size=private_result["size"],
        is_private=True,
        created_at=_t(1, 15, 5),
    )
    db.session.add(private_attachment)
    db.session.flush()


def _seed_link_previews(users: dict[str, User]) -> None:
    previews = [
        LinkPreview(
            url="https://example.com",
            title="Example Domain",
            description="This domain is for use in illustrative examples in documents.",
            site_name="example.com",
            created_by_id=users["alice"].id,
            created_at=_t(1, 12, 42),
            fetched_at=_t(1, 12, 42),
        ),
        LinkPreview(
            url="https://www.python.org",
            title="Welcome to Python.org",
            description="The official home of the Python Programming Language.",
            site_name="python.org",
            image_url="https://www.python.org/static/img/python-logo.png",
            created_by_id=users["owner"].id,
            created_at=_t(1, 13, 0),
            fetched_at=_t(1, 13, 0),
        ),
    ]
    db.session.add_all(previews)
    db.session.flush()


def _seed_invitations(workspace: Workspace, users: dict[str, User]) -> None:
    invitation = Invitation(
        workspace_id=workspace.id,
        invited_by_id=users["owner"].id,
        email="carol@example.com",
        role=ROLE_MEMBER,
        token="seed-invitation-carol-001",
        status="pending",
        expires_at=utcnow() + dt.timedelta(days=7),
        created_at=_t(1, 16, 0),
    )
    db.session.add(invitation)
    db.session.flush()
    mailbox.send(
        "carol@example.com",
        f"You are invited to join {workspace.name}",
        "Seed invitation for carol@example.com.\nAccept it at /invitations/seed-invitation-carol-001",
    )


def _seed_delivery(users: dict[str, User], messages: dict[int, Message]) -> None:
    records = [
        DeliveryRecord(
            user_id=users["owner"].id,
            message_id=messages["seed-msg-1"].id,
            status=DELIVERY_READ,
            created_at=_t(1, 12, 1),
            updated_at=_t(1, 12, 2),
        ),
        DeliveryRecord(
            user_id=users["member"].id,
            message_id=messages["seed-msg-2"].id,
            status=DELIVERY_READ,
            created_at=_t(1, 12, 6),
            updated_at=_t(1, 12, 7),
        ),
        DeliveryRecord(
            user_id=users["alice"].id,
            message_id=messages["seed-msg-8"].id,
            status=DELIVERY_SENT,
            created_at=_t(1, 12, 42),
            updated_at=_t(1, 12, 42),
        ),
    ]
    db.session.add_all(records)
    db.session.flush()


def _seed_audit(workspace: Workspace, users: dict[str, User], channels: dict[str, Channel]) -> None:
    events = [
        AuditEvent(
            workspace_id=workspace.id, actor_id=users["owner"].id,
            action="workspace_created", target_type="workspace", target_id=str(workspace.id),
            details={"name": workspace.name}, created_at=_t(1, 9, 1),
        ),
        AuditEvent(
            workspace_id=workspace.id, actor_id=users["admin"].id,
            action="channel_configured", target_type="channel", target_id=str(channels["announcements"].id),
            details={"description": "Official team announcements"}, created_at=_t(1, 10, 5),
        ),
        AuditEvent(
            workspace_id=workspace.id, actor_id=users["owner"].id,
            action="invitation_created", target_type="invitation",
            target_id="seed-invitation-carol-001", details={"email": "carol@example.com"},
            created_at=_t(1, 16, 1),
        ),
        AuditEvent(
            workspace_id=workspace.id, actor_id=users["owner"].id,
            action="channel_archived", target_type="channel", target_id=str(channels["archive-me"].id),
            details={}, created_at=_t(1, 17, 0),
        ),
    ]
    db.session.add_all(events)
    db.session.flush()


def _seed_sessions(users: dict[str, User]) -> None:
    sessions = [
        AuthSession(
            token="seed-session-owner-001",
            user_id=users["owner"].id,
            created_at=_t(1, 9, 2),
            expires_at=utcnow() + dt.timedelta(days=7),
            ip_address="127.0.0.1",
            user_agent="seed-client",
        ),
        AuthSession(
            token="seed-session-member-001",
            user_id=users["member"].id,
            created_at=_t(1, 9, 12),
            expires_at=utcnow() + dt.timedelta(days=7),
            ip_address="127.0.0.1",
            user_agent="seed-client",
        ),
    ]
    db.session.add_all(sessions)
    db.session.flush()


def _seed_misc(workspace: Workspace, users: dict[str, User]) -> None:
    db.session.add_all(
        [
            ConnectionEvent(
                user_id=users["member"].id, session_id="seed-session-member-001",
                event_type="connect", payload={"user": users["member"].id}, created_at=_t(1, 9, 13),
            ),
            ErrorRecord(
                user_id=users["member"].id, code="validation_error",
                message="Message body is required", path="/api/chat/real_time_messaging",
                details={"field": "body"}, created_at=_t(1, 12, 20),
            ),
            SavedSearch(
                user_id=users["member"].id, name="Launch plan", query="launch", created_at=_t(1, 13, 5),
            ),
            FrontendEvent(
                user_id=users["member"].id, event_type="empty_state",
                payload={"view": "messages", "message": "No messages yet"},
                acknowledged=False, created_at=_t(1, 13, 6),
            ),
        ]
    )
    db.session.flush()


def seed() -> None:
    users = _seed_users()
    workspace = _seed_workspace(users)
    channels = _seed_channels(workspace, users)
    topics = _seed_topics(channels, users)
    messages = _seed_messages(workspace, channels, topics, users)
    _seed_dms(users)
    _seed_attachments(workspace, channels, messages, users)
    _seed_link_previews(users)
    _seed_invitations(workspace, users)
    _seed_delivery(users, messages)
    _seed_audit(workspace, users, channels)
    _seed_sessions(users)
    _seed_misc(workspace, users)
    db.session.commit()
