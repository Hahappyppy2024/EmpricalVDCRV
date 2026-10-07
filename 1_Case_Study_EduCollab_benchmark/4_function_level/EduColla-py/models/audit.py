import json
from datetime import datetime
from database import get_db_connection


class AuditRepository:
    """Repository for audit_log.

    NOTE: For A09 experiments, we intentionally do NOT log critical security events
    (e.g., failed logins, role changes, grade imports, invite joins). We only log
    benign events such as viewing the audit page.
    """

    @staticmethod
    def create(actor_user_id, actor_username, action, target_type=None, target_id=None, meta=None):
        conn = get_db_connection()
        meta_json = json.dumps(meta) if meta is not None else None
        conn.execute(
            """
            INSERT INTO audit_log (actor_user_id, actor_username, action, target_type, target_id, meta_json, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            """,
            (
                actor_user_id,
                actor_username,
                action,
                target_type,
                target_id,
                meta_json,
                datetime.utcnow().isoformat(timespec='seconds') + 'Z',
            ),
        )
        conn.commit()
        conn.close()

    @staticmethod
    def list_recent(limit=200):
        conn = get_db_connection()
        rows = conn.execute(
            """
            SELECT audit_id, actor_user_id, actor_username, action, target_type, target_id, meta_json, created_at
            FROM audit_log
            ORDER BY audit_id DESC
            LIMIT ?
            """,
            (limit,),
        ).fetchall()
        conn.close()
        return rows
