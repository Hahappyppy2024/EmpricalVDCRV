from __future__ import annotations

from datetime import datetime, timezone
from typing import Optional

from sqlalchemy import and_, asc, desc, or_, select

from ..db import SessionLocal
from ..models import Attachment, DirectMessage, Message


class MessageRepository:
    @staticmethod
    def by_id(message_id: int) -> Optional[Message]:
        with SessionLocal() as session:
            return session.get(Message, message_id)

    @staticmethod
    def by_client_id(channel_id: int, client_id: str) -> Optional[Message]:
        if not client_id:
            return None
        with SessionLocal() as session:
            return session.execute(
                select(Message).where(
                    Message.channel_id == channel_id,
                    Message.client_id == client_id,
                )
            ).scalar_one_or_none()

    @staticmethod
    def create(
        channel_id: int,
        sender_id: int,
        body: str,
        topic: str,
        client_id: str,
        delivery_state: str = "sent",
    ) -> Message:
        with SessionLocal() as session:
            message = Message(
                channel_id=channel_id,
                sender_id=sender_id,
                body=body,
                topic=topic or "general",
                client_id=client_id or "",
                delivery_state=delivery_state,
            )
            session.add(message)
            session.commit()
            session.refresh(message)
            session.expunge(message)
            return message

    @staticmethod
    def update_body(message_id: int, body: str) -> Optional[Message]:
        with SessionLocal() as session:
            message = session.get(Message, message_id)
            if message is None:
                return None
            message.body = body
            message.edited = True
            message.updated_at = datetime.now(timezone.utc)
            session.commit()
            session.refresh(message)
            session.expunge(message)
            return message

    @staticmethod
    def update_delivery_state(message_id: int, delivery_state: str) -> Optional[Message]:
        with SessionLocal() as session:
            message = session.get(Message, message_id)
            if message is None:
                return None
            message.delivery_state = delivery_state
            session.commit()
            session.refresh(message)
            session.expunge(message)
            return message

    @staticmethod
    def mark_deleted(message_id: int) -> Optional[Message]:
        with SessionLocal() as session:
            message = session.get(Message, message_id)
            if message is None:
                return None
            message.deleted = True
            session.commit()
            session.refresh(message)
            session.expunge(message)
            return message

    @staticmethod
    def list_in_channel(
        channel_id: int, limit: int = 100, before_id: Optional[int] = None
    ) -> list[Message]:
        with SessionLocal() as session:
            stmt = select(Message).where(Message.channel_id == channel_id)
            if before_id is not None:
                stmt = stmt.where(Message.id < before_id)
            stmt = stmt.order_by(desc(Message.id)).limit(limit)
            return list(reversed(session.execute(stmt).scalars()))

    @staticmethod
    def search(
        user_id: int,
        keyword: Optional[str],
        start: Optional[datetime],
        end: Optional[datetime],
        channel_id: Optional[int],
        limit: int = 50,
    ) -> list[Message]:
        from ..models import Channel, Membership

        with SessionLocal() as session:
            stmt = (
                select(Message)
                .join(Channel, Message.channel_id == Channel.id)
                .where(Channel.archived.is_(False))
                .where(Message.deleted.is_(False))
            )
            if channel_id is not None:
                stmt = stmt.where(Message.channel_id == channel_id)
            if keyword:
                like = f"%{keyword.strip()}%"
                stmt = stmt.where(or_(Message.body.ilike(like), Message.topic.ilike(like)))
            if start is not None:
                stmt = stmt.where(Message.created_at >= start)
            if end is not None:
                stmt = stmt.where(Message.created_at <= end)
            stmt = stmt.order_by(desc(Message.created_at)).limit(limit)
            messages = list(session.execute(stmt).scalars())
            visible_ids = set(
                session.execute(
                    select(Membership.workspace_id).where(Membership.user_id == user_id)
                ).scalars()
            )
            return messages


class DirectMessageRepository:
    @staticmethod
    def by_thread(thread_key: str, limit: int = 100) -> list[DirectMessage]:
        with SessionLocal() as session:
            stmt = (
                select(DirectMessage)
                .where(DirectMessage.thread_key == thread_key)
                .order_by(asc(DirectMessage.created_at))
                .limit(limit)
            )
            return list(session.execute(stmt).scalars())

    @staticmethod
    def create(
        thread_key: str,
        sender_id: int,
        recipient_id: int,
        body: str,
    ) -> DirectMessage:
        with SessionLocal() as session:
            dm = DirectMessage(
                thread_key=thread_key,
                sender_id=sender_id,
                recipient_id=recipient_id,
                body=body,
            )
            session.add(dm)
            session.commit()
            session.refresh(dm)
            session.expunge(dm)
            return dm

    @staticmethod
    def mark_read(thread_key: str, user_id: int) -> int:
        from sqlalchemy import update

        with SessionLocal() as session:
            stmt = (
                update(DirectMessage)
                .where(
                    and_(
                        DirectMessage.thread_key == thread_key,
                        DirectMessage.recipient_id == user_id,
                        DirectMessage.read.is_(False),
                    )
                )
                .values(read=True)
            )
            result = session.execute(stmt)
            session.commit()
            return result.rowcount or 0

    @staticmethod
    def threads_for_user(user_id: int) -> list[DirectMessage]:
        with SessionLocal() as session:
            stmt = (
                select(DirectMessage)
                .where(
                    or_(
                        DirectMessage.sender_id == user_id,
                        DirectMessage.recipient_id == user_id,
                    )
                )
                .order_by(desc(DirectMessage.created_at))
            )
            return list(session.execute(stmt).scalars())

    @staticmethod
    def unread_count(user_id: int) -> int:
        from sqlalchemy import func

        with SessionLocal() as session:
            result = session.execute(
                select(func.count(DirectMessage.id)).where(
                    DirectMessage.recipient_id == user_id,
                    DirectMessage.read.is_(False),
                )
            ).scalar_one()
            return int(result or 0)


class AttachmentRepository:
    @staticmethod
    def by_id(attachment_id: int) -> Optional[Attachment]:
        with SessionLocal() as session:
            return session.get(Attachment, attachment_id)

    @staticmethod
    def list_for_message(message_id: int) -> list[Attachment]:
        with SessionLocal() as session:
            return list(
                session.execute(
                    select(Attachment).where(Attachment.message_id == message_id)
                ).scalars()
            )

    @staticmethod
    def list_for_user(user_id: int) -> list[Attachment]:
        with SessionLocal() as session:
            return list(
                session.execute(
                    select(Attachment)
                    .where(Attachment.owner_id == user_id)
                    .order_by(desc(Attachment.created_at))
                ).scalars()
            )

    @staticmethod
    def create(
        stored_filename: str,
        original_filename: str,
        content_type: str,
        size_bytes: int,
        owner_id: int,
        message_id: Optional[int] = None,
    ) -> Attachment:
        with SessionLocal() as session:
            attachment = Attachment(
                stored_filename=stored_filename,
                original_filename=original_filename,
                content_type=content_type,
                size_bytes=size_bytes,
                owner_id=owner_id,
                message_id=message_id,
            )
            session.add(attachment)
            session.commit()
            session.refresh(attachment)
            session.expunge(attachment)
            return attachment

    @staticmethod
    def attach_to_message(attachment_id: int, message_id: int) -> Optional[Attachment]:
        with SessionLocal() as session:
            attachment = session.get(Attachment, attachment_id)
            if attachment is None:
                return None
            attachment.message_id = message_id
            session.commit()
            session.refresh(attachment)
            session.expunge(attachment)
            return attachment
