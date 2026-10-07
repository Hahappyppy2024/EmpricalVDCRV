from __future__ import annotations

import hashlib
import secrets
from datetime import datetime, timedelta, timezone

from database import get_db_connection


class InviteTokenRepository:
    """Repository for course invite tokens.

    Stores only SHA-256(token) in DB (hex). Tokens are single-use and expire.
    """

    @staticmethod
    def _hash_token(token: str) -> str:
        return hashlib.sha256(token.encode("utf-8")).hexdigest()

    @staticmethod
    def create(
        course_id: int,
        created_by: int,
        role_in_course: str = "student",
        ttl_minutes: int = 60,
    ) -> dict:

        import base64, os
        token = base64.b64encode(os.urandom(24)).decode("ascii")  # contains +/= sometimes



        token_hash = InviteTokenRepository._hash_token(token)

        expires_at = datetime.now(timezone.utc) + timedelta(minutes=int(ttl_minutes))
        expires_at_str = expires_at.strftime("%Y-%m-%d %H:%M:%S")

        conn = get_db_connection()
        cur = conn.execute(
            """
            INSERT INTO invite_token (course_id, role_in_course, token_hash, expires_at, created_by)
            VALUES (?, ?, ?, ?, ?)
            """,
            (course_id, role_in_course, token_hash, expires_at_str, created_by),
        )
        invite_id = cur.lastrowid
        conn.commit()
        conn.close()


        return {
            "invite_id": invite_id,
            "token": token,  # plaintext token is returned but NOT stored
            "expires_at": expires_at_str,
            "role_in_course": role_in_course,
            "course_id": course_id,
        }

    @staticmethod
    def get_valid_by_token(token: str) -> dict | None:
        token_hash = InviteTokenRepository._hash_token(token)
        conn = get_db_connection()
        row = conn.execute(
            """
            SELECT
              invite_token_id AS invite_id,
              course_id,
              role_in_course,
              token_hash,
              created_by,
              created_at,
              expires_at,
              used_at,
              used_by
            FROM invite_token
            WHERE token_hash = ?
              AND used_at IS NULL
               AND expires_at > CURRENT_TIMESTAMP  
            ORDER BY invite_token_id DESC
            LIMIT 1
            """,
            (token_hash,),
        ).fetchone()
        conn.close()
        return dict(row) if row else None

    @staticmethod
    def mark_used(invite_id: int, used_by: int) -> None:
        conn = get_db_connection()
        conn.execute(
            """
            UPDATE invite_token
            SET used_at = CURRENT_TIMESTAMP, used_by = ?
            WHERE invite_token_id = ?
            """,
            (used_by, invite_id),
        )
        conn.commit()
        conn.close()