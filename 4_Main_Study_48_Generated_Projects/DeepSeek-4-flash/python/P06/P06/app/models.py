"""SQLAlchemy 2 models for the Real-Time Team Chat System.

Every persistent entity referenced by the use-case data contracts is defined
here: User, AuthSession, Workspace, Channel, Membership, ChannelMember,
Invitation, Topic, Message, DirectMessageThread, DirectMessage, StoredFile,
Attachment, LinkPreview, AuditEvent, DeliveryRecord, ConnectionEvent,
ErrorRecord, SavedSearch and FrontendEvent.
"""

from __future__ import annotations

import datetime as dt

from sqlalchemy import (
    JSON,
    Boolean,
    DateTime,
    ForeignKey,
    Index,
    String,
    Text,
    UniqueConstraint,
)
from sqlalchemy.orm import Mapped, mapped_column, relationship
from werkzeug.security import check_password_hash, generate_password_hash

from .extensions import db

ROLE_OWNER = "owner"
ROLE_ADMIN = "admin"
ROLE_MEMBER = "member"

VISIBILITY_PUBLIC = "public"
VISIBILITY_PRIVATE = "private"

INVITATION_PENDING = "pending"
INVITATION_ACCEPTED = "accepted"
INVITATION_DECLINED = "declined"
INVITATION_REVOKED = "revoked"

DELIVERY_SENT = "sent"
DELIVERY_DELIVERED = "delivered"
DELIVERY_READ = "read"
DELIVERY_FAILED = "failed"

RESET_TTL_SECONDS = 3600


def utcnow() -> dt.datetime:
    return dt.datetime.now(dt.timezone.utc).replace(tzinfo=None)


def iso(dt_value: dt.datetime | None) -> str | None:
    if dt_value is None:
        return None
    return dt_value.isoformat() + "Z"


_hash_cache: dict[str, str] = {}


def password_hasher(raw: str) -> str:
    """Deterministic within a process: same raw password reuses the same hash."""
    if raw not in _hash_cache:
        _hash_cache[raw] = generate_password_hash(
            raw, method="pbkdf2:sha256:260000"
        )
    return _hash_cache[raw]


class User(db.Model):
    __tablename__ = "users"

    id: Mapped[int] = mapped_column(primary_key=True)
    username: Mapped[str] = mapped_column(String(80), unique=True, index=True)
    email: Mapped[str] = mapped_column(String(255), unique=True, index=True)
    full_name: Mapped[str] = mapped_column(String(120), default="")
    password_hash: Mapped[str] = mapped_column(String(255))
    role: Mapped[str] = mapped_column(String(20), default=ROLE_MEMBER)
    status: Mapped[str] = mapped_column(String(20), default="active")
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)
    reset_token: Mapped[str | None] = mapped_column(String(255), index=True)
    reset_expires_at: Mapped[dt.datetime | None] = mapped_column(DateTime)

    sessions: Mapped[list[AuthSession]] = relationship(
        back_populates="user", cascade="all, delete-orphan"
    )

    def set_password(self, raw: str) -> None:
        self.password_hash = password_hasher(raw)

    def check_password(self, raw: str) -> bool:
        return check_password_hash(self.password_hash, raw)

    @property
    def display_name(self) -> str:
        return self.full_name or self.username

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "username": self.username,
            "full_name": self.full_name,
            "display_name": self.display_name,
            "email": self.email,
            "role": self.role,
            "status": self.status,
            "created_at": iso(self.created_at),
        }


class AuthSession(db.Model):
    """Persistent server-side session identified by an HTTP-only cookie."""

    __tablename__ = "auth_sessions"

    id: Mapped[int] = mapped_column(primary_key=True)
    token: Mapped[str] = mapped_column(String(64), unique=True, index=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"), index=True)
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)
    expires_at: Mapped[dt.datetime] = mapped_column(DateTime)
    ip_address: Mapped[str] = mapped_column(String(64), default="")
    user_agent: Mapped[str] = mapped_column(String(255), default="")
    active: Mapped[bool] = mapped_column(Boolean, default=True)

    user: Mapped[User] = relationship(back_populates="sessions")


class Workspace(db.Model):
    __tablename__ = "workspaces"

    id: Mapped[int] = mapped_column(primary_key=True)
    name: Mapped[str] = mapped_column(String(120))
    slug: Mapped[str] = mapped_column(String(80), unique=True, index=True)
    description: Mapped[str] = mapped_column(Text, default="")
    owner_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    archived: Mapped[bool] = mapped_column(Boolean, default=False)
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    owner: Mapped[User] = relationship(foreign_keys=[owner_id])
    channels: Mapped[list[Channel]] = relationship(
        back_populates="workspace", cascade="all, delete-orphan"
    )
    memberships: Mapped[list[Membership]] = relationship(
        back_populates="workspace", cascade="all, delete-orphan"
    )

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "name": self.name,
            "slug": self.slug,
            "description": self.description,
            "owner_id": self.owner_id,
            "archived": self.archived,
            "created_at": iso(self.created_at),
        }


class Channel(db.Model):
    __tablename__ = "channels"
    __table_args__ = (
        UniqueConstraint("workspace_id", "name", name="uq_channel_workspace_name"),
        Index("ix_channel_workspace", "workspace_id"),
    )

    id: Mapped[int] = mapped_column(primary_key=True)
    workspace_id: Mapped[int] = mapped_column(ForeignKey("workspaces.id"))
    name: Mapped[str] = mapped_column(String(80))
    description: Mapped[str] = mapped_column(Text, default="")
    visibility: Mapped[str] = mapped_column(
        String(20), default=VISIBILITY_PUBLIC
    )
    created_by_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    archived: Mapped[bool] = mapped_column(Boolean, default=False)
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    workspace: Mapped[Workspace] = relationship(back_populates="channels")
    topics: Mapped[list[Topic]] = relationship(
        back_populates="channel", cascade="all, delete-orphan"
    )

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "workspace_id": self.workspace_id,
            "name": self.name,
            "description": self.description,
            "visibility": self.visibility,
            "created_by_id": self.created_by_id,
            "archived": self.archived,
            "created_at": iso(self.created_at),
        }


class Membership(db.Model):
    __tablename__ = "memberships"
    __table_args__ = (
        UniqueConstraint("workspace_id", "user_id", name="uq_membership_ws_user"),
        Index("ix_membership_user", "user_id"),
    )

    id: Mapped[int] = mapped_column(primary_key=True)
    workspace_id: Mapped[int] = mapped_column(ForeignKey("workspaces.id"))
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    role: Mapped[str] = mapped_column(String(20), default=ROLE_MEMBER)
    joined_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    workspace: Mapped[Workspace] = relationship(back_populates="memberships")
    user: Mapped[User] = relationship()

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "workspace_id": self.workspace_id,
            "user_id": self.user_id,
            "role": self.role,
            "joined_at": iso(self.joined_at),
        }


class ChannelMember(db.Model):
    """Membership of a user in a private channel."""

    __tablename__ = "channel_members"
    __table_args__ = (
        UniqueConstraint("channel_id", "user_id", name="uq_channel_member"),
    )

    id: Mapped[int] = mapped_column(primary_key=True)
    channel_id: Mapped[int] = mapped_column(ForeignKey("channels.id"))
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    added_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)


class Invitation(db.Model):
    __tablename__ = "invitations"
    __table_args__ = (Index("ix_invitation_workspace", "workspace_id"),)

    id: Mapped[int] = mapped_column(primary_key=True)
    workspace_id: Mapped[int] = mapped_column(ForeignKey("workspaces.id"))
    invited_by_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    email: Mapped[str] = mapped_column(String(255))
    role: Mapped[str] = mapped_column(String(20), default=ROLE_MEMBER)
    token: Mapped[str] = mapped_column(String(64), unique=True, index=True)
    status: Mapped[str] = mapped_column(
        String(20), default=INVITATION_PENDING
    )
    expires_at: Mapped[dt.datetime] = mapped_column(DateTime)
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    workspace: Mapped[Workspace] = relationship()
    invited_by: Mapped[User] = relationship(foreign_keys=[invited_by_id])

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "workspace_id": self.workspace_id,
            "workspace_name": self.workspace.name if self.workspace else None,
            "email": self.email,
            "role": self.role,
            "token": self.token,
            "status": self.status,
            "expires_at": iso(self.expires_at),
            "created_at": iso(self.created_at),
        }


class Topic(db.Model):
    __tablename__ = "topics"
    __table_args__ = (
        UniqueConstraint("channel_id", "name", name="uq_topic_channel_name"),
    )

    id: Mapped[int] = mapped_column(primary_key=True)
    channel_id: Mapped[int] = mapped_column(ForeignKey("channels.id"))
    name: Mapped[str] = mapped_column(String(120))
    created_by_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    channel: Mapped[Channel] = relationship(back_populates="topics")

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "channel_id": self.channel_id,
            "name": self.name,
            "created_at": iso(self.created_at),
        }


class Message(db.Model):
    """Channel message (public/private channel, optionally on a topic)."""

    __tablename__ = "messages"
    __table_args__ = (
        UniqueConstraint("sender_id", "client_msg_id", name="uq_message_client"),
        Index("ix_message_channel_created", "channel_id", "created_at"),
    )

    id: Mapped[int] = mapped_column(primary_key=True)
    workspace_id: Mapped[int] = mapped_column(ForeignKey("workspaces.id"))
    channel_id: Mapped[int] = mapped_column(ForeignKey("channels.id"))
    topic_id: Mapped[int | None] = mapped_column(ForeignKey("topics.id"))
    sender_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    body: Mapped[str] = mapped_column(Text)
    client_msg_id: Mapped[str | None] = mapped_column(String(120))
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)
    updated_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)
    is_edited: Mapped[bool] = mapped_column(Boolean, default=False)
    is_deleted: Mapped[bool] = mapped_column(Boolean, default=False)

    channel: Mapped[Channel] = relationship()
    topic: Mapped[Topic | None] = relationship()
    sender: Mapped[User] = relationship()

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "workspace_id": self.workspace_id,
            "channel_id": self.channel_id,
            "topic_id": self.topic_id,
            "topic": self.topic.name if self.topic else "general",
            "sender": self.sender.to_dict() if self.sender else None,
            "body": self.body if not self.is_deleted else "",
            "is_deleted": self.is_deleted,
            "is_edited": self.is_edited,
            "client_msg_id": self.client_msg_id,
            "created_at": iso(self.created_at),
            "updated_at": iso(self.updated_at),
        }


class DirectMessageThread(db.Model):
    __tablename__ = "direct_message_threads"
    __table_args__ = (
        UniqueConstraint("user_a_id", "user_b_id", name="uq_dm_thread_pair"),
    )

    id: Mapped[int] = mapped_column(primary_key=True)
    user_a_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    user_b_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "user_a_id": self.user_a_id,
            "user_b_id": self.user_b_id,
            "created_at": iso(self.created_at),
        }


class DirectMessage(db.Model):
    __tablename__ = "direct_messages"
    __table_args__ = (
        UniqueConstraint("sender_id", "client_msg_id", name="uq_dm_client_id"),
        Index("ix_dm_thread_created", "thread_id", "created_at"),
    )

    id: Mapped[int] = mapped_column(primary_key=True)
    thread_id: Mapped[int] = mapped_column(ForeignKey("direct_message_threads.id"))
    sender_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    body: Mapped[str] = mapped_column(Text)
    client_msg_id: Mapped[str | None] = mapped_column(String(120))
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)
    updated_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)
    is_edited: Mapped[bool] = mapped_column(Boolean, default=False)
    is_deleted: Mapped[bool] = mapped_column(Boolean, default=False)
    delivered: Mapped[bool] = mapped_column(Boolean, default=False)
    read_at: Mapped[dt.datetime | None] = mapped_column(DateTime)

    thread: Mapped[DirectMessageThread] = relationship()
    sender: Mapped[User] = relationship()

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "thread_id": self.thread_id,
            "sender": self.sender.to_dict() if self.sender else None,
            "body": self.body if not self.is_deleted else "",
            "is_deleted": self.is_deleted,
            "is_edited": self.is_edited,
            "delivered": self.delivered,
            "read_at": iso(self.read_at),
            "created_at": iso(self.created_at),
            "updated_at": iso(self.updated_at),
        }


class StoredFile(db.Model):
    __tablename__ = "stored_files"

    id: Mapped[int] = mapped_column(primary_key=True)
    stored_name: Mapped[str] = mapped_column(String(160), unique=True)
    original_name: Mapped[str] = mapped_column(String(255))
    sha256: Mapped[str] = mapped_column(String(64))
    size: Mapped[int] = mapped_column()
    content_type: Mapped[str] = mapped_column(String(120), default="application/octet-stream")
    backend: Mapped[str] = mapped_column(String(20), default="local")
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "stored_name": self.stored_name,
            "original_name": self.original_name,
            "sha256": self.sha256,
            "size": self.size,
            "content_type": self.content_type,
            "backend": self.backend,
            "created_at": iso(self.created_at),
        }


class Attachment(db.Model):
    __tablename__ = "attachments"
    __table_args__ = (Index("ix_attachment_owner", "owner_id"),)

    id: Mapped[int] = mapped_column(primary_key=True)
    owner_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    message_id: Mapped[int | None] = mapped_column(ForeignKey("messages.id"))
    direct_message_id: Mapped[int | None] = mapped_column(ForeignKey("direct_messages.id"))
    stored_file_id: Mapped[int] = mapped_column(ForeignKey("stored_files.id"))
    filename: Mapped[str] = mapped_column(String(255))
    content_type: Mapped[str] = mapped_column(String(120))
    size: Mapped[int] = mapped_column()
    is_private: Mapped[bool] = mapped_column(Boolean, default=False)
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    owner: Mapped[User] = relationship()
    stored_file: Mapped[StoredFile] = relationship()

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "owner_id": self.owner_id,
            "owner": self.owner.to_dict() if self.owner else None,
            "message_id": self.message_id,
            "direct_message_id": self.direct_message_id,
            "stored_file_id": self.stored_file_id,
            "filename": self.filename,
            "content_type": self.content_type,
            "size": self.size,
            "is_private": self.is_private,
            "created_at": iso(self.created_at),
        }


class LinkPreview(db.Model):
    __tablename__ = "link_previews"

    id: Mapped[int] = mapped_column(primary_key=True)
    url: Mapped[str] = mapped_column(String(2048), unique=True, index=True)
    title: Mapped[str] = mapped_column(String(500), default="")
    description: Mapped[str] = mapped_column(Text, default="")
    site_name: Mapped[str] = mapped_column(String(255), default="")
    image_url: Mapped[str | None] = mapped_column(String(2048))
    created_by_id: Mapped[int | None] = mapped_column(ForeignKey("users.id"))
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)
    fetched_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    created_by: Mapped[User | None] = relationship()

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "url": self.url,
            "title": self.title,
            "description": self.description,
            "site_name": self.site_name,
            "image_url": self.image_url,
            "created_by": self.created_by.to_dict() if self.created_by else None,
            "created_at": iso(self.created_at),
            "fetched_at": iso(self.fetched_at),
        }


class AuditEvent(db.Model):
    __tablename__ = "audit_events"
    __table_args__ = (Index("ix_audit_workspace", "workspace_id"),)

    id: Mapped[int] = mapped_column(primary_key=True)
    workspace_id: Mapped[int | None] = mapped_column(ForeignKey("workspaces.id"))
    actor_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    action: Mapped[str] = mapped_column(String(60))
    target_type: Mapped[str] = mapped_column(String(60), default="")
    target_id: Mapped[str] = mapped_column(String(120), default="")
    details: Mapped[dict] = mapped_column(JSON, default=dict)
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    actor: Mapped[User] = relationship(foreign_keys=[actor_id])

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "workspace_id": self.workspace_id,
            "actor": self.actor.to_dict() if self.actor else None,
            "action": self.action,
            "target_type": self.target_type,
            "target_id": self.target_id,
            "details": self.details,
            "created_at": iso(self.created_at),
        }


class DeliveryRecord(db.Model):
    __tablename__ = "delivery_records"
    __table_args__ = (Index("ix_delivery_user", "user_id"),)

    id: Mapped[int] = mapped_column(primary_key=True)
    message_id: Mapped[int | None] = mapped_column(ForeignKey("messages.id"))
    direct_message_id: Mapped[int | None] = mapped_column(ForeignKey("direct_messages.id"))
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    status: Mapped[str] = mapped_column(String(20), default=DELIVERY_SENT)
    client_msg_id: Mapped[str | None] = mapped_column(String(120))
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)
    updated_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    user: Mapped[User] = relationship()

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "message_id": self.message_id,
            "direct_message_id": self.direct_message_id,
            "user_id": self.user_id,
            "status": self.status,
            "client_msg_id": self.client_msg_id,
            "created_at": iso(self.created_at),
            "updated_at": iso(self.updated_at),
        }


class ConnectionEvent(db.Model):
    __tablename__ = "connection_events"
    __table_args__ = (Index("ix_conn_event_user", "user_id"),)

    id: Mapped[int] = mapped_column(primary_key=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    session_id: Mapped[str] = mapped_column(String(64), default="")
    event_type: Mapped[str] = mapped_column(String(30))
    message_id: Mapped[int | None] = mapped_column(ForeignKey("messages.id"))
    payload: Mapped[dict] = mapped_column(JSON, default=dict)
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "user_id": self.user_id,
            "session_id": self.session_id,
            "event_type": self.event_type,
            "message_id": self.message_id,
            "payload": self.payload,
            "created_at": iso(self.created_at),
        }


class ErrorRecord(db.Model):
    __tablename__ = "error_records"
    __table_args__ = (Index("ix_error_user", "user_id"),)

    id: Mapped[int] = mapped_column(primary_key=True)
    user_id: Mapped[int | None] = mapped_column(ForeignKey("users.id"))
    code: Mapped[str] = mapped_column(String(60))
    message: Mapped[str] = mapped_column(Text)
    path: Mapped[str] = mapped_column(String(255), default="")
    details: Mapped[dict] = mapped_column(JSON, default=dict)
    acknowledged: Mapped[bool] = mapped_column(Boolean, default=False)
    acknowledged_at: Mapped[dt.datetime | None] = mapped_column(DateTime)
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "user_id": self.user_id,
            "code": self.code,
            "message": self.message,
            "path": self.path,
            "details": self.details,
            "acknowledged": self.acknowledged,
            "acknowledged_at": iso(self.acknowledged_at),
            "created_at": iso(self.created_at),
        }


class SavedSearch(db.Model):
    __tablename__ = "saved_searches"
    __table_args__ = (Index("ix_saved_search_user", "user_id"),)

    id: Mapped[int] = mapped_column(primary_key=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    name: Mapped[str] = mapped_column(String(120))
    query: Mapped[str] = mapped_column(Text)
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "user_id": self.user_id,
            "name": self.name,
            "query": self.query,
            "created_at": iso(self.created_at),
        }


class FrontendEvent(db.Model):
    """API/event states observed by the frontend (CHAT-11)."""

    __tablename__ = "frontend_events"
    __table_args__ = (Index("ix_frontend_event_user", "user_id"),)

    id: Mapped[int] = mapped_column(primary_key=True)
    user_id: Mapped[int | None] = mapped_column(ForeignKey("users.id"))
    event_type: Mapped[str] = mapped_column(String(60))
    payload: Mapped[dict] = mapped_column(JSON, default=dict)
    acknowledged: Mapped[bool] = mapped_column(Boolean, default=False)
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=utcnow)

    def to_dict(self) -> dict:
        return {
            "id": self.id,
            "user_id": self.user_id,
            "event_type": self.event_type,
            "payload": self.payload,
            "acknowledged": self.acknowledged,
            "created_at": iso(self.created_at),
        }
