from __future__ import annotations

from datetime import datetime, timezone

from ..errors import ConflictError, ForbiddenError, NotFoundError, ValidationError
from ..repositories import (
    InvitationRepository,
    MembershipRepository,
    UserRepository,
    WorkspaceRepository,
)
from .session_service import require_user

VALID_ROLES = {"owner", "admin", "member", "guest"}


def list_members(workspace_id: int) -> list[dict]:
    user = require_user()
    ws = WorkspaceRepository.by_id(workspace_id)
    if ws is None:
        raise NotFoundError("Workspace not found.")
    member = MembershipRepository.by_user_workspace(user.id, workspace_id)
    if member is None or member.status != "active":
        raise ForbiddenError("Not a workspace member.")
    members = MembershipRepository.list_for_workspace(workspace_id)
    return [_serialize_member(m) for m in members]


def list_invitations(workspace_id: int) -> list[dict]:
    user = require_user()
    _ensure_owner_or_admin(workspace_id, user.id)
    invitations = InvitationRepository.list_for_workspace(workspace_id)
    return [_serialize_invitation(i) for i in invitations]


def invite_member(workspace_id: int, payload: dict) -> dict:
    user = require_user()
    ws = WorkspaceRepository.by_id(workspace_id)
    if ws is None:
        raise NotFoundError("Workspace not found.")
    _ensure_owner_or_admin(workspace_id, user.id)
    email = (payload.get("email") or "").strip().lower()
    if not email or "@" not in email:
        raise ValidationError("A valid invitation email is required.")
    if UserRepository.by_email(email):
        existing = MembershipRepository.by_user_workspace(
            UserRepository.by_email(email).id, workspace_id
        )
        if existing and existing.status == "active":
            raise ConflictError("User is already an active member.")
    role = payload.get("role", "member")
    if role not in VALID_ROLES:
        raise ValidationError(f"Invalid role. Allowed: {sorted(VALID_ROLES)}")
    import secrets

    token = secrets.token_urlsafe(20)
    invitation = InvitationRepository.create(
        workspace_id=workspace_id,
        email=email,
        token=token,
        proposed_role=role,
        invited_by=user.id,
    )
    return _serialize_invitation(invitation)


def respond_invitation(token: str, action: str) -> dict:
    user = require_user()
    invitation = InvitationRepository.by_token(token)
    if invitation is None or invitation.status != "pending":
        raise NotFoundError("Invitation not found or already responded.")
    if action == "decline":
        InvitationRepository.update_status(
            invitation.id, "declined", datetime.now(timezone.utc)
        )
        return {"status": "declined"}
    if action != "accept":
        raise ValidationError("Action must be 'accept' or 'decline'.")
    user_email = user.email.lower()
    if user_email != invitation.email.lower():
        raise ForbiddenError("Invitation is for a different email address.")
    existing = MembershipRepository.by_user_workspace(user.id, invitation.workspace_id)
    if existing and existing.status == "active":
        InvitationRepository.update_status(
            invitation.id, "accepted", datetime.now(timezone.utc)
        )
        return _serialize_member(existing)
    member = MembershipRepository.create(
        user_id=user.id,
        workspace_id=invitation.workspace_id,
        role=invitation.proposed_role,
        invited_by=invitation.invited_by,
    )
    InvitationRepository.update_status(
        invitation.id, "accepted", datetime.now(timezone.utc)
    )
    return _serialize_member(member)


def change_role(workspace_id: int, target_user_id: int, new_role: str) -> dict:
    user = require_user()
    _ensure_owner_or_admin(workspace_id, user.id)
    if new_role not in VALID_ROLES:
        raise ValidationError(f"Invalid role. Allowed: {sorted(VALID_ROLES)}")
    target = UserRepository.by_id(target_user_id)
    if target is None:
        raise NotFoundError("Target user not found.")
    member = MembershipRepository.by_user_workspace(target_user_id, workspace_id)
    if member is None:
        raise NotFoundError("Target user is not in the workspace.")
    if member.role == "owner" and new_role != "owner":
        ws = WorkspaceRepository.by_id(workspace_id)
        owners = [
            m for m in MembershipRepository.list_for_workspace(workspace_id) if m.role == "owner" and m.status == "active"
        ]
        if len(owners) <= 1:
            raise ConflictError("Cannot remove the last owner.")
    updated = MembershipRepository.update_role(target_user_id, workspace_id, new_role)
    return _serialize_member(updated)


def remove_member(workspace_id: int, target_user_id: int) -> dict:
    user = require_user()
    _ensure_owner_or_admin(workspace_id, user.id)
    if target_user_id == user.id:
        raise ValidationError("Use leave_workspace to remove yourself.")
    target = MembershipRepository.by_user_workspace(target_user_id, workspace_id)
    if target is None:
        raise NotFoundError("Membership not found.")
    if target.role == "owner":
        raise ForbiddenError("Owners cannot be removed directly. Transfer ownership first.")
    MembershipRepository.update_status(target_user_id, workspace_id, "removed")
    return {"status": "removed", "user_id": target_user_id, "workspace_id": workspace_id}


def leave_workspace(workspace_id: int) -> dict:
    user = require_user()
    member = MembershipRepository.by_user_workspace(user.id, workspace_id)
    if member is None:
        raise NotFoundError("Membership not found.")
    if member.role == "owner":
        ws = WorkspaceRepository.by_id(workspace_id)
        if ws.owner_id == user.id:
            raise ConflictError("Owner cannot leave workspace; delete it instead.")
    MembershipRepository.update_status(user.id, workspace_id, "removed")
    return {"status": "left", "workspace_id": workspace_id}


def _ensure_owner_or_admin(workspace_id: int, user_id: int) -> None:
    member = MembershipRepository.by_user_workspace(user_id, workspace_id)
    if member is None or member.status != "active":
        raise ForbiddenError("Not a workspace member.")
    if member.role not in ("owner", "admin"):
        raise ForbiddenError("Only owners or admins may perform this action.")


def _serialize_member(m) -> dict:
    return {
        "id": m.id,
        "user_id": m.user_id,
        "workspace_id": m.workspace_id,
        "role": m.role,
        "status": m.status,
        "invited_by": m.invited_by,
        "invited_at": m.invited_at.isoformat() if m.invited_at else None,
    }


def _serialize_invitation(i) -> dict:
    return {
        "id": i.id,
        "workspace_id": i.workspace_id,
        "email": i.email,
        "token": i.token,
        "proposed_role": i.proposed_role,
        "status": i.status,
        "invited_by": i.invited_by,
        "created_at": i.created_at.isoformat() if i.created_at else None,
        "responded_at": i.responded_at.isoformat() if i.responded_at else None,
    }
