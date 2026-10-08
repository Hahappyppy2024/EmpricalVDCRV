import os
from pathlib import Path
from flask import Flask, jsonify, send_from_directory
from flask_sock import Sock
from .auth import Auth
from .db import Database
from .errors import ApiError
from .repository import Repository
from .realtime import RealtimeGateway
from .routes import register_routes


def create_app(config=None):
    config = config or {}
    root = Path(__file__).resolve().parent.parent
    app = Flask(__name__, static_folder=None)
    app.config.update(APP_ENV=os.getenv("APP_ENV","development"),DATABASE_PATH=os.getenv("DATABASE_PATH",str(root/"var/team_chat.sqlite")),SESSION_COOKIE=os.getenv("SESSION_COOKIE","chat_session"),SESSION_TTL=int(os.getenv("SESSION_TTL","86400")),MAX_ATTACHMENT_BYTES=int(os.getenv("MAX_ATTACHMENT_BYTES","1048576")))
    app.config.update(config)
    db = Database(app.config["DATABASE_PATH"])
    auth = Auth(db, app.config["SESSION_COOKIE"], app.config["SESSION_TTL"])
    repo = Repository(db)
    gateway = RealtimeGateway(repo)
    app.extensions.update(db=db, auth=auth, repo=repo, realtime=gateway)
    register_routes(app, Sock(app), auth, repo, gateway)

    @app.get("/")
    def index():
        return send_from_directory(root/"public", "index.html")

    @app.get("/assets/<path:name>")
    def assets(name):
        return send_from_directory(root/"public", name)

    @app.errorhandler(ApiError)
    def api_error(error):
        return jsonify(error={"code":error.code,"message":error.message,"fields":error.fields}), error.status

    @app.errorhandler(404)
    def not_found(_):
        return jsonify(error={"code":"not_found","message":"Resource not found.","fields":{}}), 404

    @app.errorhandler(Exception)
    def internal_error(_):
        return jsonify(error={"code":"internal_error","message":"The request could not be completed.","fields":{}}), 500

    return app
