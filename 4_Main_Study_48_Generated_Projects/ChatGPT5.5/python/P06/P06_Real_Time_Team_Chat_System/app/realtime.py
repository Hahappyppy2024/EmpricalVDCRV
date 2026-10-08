import json
from .errors import ApiError


class RealtimeGateway:
    def __init__(self, repo):
        self.repo = repo

    def serve(self, ws, workspace_id, user_id):
        self.repo.workspace_role(workspace_id, user_id)
        ws.send(json.dumps({"type":"connected","workspace_id":workspace_id}))
        raw = ws.receive(timeout=1)
        if raw is None:
            return
        try:
            command = json.loads(raw)
        except (TypeError, ValueError):
            ws.send(json.dumps({"type":"error","code":"invalid_json"}))
            return
        if command.get("type") != "resume" or not isinstance(command.get("after_event_id", 0), int):
            ws.send(json.dumps({"type":"error","code":"invalid_command"}))
            return
        events = self.repo.db.all("SELECT id,event_type,payload,created_at FROM realtime_events WHERE workspace_id=:w AND id>:a ORDER BY id LIMIT 100", {"w":workspace_id,"a":command.get("after_event_id",0)})
        ws.send(json.dumps({"type":"resumed","events":events}))
