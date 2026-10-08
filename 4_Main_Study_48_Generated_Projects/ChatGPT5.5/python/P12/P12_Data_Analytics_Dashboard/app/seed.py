import json
from datetime import datetime,timezone
from sqlalchemy import text
from werkzeug.security import generate_password_hash
from .db import reset_database


def now(): return datetime.now(timezone.utc).isoformat()


def seed(root,path):
    db=reset_database(root,path); password=generate_password_hash("Password123!")
    def load(conn):
        users=[(1,"analyst@example.test","Analyst","analyst","active"),(2,"viewer@example.test","Viewer","viewer","active"),(3,"admin@example.test","Admin","admin","active"),(4,"outsider@example.test","Outsider","viewer","active"),(5,"disabled@example.test","Disabled","analyst","disabled")]
        for uid,email,name,role,status in users: conn.execute(text("INSERT INTO users VALUES(:i,:e,:p,:n,:r,:s,1)"),{"i":uid,"e":email,"p":password,"n":name,"r":role,"s":status})
        conn.execute(text("INSERT INTO workspaces VALUES(1,'Analytics Lab','analytics-lab',10000,'active',1)"))
        for uid,role in [(1,"analyst"),(2,"viewer"),(3,"admin")]: conn.execute(text("INSERT INTO workspace_members VALUES(1,:u,:r,'active')"),{"u":uid,"r":role})
        conn.execute(text("INSERT INTO datasets VALUES(1,1,1,'Quarterly Sales','Seed sales data','ready',4,1,:n)"),{"n":now()})
        conn.execute(text("INSERT INTO ingestion_jobs VALUES(1,1,'succeeded',NULL,:n,:n)"),{"n":now()})
        for cid,name,kind,pos in [(1,"region","string",0),(2,"revenue","number",1),(3,"units","number",2)]: conn.execute(text("INSERT INTO dataset_columns VALUES(:i,1,:n,:t,:p)"),{"i":cid,"n":name,"t":kind,"p":pos})
        rows=[{"region":"North","revenue":1200.0,"units":12},{"region":"South","revenue":900.0,"units":10},{"region":"North","revenue":1500.0,"units":15},{"region":"West","revenue":700.0,"units":8}]
        for idx,row in enumerate(rows): conn.execute(text("INSERT INTO dataset_rows(dataset_id,row_index,data) VALUES(1,:i,:d)"),{"i":idx,"d":json.dumps(row)})
        conn.execute(text("INSERT INTO charts VALUES(1,1,1,'Revenue by region','bar','region','revenue','sum','[]',1,:n)"),{"n":now()})
        conn.execute(text("INSERT INTO dashboards VALUES(1,1,1,'Executive Overview','Seed dashboard',1,:n)"),{"n":now()})
        conn.execute(text("INSERT INTO dashboard_charts VALUES(1,1,0)")); conn.execute(text("INSERT INTO dashboard_shares VALUES(1,1,2,'read')"))
        conn.execute(text("INSERT INTO lineage_edges VALUES(1,1,'chart',1,:n)"),{"n":now()})
        conn.execute(text("INSERT INTO data_sources VALUES(1,1,1,'Warehouse Demo','postgres','warehouse.local','analytics','reader',:h,'********','ready',1)"),{"h":"seed-secret-hash"})
        conn.execute(text("INSERT INTO connector_settings VALUES(1,'csv',1,1),(2,'postgres',1,1),(3,'sqlite',0,1)"))
        conn.execute(text("INSERT INTO audit_events VALUES(1,1,1,'dataset.created','Quarterly Sales',:n)"),{"n":now()})
    db.transaction(load); return db
