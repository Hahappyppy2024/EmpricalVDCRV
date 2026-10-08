import csv,hashlib,io,json,re,secrets
from datetime import datetime,timezone,timedelta
from flask import Response,jsonify,request
from sqlalchemy import text
from sqlalchemy.exc import IntegrityError
from werkzeug.security import generate_password_hash
from .auth import now
from .errors import ApiError,require


def body():
    data=request.get_json(silent=True)
    if not isinstance(data,dict): raise ApiError(422,"validation_error","A JSON object is required.",{"body":"object required"})
    return data
def duplicate(work):
    try:return work()
    except IntegrityError: raise ApiError(409,"conflict","Resource already exists.")
def version(data,item):
    if "version" in data and int(data["version"])!=int(item["version"]): raise ApiError(409,"stale_version","Resource version is stale.")
def integer_arg(name,default,low,high):
    try:value=int(request.args.get(name,default))
    except ValueError: raise ApiError(422,"validation_error",f"Invalid {name}.",{name:"integer required"})
    if value<low or value>high: raise ApiError(422,"validation_error",f"Invalid {name}.",{name:f"must be {low}..{high}"})
    return value
def public_source(item): return {k:item[k] for k in ("id","workspace_id","owner_id","name","type","host","database_name","username","secret_mask","status","version")}
def valid_expression(expression,columns): return re.fullmatch(r"[A-Za-z_][A-Za-z0-9_]*\s*[+\-*/]\s*-?\d+(?:\.\d+)?",expression or "") and expression.split()[0] in columns


def register_routes(app,auth,repo):
    db=repo.db
    # DATA-01
    @app.post("/api/auth/login")
    def login():
        data=body(); require(data,"email","password"); user,token=auth.login(data["email"],data["password"]); response=jsonify(user=user); response.set_cookie(auth.cookie,token,httponly=True,samesite="Lax"); return response
    @app.post("/api/auth/logout")
    def logout(): auth.current(); auth.logout(); response=jsonify(logged_out=True); response.delete_cookie(auth.cookie,httponly=True,samesite="Lax"); return response
    @app.post("/api/auth/password-reset-requests")
    def reset_request():
        data=body(); require(data,"email"); user=db.one("SELECT * FROM users WHERE email=:e AND status='active'",{"e":str(data["email"]).lower()}); token=None
        if user:
            token="reset-"+secrets.token_hex(8); expires=(datetime.now(timezone.utc)+timedelta(seconds=app.config["RESET_TTL"])).isoformat(); db.execute("INSERT INTO reset_tokens(user_id,token_hash,expires_at) VALUES(:u,:h,:e)",{"u":user["id"],"h":hashlib.sha256(token.encode()).hexdigest(),"e":expires})
        return jsonify(accepted=True,reset_token=token if app.config["APP_ENV"]=="test" else None),201
    @app.post("/api/auth/password-resets")
    def reset_password():
        data=body(); require(data,"token","password")
        if not isinstance(data["password"],str) or len(data["password"])<10: raise ApiError(422,"validation_error","Invalid password.",{"password":"minimum 10 characters"})
        item=db.one("SELECT * FROM reset_tokens WHERE token_hash=:h",{"h":hashlib.sha256(str(data["token"]).encode()).hexdigest()})
        if not item or item["used_at"] is not None or item["expires_at"]<=now(): raise ApiError(409,"invalid_reset_token","Reset token is invalid or expired.")
        def update(conn):
            conn.execute(text("UPDATE users SET password_hash=:p WHERE id=:u"),{"p":generate_password_hash(data["password"]),"u":item["user_id"]}); conn.execute(text("UPDATE reset_tokens SET used_at=:n WHERE id=:i"),{"n":now(),"i":item["id"]}); conn.execute(text("DELETE FROM sessions WHERE user_id=:u"),{"u":item["user_id"]})
        db.transaction(update); return jsonify(password_reset=True)
    # DATA-02
    @app.post("/api/datasets")
    def dataset_upload():
        user=auth.current(["analyst"]); workspace_id=request.form.get("workspace_id",type=int); name=request.form.get("name","").strip(); upload=request.files.get("file")
        if not workspace_id or not name or not upload: raise ApiError(422,"validation_error","Workspace, name and CSV file are required.")
        role=repo.workspace_role(workspace_id,user["id"])
        if role!="analyst": raise ApiError(403,"forbidden","Analyst workspace role required.")
        raw=upload.read(app.config["MAX_UPLOAD_BYTES"]+1)
        if not raw or len(raw)>app.config["MAX_UPLOAD_BYTES"] or upload.mimetype not in ("text/csv","application/vnd.ms-excel"): raise ApiError(422,"validation_error","Invalid CSV upload.")
        try:
            reader=csv.DictReader(io.StringIO(raw.decode("utf-8-sig"))); headers=reader.fieldnames or []; rows=list(reader)
        except (UnicodeDecodeError,csv.Error): raise ApiError(422,"validation_error","CSV could not be parsed.")
        if not headers or len(set(headers))!=len(headers) or any(not re.fullmatch(r"[A-Za-z_][A-Za-z0-9_]{0,39}",h or "") for h in headers) or not rows or len(rows)>1000: raise ApiError(422,"validation_error","CSV headers or row count are invalid.")
        quota=repo.need("workspaces",workspace_id)["quota_rows"]
        if len(rows)>quota: raise ApiError(409,"quota_exceeded","Workspace row quota exceeded.")
        def create(conn):
            did=conn.execute(text("INSERT INTO datasets(workspace_id,owner_id,name,description,row_count,created_at) VALUES(:w,:u,:n,:d,:r,:t)"),{"w":workspace_id,"u":user["id"],"n":name,"d":request.form.get("description",""),"r":len(rows),"t":now()}).lastrowid
            conn.execute(text("INSERT INTO ingestion_jobs(dataset_id,status,created_at,finished_at) VALUES(:d,'succeeded',:n,:n)"),{"d":did,"n":now()})
            for pos,key in enumerate(headers):
                numeric=all(_number(row[key]) is not None for row in rows); conn.execute(text("INSERT INTO dataset_columns(dataset_id,name,data_type,position) VALUES(:d,:n,:t,:p)"),{"d":did,"n":key,"t":"number" if numeric else "string","p":pos})
            for idx,row in enumerate(rows):
                typed={k:(_number(v) if _number(v) is not None else v) for k,v in row.items()}; conn.execute(text("INSERT INTO dataset_rows(dataset_id,row_index,data) VALUES(:d,:i,:x)"),{"d":did,"i":idx,"x":json.dumps(typed)})
            return did
        did=duplicate(lambda:db.transaction(create)); repo.audit(workspace_id,user["id"],"dataset.created",name); return jsonify(repo.need("datasets",did)),201
    @app.get("/api/datasets/<int:dataset_id>/ingestion")
    def ingestion(dataset_id): user=auth.current(["analyst"]); repo.dataset(dataset_id,user); return jsonify(db.one("SELECT * FROM ingestion_jobs WHERE dataset_id=:d",{"d":dataset_id}))
    # DATA-03
    @app.get("/api/datasets")
    def datasets():
        user=auth.current(["analyst","viewer"]); page=integer_arg("page",1,1,10000); q=request.args.get("q","").strip(); owner=request.args.get("owner"); status=request.args.get("status","")
        if status and status not in ("ready","archived","failed"): raise ApiError(422,"validation_error","Invalid dataset status.")
        sql="SELECT DISTINCT d.* FROM datasets d JOIN workspace_members wm ON wm.workspace_id=d.workspace_id WHERE wm.user_id=:u AND wm.status='active'"; params={"u":user["id"],"q":f"%{q}%","o":20*(page-1)}
        if q: sql+=" AND (d.name LIKE :q OR d.description LIKE :q)"
        if owner:
            try: params["owner"]=int(owner)
            except ValueError: raise ApiError(422,"validation_error","Invalid owner.")
            sql+=" AND d.owner_id=:owner"
        if status: sql+=" AND d.status=:s"; params["s"]=status
        sql+=" ORDER BY d.id LIMIT 20 OFFSET :o"; return jsonify(items=db.all(sql,params),page=page)
    @app.get("/api/datasets/<int:dataset_id>")
    def dataset_get(dataset_id): user=auth.current(["analyst","viewer"]); return jsonify(repo.dataset(dataset_id,user))
    @app.patch("/api/datasets/<int:dataset_id>")
    def dataset_update(dataset_id):
        user=auth.current(["analyst"]); item=repo.dataset(dataset_id,user,True); data=body(); version(data,item); name=data.get("name",item["name"]); description=data.get("description",item["description"])
        if not isinstance(name,str) or not 2<=len(name)<=80 or not isinstance(description,str) or len(description)>500: raise ApiError(422,"validation_error","Invalid dataset metadata.")
        duplicate(lambda:db.execute("UPDATE datasets SET name=:n,description=:d,version=version+1 WHERE id=:i",{"n":name,"d":description,"i":dataset_id})); return jsonify(repo.need("datasets",dataset_id))
    @app.post("/api/datasets/<int:dataset_id>/archive")
    def dataset_archive(dataset_id):
        user=auth.current(["analyst"]); item=repo.dataset(dataset_id,user,True)
        if item["status"]=="archived": raise ApiError(409,"invalid_state","Dataset is already archived.")
        db.execute("UPDATE datasets SET status='archived',version=version+1 WHERE id=:i",{"i":dataset_id}); return jsonify(repo.need("datasets",dataset_id))
    # DATA-04
    @app.get("/api/datasets/<int:dataset_id>/preview")
    def dataset_preview(dataset_id):
        user=auth.current(["analyst","viewer"]); item=repo.dataset(dataset_id,user); offset=integer_arg("offset",0,0,1000000); limit=integer_arg("limit",25,1,100)
        if item["status"]!="ready": raise ApiError(409,"invalid_state","Dataset is not available for preview.")
        rows=db.all("SELECT row_index,data FROM dataset_rows WHERE dataset_id=:d ORDER BY row_index LIMIT :l OFFSET :o",{"d":dataset_id,"l":limit,"o":offset}); return jsonify(items=[{"row_index":x["row_index"],**json.loads(x["data"])} for x in rows],offset=offset,limit=limit)
    @app.get("/api/datasets/<int:dataset_id>/schema")
    def schema(dataset_id): user=auth.current(["analyst","viewer"]); repo.dataset(dataset_id,user); return jsonify(items=repo.columns(dataset_id))
    # DATA-05
    @app.post("/api/datasets/<int:dataset_id>/query-preview")
    def query_preview(dataset_id):
        user=auth.current(["analyst"]); repo.dataset(dataset_id,user); data=body(); filters=data.get("filters",[]); limit=data.get("limit",50)
        if not isinstance(filters,list) or not isinstance(limit,int) or limit<1 or limit>100: raise ApiError(422,"validation_error","Invalid query preview.")
        rows=_filter_rows(repo.rows(dataset_id),filters,{x["name"] for x in repo.columns(dataset_id)}); return jsonify(items=rows[:limit],matched=len(rows))
    @app.put("/api/charts/<int:chart_id>/filters")
    def filters_update(chart_id):
        user=auth.current(["analyst"]); chart=repo.chart(chart_id,user,True); data=body(); filters=data.get("filters"); _filter_rows([],filters,{x["name"] for x in repo.columns(chart["dataset_id"])})
        db.execute("UPDATE charts SET filters=:f,version=version+1 WHERE id=:i",{"f":json.dumps(filters),"i":chart_id}); return jsonify(repo.need("charts",chart_id))
    # DATA-06
    @app.post("/api/charts")
    def chart_create():
        user=auth.current(["analyst"]); data=body(); require(data,"dataset_id","name","chart_type","dimension","measure","aggregation"); dataset=repo.dataset(int(data["dataset_id"]),user); columns={x["name"]:x["data_type"] for x in repo.columns(dataset["id"])}
        _chart_fields(data,columns); cid=db.execute("INSERT INTO charts(dataset_id,owner_id,name,chart_type,dimension,measure,aggregation,filters,created_at) VALUES(:d,:u,:n,:t,:x,:m,:a,'[]',:z)",{"d":dataset["id"],"u":user["id"],"n":data["name"],"t":data["chart_type"],"x":data["dimension"],"m":data["measure"],"a":data["aggregation"],"z":now()}); db.execute("INSERT INTO lineage_edges(dataset_id,target_type,target_id,created_at) VALUES(:d,'chart',:c,:n)",{"d":dataset["id"],"c":cid,"n":now()}); return jsonify(repo.need("charts",cid)),201
    @app.get("/api/charts/<int:chart_id>")
    def chart_get(chart_id): user=auth.current(["analyst","viewer"]); return jsonify(repo.chart(chart_id,user))
    @app.patch("/api/charts/<int:chart_id>")
    def chart_update(chart_id):
        user=auth.current(["analyst"]); chart=repo.chart(chart_id,user,True); data=body(); version(data,chart); merged={**chart,**data}; _chart_fields(merged,{x["name"]:x["data_type"] for x in repo.columns(chart["dataset_id"])})
        db.execute("UPDATE charts SET name=:n,chart_type=:t,dimension=:d,measure=:m,aggregation=:a,version=version+1 WHERE id=:i",{"n":merged["name"],"t":merged["chart_type"],"d":merged["dimension"],"m":merged["measure"],"a":merged["aggregation"],"i":chart_id}); return jsonify(repo.need("charts",chart_id))
    @app.post("/api/charts/<int:chart_id>/data")
    def chart_data(chart_id):
        user=auth.current(["analyst"]); chart=repo.chart(chart_id,user); rows=_filter_rows(repo.rows(chart["dataset_id"]),json.loads(chart["filters"]),{x["name"] for x in repo.columns(chart["dataset_id"])}); groups={}
        for row in rows: groups.setdefault(str(row[chart["dimension"]]),[]).append(row[chart["measure"]])
        points=[]
        for key,values in sorted(groups.items()): points.append({"dimension":key,"value":len(values) if chart["aggregation"]=="count" else (sum(values)/len(values) if chart["aggregation"]=="avg" else sum(values))})
        return jsonify(items=points)
    # DATA-07
    @app.get("/api/datasets/<int:dataset_id>/calculated-columns")
    def calculated_list(dataset_id): user=auth.current(["analyst"]); repo.dataset(dataset_id,user); return jsonify(items=db.all("SELECT * FROM calculated_columns WHERE dataset_id=:d ORDER BY id",{"d":dataset_id}))
    @app.post("/api/datasets/<int:dataset_id>/calculated-columns")
    def calculated_create(dataset_id):
        user=auth.current(["analyst"]); repo.dataset(dataset_id,user,True); data=body(); require(data,"name","expression"); columns={x["name"] for x in repo.columns(dataset_id)}
        if not re.fullmatch(r"[A-Za-z_][A-Za-z0-9_]{0,39}",str(data["name"])) or not valid_expression(data["expression"],columns): raise ApiError(422,"validation_error","Invalid calculated column.")
        cid=duplicate(lambda:db.execute("INSERT INTO calculated_columns(dataset_id,name,expression) VALUES(:d,:n,:e)",{"d":dataset_id,"n":data["name"],"e":data["expression"]})); return jsonify(repo.need("calculated_columns",cid)),201
    @app.patch("/api/calculated-columns/<int:column_id>")
    def calculated_update(column_id):
        user=auth.current(["analyst"]); item=repo.need("calculated_columns",column_id); repo.dataset(item["dataset_id"],user,True); data=body(); version(data,item); expression=data.get("expression",item["expression"]); name_=data.get("name",item["name"])
        if not re.fullmatch(r"[A-Za-z_][A-Za-z0-9_]{0,39}",str(name_)) or not valid_expression(expression,{x["name"] for x in repo.columns(item["dataset_id"])}): raise ApiError(422,"validation_error","Invalid calculated column.")
        duplicate(lambda:db.execute("UPDATE calculated_columns SET name=:n,expression=:e,version=version+1 WHERE id=:i",{"n":name_,"e":expression,"i":column_id})); return jsonify(repo.need("calculated_columns",column_id))
    @app.delete("/api/calculated-columns/<int:column_id>")
    def calculated_delete(column_id): user=auth.current(["analyst"]); item=repo.need("calculated_columns",column_id); repo.dataset(item["dataset_id"],user,True); db.execute("DELETE FROM calculated_columns WHERE id=:i",{"i":column_id}); return "",204
    # DATA-08
    @app.post("/api/dashboards")
    def dashboard_create():
        user=auth.current(["analyst"]); data=body(); require(data,"workspace_id","name","chart_ids"); workspace_id=int(data["workspace_id"])
        if repo.workspace_role(workspace_id,user["id"])!="analyst": raise ApiError(403,"forbidden","Analyst workspace role required.")
        if not isinstance(data["chart_ids"],list): raise ApiError(422,"validation_error","Chart IDs must be an array.")
        for cid in data["chart_ids"]: chart=repo.chart(int(cid),user); dataset=repo.need("datasets",chart["dataset_id"]); _same_workspace(workspace_id,dataset["workspace_id"])
        def create(conn):
            did=conn.execute(text("INSERT INTO dashboards(workspace_id,owner_id,name,description,created_at) VALUES(:w,:u,:n,:d,:t)"),{"w":workspace_id,"u":user["id"],"n":data["name"],"d":data.get("description",""),"t":now()}).lastrowid
            for pos,cid in enumerate(data["chart_ids"]): conn.execute(text("INSERT INTO dashboard_charts VALUES(:d,:c,:p)"),{"d":did,"c":cid,"p":pos})
            return did
        did=db.transaction(create); return jsonify(_dashboard_payload(repo,user,did)),201
    @app.patch("/api/dashboards/<int:dashboard_id>")
    def dashboard_update(dashboard_id):
        user=auth.current(["analyst"]); item=repo.dashboard(dashboard_id,user,True); data=body(); version(data,item); name_=data.get("name",item["name"])
        if not isinstance(name_,str) or not 2<=len(name_)<=80: raise ApiError(422,"validation_error","Invalid dashboard name.")
        db.execute("UPDATE dashboards SET name=:n,description=:d,version=version+1 WHERE id=:i",{"n":name_,"d":data.get("description",item["description"]),"i":dashboard_id}); return jsonify(_dashboard_payload(repo,user,dashboard_id))
    @app.post("/api/dashboards/<int:dashboard_id>/shares")
    def share_create(dashboard_id):
        user=auth.current(["analyst"]); item=repo.dashboard(dashboard_id,user,True); data=body(); require(data,"user_id","permission")
        if item["owner_id"]!=user["id"]: raise ApiError(403,"forbidden","Only owner may share this dashboard.")
        if data["permission"] not in ("read","edit"): raise ApiError(422,"validation_error","Permission must be read or edit.")
        repo.workspace_role(item["workspace_id"],int(data["user_id"])); sid=duplicate(lambda:db.execute("INSERT INTO dashboard_shares(dashboard_id,user_id,permission) VALUES(:d,:u,:p)",{"d":dashboard_id,"u":int(data["user_id"]),"p":data["permission"]})); return jsonify(repo.need("dashboard_shares",sid)),201
    @app.delete("/api/dashboard-shares/<int:share_id>")
    def share_delete(share_id):
        user=auth.current(["analyst"]); share=repo.need("dashboard_shares",share_id); dashboard=repo.dashboard(share["dashboard_id"],user,True)
        if dashboard["owner_id"]!=user["id"]: raise ApiError(403,"forbidden","Only owner may revoke a share.")
        db.execute("DELETE FROM dashboard_shares WHERE id=:i",{"i":share_id}); return "",204
    @app.get("/api/dashboards/<int:dashboard_id>")
    def dashboard_get(dashboard_id): user=auth.current(["analyst","viewer"]); return jsonify(_dashboard_payload(repo,user,dashboard_id))
    # DATA-09
    @app.get("/api/charts/<int:chart_id>/export")
    def chart_export(chart_id):
        user=auth.current(["analyst","viewer"]); repo.chart(chart_id,user)
        if request.args.get("format")!="csv": raise ApiError(422,"validation_error","Only CSV chart export is supported.")
        chart=repo.need("charts",chart_id); rows=repo.rows(chart["dataset_id"]); output=io.StringIO(); writer=csv.DictWriter(output,fieldnames=list(rows[0])); writer.writeheader(); writer.writerows(rows); db.execute("INSERT INTO export_jobs(actor_id,resource_type,resource_id,format,status,created_at) VALUES(:u,'chart',:r,'csv','succeeded',:n)",{"u":user["id"],"r":chart_id,"n":now()}); return Response(output.getvalue(),mimetype="text/csv",headers={"Content-Disposition":f"attachment; filename=chart-{chart_id}.csv"})
    @app.get("/api/dashboards/<int:dashboard_id>/export")
    def dashboard_export(dashboard_id):
        user=auth.current(["analyst","viewer"]); item=repo.dashboard(dashboard_id,user)
        if request.args.get("format")!="pdf": raise ApiError(422,"validation_error","Only PDF dashboard export is supported.")
        content=(f"%PDF-1.4\n% Offline dashboard export\n{item['name']}\n%%EOF\n").encode(); db.execute("INSERT INTO export_jobs(actor_id,resource_type,resource_id,format,status,created_at) VALUES(:u,'dashboard',:r,'pdf','succeeded',:n)",{"u":user["id"],"r":dashboard_id,"n":now()}); return Response(content,mimetype="application/pdf",headers={"Content-Disposition":f"attachment; filename=dashboard-{dashboard_id}.pdf"})
    # DATA-10
    @app.get("/api/data-sources")
    def sources():
        user=auth.current(["analyst","admin"]); rows=db.all("SELECT * FROM data_sources ORDER BY id") if user["global_role"]=="admin" else db.all("SELECT * FROM data_sources WHERE owner_id=:u ORDER BY id",{"u":user["id"]}); return jsonify(items=[public_source(x) for x in rows])
    @app.post("/api/data-sources")
    def source_create():
        user=auth.current(["analyst","admin"]); data=body(); require(data,"workspace_id","name","type","host","database_name","username","secret"); workspace_id=int(data["workspace_id"]); repo.workspace_role(workspace_id,user["id"])
        _source_fields(data); sid=duplicate(lambda:db.execute("INSERT INTO data_sources(workspace_id,owner_id,name,type,host,database_name,username,secret_hash,secret_mask) VALUES(:w,:u,:n,:t,:h,:d,:x,:s,'********')",{"w":workspace_id,"u":user["id"],"n":data["name"],"t":data["type"],"h":data["host"],"d":data["database_name"],"x":data["username"],"s":hashlib.sha256(data["secret"].encode()).hexdigest()})); return jsonify(public_source(repo.need("data_sources",sid))),201
    @app.patch("/api/data-sources/<int:source_id>")
    def source_update(source_id):
        user=auth.current(["analyst","admin"]); item=repo.source(source_id,user,True); data=body(); version(data,item); merged={**item,**data}; _source_fields(merged); values={"n":merged["name"],"h":merged["host"],"d":merged["database_name"],"u":merged["username"],"i":source_id,"s":hashlib.sha256(data["secret"].encode()).hexdigest() if data.get("secret") else item["secret_hash"]}; duplicate(lambda:db.execute("UPDATE data_sources SET name=:n,host=:h,database_name=:d,username=:u,secret_hash=:s,version=version+1,status='untested' WHERE id=:i",values)); return jsonify(public_source(repo.need("data_sources",source_id)))
    @app.post("/api/data-sources/<int:source_id>/test")
    def source_test(source_id):
        user=auth.current(["analyst","admin"]); item=repo.source(source_id,user,True); status="ready" if item["host"] in ("warehouse.local","localhost") else "failed"; db.execute("UPDATE data_sources SET status=:s WHERE id=:i",{"s":status,"i":source_id}); return jsonify(source_id=source_id,status=status,latency_ms=12)
    @app.delete("/api/data-sources/<int:source_id>")
    def source_delete(source_id): user=auth.current(["analyst","admin"]); repo.source(source_id,user,True); db.execute("DELETE FROM data_sources WHERE id=:i",{"i":source_id}); return "",204
    # DATA-11
    @app.get("/api/datasets/<int:dataset_id>/lineage")
    def lineage(dataset_id): user=auth.current(["analyst","admin"]); repo.dataset(dataset_id,user); return jsonify(dataset_id=dataset_id,edges=db.all("SELECT * FROM lineage_edges WHERE dataset_id=:d ORDER BY id",{"d":dataset_id}))
    @app.get("/api/workspaces/<int:workspace_id>/audit-events")
    def audit_events(workspace_id): user=auth.current(["analyst","admin"]); repo.workspace_role(workspace_id,user["id"]); return jsonify(items=db.all("SELECT * FROM audit_events WHERE workspace_id=:w ORDER BY id DESC LIMIT 100",{"w":workspace_id}))
    # DATA-12
    @app.get("/api/admin/workspaces")
    def admin_workspaces(): auth.current(["admin"]); return jsonify(items=db.all("SELECT * FROM workspaces ORDER BY id"))
    @app.post("/api/admin/workspaces")
    def admin_workspace_create():
        auth.current(["admin"]); data=body(); require(data,"name","slug","quota_rows")
        if not re.fullmatch(r"[a-z0-9-]{3,40}",str(data["slug"])) or not isinstance(data["quota_rows"],int) or not 100<=data["quota_rows"]<=1000000: raise ApiError(422,"validation_error","Invalid workspace settings.")
        wid=duplicate(lambda:db.execute("INSERT INTO workspaces(name,slug,quota_rows) VALUES(:n,:s,:q)",{"n":data["name"],"s":data["slug"],"q":data["quota_rows"]})); return jsonify(repo.need("workspaces",wid)),201
    @app.patch("/api/admin/workspaces/<int:workspace_id>")
    def admin_workspace_update(workspace_id):
        auth.current(["admin"]); item=repo.need("workspaces",workspace_id); data=body(); version(data,item); status=data.get("status",item["status"]); quota=data.get("quota_rows",item["quota_rows"])
        if status not in ("active","suspended") or not isinstance(quota,int) or not 100<=quota<=1000000: raise ApiError(422,"validation_error","Invalid workspace settings.")
        db.execute("UPDATE workspaces SET name=:n,quota_rows=:q,status=:s,version=version+1 WHERE id=:i",{"n":data.get("name",item["name"]),"q":quota,"s":status,"i":workspace_id}); return jsonify(repo.need("workspaces",workspace_id))
    @app.patch("/api/admin/workspaces/<int:workspace_id>/members/<int:user_id>")
    def admin_member_update(workspace_id,user_id):
        auth.current(["admin"]); repo.need("workspaces",workspace_id); repo.need("users",user_id); data=body(); require(data,"role","status")
        if data["role"] not in ("analyst","viewer","admin") or data["status"] not in ("active","suspended"): raise ApiError(422,"validation_error","Invalid membership settings.")
        db.execute("INSERT INTO workspace_members VALUES(:w,:u,:r,:s) ON CONFLICT(workspace_id,user_id) DO UPDATE SET role=:r,status=:s",{"w":workspace_id,"u":user_id,"r":data["role"],"s":data["status"]}); return jsonify(workspace_id=workspace_id,user_id=user_id,role=data["role"],status=data["status"])
    @app.patch("/api/admin/connectors/<int:connector_id>")
    def connector_update(connector_id):
        auth.current(["admin"]); item=repo.need("connector_settings",connector_id); data=body(); version(data,item)
        if not isinstance(data.get("enabled"),bool): raise ApiError(422,"validation_error","Enabled must be boolean.")
        db.execute("UPDATE connector_settings SET enabled=:e,version=version+1 WHERE id=:i",{"e":int(data["enabled"]),"i":connector_id}); return jsonify(repo.need("connector_settings",connector_id))


def _number(value):
    try:return float(value)
    except (TypeError,ValueError):return None
def _filter_rows(rows,filters,columns):
    if not isinstance(filters,list): raise ApiError(422,"validation_error","Filters must be an array.")
    result=list(rows)
    for item in filters:
        if not isinstance(item,dict) or item.get("column") not in columns or item.get("operator") not in ("eq","ne","gt","gte","lt","lte","contains") or "value" not in item: raise ApiError(422,"validation_error","Invalid filter definition.")
        column,operator,wanted=item["column"],item["operator"],item["value"]
        def match(row):
            actual=row.get(column)
            if operator=="eq": return actual==wanted
            if operator=="ne": return actual!=wanted
            if operator=="contains": return str(wanted).lower() in str(actual).lower()
            a,b=_number(actual),_number(wanted)
            if a is None or b is None: return False
            return {"gt":a>b,"gte":a>=b,"lt":a<b,"lte":a<=b}[operator]
        result=[row for row in result if match(row)]
    return result
def _chart_fields(data,columns):
    if data.get("chart_type") not in ("bar","line","pie") or data.get("dimension") not in columns or data.get("measure") not in columns or data.get("aggregation") not in ("sum","avg","count") or (data.get("aggregation")!="count" and columns.get(data.get("measure"))!="number"): raise ApiError(422,"validation_error","Invalid chart definition.")
def _same_workspace(expected,actual):
    if expected!=actual: raise ApiError(422,"validation_error","Chart belongs to another workspace.")
def _dashboard_payload(repo,user,dashboard_id):
    item=repo.dashboard(dashboard_id,user); item["charts"]=repo.db.all("SELECT c.* FROM dashboard_charts dc JOIN charts c ON c.id=dc.chart_id WHERE dc.dashboard_id=:d ORDER BY dc.position",{"d":dashboard_id}); item["shares"]=repo.db.all("SELECT id,user_id,permission FROM dashboard_shares WHERE dashboard_id=:d ORDER BY id",{"d":dashboard_id}) if item["owner_id"]==user["id"] or user["global_role"]=="admin" else []; return item
def _source_fields(data):
    if data.get("type") not in ("postgres","sqlite") or data.get("host") not in ("warehouse.local","localhost","offline.local") or not re.fullmatch(r"[A-Za-z0-9_]{1,50}",str(data.get("database_name",""))) or not re.fullmatch(r"[A-Za-z0-9_]{1,50}",str(data.get("username",""))) or ("secret" in data and len(str(data["secret"]))<8): raise ApiError(422,"validation_error","Invalid data source configuration.")
