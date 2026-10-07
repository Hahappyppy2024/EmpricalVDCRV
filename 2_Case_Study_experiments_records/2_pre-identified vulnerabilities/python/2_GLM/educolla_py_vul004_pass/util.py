import json
from datetime import datetime, timezone

def now_iso():
    return datetime.now(timezone.utc).isoformat()

def json_dumps(v):
    if v is None:
        return None
    return json.dumps(v, ensure_ascii=False)

def json_loads(s):
    if s is None:
        return None
    try:
        return json.loads(s)
    except Exception:
        return None
