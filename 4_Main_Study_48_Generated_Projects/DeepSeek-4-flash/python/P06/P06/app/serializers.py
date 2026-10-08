"""JSON serializers for API responses and realtime events."""

from __future__ import annotations

from .models import iso
from .repositories import delivery_repo


def user_dict(user) -> dict:
    return {
        "id": user.id,
        "username": user.username,
        "full_name": user.full_name,
        "display_name": user.display_name,
        "email": user.email,
        "role": user.role,
    }


def channel_dict(channel) -> dict:
    return {
        "id": channel.id,
        "workspace_id": channel.workspace_id,
        "name": channel.name,
        "description": channel.description,
        "visibility": channel.visibility,
        "archived": channel.archived,
        "created_at": iso(channel.created_at),
    }


def topic_dict(topic) -> dict:
    return {
        "id": topic.id,
        "channel_id": topic.channel_id,
        "name": topic.name,
        "created_at": iso(topic.created_at),
    }


def message_dict(message, viewer=None) -> dict:
    data = {
        "id": message.id,
        "workspace_id": message.workspace_id,
        "channel_id": message.channel_id,
        "topic_id": message.topic_id,
        "topic": message.topic.name if message.topic else "general",
        "sender": user_dict(message.sender) if message.sender else None,
        "body": message.body if not message.is_deleted else "",
        "is_deleted": message.is_deleted,
        "is_edited": message.is_edited,
        "client_msg_id": message.client_msg_id,
        "created_at": iso(message.created_at),
        "updated_at": iso(message.updated_at),
    }
    if viewer is not None:
        record = delivery_repo.get_for_message(message.id, viewer.id)
        data["delivery"] = record.status if record else None
    return data


def dm_dict(dm, viewer=None) -> dict:
    data = {
        "id": dm.id,
        "thread_id": dm.thread_id,
        "sender": user_dict(dm.sender) if dm.sender else None,
        "body": dm.body if not dm.is_deleted else "",
        "is_deleted": dm.is_deleted,
        "is_edited": dm.is_edited,
        "delivered": dm.delivered,
        "read_at": iso(dm.read_at),
        "created_at": iso(dm.created_at),
        "updated_at": iso(dm.updated_at),
    }
    return data


def thread_dict(thread, current_user_id: int, dm_repo) -> dict:
    other_id = (
        thread.user_b_id if thread.user_a_id == current_user_id else thread.user_a_id
    )
    last = dm_repo.last_message(thread.id)
    return {
        "id": thread.id,
        "user_a_id": thread.user_a_id,
        "user_b_id": thread.user_b_id,
        "other_user_id": other_id,
        "other": last.sender.display_name if last and last.sender else str(other_id),
        "unread": dm_repo.unread_count(thread.id, current_user_id),
        "last_message": dm_dict(last) if last else None,
        "created_at": iso(thread.created_at),
    }


def attachment_dict(attachment) -> dict:
    return {
        "id": attachment.id,
        "owner_id": attachment.owner_id,
        "owner": user_dict(attachment.owner) if attachment.owner else None,
        "message_id": attachment.message_id,
        "direct_message_id": attachment.direct_message_id,
        "stored_file_id": attachment.stored_file_id,
        "filename": attachment.filename,
        "content_type": attachment.content_type,
        "size": attachment.size,
        "is_private": attachment.is_private,
        "created_at": iso(attachment.created_at),
    }


def preview_dict(preview) -> dict:
    return {
        "id": preview.id,
        "url": preview.url,
        "title": preview.title,
        "description": preview.description,
        "site_name": preview.site_name,
        "image_url": preview.image_url,
        "created_at": iso(preview.created_at),
        "fetched_at": iso(preview.fetched_at),
    }
