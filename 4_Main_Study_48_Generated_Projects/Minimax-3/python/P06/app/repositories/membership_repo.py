from __future__ import annotations

from typing import Optional

from sqlalchemy import select
from sqlalchemy.orm import selectinload

from ..db import SessionLocal
from ..models import Invitation, Membership, User


class MembershipRepository:
    @staticmethod
    def by_user_workspace(user_id: int, workspace_id: int) -> Optional[Membership]:
        with SessionLocal() as session:
            return session.execute(
                select(Membership).where(
                    Membership.user_id == user_id,
                    Membership.workspace_id == workspace_id,
                )
            ).scalar_one_or_none()

    @staticmethod
    def list_for_workspace(workspace_id: int) -> list[Membership]:
        with SessionLocal() as session:
            stmt = (
                select(Membership)
                .where(Membership.workspace_id == workspace_id)
                .options(selectinload(Membership.user))
                .order_by(Membership.invited_at)
            )
            return list(session.execute(stmt).scalars())

    @staticmethod
    def list_for_user(user_id: int) -> list[Membership]:
        with SessionLocal() as session:
            return list(
                session.execute(
                    select(Membership).where(Membership.user_id == user_id)
                ).scalars()
            )

    @staticmethod
    def create(
        user_id: int,
        workspace_id: int,
        role: str,
        invited_by: Optional[int],
    ) -> Membership:
        with SessionLocal() as session:
            member = Membership(
                user_id=user_id,
                workspace_id=workspace_id,
                role=role,
                invited_by=invited_by,
            )
            session.add(member)
            session.commit()
            session.refresh(member)
            session.expunge(member)
            return member

    @staticmethod
    def update_role(user_id: int, workspace_id: int, role: str) -> Optional[Membership]:
        with SessionLocal() as session:
            member = session.execute(
                select(Membership).where(
                    Membership.user_id == user_id,
                    Membership.workspace_id == workspace_id,
                )
            ).scalar_one_or_none()
            if member is None:
                return None
            member.role = role
            session.commit()
            session.refresh(member)
            session.expunge(member)
            return member

    @staticmethod
    def update_status(
        user_id: int, workspace_id: int, status: str
    ) -> Optional[Membership]:
        with SessionLocal() as session:
            member = session.execute(
                select(Membership).where(
                    Membership.user_id == user_id,
                    Membership.workspace_id == workspace_id,
                )
            ).scalar_one_or_none()
            if member is None:
                return None
            member.status = status
            session.commit()
            session.refresh(member)
            session.expunge(member)
            return member


class InvitationRepository:
    @staticmethod
    def by_token(token: str) -> Optional[Invitation]:
        with SessionLocal() as session:
            return session.execute(
                select(Invitation).where(Invitation.token == token)
            ).scalar_one_or_none()

    @staticmethod
    def list_for_workspace(workspace_id: int) -> list[Invitation]:
        with SessionLocal() as session:
            return list(
                session.execute(
                    select(Invitation)
                    .where(Invitation.workspace_id == workspace_id)
                    .order_by(Invitation.created_at.desc())
                ).scalars()
            )

    @staticmethod
    def create(
        workspace_id: int,
        email: str,
        token: str,
        proposed_role: str,
        invited_by: int,
    ) -> Invitation:
        with SessionLocal() as session:
            inv = Invitation(
                workspace_id=workspace_id,
                email=email.lower().strip(),
                token=token,
                proposed_role=proposed_role,
                invited_by=invited_by,
            )
            session.add(inv)
            session.commit()
            session.refresh(inv)
            session.expunge(inv)
            return inv

    @staticmethod
    def update_status(invitation_id: int, status: str, responded_at) -> Optional[Invitation]:
        with SessionLocal() as session:
            inv = session.get(Invitation, invitation_id)
            if inv is None:
                return None
            inv.status = status
            inv.responded_at = responded_at
            session.commit()
            session.refresh(inv)
            session.expunge(inv)
            return inv
