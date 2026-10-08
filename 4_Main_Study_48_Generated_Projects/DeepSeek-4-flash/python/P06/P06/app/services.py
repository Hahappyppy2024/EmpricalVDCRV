"""Application services: all business rules, validation and workflow transitions.

Services operate through the repository layer, raise ``AppError`` for any
rejected operation and publish realtime events through the hub stored in
``current_app.extensions["realtime"]``.
"""

from __future__ import annotations

import datetime as dt
import hashlib
import re
import secrets
import unicodedata

from flask import current_app, request

from .adapters import link_preview_fetcher, mailbox, storage
from .errors import AppError
from .models import (
    DELIVERY_READ,
    DELIVERY_SENT,
    ROLE_ADMIN,
    ROLE_MEMBER,
    ROLE_OWNER,
    VISIBILITY_PRIVATE,
    VISIBILITY_PUBLIC,
    utcnow,
)
from .repositories import (
    attachment_repo,
    audit_repo,
    channel_member_repo,
    channel_repo,
    connection_event_repo,
    dm_repo,
    error_repo,
    frontend_event_repo,
    invitation_repo,
    link_preview_repo,
    membership_repo,
    message_repo,
    saved_search_repo,
    session_repo,
    stored_file_repo,
    topic_repo,
    user_repo,
    workspace_repo,
    delivery_repo,
)

EMAIL_RE = re.compile(r"^[^@\s]+@[^@\s]+\.[^@\s]+$")
USERNAME_RE = re.compile(r"^[a-zA-Z0-9_.-]{3,32}$")
SLUG_RE = re.compile(r"^[a-z0-9-]{3,64}$")
URL_RE = re.compile(r"^https?://\S+$")


def hub():
    return current_app.extensions["realtime"]


def now() -> dt.datetime:
    return utcnow()


def require(condition: bool, message: str, code: str = "validation_error", status: int = 400) -> None:
    if not condition:
        raise AppError(message, code=code, status=status)


def slugify(value: str) -> str:
    value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode()
    value = re.sub(r"[^a-zA-Z0-9]+", "-", value).strip("-").lower()
    return value or "workspace"


def make_slug(base: str) -> str:
    candidate = slugify(base)
    if workspace_repo.get_by_slug(candidate) is None:
        return candidate
    for index in range(2, 1000):
        trial = f"{candidate}-{index}"
        if workspace_repo.get_by_slug(trial) is None:
            return trial
    return f"{candidate}-{secrets.token_hex(4)}"


def client_ip() -> str:
    return request.headers.get("X-Forwarded-For", request.remote_addr or "")[:64]


def client_user_agent() -> str:
    return (request.headers.get("User-Agent") or "")[:255]


def _deterministic_token(seed: str) -> str:
    return hashlib.sha256(
        (seed + current_app.config["SECRET_KEY"]).encode("utf-8")
    ).hexdigest()


def workspace_role(workspace, user) -> str | None:
    membership = membership_repo.get(workspace.id, user.id)
    return membership.role if membership else None


def require_workspace_member(workspace, user) -> str:
    role = workspace_role(workspace, user)
    require(role is not None, "You are not a member of this workspace", code="access_denied", status=403)
    return role


def require_privileged(workspace, user) -> str:
    role = require_workspace_member(workspace, user)
    require(
        role in (ROLE_OWNER, ROLE_ADMIN),
        "This action requires an owner or admin role",
        code="access_denied",
        status=403,
    )
    return role


def channel_accessible(channel, user) -> bool:
    if channel.visibility == VISIBILITY_PUBLIC:
        return True
    workspace = workspace_repo.get(channel.workspace_id)
    role = workspace_role(workspace, user) if workspace else None
    if role in (ROLE_OWNER, ROLE_ADMIN):
        return True
    return user.id in channel_member_repo.user_ids(channel.id)


def require_channel_access(channel, user) -> None:
    require(
        channel_accessible(channel, user),
        "You do not have access to this channel",
        code="access_denied",
        status=403,
    )


def visible_channel_ids(workspace, user) -> list[int]:
    role = workspace_role(workspace, user)
    channels = channel_repo.list_for_workspace(workspace.id)
    ids = []
    for channel in channels:
        if channel_accessible(channel, user):
            ids.append(channel.id)
    return ids


def publish(key: str, payload: dict) -> None:
    hub().publish(key, payload)


def public_base_url() -> str:
    return current_app.config["PUBLIC_BASE_URL"].rstrip("/")


class AuthService:
    def register(self, username: str, email: str, full_name: str, password: str) -> dict:
        username = (username or "").strip()
        email = (email or "").strip().lower()
        full_name = (full_name or "").strip()
        require(username, "Username is required")
        require(USERNAME_RE.match(username), "Username must be 3-32 letters, digits, dots, dashes or underscores")
        require(email, "Email is required")
        require(EMAIL_RE.match(email), "A valid email address is required")
        require(len(password) >= 8, "Password must be at least 8 characters")
        require(user_repo.get_by_username(username) is None, "That username is already taken", "username_taken", 409)
        require(user_repo.get_by_email(email) is None, "That email is already registered", "email_taken", 409)
        user = user_repo.create(username, email, full_name, password, role=ROLE_MEMBER)
        connection_event_repo.create(user.id, "connect", session_id="register")
        return {"user": user.to_dict()}

    def login(self, identifier: str, password: str) -> dict:
        identifier = (identifier or "").strip()
        require(identifier, "Username or email is required")
        require(password, "Password is required")
        user = user_repo.get_by_username_or_email(identifier)
        if user is None or not user.check_password(password):
            raise AppError("Incorrect credentials", code="invalid_credentials", status=401)
        require(user.status == "active", "This account has been disabled", "account_disabled", 403)
        return {"user": user.to_dict()}

    def request_recovery(self, email: str) -> bool:
        email = (email or "").strip().lower()
        user = user_repo.get_by_email(email)
        if user is None:
            return False
        token = _deterministic_token(f"reset:{user.id}:{user.email}")
        user_repo.set_reset_token(user, token, now() + dt.timedelta(hours=1))
        link = f"{public_base_url()}/reset-password/{token}"
        mailbox.send(
            user.email,
            "Reset your password",
            f"Hello {user.display_name},\n\n"
            f"Use the link below to reset your password:\n{link}\n\n"
            "If you did not request this, you can ignore this message.",
        )
        return True

    def reset_password(self, token: str, new_password: str) -> dict:
        require(token, "Reset token is required")
        require(len(new_password) >= 8, "Password must be at least 8 characters")
        user = user_repo.get_by_reset_token(token)
        require(
            user is not None and user.reset_expires_at is not None and user.reset_expires_at > now(),
            "The reset link is invalid or has expired",
            code="invalid_reset_token",
            status=400,
        )
        user_repo.set_password(user, new_password)
        user_repo.clear_reset_token(user)
        session_repo.invalidate_all_for_user(user.id)
        return {"user": user.to_dict()}

    def update_profile(self, user, full_name: str, email: str) -> dict:
        full_name = (full_name or "").strip()
        email = (email or "").strip().lower()
        require(email, "Email is required")
        require(EMAIL_RE.match(email), "A valid email address is required")
        existing = user_repo.get_by_email(email)
        require(existing is None or existing.id == user.id, "That email is already in use", "email_taken", 409)
        user_repo.update(user, full_name=full_name, email=email)
        return {"user": user.to_dict()}

    def change_password(self, user, current_password: str, new_password: str) -> None:
        require(current_password, "Current password is required")
        require(new_password, "New password is required")
        require(len(new_password) >= 8, "Password must be at least 8 characters")
        require(user.check_password(current_password), "Current password is incorrect", "invalid_credentials", 401)
        user_repo.set_password(user, new_password)
        session_repo.invalidate_all_for_user(user.id)

    def all_users(self) -> list[dict]:
        return [u.to_dict() for u in user_repo.all()]

    def sessions_for(self, user) -> list[dict]:
        return [
            {
                "id": s.id,
                "created_at": s.created_at.isoformat() + "Z",
                "expires_at": s.expires_at.isoformat() + "Z",
                "ip_address": s.ip_address,
                "active": s.active,
            }
            for s in session_repo.list_for_user(user.id)
        ]


class WorkspaceService:
    def list_for(self, user) -> dict:
        result = []
        for workspace in workspace_repo.list_for_user(user.id):
            role = workspace_role(workspace, user)
            channels = []
            for channel in channel_repo.list_for_workspace(workspace.id):
                if channel_accessible(channel, user):
                    channels.append(channel.to_dict())
            result.append(
                {
                    "workspace": workspace.to_dict(),
                    "role": role,
                    "channels": channels,
                }
            )
        return {"workspaces": result}

    def create_workspace(self, user, name: str, description: str) -> dict:
        name = (name or "").strip()
        require(name, "Workspace name is required")
        slug = make_slug(name)
        workspace = workspace_repo.create(name, slug, (description or "").strip(), user.id)
        membership_repo.create(workspace.id, user.id, ROLE_OWNER)
        channel = channel_repo.create(
            workspace.id, "general", "General discussion for everyone", VISIBILITY_PUBLIC, user.id
        )
        audit_repo.create(workspace.id, user.id, "workspace_created", "workspace", workspace.id)
        audit_repo.create(workspace.id, user.id, "channel_created", "channel", channel.id)
        publish(f"ws:{workspace.slug}", {"type": "workspace_created", "workspace": workspace.to_dict()})
        return {
            "workspace": workspace.to_dict(),
            "channels": [channel.to_dict()],
        }

    def update_workspace(self, user, slug: str, name: str, description: str) -> dict:
        workspace = self._get_by_slug(slug)
        role = require_privileged(workspace, user)
        name = (name or "").strip()
        require(name, "Workspace name is required")
        workspace_repo.update(workspace, name=name, description=(description or "").strip())
        audit_repo.create(workspace.id, user.id, "workspace_updated", "workspace", workspace.id, {"role": role})
        return {"workspace": workspace.to_dict()}

    def create_channel(self, user, slug: str, name: str, description: str, visibility: str) -> dict:
        workspace = self._get_by_slug(slug)
        require_privileged(workspace, user)
        name = (name or "").strip().lower()
        require(name, "Channel name is required")
        require(visibility in (VISIBILITY_PUBLIC, VISIBILITY_PRIVATE), "Visibility must be public or private")
        require(channel_repo.get_by_workspace_and_name(workspace.id, name) is None, "A channel with this name already exists", "channel_exists", 409)
        channel = channel_repo.create(workspace.id, name, (description or "").strip(), visibility, user.id)
        audit_repo.create(workspace.id, user.id, "channel_created", "channel", channel.id)
        publish(f"ws:{workspace.slug}", {"type": "channel_created", "channel": channel.to_dict()})
        return {"channel": channel.to_dict()}

    def update_channel(self, user, slug: str, channel_id: int, name: str, description: str) -> dict:
        workspace = self._get_by_slug(slug)
        require_workspace_member(workspace, user)
        channel = self._get_channel(workspace, channel_id)
        require_channel_access(channel, user)
        name = (name or "").strip()
        require(name, "Channel name is required")
        existing = channel_repo.get_by_workspace_and_name(workspace.id, name.lower())
        require(existing is None or existing.id == channel.id, "A channel with this name already exists", "channel_exists", 409)
        channel_repo.update(channel, name=name.lower(), description=(description or "").strip())
        return {"channel": channel.to_dict()}

    def _get_by_slug(self, slug: str):
        workspace = workspace_repo.get_by_slug(slug)
        require(workspace is not None, "Workspace not found", code="not_found", status=404)
        return workspace

    def _get_channel(self, workspace, channel_id: int):
        channel = channel_repo.get(channel_id)
        require(
            channel is not None and channel.workspace_id == workspace.id,
            "Channel not found",
            code="not_found",
            status=404,
        )
        return channel


class MembershipService:
    def list(self, user, slug: str) -> dict:
        workspace = self._workspace(slug)
        require_workspace_member(workspace, user)
        members = [
            {
                "user": membership.user.to_dict() if membership.user else None,
                "role": membership.role,
                "joined_at": membership.joined_at.isoformat() + "Z",
            }
            for membership in membership_repo.list_for_workspace(workspace.id)
            if membership.user
        ]
        invitations = [
            inv.to_dict()
            for inv in invitation_repo.list_for_workspace(workspace.id)
        ]
        return {
            "workspace": workspace.to_dict(),
            "members": members,
            "invitations": invitations,
            "my_role": workspace_role(workspace, user),
        }

    def invite(self, user, slug: str, email: str, role: str) -> dict:
        workspace = self._workspace(slug)
        require_privileged(workspace, user)
        email = (email or "").strip().lower()
        role = role or ROLE_MEMBER
        require(EMAIL_RE.match(email), "A valid email address is required")
        require(role in (ROLE_OWNER, ROLE_ADMIN, ROLE_MEMBER), "Invalid role")
        target = user_repo.get_by_email(email)
        if target:
            require(
                membership_repo.get(workspace.id, target.id) is None,
                "That user is already a member of this workspace",
                code="already_member",
                status=409,
            )
        token = _deterministic_token(f"invite:{workspace.id}:{email}:{role}")
        existing = invitation_repo.get_by_token(token)
        if existing and existing.status == "pending":
            raise AppError("An invitation for this email is already pending", code="already_invited", status=409)
        if existing:
            invitation_repo.update_status(existing, "declined")
        invitation = invitation_repo.create(
            workspace.id, user.id, email, role, token, ttl_seconds=604800
        )
        link = f"{public_base_url()}/invitations/{token}"
        mailbox.send(
            email,
            f"You are invited to join {workspace.name}",
            f"Hello,\n\nYou have been invited to join the workspace "
            f'"{workspace.name}" with the role {role}.\n\n'
            f"Accept the invitation here:\n{link}\n\n"
            "This invitation expires in 7 days.",
        )
        audit_repo.create(workspace.id, user.id, "invitation_created", "invitation", invitation.id, {"email": email})
        publish(f"ws:{workspace.slug}", {"type": "invitation_created", "invitation": invitation.to_dict()})
        return {"invitation": invitation.to_dict()}

    def accept_invitation(self, user, token: str) -> dict:
        invitation = invitation_repo.get_by_token(token)
        require(
            invitation is not None,
            "Invitation not found",
            code="not_found",
            status=404,
        )
        require(invitation.status == "pending", "This invitation is no longer valid", "invitation_invalid", 400)
        require(invitation.expires_at > now(), "This invitation has expired", "invitation_expired", 400)
        workspace = workspace_repo.get(invitation.workspace_id)
        require(workspace is not None, "Workspace not found", "not_found", 404)
        require(user.email.lower() == invitation.email.lower(), "This invitation is for a different email address", "access_denied", 403)
        membership = membership_repo.create(workspace.id, user.id, invitation.role)
        invitation_repo.update_status(invitation, "accepted")
        audit_repo.create(workspace.id, user.id, "membership_accepted", "membership", membership.id, {"email": invitation.email})
        publish(f"ws:{workspace.slug}", {"type": "member_joined", "user": user.to_dict()})
        return {"workspace": workspace.to_dict(), "membership": membership.to_dict()}

    def change_role(self, actor, slug: str, target_user_id: int, role: str) -> dict:
        workspace = self._workspace(slug)
        require_privileged(workspace, actor)
        require(role in (ROLE_OWNER, ROLE_ADMIN, ROLE_MEMBER), "Invalid role")
        target = user_repo.get(target_user_id)
        require(target is not None, "User not found", "not_found", 404)
        membership = membership_repo.get(workspace.id, target.id)
        require(membership is not None, "That user is not a member of this workspace", "not_member", 404)
        if membership.role == ROLE_OWNER and role != ROLE_OWNER:
            raise AppError("The workspace owner role cannot be changed", code="owner_protected", status=403)
        if target.id == actor.id:
            require(role == ROLE_OWNER, "You cannot demote yourself", "self_role_change", 400)
        membership_repo.update_role(membership, role)
        audit_repo.create(workspace.id, actor.id, "membership_role_changed", "membership", membership.id, {"role": role})
        publish(f"ws:{workspace.slug}", {"type": "member_role_changed", "user_id": target.id, "role": role})
        return {"membership": membership.to_dict()}

    def remove_member(self, actor, slug: str, target_user_id: int) -> dict:
        workspace = self._workspace(slug)
        role = require_privileged(workspace, actor)
        target = user_repo.get(target_user_id)
        require(target is not None, "User not found", "not_found", 404)
        membership = membership_repo.get(workspace.id, target.id)
        require(membership is not None, "That user is not a member of this workspace", "not_member", 404)
        require(membership.role != ROLE_OWNER, "The workspace owner cannot be removed", "owner_protected", 403)
        if target.id == actor.id and role != ROLE_OWNER:
            raise AppError("You cannot remove yourself", "self_remove", 400)
        membership_repo.delete(membership)
        channel_member_repo.remove_user_from_workspace_channels(workspace.id, target.id)
        audit_repo.create(workspace.id, actor.id, "member_removed", "membership", membership.id, {"user_id": target.id})
        publish(f"ws:{workspace.slug}", {"type": "member_removed", "user_id": target.id})
        return {"ok": True, "user_id": target.id}

    def _workspace(self, slug: str):
        workspace = workspace_repo.get_by_slug(slug)
        require(workspace is not None, "Workspace not found", code="not_found", status=404)
        return workspace


class MessagingService:
    def list(self, user, slug: str, channel_id: int, topic_id: int | None = None, before_id: int | None = None, limit: int = 50) -> dict:
        workspace = self._workspace(slug)
        require_workspace_member(workspace, user)
        channel = self._channel(workspace, channel_id)
        require_channel_access(channel, user)
        if topic_id is not None:
            topic = topic_repo.get(topic_id)
            require(topic is not None and topic.channel_id == channel.id, "Topic not found", "not_found", 404)
        messages = message_repo.list_for_channel(channel.id, topic_id=topic_id, before_id=before_id, limit=limit)
        topics = [t.to_dict() for t in topic_repo.list_for_channel(channel.id)]
        attachments = {}
        for msg in messages:
            items = [a.to_dict() for a in attachment_repo.list_for_message(msg.id)]
            if items:
                attachments[msg.id] = items
        return {
            "workspace": workspace.to_dict(),
            "channel": channel.to_dict(),
            "topics": topics,
            "messages": [m.to_dict() for m in messages],
            "attachments": attachments,
        }

    def send(self, user, slug: str, channel_id: int, body: str, client_msg_id: str | None = None, topic_id: int | None = None) -> dict:
        workspace = self._workspace(slug)
        require_workspace_member(workspace, user)
        channel = self._channel(workspace, channel_id)
        require_channel_access(channel, user)
        body = (body or "").strip()
        require(body, "Message body is required")
        if topic_id is not None:
            topic = topic_repo.get(topic_id)
            require(topic is not None and topic.channel_id == channel.id, "Topic not found", "not_found", 404)
        if client_msg_id:
            existing = message_repo.get_by_client_id(user.id, client_msg_id)
            if existing:
                return {"message": existing.to_dict(), "duplicate": True, "delivery": DELIVERY_SENT}
        message = message_repo.create(workspace.id, channel.id, user.id, body, client_msg_id or None, topic_id=topic_id)
        delivery_repo.create(user.id, message_id=message.id, status=DELIVERY_SENT, client_msg_id=client_msg_id)
        payload = {"type": "message_new", "message": message.to_dict()}
        publish(f"ch:{channel.id}", payload)
        publish(f"ws:{workspace.slug}", payload)
        return {"message": message.to_dict(), "duplicate": False, "delivery": DELIVERY_SENT}

    def edit(self, user, slug: str, message_id: int, body: str) -> dict:
        workspace = self._workspace(slug)
        require_workspace_member(workspace, user)
        message = self._message(workspace, message_id)
        require(message.sender_id == user.id, "Only the author can edit this message", "access_denied", 403)
        body = (body or "").strip()
        require(body, "Message body is required")
        message_repo.update_body(message, body)
        payload = {"type": "message_edited", "message": message.to_dict()}
        publish(f"ch:{message.channel_id}", payload)
        publish(f"ws:{workspace.slug}", payload)
        return {"message": message.to_dict()}

    def delete(self, user, slug: str, message_id: int) -> dict:
        workspace = self._workspace(slug)
        role = require_workspace_member(workspace, user)
        message = self._message(workspace, message_id)
        if message.sender_id != user.id and role not in (ROLE_OWNER, ROLE_ADMIN):
            raise AppError("Only the author or a privileged user can delete this message", "access_denied", 403)
        message_repo.mark_deleted(message)
        payload = {"type": "message_deleted", "message_id": message.id, "channel_id": message.channel_id}
        publish(f"ch:{message.channel_id}", payload)
        publish(f"ws:{workspace.slug}", payload)
        return {"ok": True, "message_id": message.id}

    def _workspace(self, slug: str):
        workspace = workspace_repo.get_by_slug(slug)
        require(workspace is not None, "Workspace not found", code="not_found", status=404)
        return workspace

    def _channel(self, workspace, channel_id: int):
        channel = channel_repo.get(channel_id)
        require(channel is not None and channel.workspace_id == workspace.id, "Channel not found", "not_found", 404)
        return channel

    def _message(self, workspace, message_id: int):
        message = message_repo.get(message_id)
        require(message is not None and message.workspace_id == workspace.id, "Message not found", "not_found", 404)
        return message


class SearchService:
    def search(self, user, slug: str | None, keyword: str, date_from: str | None = None, date_to: str | None = None, include_dms: bool = False, limit: int = 100) -> dict:
        keyword = (keyword or "").strip()
        require(keyword, "A search query is required")
        date_from_dt = self._parse_date(date_from)
        date_to_dt = self._parse_date(date_to)
        date_to_dt = (date_to_dt + dt.timedelta(days=1) - dt.timedelta(seconds=1)) if date_to_dt else None
        channel_matches: list[dict] = []
        workspace_matches: list[dict] = []
        if slug:
            workspace = workspace_repo.get_by_slug(slug)
            require(workspace is not None, "Workspace not found", "not_found", 404)
            role = require_workspace_member(workspace, user)
            channel_ids = visible_channel_ids(workspace, user)
            for message in message_repo.search(keyword, workspace.id, channel_ids, date_from_dt, date_to_dt, limit):
                channel_matches.append(message.to_dict())
            workspace_matches.append({"workspace": workspace.to_dict(), "role": role, "matches": channel_matches})
        else:
            for workspace in workspace_repo.list_for_user(user.id):
                channel_ids = visible_channel_ids(workspace, user)
                matches = [
                    m.to_dict()
                    for m in message_repo.search(keyword, workspace.id, channel_ids, date_from_dt, date_to_dt, limit)
                ]
                if matches:
                    workspace_matches.append(
                        {"workspace": workspace.to_dict(), "role": workspace_role(workspace, user), "matches": matches}
                    )
        dm_matches: list[dict] = []
        if include_dms:
            for thread in dm_repo.list_threads_for_user(user.id):
                for dm in dm_repo.list_for_thread(thread.id):
                    if keyword.lower() in (dm.body or "").lower() and not dm.is_deleted:
                        if date_from_dt and dm.created_at < date_from_dt:
                            continue
                        if date_to_dt and dm.created_at > date_to_dt:
                            continue
                        dm_matches.append(dm.to_dict())
        return {
            "query": keyword,
            "date_from": date_from,
            "date_to": date_to,
            "channel_results": workspace_matches,
            "dm_results": dm_matches,
            "total": sum(len(w["matches"]) for w in workspace_matches) + len(dm_matches),
        }

    def save(self, user, name: str, query: str) -> dict:
        name = (name or "").strip()
        require(name, "A name for the saved search is required")
        saved = saved_search_repo.create(user.id, name, (query or "").strip())
        return {"saved_search": saved.to_dict()}

    def list_saved(self, user) -> dict:
        return {"saved_searches": [s.to_dict() for s in saved_search_repo.list_for_user(user.id)]}

    def update_saved(self, user, search_id: int, name: str, query: str) -> dict:
        search = saved_search_repo.get(search_id)
        require(search is not None and search.user_id == user.id, "Saved search not found", "not_found", 404)
        saved_search_repo.update(search, name=(name or "").strip(), query=(query or "").strip())
        return {"saved_search": search.to_dict()}

    def delete_saved(self, user, search_id: int) -> dict:
        search = saved_search_repo.get(search_id)
        require(search is not None and search.user_id == user.id, "Saved search not found", "not_found", 404)
        saved_search_repo.delete(search)
        return {"ok": True, "search_id": search_id}

    def _parse_date(self, value: str | None) -> dt.datetime | None:
        if not value:
            return None
        try:
            return dt.datetime.strptime(value, "%Y-%m-%d")
        except ValueError:
            raise AppError("Dates must use the YYYY-MM-DD format", "invalid_date")


class DMService:
    def list_threads(self, user) -> dict:
        threads = []
        for thread in dm_repo.list_threads_for_user(user.id):
            threads.append(thread_dict(thread, user.id, dm_repo))
        return {"threads": threads}

    def list_messages(self, user, thread_id: int) -> dict:
        thread = dm_repo.get_thread(thread_id)
        require(thread is not None, "Thread not found", "not_found", 404)
        require(user.id in (thread.user_a_id, thread.user_b_id), "You cannot access this conversation", "access_denied", 403)
        other_id = thread.user_b_id if thread.user_a_id == user.id else thread.user_a_id
        other = user_repo.get(other_id)
        dm_repo.mark_thread_read(thread.id, user.id)
        return {
            "thread": thread.to_dict(),
            "other": other.to_dict() if other else None,
            "messages": [dm.to_dict() for dm in dm_repo.list_for_thread(thread.id)],
        }

    def send(self, user, recipient_username: str, body: str, client_msg_id: str | None = None) -> dict:
        body = (body or "").strip()
        require(body, "Message body is required")
        require(recipient_username, "A recipient is required")
        recipient = user_repo.get_by_username(recipient_username)
        require(recipient is not None, "Recipient not found", "not_found", 404)
        require(recipient.id != user.id, "You cannot send a message to yourself", "self_message", 400)
        if client_msg_id:
            existing = dm_repo.get_dm_by_client_id(user.id, client_msg_id)
            if existing:
                return {"direct_message": existing.to_dict(), "duplicate": True, "delivery": DELIVERY_SENT}
        thread = dm_repo.get_or_create_thread(user.id, recipient.id)
        dm = dm_repo.create_dm(thread.id, user.id, body, client_msg_id or None)
        dm_repo.mark_delivered(dm)
        delivery_repo.create(user.id, direct_message_id=dm.id, status=DELIVERY_SENT, client_msg_id=client_msg_id)
        payload = {"type": "dm_new", "direct_message": dm.to_dict(), "thread_id": thread.id}
        publish(f"dm:{thread.id}", payload)
        hub().publish_to_user(recipient.id, payload)
        return {"direct_message": dm.to_dict(), "thread_id": thread.id, "duplicate": False, "delivery": DELIVERY_SENT}

    def edit(self, user, dm_id: int, body: str) -> dict:
        dm = dm_repo.get_dm(dm_id)
        require(dm is not None, "Message not found", "not_found", 404)
        require(dm.sender_id == user.id, "Only the author can edit this message", "access_denied", 403)
        body = (body or "").strip()
        require(body, "Message body is required")
        dm_repo.update_dm_body(dm, body)
        payload = {"type": "dm_edited", "direct_message": dm.to_dict(), "thread_id": dm.thread_id}
        publish(f"dm:{dm.thread_id}", payload)
        return {"direct_message": dm.to_dict()}

    def delete(self, user, dm_id: int) -> dict:
        dm = dm_repo.get_dm(dm_id)
        require(dm is not None, "Message not found", "not_found", 404)
        require(dm.sender_id == user.id, "Only the author can delete this message", "access_denied", 403)
        dm_repo.mark_dm_deleted(dm)
        publish(f"dm:{dm.thread_id}", {"type": "dm_deleted", "direct_message_id": dm.id, "thread_id": dm.thread_id})
        return {"ok": True, "direct_message_id": dm.id}

    def mark_read(self, user, thread_id: int) -> dict:
        thread = dm_repo.get_thread(thread_id)
        require(thread is not None, "Thread not found", "not_found", 404)
        require(user.id in (thread.user_a_id, thread.user_b_id), "Access denied", "access_denied", 403)
        count = dm_repo.mark_thread_read(thread.id, user.id)
        return {"ok": True, "read": count}


class AttachmentService:
    MAX_SIZE = 16 * 1024 * 1024

    def upload(self, user, file_storage, is_private: bool = False, message_id: int | None = None, direct_message_id: int | None = None) -> dict:
        require(file_storage is not None, "No file was uploaded", "missing_file")
        filename = (file_storage.filename or "").strip()
        require(filename, "No file was uploaded", "missing_file")
        require(filename.count(".") >= 1, "File must have an extension", "invalid_file_type")
        extension = "." + filename.rsplit(".", 1)[1].lower()
        require(
            extension in storage.ALLOWED_EXTENSIONS,
            "File type is not allowed",
            "invalid_file_type",
        )
        data = file_storage.read()
        require(data, "The uploaded file is empty", "empty_file")
        require(len(data) <= self.MAX_SIZE, "The uploaded file is too large", "file_too_large", 413)
        if message_id is not None:
            message = message_repo.get(message_id)
            require(message is not None, "Message not found", "not_found", 404)
            require(message.sender_id == user.id, "You can only attach files to your own messages", "access_denied", 403)
        result = storage.save(filename, data)
        stored = stored_file_repo.create(
            result["stored_name"], filename, result["sha256"], result["size"], file_storage.mimetype or "application/octet-stream"
        )
        attachment = attachment_repo.create(
            owner_id=user.id,
            stored_file_id=stored.id,
            filename=filename,
            content_type=file_storage.mimetype or "application/octet-stream",
            size=result["size"],
            message_id=message_id,
            direct_message_id=direct_message_id,
            is_private=bool(is_private),
        )
        return {"attachment": attachment.to_dict()}

    def list_for(self, user) -> dict:
        return {"attachments": [a.to_dict() for a in attachment_repo.list_for_user(user.id)]}

    def download(self, user, attachment_id: int) -> dict:
        attachment = attachment_repo.get(attachment_id)
        require(attachment is not None, "Attachment not found", "not_found", 404)
        self._assert_access(attachment, user)
        path = storage.open(attachment.stored_file.stored_name)
        require(path.exists(), "Stored file is missing", "storage_unavailable", 500)
        return {"attachment": attachment, "path": path}

    def update_metadata(self, user, attachment_id: int, is_private: bool | None = None) -> dict:
        attachment = attachment_repo.get(attachment_id)
        require(attachment is not None, "Attachment not found", "not_found", 404)
        require(attachment.owner_id == user.id, "You can only modify your own attachments", "access_denied", 403)
        if is_private is not None:
            attachment_repo.update(attachment, is_private=bool(is_private))
        return {"attachment": attachment.to_dict()}

    def upload_metadata(
        self,
        user,
        message_id: int | None,
        direct_message_id: int | None,
        filename: str,
        size: int,
        content_type: str,
        is_private: bool,
    ) -> dict:
        require(filename, "A filename is required", "missing_file")
        require(size is not None and size > 0, "A positive size is required", "validation_error")
        stored = stored_file_repo.create(
            f"metadata-{user.id}-{message_id or 0}-{direct_message_id or 0}",
            filename,
            "0" * 64,
            size,
            content_type or "application/octet-stream",
        )
        attachment = attachment_repo.create(
            owner_id=user.id,
            stored_file_id=stored.id,
            filename=filename,
            content_type=content_type or "application/octet-stream",
            size=size,
            message_id=message_id,
            direct_message_id=direct_message_id,
            is_private=is_private,
        )
        return {"attachment": attachment.to_dict()}

    def get_metadata(self, user, attachment_id: int) -> dict:
        attachment = attachment_repo.get(attachment_id)
        require(attachment is not None, "Attachment not found", "not_found", 404)
        self._assert_access(attachment, user)
        return {"attachment": attachment.to_dict()}

    def _assert_access(self, attachment, user) -> None:
        if attachment.is_private:
            require(attachment.owner_id == user.id, "You cannot access another user's private file", "access_denied", 403)
            return
        if attachment.message_id is not None:
            message = message_repo.get(attachment.message_id)
            if message is not None:
                workspace = workspace_repo.get(message.workspace_id)
                if workspace and workspace_role(workspace, user) is not None:
                    channel = channel_repo.get(message.channel_id)
                    if channel and channel_accessible(channel, user):
                        return
        if attachment.owner_id == user.id:
            return
        raise AppError("You do not have access to this file", "access_denied", 403)


class LinkPreviewService:
    def fetch(self, user, url: str) -> dict:
        url = (url or "").strip()
        require(url, "A URL is required")
        require(URL_RE.match(url), "URL must start with http:// or https://", "invalid_url")
        preview = link_preview_repo.get_by_url(url)
        if preview is None:
            metadata = link_preview_fetcher.fetch(url)
            preview = link_preview_repo.create(
                url,
                metadata["title"],
                metadata["description"],
                metadata["site_name"],
                metadata.get("image_url"),
                created_by_id=user.id if user else None,
            )
        return {"preview": preview.to_dict()}

    def list_recent(self, user) -> dict:
        return {"previews": [p.to_dict() for p in link_preview_repo.list_recent(50)]}

    def update(self, user, preview_id: int, title: str, description: str, site_name: str) -> dict:
        preview = link_preview_repo.get(preview_id)
        require(preview is not None, "Preview not found", "not_found", 404)
        link_preview_repo.update(
            preview,
            title=(title or "").strip(),
            description=(description or "").strip(),
            site_name=(site_name or "").strip(),
        )
        return {"preview": preview.to_dict()}


class ChannelManagementService:
    def list(self, user, slug: str) -> dict:
        workspace = self._workspace(slug)
        require_workspace_member(workspace, user)
        channels = [ch.to_dict() for ch in channel_repo.list_for_workspace(workspace.id)]
        audit = [e.to_dict() for e in audit_repo.list_for_workspace(workspace.id)]
        return {
            "workspace": workspace.to_dict(),
            "channels": channels,
            "audit": audit,
            "my_role": workspace_role(workspace, user),
        }

    def rename(self, user, slug: str, channel_id: int, name: str) -> dict:
        workspace = self._workspace(slug)
        require_privileged(workspace, user)
        channel = self._channel(workspace, channel_id)
        name = (name or "").strip().lower()
        require(name, "Channel name is required")
        existing = channel_repo.get_by_workspace_and_name(workspace.id, name)
        require(existing is None or existing.id == channel.id, "A channel with this name already exists", "channel_exists", 409)
        old_name = channel.name
        channel_repo.update(channel, name=name)
        audit_repo.create(workspace.id, user.id, "channel_renamed", "channel", channel.id, {"old": old_name, "new": name})
        publish(f"ws:{workspace.slug}", {"type": "channel_updated", "channel": channel.to_dict()})
        return {"channel": channel.to_dict()}

    def archive(self, user, slug: str, channel_id: int, archived: bool) -> dict:
        workspace = self._workspace(slug)
        require_privileged(workspace, user)
        channel = self._channel(workspace, channel_id)
        channel_repo.update(channel, archived=bool(archived))
        audit_repo.create(workspace.id, user.id, "channel_archived" if archived else "channel_unarchived", "channel", channel.id)
        publish(f"ws:{workspace.slug}", {"type": "channel_updated", "channel": channel.to_dict()})
        return {"channel": channel.to_dict()}

    def configure(self, user, slug: str, channel_id: int, description: str | None = None, visibility: str | None = None) -> dict:
        workspace = self._workspace(slug)
        require_privileged(workspace, user)
        channel = self._channel(workspace, channel_id)
        fields = {}
        if description is not None:
            fields["description"] = (description or "").strip()
        if visibility is not None:
            require(visibility in (VISIBILITY_PUBLIC, VISIBILITY_PRIVATE), "Visibility must be public or private")
            fields["visibility"] = visibility
        if fields:
            channel_repo.update(channel, **fields)
        audit_repo.create(workspace.id, user.id, "channel_configured", "channel", channel.id, fields)
        publish(f"ws:{workspace.slug}", {"type": "channel_updated", "channel": channel.to_dict()})
        return {"channel": channel.to_dict()}

    def audit(self, user, slug: str) -> dict:
        workspace = self._workspace(slug)
        require_privileged(workspace, user)
        return {"workspace": workspace.to_dict(), "audit": [e.to_dict() for e in audit_repo.list_for_workspace(workspace.id)]}

    def _workspace(self, slug: str):
        workspace = workspace_repo.get_by_slug(slug)
        require(workspace is not None, "Workspace not found", code="not_found", status=404)
        return workspace

    def _channel(self, workspace, channel_id: int):
        channel = channel_repo.get(channel_id)
        require(channel is not None and channel.workspace_id == workspace.id, "Channel not found", "not_found", 404)
        return channel


class ConnectionService:
    def record_event(self, user, event_type: str, session_id: str = "", message_id: int | None = None, payload: dict | None = None) -> dict:
        require(event_type in ("connect", "disconnect", "reconnect", "duplicate", "retry", "delivered", "read", "failed"), "Unknown event type")
        event = connection_event_repo.create(user.id, event_type, session_id=session_id, message_id=message_id, payload=payload or {})
        if event_type in ("delivered", "read", "failed") and message_id is not None:
            message = message_repo.get(message_id)
            if message and message.workspace_id:
                channel = channel_repo.get(message.channel_id)
                if channel and channel_accessible(channel, user):
                    if event_type == "read":
                        delivery_repo.mark_read(message.id, user.id)
                    elif event_type == "delivered":
                        delivery_repo.mark_delivered(message.id, user.id)
        return {"event": event.to_dict()}

    def get_state(self, user) -> dict:
        deliveries = [d.to_dict() for d in delivery_repo.list_for_user(user.id)]
        events = [e.to_dict() for e in connection_event_repo.list_for_user(user.id)]
        counts = {"sent": 0, "delivered": 0, "read": 0, "failed": 0}
        for record in deliveries:
            counts[record["status"]] = counts.get(record["status"], 0) + 1
        return {
            "delivery_records": deliveries,
            "connection_events": events,
            "delivery_counts": counts,
            "online_user_ids": sorted(hub().online_user_ids()),
        }


class ErrorService:
    def report(self, user, code: str, message: str, path: str = "", details: dict | None = None) -> dict:
        require(code, "Error code is required")
        require(message, "Error message is required")
        record = error_repo.create(user.id if user else None, (code or "").strip(), (message or "").strip(), path or "", details or {})
        return {"error_record": record.to_dict()}

    def list(self, user) -> dict:
        return {"error_records": [e.to_dict() for e in error_repo.list_for_user(user.id if user else None)]}

    def acknowledge(self, user, record_id: int) -> dict:
        record = error_repo.get(record_id)
        require(record is not None, "Error record not found", "not_found", 404)
        if record.user_id is not None and user and record.user_id != user.id:
            raise AppError("You can only acknowledge your own error records", "access_denied", 403)
        error_repo.acknowledge(record)
        return {"error_record": record.to_dict()}


class FrontendService:
    def config(self, user) -> dict:
        scheme = "ws" if request.scheme == "http" else "wss"
        return {
            "ws_url": f"{scheme}://{request.host}/ws/chat",
            "current_user": user.to_dict() if user else None,
            "server_time": now().isoformat() + "Z",
            "features": {
                "realtime": True,
                "typing_indicators": True,
                "presence": True,
                "link_preview": True,
                "attachments": True,
                "search": True,
            },
            "limits": {
                "max_upload_bytes": current_app.config["MAX_UPLOAD_BYTES"],
                "max_message_length": 10000,
            },
        }

    def record(self, user, event_type: str, payload: dict | None = None) -> dict:
        require(event_type, "Event type is required")
        event = frontend_event_repo.create(user.id if user else None, (event_type or "").strip()[:60], payload or {})
        return {"frontend_event": event.to_dict()}

    def list(self, user) -> dict:
        return {"frontend_events": [e.to_dict() for e in frontend_event_repo.list_for_user(user.id if user else None)]}

    def acknowledge(self, user, event_id: int) -> dict:
        event = frontend_event_repo.get(event_id)
        require(event is not None, "Event not found", "not_found", 404)
        if event.user_id is not None and user and event.user_id != user.id:
            raise AppError("You can only acknowledge your own events", "access_denied", 403)
        frontend_event_repo.acknowledge(event)
        return {"frontend_event": event.to_dict()}


auth_service = AuthService()
workspace_service = WorkspaceService()
membership_service = MembershipService()
messaging_service = MessagingService()
search_service = SearchService()
dm_service = DMService()
attachment_service = AttachmentService()
link_preview_service = LinkPreviewService()
channel_management_service = ChannelManagementService()
connection_service = ConnectionService()
error_service = ErrorService()
frontend_service = FrontendService()
