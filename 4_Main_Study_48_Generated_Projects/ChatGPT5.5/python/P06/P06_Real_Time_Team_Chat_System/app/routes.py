import json
import re
import secrets
from datetime import datetime, timezone, timedelta
from urllib.parse import urlparse
from flask import jsonify, request
from sqlalchemy import text
from sqlalchemy.exc import IntegrityError
from werkzeug.security import generate_password_hash
from .auth import utcnow
from .errors import ApiError, require


def body():
    data = request.get_json(silent=True)
    if not isinstance(data, dict):
        raise ApiError(422, "validation_error", "A JSON object is required.", {"body":"object required"})
    return data


def page_limit(default=50):
    try:
        limit = int(request.args.get("limit", default))
    except ValueError:
        raise ApiError(422, "validation_error", "Invalid limit.", {"limit":"integer required"})
    if limit < 1 or limit > 100:
        raise ApiError(422, "validation_error", "Invalid limit.", {"limit":"must be 1..100"})
    return limit


def checked_version(data, row):
    if "version" in data and int(data["version"]) != int(row["version"]):
        raise ApiError(409, "stale_version", "Resource version is stale.")


def duplicate(work):
    try:
        return work()
    except IntegrityError:
        raise ApiError(409, "conflict", "Resource already exists.")


def register_routes(app, sock, auth, repo, gateway):
    db = repo.db

    # CHAT-01 Accounts
    @app.post("/api/auth/register")
    def register():
        data = body(); require(data,"email","password","display_name")
        email = str(data["email"]).lower()
        if not re.fullmatch(r"[^@\s]+@[^@\s]+\.[^@\s]+", email) or len(data["password"]) < 10 or not 2 <= len(data["display_name"]) <= 60:
            raise ApiError(422,"validation_error","Invalid account fields.",{"email":"valid email required","password":"minimum 10 characters","display_name":"2..60 characters"})
        uid = duplicate(lambda: db.execute("INSERT INTO users(email,password_hash,display_name) VALUES(:e,:p,:n)",{"e":email,"p":generate_password_hash(data["password"]),"n":data["display_name"]}))
        return jsonify(auth.public(repo.need("users",uid))), 201

    @app.post("/api/auth/login")
    def login():
        data=body(); require(data,"email","password")
        user, token = auth.login(data["email"],data["password"])
        response=jsonify(user=user)
        response.set_cookie(auth.cookie,token,httponly=True,samesite="Lax",secure=False)
        return response

    @app.post("/api/auth/logout")
    def logout():
        auth.current(); auth.logout(); response=jsonify(logged_out=True); response.delete_cookie(auth.cookie,httponly=True,samesite="Lax"); return response

    @app.get("/api/profile")
    def profile():
        return jsonify(auth.public(auth.current()))

    @app.patch("/api/profile")
    def profile_update():
        user=auth.current(); data=body(); checked_version(data,user)
        name=data.get("display_name",user["display_name"])
        if not isinstance(name,str) or not 2 <= len(name) <= 60:
            raise ApiError(422,"validation_error","Invalid display name.",{"display_name":"2..60 characters"})
        db.execute("UPDATE users SET display_name=:n,version=version+1 WHERE id=:id",{"n":name,"id":user["id"]})
        return jsonify(auth.public(repo.need("users",user["id"])))

    # CHAT-02 Workspaces and channels
    @app.post("/api/workspaces")
    def workspace_create():
        user=auth.current(); data=body(); require(data,"name","slug")
        if not re.fullmatch(r"[a-z0-9-]{3,40}",str(data["slug"])):
            raise ApiError(422,"validation_error","Invalid workspace slug.",{"slug":"lowercase letters, digits, hyphens"})
        def create(conn):
            wid=conn.execute(text("INSERT INTO workspaces(name,slug,created_by) VALUES(:n,:s,:u)"),{"n":data["name"],"s":data["slug"],"u":user["id"]}).lastrowid
            conn.execute(text("INSERT INTO workspace_members VALUES(:w,:u,'workspace_admin','active')"),{"w":wid,"u":user["id"]})
            conn.execute(text("INSERT INTO channels(workspace_id,name,visibility) VALUES(:w,'general','public')"),{"w":wid})
            return wid
        wid=duplicate(lambda: db.transaction(create)); return jsonify(repo.need("workspaces",wid)),201

    @app.get("/api/workspaces/<int:workspace_id>/channels")
    def channel_list(workspace_id):
        user=auth.current(); repo.workspace_role(workspace_id,user["id"])
        rows=db.all("SELECT DISTINCT c.* FROM channels c LEFT JOIN channel_members cm ON cm.channel_id=c.id AND cm.user_id=:u WHERE c.workspace_id=:w AND (c.visibility='public' OR cm.user_id IS NOT NULL) ORDER BY c.id",{"w":workspace_id,"u":user["id"]})
        return jsonify(items=rows)

    @app.post("/api/workspaces/<int:workspace_id>/channels")
    def channel_create(workspace_id):
        user=auth.current(); repo.workspace_admin(workspace_id,user["id"]); data=body(); require(data,"name","visibility")
        if data["visibility"] not in ("public","private") or not re.fullmatch(r"[a-z0-9-]{2,40}",str(data["name"])):
            raise ApiError(422,"validation_error","Invalid channel fields.",{"name":"channel slug required","visibility":"public or private"})
        cid=duplicate(lambda:db.execute("INSERT INTO channels(workspace_id,name,visibility) VALUES(:w,:n,:v)",{"w":workspace_id,"n":data["name"],"v":data["visibility"]}))
        db.execute("INSERT INTO channel_members VALUES(:c,:u,'channel_admin')",{"c":cid,"u":user["id"]})
        return jsonify(repo.need("channels",cid)),201

    @app.patch("/api/channels/<int:channel_id>")
    def channel_update(channel_id):
        user=auth.current(); channel=repo.channel(channel_id,user["id"],True); data=body(); checked_version(data,channel)
        visibility=data.get("visibility",channel["visibility"]); name=data.get("name",channel["name"])
        if visibility not in ("public","private") or not re.fullmatch(r"[a-z0-9-]{2,40}",str(name)):
            raise ApiError(422,"validation_error","Invalid channel fields.")
        db.execute("UPDATE channels SET name=:n,visibility=:v,version=version+1 WHERE id=:id",{"n":name,"v":visibility,"id":channel_id})
        return jsonify(repo.need("channels",channel_id))

    # CHAT-03 Membership lifecycle
    @app.post("/api/workspaces/<int:workspace_id>/invitations")
    def invite(workspace_id):
        user=auth.current(); repo.workspace_admin(workspace_id,user["id"]); data=body(); require(data,"email","role")
        if data["role"] not in ("member","channel_admin"):
            raise ApiError(422,"validation_error","Invalid invitation role.",{"role":"member or channel_admin"})
        token="invite-"+secrets.token_hex(8); future=(datetime.now(timezone.utc)+timedelta(days=1)).isoformat()
        iid=duplicate(lambda:db.execute("INSERT INTO invitations(workspace_id,email,role,token,expires_at) VALUES(:w,:e,:r,:t,:x)",{"w":workspace_id,"e":str(data["email"]).lower(),"r":data["role"],"t":token,"x":future}))
        invitation=repo.need("invitations",iid); invitation["token"]=token; return jsonify(invitation),201

    @app.post("/api/invitations/<token>/accept")
    def invite_accept(token):
        user=auth.current(); invitation=db.one("SELECT * FROM invitations WHERE token=:t",{"t":token})
        if not invitation: raise ApiError(404,"not_found","Invitation not found.")
        if invitation["status"]!="pending" or invitation["expires_at"]<=utcnow(): raise ApiError(409,"invalid_state","Invitation is not active.")
        if invitation["email"]!=user["email"]: raise ApiError(403,"forbidden","Invitation belongs to another account.")
        def accept(conn):
            conn.execute(text("INSERT INTO workspace_members VALUES(:w,:u,:r,'active')"),{"w":invitation["workspace_id"],"u":user["id"],"r":invitation["role"]})
            conn.execute(text("UPDATE invitations SET status='accepted' WHERE id=:id"),{"id":invitation["id"]})
        duplicate(lambda:db.transaction(accept)); return jsonify(accepted=True,workspace_id=invitation["workspace_id"])

    @app.patch("/api/workspaces/<int:workspace_id>/members/<int:user_id>")
    def member_update(workspace_id,user_id):
        actor=auth.current(); repo.workspace_admin(workspace_id,actor["id"]); data=body(); require(data,"role")
        member=db.one("SELECT * FROM workspace_members WHERE workspace_id=:w AND user_id=:u",{"w":workspace_id,"u":user_id})
        if not member: raise ApiError(404,"not_found","Membership not found.")
        if data["role"] not in ("member","channel_admin","workspace_admin"): raise ApiError(422,"validation_error","Invalid role.")
        if actor["id"]==user_id and data["role"]!="workspace_admin": raise ApiError(409,"invalid_state","Administrator cannot demote self.")
        db.execute("UPDATE workspace_members SET role=:r WHERE workspace_id=:w AND user_id=:u",{"r":data["role"],"w":workspace_id,"u":user_id}); return jsonify(user_id=user_id,role=data["role"])

    @app.delete("/api/workspaces/<int:workspace_id>/members/<int:user_id>")
    def member_delete(workspace_id,user_id):
        actor=auth.current(); repo.workspace_admin(workspace_id,actor["id"])
        if actor["id"]==user_id: raise ApiError(409,"invalid_state","Administrator cannot remove self.")
        if not db.one("SELECT 1 ok FROM workspace_members WHERE workspace_id=:w AND user_id=:u",{"w":workspace_id,"u":user_id}): raise ApiError(404,"not_found","Membership not found.")
        db.execute("DELETE FROM workspace_members WHERE workspace_id=:w AND user_id=:u",{"w":workspace_id,"u":user_id}); return "",204

    # CHAT-04 and CHAT-05 messages, history and search
    @app.get("/api/channels/<int:channel_id>/messages")
    def messages(channel_id):
        user=auth.current(); repo.channel(channel_id,user["id"]); limit=page_limit(); before=request.args.get("before","9999-12-31T23:59:59+00:00")
        try: datetime.fromisoformat(before)
        except ValueError: raise ApiError(422,"validation_error","Invalid history cursor.",{"before":"ISO date-time required"})
        rows=db.all("SELECT id,channel_id,author_id,body,status,version,created_at,updated_at FROM messages WHERE channel_id=:c AND created_at<:b ORDER BY created_at DESC,id DESC LIMIT :l",{"c":channel_id,"b":before,"l":limit}); return jsonify(items=rows)

    @app.post("/api/channels/<int:channel_id>/messages")
    def message_create(channel_id):
        user=auth.current(); channel=repo.channel(channel_id,user["id"]); data=body(); require(data,"body","idempotency_key")
        if channel["status"]!="active": raise ApiError(409,"invalid_state","Archived channel is read-only.")
        if not isinstance(data["body"],str) or not 1<=len(data["body"])<=4000: raise ApiError(422,"validation_error","Invalid message body.")
        mid=duplicate(lambda:db.execute("INSERT INTO messages(channel_id,author_id,body,created_at,idempotency_key) VALUES(:c,:u,:b,:n,:k)",{"c":channel_id,"u":user["id"],"b":data["body"],"n":utcnow(),"k":data["idempotency_key"]}))
        repo.event(channel["workspace_id"],"message.created",{"message_id":mid,"channel_id":channel_id}); return jsonify(repo.need("messages",mid)),201

    @app.patch("/api/messages/<int:message_id>")
    def message_update(message_id):
        user=auth.current(); message=repo.message(message_id,user["id"]); data=body(); checked_version(data,message); require(data,"body")
        if message["author_id"]!=user["id"]: raise ApiError(403,"forbidden","Only the author may edit this message.")
        if message["status"]!="active": raise ApiError(409,"invalid_state","Deleted message cannot be edited.")
        if not isinstance(data["body"],str) or not 1<=len(data["body"])<=4000: raise ApiError(422,"validation_error","Invalid message body.")
        db.execute("UPDATE messages SET body=:b,updated_at=:n,version=version+1 WHERE id=:id",{"b":data["body"],"n":utcnow(),"id":message_id}); return jsonify(repo.need("messages",message_id))

    @app.delete("/api/messages/<int:message_id>")
    def message_delete(message_id):
        user=auth.current(); message=repo.message(message_id,user["id"])
        if message["author_id"]!=user["id"]: raise ApiError(403,"forbidden","Only the author may delete this message.")
        if message["status"]!="active": raise ApiError(409,"invalid_state","Message is already deleted.")
        db.execute("UPDATE messages SET body='[deleted]',status='deleted',version=version+1 WHERE id=:id",{"id":message_id}); return "",204

    @sock.route("/ws/workspaces/<int:workspace_id>")
    def workspace_socket(ws,workspace_id):
        user=auth.current(); gateway.serve(ws,workspace_id,user["id"])

    @app.get("/api/search/messages")
    def message_search():
        user=auth.current(); query=request.args.get("q","").strip(); channel_id=request.args.get("channelId"); start=request.args.get("from"); end=request.args.get("to")
        if not query or len(query)>100: raise ApiError(422,"validation_error","Invalid search query.",{"q":"1..100 characters"})
        clauses=["m.status='active'","m.body LIKE :q","wm.user_id=:u","wm.status='active'","(c.visibility='public' OR cm.user_id IS NOT NULL)"]; params={"q":f"%{query}%","u":user["id"]}
        if channel_id: repo.channel(int(channel_id),user["id"]); clauses.append("m.channel_id=:c"); params["c"]=int(channel_id)
        if start or end:
            try:
                if start: datetime.fromisoformat(start); clauses.append("m.created_at>=:f"); params["f"]=start
                if end: datetime.fromisoformat(end); clauses.append("m.created_at<=:t"); params["t"]=end
            except ValueError: raise ApiError(422,"validation_error","Invalid search date range.")
            if start and end and start>end: raise ApiError(422,"validation_error","Invalid search date range.")
        sql="SELECT DISTINCT m.id,m.channel_id,m.author_id,m.body,m.created_at FROM messages m JOIN channels c ON c.id=m.channel_id JOIN workspace_members wm ON wm.workspace_id=c.workspace_id LEFT JOIN channel_members cm ON cm.channel_id=c.id AND cm.user_id=:u WHERE "+" AND ".join(clauses)+" ORDER BY m.created_at DESC,m.id DESC LIMIT 100"
        return jsonify(items=db.all(sql,params))

    # CHAT-06 Direct messages
    @app.post("/api/direct-threads")
    def direct_create():
        user=auth.current(); data=body(); require(data,"participant_ids")
        ids=data["participant_ids"]
        if not isinstance(ids,list) or not ids or any(not isinstance(x,int) for x in ids): raise ApiError(422,"validation_error","Invalid participants.")
        all_ids=sorted(set(ids+[user["id"]]));
        if len(all_ids)<2: raise ApiError(422,"validation_error","Another participant is required.")
        if len(db.all("SELECT id FROM users WHERE id IN ("+",".join(str(x) for x in all_ids)+") AND status='active'"))!=len(all_ids): raise ApiError(404,"not_found","Participant not found.")
        key=",".join(map(str,all_ids)); existing=db.one("SELECT * FROM direct_threads WHERE participant_key=:k",{"k":key})
        if existing: return jsonify(existing)
        def create(conn):
            tid=conn.execute(text("INSERT INTO direct_threads(created_at,participant_key) VALUES(:n,:k)"),{"n":utcnow(),"k":key}).lastrowid
            for uid in all_ids: conn.execute(text("INSERT INTO direct_participants VALUES(:t,:u)"),{"t":tid,"u":uid})
            return tid
        tid=db.transaction(create); return jsonify(repo.need("direct_threads",tid)),201

    @app.get("/api/direct-threads")
    def direct_list():
        user=auth.current(); rows=db.all("SELECT t.* FROM direct_threads t JOIN direct_participants p ON p.thread_id=t.id WHERE p.user_id=:u ORDER BY t.id",{"u":user["id"]}); return jsonify(items=rows)

    @app.get("/api/direct-threads/<int:thread_id>/messages")
    def direct_messages(thread_id):
        user=auth.current(); repo.thread(thread_id,user["id"]); return jsonify(items=db.all("SELECT * FROM direct_messages WHERE thread_id=:t ORDER BY id",{"t":thread_id}))

    @app.post("/api/direct-threads/<int:thread_id>/messages")
    def direct_message_create(thread_id):
        user=auth.current(); repo.thread(thread_id,user["id"]); data=body(); require(data,"body","idempotency_key")
        if not isinstance(data["body"],str) or not 1<=len(data["body"])<=4000: raise ApiError(422,"validation_error","Invalid message body.")
        mid=duplicate(lambda:db.execute("INSERT INTO direct_messages(thread_id,author_id,body,created_at,idempotency_key) VALUES(:t,:u,:b,:n,:k)",{"t":thread_id,"u":user["id"],"b":data["body"],"n":utcnow(),"k":data["idempotency_key"]})); return jsonify(repo.need("direct_messages",mid)),201

    # CHAT-07 Attachments
    @app.post("/api/channels/<int:channel_id>/attachments")
    def attachment_create(channel_id):
        user=auth.current(); channel=repo.channel(channel_id,user["id"])
        if channel["status"]!="active": raise ApiError(409,"invalid_state","Archived channel is read-only.")
        upload=request.files.get("file")
        if not upload or not upload.filename: raise ApiError(422,"validation_error","Attachment file required.",{"file":"required"})
        content=upload.read(app.config["MAX_ATTACHMENT_BYTES"]+1)
        if not content or len(content)>app.config["MAX_ATTACHMENT_BYTES"]: raise ApiError(422,"validation_error","Invalid attachment size.")
        allowed={"text/plain","image/png","application/pdf"}
        if upload.mimetype not in allowed: raise ApiError(422,"validation_error","Attachment type is not allowed.")
        filename=upload.filename.rsplit("/",1)[-1].rsplit("\\",1)[-1]
        aid=db.execute("INSERT INTO attachments(channel_id,uploader_id,filename,content_type,content,created_at) VALUES(:c,:u,:f,:t,:b,:n)",{"c":channel_id,"u":user["id"],"f":filename,"t":upload.mimetype,"b":content,"n":utcnow()})
        return jsonify(id=aid,channel_id=channel_id,filename=filename,content_type=upload.mimetype,size=len(content)),201

    @app.get("/api/attachments/<int:attachment_id>/content")
    def attachment_content(attachment_id):
        user=auth.current(); item=repo.need("attachments",attachment_id); repo.channel(item["channel_id"],user["id"])
        return item["content"],200,{"Content-Type":item["content_type"],"Content-Disposition":f'attachment; filename="{item["filename"]}"'}

    @app.delete("/api/attachments/<int:attachment_id>")
    def attachment_delete(attachment_id):
        user=auth.current(); item=repo.need("attachments",attachment_id); repo.channel(item["channel_id"],user["id"])
        if item["uploader_id"]!=user["id"]: raise ApiError(403,"forbidden","Only the uploader may delete this attachment.")
        db.execute("DELETE FROM attachments WHERE id=:id",{"id":attachment_id}); return "",204

    # CHAT-08 Link previews: deterministic, allow-listed metadata adapter
    @app.post("/api/link-previews")
    def preview_create():
        user=auth.current(); data=body(); require(data,"message_id","url"); message=repo.message(int(data["message_id"]),user["id"])
        parsed=urlparse(str(data["url"])); allowed=parsed.scheme=="https" and parsed.hostname in {"docs.example.test","chat.example.test"}
        if not allowed: raise ApiError(422,"validation_error","URL is outside the preview allow-list.",{"url":"approved HTTPS host required"})
        pid=duplicate(lambda:db.execute("INSERT INTO link_previews(message_id,url,title,description,created_at) VALUES(:m,:u,:t,:d,:n)",{"m":message["id"],"u":data["url"],"t":"Local preview: "+parsed.hostname,"d":"Deterministic offline metadata for "+parsed.path,"n":utcnow()})); return jsonify(repo.need("link_previews",pid)),201

    @app.get("/api/link-previews/<int:preview_id>")
    def preview_get(preview_id):
        user=auth.current(); preview=repo.need("link_previews",preview_id); repo.message(preview["message_id"],user["id"]); return jsonify(preview)

    # CHAT-09 Channel administration
    @app.post("/api/channels/<int:channel_id>/archive")
    def channel_archive(channel_id):
        user=auth.current(); channel=repo.channel(channel_id,user["id"],True)
        if channel["status"]=="archived": raise ApiError(409,"invalid_state","Channel is already archived.")
        db.execute("UPDATE channels SET status='archived',version=version+1 WHERE id=:id",{"id":channel_id}); repo.audit(channel["workspace_id"],user["id"],"channel.archived",channel["name"]); return jsonify(repo.need("channels",channel_id))

    @app.post("/api/channels/<int:channel_id>/members")
    def channel_member_add(channel_id):
        user=auth.current(); channel=repo.channel(channel_id,user["id"],True); data=body(); require(data,"user_id")
        repo.workspace_role(channel["workspace_id"],int(data["user_id"])); duplicate(lambda:db.execute("INSERT INTO channel_members VALUES(:c,:u,'member')",{"c":channel_id,"u":int(data["user_id"])})); return jsonify(channel_id=channel_id,user_id=int(data["user_id"])),201

    @app.delete("/api/channels/<int:channel_id>/members/<int:user_id>")
    def channel_member_remove(channel_id,user_id):
        actor=auth.current(); repo.channel(channel_id,actor["id"],True)
        member=db.one("SELECT * FROM channel_members WHERE channel_id=:c AND user_id=:u",{"c":channel_id,"u":user_id})
        if not member: raise ApiError(404,"not_found","Channel membership not found.")
        if actor["id"]==user_id and member["role"]=="channel_admin": raise ApiError(409,"invalid_state","Channel administrator cannot remove self.")
        db.execute("DELETE FROM channel_members WHERE channel_id=:c AND user_id=:u",{"c":channel_id,"u":user_id}); return "",204

    # CHAT-10 Connection and delivery state
    @app.post("/api/realtime/resume")
    def realtime_resume():
        user=auth.current(); data=body(); require(data,"workspace_id","after_event_id"); repo.workspace_role(int(data["workspace_id"]),user["id"])
        if not isinstance(data["after_event_id"],int) or data["after_event_id"]<0: raise ApiError(422,"validation_error","Invalid event cursor.")
        rows=db.all("SELECT * FROM realtime_events WHERE workspace_id=:w AND id>:a ORDER BY id LIMIT 100",{"w":int(data["workspace_id"]),"a":data["after_event_id"]}); return jsonify(events=rows,next_event_id=rows[-1]["id"] if rows else data["after_event_id"])

    @app.post("/api/messages/<int:message_id>/delivered")
    def delivered(message_id):
        user=auth.current(); message=repo.message(message_id,user["id"])
        existing=db.one("SELECT * FROM receipts WHERE message_id=:m AND user_id=:u",{"m":message_id,"u":user["id"]})
        if existing: return jsonify(existing)
        db.execute("INSERT INTO receipts VALUES(:m,:u,:n)",{"m":message["id"],"u":user["id"],"n":utcnow()}); return jsonify(db.one("SELECT * FROM receipts WHERE message_id=:m AND user_id=:u",{"m":message_id,"u":user["id"]})),201

    # CHAT-11 Presence and read state
    @app.put("/api/presence")
    def presence_update():
        user=auth.current(); data=body(); require(data,"workspace_id","state"); repo.workspace_role(int(data["workspace_id"]),user["id"])
        if data["state"] not in ("online","away","offline"): raise ApiError(422,"validation_error","Invalid presence state.")
        db.execute("INSERT INTO presence VALUES(:w,:u,:s,:n) ON CONFLICT(workspace_id,user_id) DO UPDATE SET state=:s,updated_at=:n",{"w":int(data["workspace_id"]),"u":user["id"],"s":data["state"],"n":utcnow()}); return jsonify(workspace_id=int(data["workspace_id"]),user_id=user["id"],state=data["state"])

    @app.get("/api/workspaces/<int:workspace_id>/presence")
    def presence_list(workspace_id):
        user=auth.current(); repo.workspace_role(workspace_id,user["id"]); return jsonify(items=db.all("SELECT p.*,u.display_name FROM presence p JOIN users u ON u.id=p.user_id WHERE p.workspace_id=:w ORDER BY p.user_id",{"w":workspace_id}))

    @app.put("/api/channels/<int:channel_id>/read-cursor")
    def read_cursor(channel_id):
        user=auth.current(); repo.channel(channel_id,user["id"]); data=body(); require(data,"message_id"); message=repo.message(int(data["message_id"]),user["id"])
        if message["channel_id"]!=channel_id: raise ApiError(422,"validation_error","Message is outside this channel.")
        db.execute("INSERT INTO read_cursors VALUES(:c,:u,:m,:n) ON CONFLICT(channel_id,user_id) DO UPDATE SET message_id=:m,updated_at=:n",{"c":channel_id,"u":user["id"],"m":message["id"],"n":utcnow()}); return jsonify(channel_id=channel_id,message_id=message["id"])

    # CHAT-12 Moderation and audit
    @app.post("/api/messages/<int:message_id>/reports")
    def report_create(message_id):
        user=auth.current(); message=repo.message(message_id,user["id"]); data=body(); require(data,"reason")
        if not isinstance(data["reason"],str) or not 3<=len(data["reason"])<=500: raise ApiError(422,"validation_error","Invalid report reason.")
        rid=duplicate(lambda:db.execute("INSERT INTO reports(message_id,reporter_id,reason,created_at) VALUES(:m,:u,:r,:n)",{"m":message["id"],"u":user["id"],"r":data["reason"],"n":utcnow()})); return jsonify(repo.need("reports",rid)),201

    @app.get("/api/admin/workspaces/<int:workspace_id>/reports")
    def reports(workspace_id):
        user=auth.current(); repo.workspace_admin(workspace_id,user["id"]); rows=db.all("SELECT r.* FROM reports r JOIN messages m ON m.id=r.message_id JOIN channels c ON c.id=m.channel_id WHERE c.workspace_id=:w ORDER BY r.id",{"w":workspace_id}); return jsonify(items=rows)

    @app.post("/api/admin/reports/<int:report_id>/resolve")
    def report_resolve(report_id):
        user=auth.current(); report=repo.need("reports",report_id); message=repo.need("messages",report["message_id"]); channel=repo.need("channels",message["channel_id"]); repo.workspace_admin(channel["workspace_id"],user["id"]); data=body(); require(data,"note")
        if report["status"]!="open": raise ApiError(409,"invalid_state","Report is already resolved.")
        db.execute("UPDATE reports SET status='resolved',resolution_note=:n WHERE id=:id",{"n":data["note"],"id":report_id}); repo.audit(channel["workspace_id"],user["id"],"report.resolved",str(report_id)); return jsonify(repo.need("reports",report_id))

    @app.get("/api/admin/workspaces/<int:workspace_id>/audit-events")
    def audits(workspace_id):
        user=auth.current(); repo.workspace_admin(workspace_id,user["id"]); return jsonify(items=db.all("SELECT * FROM audit_events WHERE workspace_id=:w ORDER BY id DESC LIMIT 100",{"w":workspace_id}))
