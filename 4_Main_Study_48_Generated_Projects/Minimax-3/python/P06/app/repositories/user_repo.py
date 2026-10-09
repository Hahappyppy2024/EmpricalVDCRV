from __future__ import annotations

from typing import Optional

from sqlalchemy import select

from ..db import SessionLocal
from ..models import User, SessionRecord


class UserRepository:
    @staticmethod
    def by_email(email: str) -> Optional[User]:
        with SessionLocal() as session:
            return session.execute(
                select(User).where(User.email == email.lower().strip())
            ).scalar_one_or_none()

    @staticmethod
    def by_username(username: str) -> Optional[User]:
        with SessionLocal() as session:
            return session.execute(
                select(User).where(User.username == username.strip())
            ).scalar_one_or_none()

    @staticmethod
    def by_id(user_id: int) -> Optional[User]:
        with SessionLocal() as session:
            return session.get(User, user_id)

    @staticmethod
    def by_reset_token(token: str) -> Optional[User]:
        with SessionLocal() as session:
            return session.execute(
                select(User).where(User.reset_token == token)
            ).scalar_one_or_none()

    @staticmethod
    def list_all() -> list[User]:
        with SessionLocal() as session:
            return list(session.execute(select(User).order_by(User.username)).scalars())

    @staticmethod
    def create(
        email: str,
        username: str,
        display_name: str,
        password_hash: str,
        is_admin: bool = False,
        avatar_seed: Optional[str] = None,
    ) -> User:
        with SessionLocal() as session:
            user = User(
                email=email.lower().strip(),
                username=username.strip(),
                display_name=display_name.strip(),
                password_hash=password_hash,
                is_admin=is_admin,
                avatar_seed=avatar_seed or username.strip(),
            )
            session.add(user)
            session.commit()
            session.refresh(user)
            session.expunge(user)
            return user

    @staticmethod
    def update_profile(
        user_id: int,
        display_name: Optional[str] = None,
        avatar_seed: Optional[str] = None,
    ) -> Optional[User]:
        with SessionLocal() as session:
            user = session.get(User, user_id)
            if user is None:
                return None
            if display_name is not None:
                user.display_name = display_name.strip()
            if avatar_seed is not None:
                user.avatar_seed = avatar_seed.strip()
            session.commit()
            session.refresh(user)
            session.expunge(user)
            return user

    @staticmethod
    def set_password_hash(user_id: int, password_hash: str) -> Optional[User]:
        with SessionLocal() as session:
            user = session.get(User, user_id)
            if user is None:
                return None
            user.password_hash = password_hash
            user.reset_token = None
            user.reset_expires_at = None
            session.commit()
            session.refresh(user)
            session.expunge(user)
            return user

    @staticmethod
    def set_reset_token(user_id: int, token: str, expires_at) -> Optional[User]:
        with SessionLocal() as session:
            user = session.get(User, user_id)
            if user is None:
                return None
            user.reset_token = token
            user.reset_expires_at = expires_at
            session.commit()
            session.refresh(user)
            session.expunge(user)
            return user


class SessionRepository:
    @staticmethod
    def create(
        session_id: str,
        user_id: int,
        ip_address: str,
        user_agent: str,
        expires_at,
    ) -> SessionRecord:
        with SessionLocal() as session:
            record = SessionRecord(
                session_id=session_id,
                user_id=user_id,
                ip_address=ip_address,
                user_agent=user_agent,
                expires_at=expires_at,
            )
            session.add(record)
            session.commit()
            session.refresh(record)
            session.expunge(record)
            return record

    @staticmethod
    def by_session_id(session_id: str) -> Optional[SessionRecord]:
        with SessionLocal() as session:
            return session.execute(
                select(SessionRecord).where(SessionRecord.session_id == session_id)
            ).scalar_one_or_none()

    @staticmethod
    def revoke(session_id: str) -> bool:
        with SessionLocal() as session:
            record = session.execute(
                select(SessionRecord).where(SessionRecord.session_id == session_id)
            ).scalar_one_or_none()
            if record is None:
                return False
            record.revoked = True
            session.commit()
            return True

    @staticmethod
    def list_for_user(user_id: int) -> list[SessionRecord]:
        with SessionLocal() as session:
            return list(
                session.execute(
                    select(SessionRecord)
                    .where(SessionRecord.user_id == user_id)
                    .order_by(SessionRecord.created_at.desc())
                ).scalars()
            )
