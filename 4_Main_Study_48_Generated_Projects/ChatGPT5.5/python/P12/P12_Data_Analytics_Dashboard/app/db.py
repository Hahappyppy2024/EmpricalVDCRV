from pathlib import Path
from sqlalchemy import create_engine,event,text


class Database:
    def __init__(self,path):
        Path(path).parent.mkdir(parents=True,exist_ok=True); self.engine=create_engine(f"sqlite:///{Path(path).resolve()}")
        event.listen(self.engine,"connect",lambda conn,_:conn.execute("PRAGMA foreign_keys=ON"))
    def one(self,sql,params=None):
        with self.engine.connect() as conn:
            row=conn.execute(text(sql),params or {}).mappings().first(); return dict(row) if row else None
    def all(self,sql,params=None):
        with self.engine.connect() as conn: return [dict(x) for x in conn.execute(text(sql),params or {}).mappings()]
    def execute(self,sql,params=None):
        with self.engine.begin() as conn: return conn.execute(text(sql),params or {}).lastrowid
    def transaction(self,work):
        with self.engine.begin() as conn: return work(conn)


def reset_database(root,path):
    db=Database(path); raw=db.engine.raw_connection()
    try: raw.executescript(Path(root,"database/schema.sql").read_text()); raw.commit()
    finally: raw.close()
    return db
