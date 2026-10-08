import hashlib,secrets
from datetime import datetime,timezone,timedelta
from flask import request
from werkzeug.security import check_password_hash
from .errors import ApiError


def now(): return datetime.now(timezone.utc).isoformat()


class Auth:
    def __init__(self,db,cookie,ttl): self.db=db; self.cookie=cookie; self.ttl=ttl
    def login(self,email,password):
        user=self.db.one("SELECT * FROM users WHERE email=:e",{"e":str(email).lower()})
        if not user or user["status"]!="active" or not check_password_hash(user["password_hash"],password): raise ApiError(401,"invalid_credentials","Email or password is invalid.")
        token=secrets.token_urlsafe(32); expires=(datetime.now(timezone.utc)+timedelta(seconds=self.ttl)).isoformat()
        self.db.execute("INSERT INTO sessions(user_id,token_hash,expires_at) VALUES(:u,:h,:e)",{"u":user["id"],"h":hashlib.sha256(token.encode()).hexdigest(),"e":expires}); return self.public(user),token
    def current(self,roles=None):
        token=request.cookies.get(self.cookie)
        if not token: raise ApiError(401,"unauthenticated","Authentication required.")
        user=self.db.one("SELECT u.* FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=:h AND s.expires_at>:n AND u.status='active'",{"h":hashlib.sha256(token.encode()).hexdigest(),"n":now()})
        if not user: raise ApiError(401,"unauthenticated","Authentication required.")
        if roles and user["global_role"] not in roles: raise ApiError(403,"forbidden","Role is not permitted.")
        return user
    def logout(self):
        token=request.cookies.get(self.cookie)
        if token: self.db.execute("DELETE FROM sessions WHERE token_hash=:h",{"h":hashlib.sha256(token.encode()).hexdigest()})
    @staticmethod
    def public(user): return {k:user[k] for k in ("id","email","display_name","global_role","status","version")}
