import json
from .errors import ApiError
from .auth import now


class Repository:
    def __init__(self,db): self.db=db
    def need(self,table,item_id):
        row=self.db.one(f"SELECT * FROM {table} WHERE id=:id",{"id":item_id})
        if not row: raise ApiError(404,"not_found","Resource not found.")
        return row
    def workspace_role(self,workspace_id,user_id):
        self.need("workspaces",workspace_id); row=self.db.one("SELECT role FROM workspace_members WHERE workspace_id=:w AND user_id=:u AND status='active'",{"w":workspace_id,"u":user_id})
        if not row: raise ApiError(403,"forbidden","Workspace access denied.")
        return row["role"]
    def dataset(self,dataset_id,user,write=False):
        item=self.need("datasets",dataset_id); self.workspace_role(item["workspace_id"],user["id"])
        if write and (user["global_role"]!="analyst" or item["owner_id"]!=user["id"]): raise ApiError(403,"forbidden","Dataset write access denied.")
        return item
    def chart(self,chart_id,user,write=False):
        chart=self.need("charts",chart_id); self.dataset(chart["dataset_id"],user)
        if write and chart["owner_id"]!=user["id"]: raise ApiError(403,"forbidden","Chart write access denied.")
        return chart
    def dashboard(self,dashboard_id,user,write=False):
        item=self.need("dashboards",dashboard_id); self.workspace_role(item["workspace_id"],user["id"])
        if item["owner_id"]==user["id"]: return item
        share=self.db.one("SELECT permission FROM dashboard_shares WHERE dashboard_id=:d AND user_id=:u",{"d":dashboard_id,"u":user["id"]})
        if not share or (write and share["permission"]!="edit"): raise ApiError(403,"forbidden","Dashboard access denied.")
        return item
    def source(self,source_id,user,write=False):
        item=self.need("data_sources",source_id); self.workspace_role(item["workspace_id"],user["id"])
        if user["global_role"]!="admin" and item["owner_id"]!=user["id"]: raise ApiError(403,"forbidden","Data source access denied.")
        return item
    def rows(self,dataset_id): return [json.loads(x["data"]) for x in self.db.all("SELECT data FROM dataset_rows WHERE dataset_id=:d ORDER BY row_index",{"d":dataset_id})]
    def columns(self,dataset_id): return self.db.all("SELECT name,data_type,position FROM dataset_columns WHERE dataset_id=:d ORDER BY position",{"d":dataset_id})
    def audit(self,workspace_id,actor_id,action,details): self.db.execute("INSERT INTO audit_events(workspace_id,actor_id,action,details,created_at) VALUES(:w,:u,:a,:d,:n)",{"w":workspace_id,"u":actor_id,"a":action,"d":details,"n":now()})
