import {ApiError} from './errors.js';import {randomToken,tokenHash,verifyPassword} from './security.js';
export class Auth{
 constructor(db,{cookie='hotel_session',ttl=86400}={}){this.db=db;this.cookie=cookie;this.ttl=ttl;}
 login(email,password){const user=this.db.prepare('SELECT * FROM users WHERE email=?').get(String(email).toLowerCase());if(!user||user.status!=='active'||!verifyPassword(password,user.password_hash))throw new ApiError(401,'invalid_credentials','Email or password is invalid.');const token=randomToken(),expires=new Date(Date.now()+this.ttl*1000).toISOString();this.db.prepare('INSERT INTO sessions(user_id,token_hash,expires_at) VALUES(?,?,?)').run(user.id,tokenHash(token),expires);return [this.public(user),token];}
 current(req,roles){const token=req.cookies?.[this.cookie];if(!token)throw new ApiError(401,'unauthenticated','Authentication required.');const user=this.db.prepare("SELECT u.* FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND s.expires_at>? AND u.status='active'").get(tokenHash(token),new Date().toISOString());if(!user)throw new ApiError(401,'unauthenticated','Authentication required.');if(roles&&!roles.includes(user.role))throw new ApiError(403,'forbidden','Role is not permitted.');return user;}
 logout(req){const token=req.cookies?.[this.cookie];if(token)this.db.prepare('DELETE FROM sessions WHERE token_hash=?').run(tokenHash(token));}
 public(user){return Object.fromEntries(['id','email','name','role','status','version'].map(k=>[k,user[k]]));}
}
