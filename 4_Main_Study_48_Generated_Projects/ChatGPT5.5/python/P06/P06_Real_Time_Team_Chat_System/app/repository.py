import json
from .auth import utcnow
from .errors import ApiError


class Repository:
    def __init__(self, db):
        self.db = db

    def need(self, table, item_id):
        row = self.db.one(f"SELECT * FROM {table} WHERE id=:id", {"id":item_id})
        if not row:
            raise ApiError(404, "not_found", "Resource not found.")
        return row

    def workspace_role(self, workspace_id, user_id):
        self.need("workspaces", workspace_id)
        row = self.db.one("SELECT role FROM workspace_members WHERE workspace_id=:w AND user_id=:u AND status='active'", {"w":workspace_id,"u":user_id})
        if not row:
            raise ApiError(403, "forbidden", "Workspace access denied.")
        return row["role"]

    def workspace_admin(self, workspace_id, user_id):
        if self.workspace_role(workspace_id, user_id) != "workspace_admin":
            raise ApiError(403, "forbidden", "Workspace administrator required.")

    def channel(self, channel_id, user_id, admin=False):
        channel = self.need("channels", channel_id)
        role = self.workspace_role(channel["workspace_id"], user_id)
        membership = self.db.one("SELECT role FROM channel_members WHERE channel_id=:c AND user_id=:u", {"c":channel_id,"u":user_id})
        if channel["visibility"] == "private" and not membership:
            raise ApiError(403, "forbidden", "Channel access denied.")
        if admin and role != "workspace_admin" and (not membership or membership["role"] != "channel_admin"):
            raise ApiError(403, "forbidden", "Channel administrator required.")
        return channel

    def message(self, message_id, user_id):
        message = self.need("messages", message_id)
        self.channel(message["channel_id"], user_id)
        return message

    def thread(self, thread_id, user_id):
        thread = self.need("direct_threads", thread_id)
        if not self.db.one("SELECT 1 ok FROM direct_participants WHERE thread_id=:t AND user_id=:u", {"t":thread_id,"u":user_id}):
            raise ApiError(403, "forbidden", "Direct thread access denied.")
        return thread

    def audit(self, workspace_id, actor_id, action, details):
        self.db.execute("INSERT INTO audit_events(workspace_id,actor_id,action,details,created_at) VALUES(:w,:a,:x,:d,:n)", {"w":workspace_id,"a":actor_id,"x":action,"d":details,"n":utcnow()})

    def event(self, workspace_id, event_type, payload):
        return self.db.execute("INSERT INTO realtime_events(workspace_id,event_type,payload,created_at) VALUES(:w,:t,:p,:n)", {"w":workspace_id,"t":event_type,"p":json.dumps(payload),"n":utcnow()})
