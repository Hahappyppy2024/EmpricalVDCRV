from datetime import datetime, timezone, timedelta
from sqlalchemy import text
from werkzeug.security import generate_password_hash
from .db import reset_database


def now():
    return datetime.now(timezone.utc).isoformat()


def seed(root, path):
    db = reset_database(root, path)
    future = (datetime.now(timezone.utc) + timedelta(days=1)).isoformat()
    password = generate_password_hash("Password123!")

    def load(conn):
        users = [(1,"alice@example.test","Alice","member","active"),(2,"bob@example.test","Bob","member","active"),(3,"admin@example.test","Admin","admin","active"),(4,"outsider@example.test","Outsider","member","active"),(5,"disabled@example.test","Disabled","member","disabled")]
        for uid,email,name,role,status in users:
            conn.execute(text("INSERT INTO users VALUES(:id,:email,:pw,:name,:role,:status,1)"),{"id":uid,"email":email,"pw":password,"name":name,"role":role,"status":status})
        conn.execute(text("INSERT INTO workspaces VALUES(1,'Research Team','research-team',3,1)"))
        for uid,role in [(1,"member"),(2,"channel_admin"),(3,"workspace_admin")]:
            conn.execute(text("INSERT INTO workspace_members VALUES(1,:u,:r,'active')"),{"u":uid,"r":role})
        conn.execute(text("INSERT INTO channels VALUES(1,1,'general','public','active',1),(2,1,'private-lab','private','active',1),(3,1,'archive-me','public','active',1)"))
        for cid,uid,role in [(1,1,"member"),(1,2,"channel_admin"),(1,3,"member"),(2,1,"member"),(2,2,"channel_admin"),(2,3,"member"),(3,2,"channel_admin"),(3,3,"member")]:
            conn.execute(text("INSERT INTO channel_members VALUES(:c,:u,:r)"),{"c":cid,"u":uid,"r":role})
        conn.execute(text("INSERT INTO messages VALUES(1,1,1,'Welcome to the research team','active',1,:n,NULL,'seed-message-1'),(2,1,2,'Weekly meeting at ten','active',1,:n,NULL,'seed-message-2'),(3,2,1,'Private experiment notes','active',1,:n,NULL,'seed-message-3')"),{"n":now()})
        conn.execute(text("INSERT INTO direct_threads VALUES(1,:n,'1,2')"),{"n":now()})
        conn.execute(text("INSERT INTO direct_participants VALUES(1,1),(1,2)"))
        conn.execute(text("INSERT INTO direct_messages VALUES(1,1,1,'Hello Bob',:n,'seed-dm-1')"),{"n":now()})
        conn.execute(text("INSERT INTO invitations VALUES(1,1,'outsider@example.test','member','invite-outsider','pending',:f)"),{"f":future})
        conn.execute(text("INSERT INTO realtime_events VALUES(1,1,'message.created',:p1,:n),(2,1,'message.created',:p2,:n)"),{"p1":'{"message_id":1}',"p2":'{"message_id":2}',"n":now()})
        conn.execute(text("INSERT INTO presence VALUES(1,1,'online',:n),(1,2,'away',:n)"),{"n":now()})
        conn.execute(text("INSERT INTO audit_events VALUES(1,1,3,'workspace.created','Research Team',:n)"),{"n":now()})
    db.transaction(load)
    return db
