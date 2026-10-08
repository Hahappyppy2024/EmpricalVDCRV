"""Repository / data-access layer.

Each repository encapsulates all SQLAlchemy queries for one aggregate and
commits after mutations. Services never touch the session directly except for
transactional helpers provided here.
"""

from __future__ import annotations

import datetime as dt

from sqlalchemy import or_, select

from .extensions import db
from .models import (
    DELIVERY_DELIVERED,
    DELIVERY_READ,
    DELIVERY_SENT,
    Attachment,
    AuditEvent,
    AuthSession,
    Channel,
    ChannelMember,
    ConnectionEvent,
    DirectMessage,
    DirectMessageThread,
    DeliveryRecord,
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


def _commit() -> None:
    db.session.commit()


def now() -> dt.datetime:
    return utcnow()


class UserRepository:
    def get(self, user_id: int) -> User | None:
        return db.session.get(User, user_id)

    def get_by_username(self, username: str) -> User | None:
        return db.session.scalar(
            select(User).where(User.username == username)
        )

    def get_by_email(self, email: str) -> User | None:
        return db.session.scalar(select(User).where(User.email == email))

    def get_by_username_or_email(self, identifier: str) -> User | None:
        return db.session.scalar(
            select(User).where(
                or_(User.username == identifier, User.email == identifier)
            )
        )

    def get_by_reset_token(self, token: str) -> User | None:
        return db.session.scalar(
            select(User).where(User.reset_token == token)
        )

    def all(self) -> list[User]:
        return list(db.session.scalars(select(User).order_by(User.id)))

    def search(self, query: str, limit: int = 20) -> list[User]:
        like = f"%{query}%"
        return list(
            db.session.scalars(
                select(User)
                .where(
                    or_(
                        User.username.ilike(like),
                        User.full_name.ilike(like),
                        User.email.ilike(like),
                    )
                )
                .order_by(User.username)
                .limit(limit)
            )
        )

    def create(
        self,
        username: str,
        email: str,
        full_name: str,
        password: str,
        role: str = "member",
    ) -> User:
        user = User(
            username=username,
            email=email,
            full_name=full_name,
            role=role,
            status="active",
        )
        user.set_password(password)
        db.session.add(user)
        _commit()
        return user

    def update(self, user: User, **fields) -> User:
        for key, value in fields.items():
            setattr(user, key, value)
        _commit()
        return user

    def set_password(self, user: User, raw: str) -> None:
        user.set_password(raw)
        _commit()

    def set_reset_token(
        self, user: User, token: str, expires_at: dt.datetime
    ) -> None:
        user.reset_token = token
        user.reset_expires_at = expires_at
        _commit()

    def clear_reset_token(self, user: User) -> None:
        user.reset_token = None
        user.reset_expires_at = None
        _commit()


class SessionRepository:
    def create(
        self,
        user_id: int,
        token: str,
        ttl_seconds: int,
        ip_address: str = "",
        user_agent: str = "",
    ) -> AuthSession:
        session = AuthSession(
            token=token,
            user_id=user_id,
            expires_at=now() + dt.timedelta(seconds=ttl_seconds),
            ip_address=ip_address,
            user_agent=user_agent[:255],
            active=True,
        )
        db.session.add(session)
        _commit()
        return session

    def get_active(self, token: str) -> AuthSession | None:
        return db.session.scalar(
            select(AuthSession).where(
                AuthSession.token == token,
                AuthSession.active.is_(True),
                AuthSession.expires_at > now(),
            )
        )

    def invalidate(self, auth_session: AuthSession) -> None:
        auth_session.active = False
        _commit()

    def invalidate_all_for_user(self, user_id: int) -> None:
        rows = db.session.scalars(
            select(AuthSession).where(
                AuthSession.user_id == user_id, AuthSession.active.is_(True)
            )
        )
        for row in rows:
            row.active = False
        _commit()

    def list_for_user(self, user_id: int) -> list[AuthSession]:
        return list(
            db.session.scalars(
                select(AuthSession)
                .where(AuthSession.user_id == user_id)
                .order_by(AuthSession.created_at.desc())
            )
        )

    def prune_expired(self) -> int:
        result = db.session.execute(
            AuthSession.__table__.delete().where(
                AuthSession.expires_at <= now()
            )
        )
        _commit()
        return result.rowcount


class WorkspaceRepository:
    def get(self, workspace_id: int) -> Workspace | None:
        return db.session.get(Workspace, workspace_id)

    def get_by_slug(self, slug: str) -> Workspace | None:
        return db.session.scalar(select(Workspace).where(Workspace.slug == slug))

    def list_for_user(self, user_id: int) -> list[Workspace]:
        return list(
            db.session.scalars(
                select(Workspace)
                .join(Membership, Membership.workspace_id == Workspace.id)
                .where(Membership.user_id == user_id)
                .order_by(Workspace.name)
            )
        )

    def create(
        self,
        name: str,
        slug: str,
        description: str,
        owner_id: int,
    ) -> Workspace:
        workspace = Workspace(
            name=name, slug=slug, description=description, owner_id=owner_id
        )
        db.session.add(workspace)
        _commit()
        return workspace

    def update(self, workspace: Workspace, **fields) -> Workspace:
        for key, value in fields.items():
            setattr(workspace, key, value)
        _commit()
        return workspace


class ChannelRepository:
    def get(self, channel_id: int) -> Channel | None:
        return db.session.get(Channel, channel_id)

    def list_for_workspace(self, workspace_id: int) -> list[Channel]:
        return list(
            db.session.scalars(
                select(Channel)
                .where(Channel.workspace_id == workspace_id)
                .order_by(Channel.name)
            )
        )

    def get_by_workspace_and_name(
        self, workspace_id: int, name: str
    ) -> Channel | None:
        return db.session.scalar(
            select(Channel).where(
                Channel.workspace_id == workspace_id, Channel.name == name
            )
        )

    def create(
        self,
        workspace_id: int,
        name: str,
        description: str,
        visibility: str,
        created_by_id: int,
    ) -> Channel:
        channel = Channel(
            workspace_id=workspace_id,
            name=name,
            description=description,
            visibility=visibility,
            created_by_id=created_by_id,
        )
        db.session.add(channel)
        _commit()
        return channel

    def update(self, channel: Channel, **fields) -> Channel:
        for key, value in fields.items():
            setattr(channel, key, value)
        _commit()
        return channel


class MembershipRepository:
    def get(self, workspace_id: int, user_id: int) -> Membership | None:
        return db.session.scalar(
            select(Membership).where(
                Membership.workspace_id == workspace_id,
                Membership.user_id == user_id,
            )
        )

    def list_for_workspace(self, workspace_id: int) -> list[Membership]:
        return list(
            db.session.scalars(
                select(Membership)
                .where(Membership.workspace_id == workspace_id)
                .order_by(Membership.role, Membership.joined_at)
            )
        )

    def list_for_user(self, user_id: int) -> list[Membership]:
        return list(
            db.session.scalars(
                select(Membership).where(Membership.user_id == user_id)
            )
        )

    def create(self, workspace_id: int, user_id: int, role: str) -> Membership:
        membership = Membership(
            workspace_id=workspace_id, user_id=user_id, role=role
        )
        db.session.add(membership)
        _commit()
        return membership

    def update_role(self, membership: Membership, role: str) -> None:
        membership.role = role
        _commit()

    def delete(self, membership: Membership) -> None:
        db.session.delete(membership)
        _commit()


class ChannelMemberRepository:
    def add(self, channel_id: int, user_id: int) -> None:
        if not db.session.scalar(
            select(ChannelMember).where(
                ChannelMember.channel_id == channel_id,
                ChannelMember.user_id == user_id,
            )
        ):
            db.session.add(ChannelMember(channel_id=channel_id, user_id=user_id))
            _commit()

    def remove(self, channel_id: int, user_id: int) -> None:
        row = db.session.scalar(
            select(ChannelMember).where(
                ChannelMember.channel_id == channel_id,
                ChannelMember.user_id == user_id,
            )
        )
        if row:
            db.session.delete(row)
            _commit()

    def user_ids(self, channel_id: int) -> set[int]:
        return {
            row for row in db.session.scalars(
                select(ChannelMember.user_id).where(
                    ChannelMember.channel_id == channel_id
                )
            )
        }

    def remove_user_from_workspace_channels(
        self, workspace_id: int, user_id: int
    ) -> None:
        channel_ids = list(
            db.session.scalars(
                select(Channel.id).where(Channel.workspace_id == workspace_id)
            )
        )
        if not channel_ids:
            return
        rows = list(
            db.session.scalars(
                select(ChannelMember).where(
                    ChannelMember.channel_id.in_(channel_ids),
                    ChannelMember.user_id == user_id,
                )
            )
        )
        for row in rows:
            db.session.delete(row)
        _commit()


class InvitationRepository:
    def create(
        self,
        workspace_id: int,
        invited_by_id: int,
        email: str,
        role: str,
        token: str,
        ttl_seconds: int,
    ) -> Invitation:
        invitation = Invitation(
            workspace_id=workspace_id,
            invited_by_id=invited_by_id,
            email=email,
            role=role,
            token=token,
            status="pending",
            expires_at=now() + dt.timedelta(seconds=ttl_seconds),
        )
        db.session.add(invitation)
        _commit()
        return invitation

    def get_by_token(self, token: str) -> Invitation | None:
        return db.session.scalar(
            select(Invitation).where(Invitation.token == token)
        )

    def list_for_workspace(self, workspace_id: int) -> list[Invitation]:
        return list(
            db.session.scalars(
                select(Invitation)
                .where(Invitation.workspace_id == workspace_id)
                .order_by(Invitation.created_at.desc())
            )
        )

    def list_pending_for_email(self, email: str) -> list[Invitation]:
        return list(
            db.session.scalars(
                select(Invitation).where(
                    Invitation.email == email,
                    Invitation.status == "pending",
                    Invitation.expires_at > now(),
                )
            )
        )

    def update_status(self, invitation: Invitation, status: str) -> None:
        invitation.status = status
        _commit()

    def update(self, invitation: Invitation, **fields) -> None:
        for key, value in fields.items():
            setattr(invitation, key, value)
        _commit()


class TopicRepository:
    def get(self, topic_id: int) -> Topic | None:
        return db.session.get(Topic, topic_id)

    def list_for_channel(self, channel_id: int) -> list[Topic]:
        return list(
            db.session.scalars(
                select(Topic)
                .where(Topic.channel_id == channel_id)
                .order_by(Topic.name)
            )
        )

    def get_or_create(
        self, channel_id: int, name: str, created_by_id: int
    ) -> Topic:
        topic = db.session.scalar(
            select(Topic).where(
                Topic.channel_id == channel_id, Topic.name == name
            )
        )
        if topic is None:
            topic = Topic(
                channel_id=channel_id, name=name, created_by_id=created_by_id
            )
            db.session.add(topic)
            _commit()
        return topic


class MessageRepository:
    def get(self, message_id: int) -> Message | None:
        return db.session.get(Message, message_id)

    def get_by_client_id(
        self, sender_id: int, client_msg_id: str
    ) -> Message | None:
        return db.session.scalar(
            select(Message).where(
                Message.sender_id == sender_id,
                Message.client_msg_id == client_msg_id,
            )
        )

    def create(
        self,
        workspace_id: int,
        channel_id: int,
        sender_id: int,
        body: str,
        client_msg_id: str | None,
        topic_id: int | None = None,
    ) -> Message:
        message = Message(
            workspace_id=workspace_id,
            channel_id=channel_id,
            topic_id=topic_id,
            sender_id=sender_id,
            body=body,
            client_msg_id=client_msg_id,
        )
        db.session.add(message)
        _commit()
        return message

    def list_for_channel(
        self,
        channel_id: int,
        topic_id: int | None = None,
        before_id: int | None = None,
        limit: int = 50,
    ) -> list[Message]:
        query = select(Message).where(Message.channel_id == channel_id)
        if topic_id is not None:
            query = query.where(Message.topic_id == topic_id)
        if before_id is not None:
            query = query.where(Message.id < before_id)
        return list(
            db.session.scalars(
                query.order_by(Message.id.desc()).limit(limit)
            )
        )[::-1]

    def search(
        self,
        keyword: str,
        workspace_id: int,
        channel_ids: list[int],
        date_from: dt.datetime | None = None,
        date_to: dt.datetime | None = None,
        limit: int = 100,
    ) -> list[Message]:
        if not channel_ids:
            return []
        like = f"%{keyword}%"
        query = select(Message).where(
            Message.workspace_id == workspace_id,
            Message.channel_id.in_(channel_ids),
            Message.is_deleted.is_(False),
            Message.body.ilike(like),
        )
        if date_from is not None:
            query = query.where(Message.created_at >= date_from)
        if date_to is not None:
            query = query.where(Message.created_at <= date_to)
        return list(
            db.session.scalars(
                query.order_by(Message.created_at.desc()).limit(limit)
            )
        )

    def update_body(self, message: Message, body: str) -> None:
        message.body = body
        message.is_edited = True
        message.updated_at = now()
        _commit()

    def mark_deleted(self, message: Message) -> None:
        message.is_deleted = True
        message.updated_at = now()
        _commit()


class DMRepository:
    def get_thread(self, thread_id: int) -> DirectMessageThread | None:
        return db.session.get(DirectMessageThread, thread_id)

    def get_thread_for_users(
        self, user_id: int, other_id: int
    ) -> DirectMessageThread | None:
        a, b = sorted((user_id, other_id))
        return db.session.scalar(
            select(DirectMessageThread).where(
                DirectMessageThread.user_a_id == a,
                DirectMessageThread.user_b_id == b,
            )
        )

    def get_or_create_thread(self, user_id: int, other_id: int) -> DirectMessageThread:
        a, b = sorted((user_id, other_id))
        thread = db.session.scalar(
            select(DirectMessageThread).where(
                DirectMessageThread.user_a_id == a,
                DirectMessageThread.user_b_id == b,
            )
        )
        if thread is None:
            thread = DirectMessageThread(user_a_id=a, user_b_id=b)
            db.session.add(thread)
            _commit()
        return thread

    def list_threads_for_user(self, user_id: int) -> list[DirectMessageThread]:
        return list(
            db.session.scalars(
                select(DirectMessageThread).where(
                    or_(
                        DirectMessageThread.user_a_id == user_id,
                        DirectMessageThread.user_b_id == user_id,
                    )
                ).order_by(DirectMessageThread.created_at.desc())
            )
        )

    def get_dm(self, dm_id: int) -> DirectMessage | None:
        return db.session.get(DirectMessage, dm_id)

    def get_dm_by_client_id(
        self, sender_id: int, client_msg_id: str
    ) -> DirectMessage | None:
        return db.session.scalar(
            select(DirectMessage).where(
                DirectMessage.sender_id == sender_id,
                DirectMessage.client_msg_id == client_msg_id,
            )
        )

    def create_dm(
        self,
        thread_id: int,
        sender_id: int,
        body: str,
        client_msg_id: str | None,
    ) -> DirectMessage:
        dm = DirectMessage(
            thread_id=thread_id,
            sender_id=sender_id,
            body=body,
            client_msg_id=client_msg_id,
        )
        db.session.add(dm)
        _commit()
        return dm

    def list_for_thread(self, thread_id: int, limit: int = 100) -> list[DirectMessage]:
        return list(
            db.session.scalars(
                select(DirectMessage)
                .where(DirectMessage.thread_id == thread_id)
                .order_by(DirectMessage.id.desc())
                .limit(limit)
            )
        )[::-1]

    def last_message(self, thread_id: int) -> DirectMessage | None:
        return db.session.scalar(
            select(DirectMessage)
            .where(DirectMessage.thread_id == thread_id)
            .order_by(DirectMessage.id.desc())
            .limit(1)
        )

    def unread_count(self, thread_id: int, user_id: int) -> int:
        return len(
            list(
                db.session.scalars(
                    select(DirectMessage).where(
                        DirectMessage.thread_id == thread_id,
                        DirectMessage.sender_id != user_id,
                        DirectMessage.read_at.is_(None),
                    )
                )
            )
        )

    def update_dm_body(self, dm: DirectMessage, body: str) -> None:
        dm.body = body
        dm.is_edited = True
        dm.updated_at = now()
        _commit()

    def mark_dm_deleted(self, dm: DirectMessage) -> None:
        dm.is_deleted = True
        dm.updated_at = now()
        _commit()

    def mark_delivered(self, dm: DirectMessage) -> None:
        dm.delivered = True
        _commit()

    def mark_read(self, dm: DirectMessage) -> None:
        dm.read_at = now()
        _commit()

    def mark_thread_read(self, thread_id: int, reader_id: int) -> int:
        rows = list(
            db.session.scalars(
                select(DirectMessage).where(
                    DirectMessage.thread_id == thread_id,
                    DirectMessage.sender_id != reader_id,
                    DirectMessage.read_at.is_(None),
                )
            )
        )
        for row in rows:
            row.read_at = now()
        _commit()
        return len(rows)


class StoredFileRepository:
    def create(
        self,
        stored_name: str,
        original_name: str,
        sha256: str,
        size: int,
        content_type: str,
    ) -> StoredFile:
        stored = StoredFile(
            stored_name=stored_name,
            original_name=original_name,
            sha256=sha256,
            size=size,
            content_type=content_type,
        )
        db.session.add(stored)
        _commit()
        return stored

    def get(self, stored_file_id: int) -> StoredFile | None:
        return db.session.get(StoredFile, stored_file_id)


class AttachmentRepository:
    def create(
        self,
        owner_id: int,
        stored_file_id: int,
        filename: str,
        content_type: str,
        size: int,
        message_id: int | None = None,
        direct_message_id: int | None = None,
        is_private: bool = False,
    ) -> Attachment:
        attachment = Attachment(
            owner_id=owner_id,
            stored_file_id=stored_file_id,
            filename=filename,
            content_type=content_type,
            size=size,
            message_id=message_id,
            direct_message_id=direct_message_id,
            is_private=is_private,
        )
        db.session.add(attachment)
        _commit()
        return attachment

    def get(self, attachment_id: int) -> Attachment | None:
        return db.session.get(Attachment, attachment_id)

    def list_for_user(self, user_id: int) -> list[Attachment]:
        return list(
            db.session.scalars(
                select(Attachment)
                .where(Attachment.owner_id == user_id)
                .order_by(Attachment.created_at.desc())
            )
        )

    def list_for_message(self, message_id: int) -> list[Attachment]:
        return list(
            db.session.scalars(
                select(Attachment)
                .where(Attachment.message_id == message_id)
                .order_by(Attachment.id)
            )
        )

    def update(self, attachment: Attachment, **fields) -> None:
        for key, value in fields.items():
            setattr(attachment, key, value)
        _commit()


class LinkPreviewRepository:
    def get_by_url(self, url: str) -> LinkPreview | None:
        return db.session.scalar(
            select(LinkPreview).where(LinkPreview.url == url)
        )

    def get(self, preview_id: int) -> LinkPreview | None:
        return db.session.get(LinkPreview, preview_id)

    def create(
        self,
        url: str,
        title: str,
        description: str,
        site_name: str,
        image_url: str | None,
        created_by_id: int | None,
    ) -> LinkPreview:
        preview = LinkPreview(
            url=url,
            title=title,
            description=description,
            site_name=site_name,
            image_url=image_url,
            created_by_id=created_by_id,
        )
        db.session.add(preview)
        _commit()
        return preview

    def update(self, preview: LinkPreview, **fields) -> None:
        for key, value in fields.items():
            setattr(preview, key, value)
        _commit()

    def list_recent(self, limit: int = 50) -> list[LinkPreview]:
        return list(
            db.session.scalars(
                select(LinkPreview)
                .order_by(LinkPreview.created_at.desc())
                .limit(limit)
            )
        )


class AuditRepository:
    def create(
        self,
        workspace_id: int | None,
        actor_id: int,
        action: str,
        target_type: str = "",
        target_id: str = "",
        details: dict | None = None,
    ) -> AuditEvent:
        event = AuditEvent(
            workspace_id=workspace_id,
            actor_id=actor_id,
            action=action,
            target_type=target_type,
            target_id=str(target_id or ""),
            details=details or {},
        )
        db.session.add(event)
        _commit()
        return event

    def list_for_workspace(self, workspace_id: int, limit: int = 100) -> list[AuditEvent]:
        return list(
            db.session.scalars(
                select(AuditEvent)
                .where(AuditEvent.workspace_id == workspace_id)
                .order_by(AuditEvent.created_at.desc())
                .limit(limit)
            )
        )

    def list_for_user(self, user_id: int, limit: int = 100) -> list[AuditEvent]:
        return list(
            db.session.scalars(
                select(AuditEvent)
                .where(AuditEvent.actor_id == user_id)
                .order_by(AuditEvent.created_at.desc())
                .limit(limit)
            )
        )


class DeliveryRepository:
    def create(
        self,
        user_id: int,
        message_id: int | None = None,
        direct_message_id: int | None = None,
        status: str = DELIVERY_SENT,
        client_msg_id: str | None = None,
    ) -> DeliveryRecord:
        record = DeliveryRecord(
            user_id=user_id,
            message_id=message_id,
            direct_message_id=direct_message_id,
            status=status,
            client_msg_id=client_msg_id,
        )
        db.session.add(record)
        _commit()
        return record

    def get_for_message(self, message_id: int, user_id: int) -> DeliveryRecord | None:
        return db.session.scalar(
            select(DeliveryRecord).where(
                DeliveryRecord.message_id == message_id,
                DeliveryRecord.user_id == user_id,
            )
        )

    def get(self, record_id: int) -> DeliveryRecord | None:
        return db.session.get(DeliveryRecord, record_id)

    def update_status(self, record: DeliveryRecord, status: str) -> None:
        record.status = status
        record.updated_at = now()
        _commit()

    def mark_delivered(self, message_id: int, user_id: int) -> DeliveryRecord:
        record = self.get_for_message(message_id, user_id)
        if record is None:
            record = self.create(
                user_id=user_id, message_id=message_id, status=DELIVERY_DELIVERED
            )
        elif record.status == DELIVERY_SENT:
            self.update_status(record, DELIVERY_DELIVERED)
        return record

    def mark_read(self, message_id: int, user_id: int) -> DeliveryRecord:
        record = self.get_for_message(message_id, user_id)
        if record is None:
            record = self.create(
                user_id=user_id, message_id=message_id, status=DELIVERY_READ
            )
        else:
            self.update_status(record, DELIVERY_READ)
        return record

    def list_for_user(self, user_id: int) -> list[DeliveryRecord]:
        return list(
            db.session.scalars(
                select(DeliveryRecord)
                .where(DeliveryRecord.user_id == user_id)
                .order_by(DeliveryRecord.updated_at.desc())
            )
        )


class ConnectionEventRepository:
    def create(
        self,
        user_id: int,
        event_type: str,
        session_id: str = "",
        message_id: int | None = None,
        payload: dict | None = None,
    ) -> ConnectionEvent:
        event = ConnectionEvent(
            user_id=user_id,
            session_id=session_id,
            event_type=event_type,
            message_id=message_id,
            payload=payload or {},
        )
        db.session.add(event)
        _commit()
        return event

    def get(self, event_id: int) -> ConnectionEvent | None:
        return db.session.get(ConnectionEvent, event_id)

    def list_for_user(self, user_id: int, limit: int = 100) -> list[ConnectionEvent]:
        return list(
            db.session.scalars(
                select(ConnectionEvent)
                .where(ConnectionEvent.user_id == user_id)
                .order_by(ConnectionEvent.created_at.desc())
                .limit(limit)
            )
        )


class ErrorRepository:
    def create(
        self,
        user_id: int | None,
        code: str,
        message: str,
        path: str = "",
        details: dict | None = None,
    ) -> ErrorRecord:
        record = ErrorRecord(
            user_id=user_id,
            code=code,
            message=message,
            path=path,
            details=details or {},
        )
        db.session.add(record)
        _commit()
        return record

    def get(self, record_id: int) -> ErrorRecord | None:
        return db.session.get(ErrorRecord, record_id)

    def list_for_user(self, user_id: int | None) -> list[ErrorRecord]:
        query = select(ErrorRecord).order_by(ErrorRecord.created_at.desc())
        if user_id is not None:
            query = query.where(ErrorRecord.user_id == user_id)
        return list(db.session.scalars(query.limit(100)))

    def acknowledge(self, record: ErrorRecord) -> None:
        record.acknowledged = True
        record.acknowledged_at = now()
        _commit()


class SavedSearchRepository:
    def create(self, user_id: int, name: str, query: str) -> SavedSearch:
        search = SavedSearch(user_id=user_id, name=name, query=query)
        db.session.add(search)
        _commit()
        return search

    def get(self, search_id: int) -> SavedSearch | None:
        return db.session.get(SavedSearch, search_id)

    def list_for_user(self, user_id: int) -> list[SavedSearch]:
        return list(
            db.session.scalars(
                select(SavedSearch)
                .where(SavedSearch.user_id == user_id)
                .order_by(SavedSearch.created_at.desc())
            )
        )

    def update(self, search: SavedSearch, **fields) -> None:
        for key, value in fields.items():
            setattr(search, key, value)
        _commit()

    def delete(self, search: SavedSearch) -> None:
        db.session.delete(search)
        _commit()


class FrontendEventRepository:
    def create(
        self,
        user_id: int | None,
        event_type: str,
        payload: dict | None = None,
    ) -> FrontendEvent:
        event = FrontendEvent(
            user_id=user_id, event_type=event_type, payload=payload or {}
        )
        db.session.add(event)
        _commit()
        return event

    def get(self, event_id: int) -> FrontendEvent | None:
        return db.session.get(FrontendEvent, event_id)

    def list_for_user(self, user_id: int | None, limit: int = 100) -> list[FrontendEvent]:
        query = select(FrontendEvent).order_by(FrontendEvent.created_at.desc())
        if user_id is not None:
            query = query.where(FrontendEvent.user_id == user_id)
        return list(db.session.scalars(query.limit(limit)))

    def acknowledge(self, event: FrontendEvent) -> None:
        event.acknowledged = True
        _commit()


user_repo = UserRepository()
session_repo = SessionRepository()
workspace_repo = WorkspaceRepository()
channel_repo = ChannelRepository()
membership_repo = MembershipRepository()
channel_member_repo = ChannelMemberRepository()
invitation_repo = InvitationRepository()
topic_repo = TopicRepository()
message_repo = MessageRepository()
dm_repo = DMRepository()
stored_file_repo = StoredFileRepository()
attachment_repo = AttachmentRepository()
link_preview_repo = LinkPreviewRepository()
audit_repo = AuditRepository()
delivery_repo = DeliveryRepository()
connection_event_repo = ConnectionEventRepository()
error_repo = ErrorRepository()
saved_search_repo = SavedSearchRepository()
frontend_event_repo = FrontendEventRepository()
