import os
from pathlib import Path
from flask import Flask,jsonify,send_from_directory
from .auth import Auth
from .db import Database
from .errors import ApiError
from .repository import Repository
from .routes import register_routes


def create_app(config=None):
    config=config or {}; root=Path(__file__).resolve().parent.parent; app=Flask(__name__,static_folder=None)
    app.config.update(APP_ENV=os.getenv("APP_ENV","development"),DATABASE_PATH=os.getenv("DATABASE_PATH",str(root/"var/analytics.sqlite")),SESSION_COOKIE=os.getenv("SESSION_COOKIE","analytics_session"),SESSION_TTL=int(os.getenv("SESSION_TTL","86400")),RESET_TTL=int(os.getenv("RESET_TTL","1800")),MAX_UPLOAD_BYTES=int(os.getenv("MAX_UPLOAD_BYTES","1048576")))
    app.config.update(config); db=Database(app.config["DATABASE_PATH"]); auth=Auth(db,app.config["SESSION_COOKIE"],app.config["SESSION_TTL"]); repo=Repository(db); app.extensions.update(db=db,auth=auth,repo=repo)
    register_routes(app,auth,repo)
    @app.get("/")
    def index(): return send_from_directory(root/"public","index.html")
    @app.get("/assets/<path:name>")
    def assets(name): return send_from_directory(root/"public",name)
    @app.errorhandler(ApiError)
    def api_error(error): return jsonify(error={"code":error.code,"message":error.message,"fields":error.fields}),error.status
    @app.errorhandler(404)
    def missing(_): return jsonify(error={"code":"not_found","message":"Resource not found.","fields":{}}),404
    @app.errorhandler(Exception)
    def internal(_): return jsonify(error={"code":"internal_error","message":"The request could not be completed.","fields":{}}),500
    return app
