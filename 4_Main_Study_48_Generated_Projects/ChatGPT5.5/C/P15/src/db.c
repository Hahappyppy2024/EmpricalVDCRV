#include "netd.h"
#include <openssl/evp.h>
#include <stdio.h>
#include <string.h>

static int exec_sql(netd_store *s,const char *sql) { char *msg=NULL; int rc=sqlite3_exec(s->db,sql,NULL,NULL,&msg); if(msg) sqlite3_free(msg); return rc==SQLITE_OK?0:-1; }
static void hash_token(const char *token,unsigned char out[32]) { unsigned int n=0; EVP_Digest(token,strlen(token),out,&n,EVP_sha256(),NULL); }

int store_open(netd_store *s,const netd_config *c,char *err,size_t cap) {
    memset(s,0,sizeof(*s)); pthread_mutex_init(&s->lock,NULL);
    int rc=sqlite3_open_v2(c->database_path,&s->db,SQLITE_OPEN_READWRITE|SQLITE_OPEN_CREATE|SQLITE_OPEN_FULLMUTEX,NULL);
    if(rc!=SQLITE_OK){snprintf(err,cap,"DB_OPEN"); if(s->db)sqlite3_close(s->db);s->db=NULL;return -1;}
    sqlite3_busy_timeout(s->db,c->sqlite_busy_timeout_ms);
    if(exec_sql(s,"PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;")){snprintf(err,cap,"DB_PRAGMA");return -1;} return 0;
}
void store_close(netd_store *s){if(s->db)sqlite3_close(s->db);s->db=NULL;pthread_mutex_destroy(&s->lock);}

static int seed_token(netd_store *s,long pid,const char *token,const char *role,int revoked,long expires){
    unsigned char h[32];hash_token(token,h);sqlite3_stmt *q=NULL;
    if(sqlite3_prepare_v2(s->db,"INSERT INTO tokens(principal_id,token_hash,role,revoked,expires_at) VALUES(?,?,?,?,?)",-1,&q,NULL)!=SQLITE_OK)return -1;
    sqlite3_bind_int64(q,1,pid);sqlite3_bind_blob(q,2,h,32,SQLITE_TRANSIENT);sqlite3_bind_text(q,3,role,-1,SQLITE_TRANSIENT);sqlite3_bind_int(q,4,revoked);sqlite3_bind_int64(q,5,expires);
    int rc=sqlite3_step(q);sqlite3_finalize(q);return rc==SQLITE_DONE?0:-1;
}

int store_initialize(netd_store *s,int reset,char *err,size_t cap){
    pthread_mutex_lock(&s->lock);
    if(reset&&exec_sql(s,"DROP TABLE IF EXISTS maintenance_runs;DROP TABLE IF EXISTS records;DROP TABLE IF EXISTS grants;DROP TABLE IF EXISTS tokens;DROP TABLE IF EXISTS principals;DROP TABLE IF EXISTS schema_version;")){goto fail;}
    const char *schema="BEGIN;CREATE TABLE IF NOT EXISTS schema_version(version INTEGER NOT NULL);"
      "INSERT INTO schema_version SELECT 1 WHERE NOT EXISTS(SELECT 1 FROM schema_version);"
      "CREATE TABLE IF NOT EXISTS principals(id INTEGER PRIMARY KEY,name TEXT UNIQUE NOT NULL);"
      "CREATE TABLE IF NOT EXISTS tokens(id INTEGER PRIMARY KEY,principal_id INTEGER NOT NULL REFERENCES principals(id),token_hash BLOB UNIQUE NOT NULL,role TEXT NOT NULL,revoked INTEGER NOT NULL DEFAULT 0,expires_at INTEGER NOT NULL);"
      "CREATE TABLE IF NOT EXISTS grants(principal_id INTEGER NOT NULL REFERENCES principals(id),namespace TEXT NOT NULL,can_write INTEGER NOT NULL,PRIMARY KEY(principal_id,namespace));"
      "CREATE TABLE IF NOT EXISTS records(namespace TEXT NOT NULL,key TEXT NOT NULL,value BLOB NOT NULL,version INTEGER NOT NULL,expires_at INTEGER,PRIMARY KEY(namespace,key));"
      "CREATE INDEX IF NOT EXISTS idx_records_list ON records(namespace,key);"
      "CREATE TABLE IF NOT EXISTS maintenance_runs(id INTEGER PRIMARY KEY,run_at INTEGER NOT NULL,deleted_count INTEGER NOT NULL);COMMIT;";
    if(exec_sql(s,schema))goto fail;
    sqlite3_stmt *q=NULL;if(sqlite3_prepare_v2(s->db,"SELECT COUNT(*) FROM principals",-1,&q,NULL)!=SQLITE_OK)goto fail;
    int empty=sqlite3_step(q)==SQLITE_ROW&&sqlite3_column_int(q,0)==0;sqlite3_finalize(q);
    if(empty){
      if(exec_sql(s,"BEGIN;INSERT INTO principals(id,name) VALUES(1,'client'),(2,'monitor'),(3,'admin'),(4,'revoked');INSERT INTO grants VALUES(1,'public',1),(1,'client',1),(3,'*',1);COMMIT;"))goto fail;
      long future=(long)time(NULL)+315360000L;
      if(seed_token(s,1,"client-token","client",0,future)||seed_token(s,2,"monitor-token","monitoring",0,future)||seed_token(s,3,"admin-token","administrator",0,future)||seed_token(s,4,"revoked-token","client",1,future))goto fail;
      if(exec_sql(s,"INSERT INTO records(namespace,key,value,version,expires_at) VALUES('public','welcome',X'68656C6C6F',1,NULL),('client','seed',X'736565646564',1,NULL);"))goto fail;
    }
    pthread_mutex_unlock(&s->lock);return 0;
fail: snprintf(err,cap,"DB_SCHEMA");exec_sql(s,"ROLLBACK;");pthread_mutex_unlock(&s->lock);return -1;
}

int store_integrity(netd_store *s){pthread_mutex_lock(&s->lock);sqlite3_stmt*q=NULL;int ok=sqlite3_prepare_v2(s->db,"PRAGMA quick_check",-1,&q,NULL)==SQLITE_OK&&sqlite3_step(q)==SQLITE_ROW&&!strcmp((const char*)sqlite3_column_text(q,0),"ok");if(q)sqlite3_finalize(q);pthread_mutex_unlock(&s->lock);return ok;}
int store_auth(netd_store*s,const char*token,netd_session*session){unsigned char h[32];hash_token(token,h);pthread_mutex_lock(&s->lock);sqlite3_stmt*q=NULL;int found=0;
 if(sqlite3_prepare_v2(s->db,"SELECT p.id,p.name,t.role FROM tokens t JOIN principals p ON p.id=t.principal_id WHERE t.token_hash=? AND t.revoked=0 AND t.expires_at>?",-1,&q,NULL)==SQLITE_OK){sqlite3_bind_blob(q,1,h,32,SQLITE_TRANSIENT);sqlite3_bind_int64(q,2,(long long)time(NULL));if(sqlite3_step(q)==SQLITE_ROW){session->principal_id=sqlite3_column_int64(q,0);snprintf(session->principal,sizeof(session->principal),"%s",sqlite3_column_text(q,1));const char*r=(const char*)sqlite3_column_text(q,2);session->role=!strcmp(r,"client")?ROLE_CLIENT:!strcmp(r,"monitoring")?ROLE_MONITORING:ROLE_ADMIN;session->authenticated=1;found=1;}}if(q)sqlite3_finalize(q);pthread_mutex_unlock(&s->lock);return found;}
int store_has_grant(netd_store*s,long pid,netd_role role,const char*ns,int write){if(role==ROLE_ADMIN)return 1;if(role!=ROLE_CLIENT)return 0;pthread_mutex_lock(&s->lock);sqlite3_stmt*q=NULL;int ok=0;if(sqlite3_prepare_v2(s->db,"SELECT can_write FROM grants WHERE principal_id=? AND namespace=?",-1,&q,NULL)==SQLITE_OK){sqlite3_bind_int64(q,1,pid);sqlite3_bind_text(q,2,ns,-1,SQLITE_TRANSIENT);if(sqlite3_step(q)==SQLITE_ROW)ok=!write||sqlite3_column_int(q,0);}if(q)sqlite3_finalize(q);pthread_mutex_unlock(&s->lock);return ok;}

int store_put(netd_store*s,const char*ns,const char*key,long ttl,const unsigned char*v,size_t n,long long*exp){pthread_mutex_lock(&s->lock);sqlite3_stmt*q=NULL;long long e=ttl?time(NULL)+ttl:0;int rc=-1;if(sqlite3_prepare_v2(s->db,"INSERT INTO records(namespace,key,value,version,expires_at) VALUES(?,?,?,1,CASE WHEN ?=0 THEN NULL ELSE ? END)",-1,&q,NULL)==SQLITE_OK){sqlite3_bind_text(q,1,ns,-1,SQLITE_TRANSIENT);sqlite3_bind_text(q,2,key,-1,SQLITE_TRANSIENT);sqlite3_bind_blob(q,3,v,(int)n,SQLITE_TRANSIENT);sqlite3_bind_int64(q,4,e);sqlite3_bind_int64(q,5,e);rc=sqlite3_step(q)==SQLITE_DONE?0:1;}if(q)sqlite3_finalize(q);pthread_mutex_unlock(&s->lock);*exp=e;return rc;}
int store_get(netd_store*s,const char*ns,const char*key,unsigned char*v,size_t cap,size_t*n,int*ver,long long*exp){pthread_mutex_lock(&s->lock);sqlite3_stmt*q=NULL;int rc=1;if(sqlite3_prepare_v2(s->db,"SELECT value,version,COALESCE(expires_at,0) FROM records WHERE namespace=? AND key=? AND (expires_at IS NULL OR expires_at>?)",-1,&q,NULL)==SQLITE_OK){sqlite3_bind_text(q,1,ns,-1,SQLITE_TRANSIENT);sqlite3_bind_text(q,2,key,-1,SQLITE_TRANSIENT);sqlite3_bind_int64(q,3,(long long)time(NULL));if(sqlite3_step(q)==SQLITE_ROW){int z=sqlite3_column_bytes(q,0);if(z>=0&&(size_t)z<=cap){memcpy(v,sqlite3_column_blob(q,0),(size_t)z);*n=(size_t)z;*ver=sqlite3_column_int(q,1);*exp=sqlite3_column_int64(q,2);rc=0;}}}if(q)sqlite3_finalize(q);pthread_mutex_unlock(&s->lock);return rc;}
int store_list(netd_store*s,const char*ns,const char*prefix,int limit,const char*after,char*out,size_t cap){pthread_mutex_lock(&s->lock);sqlite3_stmt*q=NULL;int rc=-1;out[0]=0;if(sqlite3_prepare_v2(s->db,"SELECT key FROM records WHERE namespace=? AND key LIKE ?||'%' AND key>? AND (expires_at IS NULL OR expires_at>?) ORDER BY key LIMIT ?",-1,&q,NULL)==SQLITE_OK){sqlite3_bind_text(q,1,ns,-1,SQLITE_TRANSIENT);sqlite3_bind_text(q,2,prefix,-1,SQLITE_TRANSIENT);sqlite3_bind_text(q,3,after,-1,SQLITE_TRANSIENT);sqlite3_bind_int64(q,4,(long long)time(NULL));sqlite3_bind_int(q,5,limit);size_t used=0;int count=0;while(sqlite3_step(q)==SQLITE_ROW){const char*k=(const char*)sqlite3_column_text(q,0);int w=snprintf(out+used,cap-used,"%s%s",count?",":"",k);if(w<0||(size_t)w>=cap-used){rc=-1;break;}used+=(size_t)w;count++;rc=count;}if(rc==-1&&count==0)rc=0;}if(q)sqlite3_finalize(q);pthread_mutex_unlock(&s->lock);return rc;}
int store_update(netd_store*s,const char*ns,const char*key,int expected,long ttl,const unsigned char*v,size_t n,int*nv){pthread_mutex_lock(&s->lock);sqlite3_stmt*q=NULL;long long e=ttl?time(NULL)+ttl:0;int rc=-1;if(sqlite3_prepare_v2(s->db,"UPDATE records SET value=?,version=version+1,expires_at=CASE WHEN ?=0 THEN NULL ELSE ? END WHERE namespace=? AND key=? AND version=? AND (expires_at IS NULL OR expires_at>?)",-1,&q,NULL)==SQLITE_OK){sqlite3_bind_blob(q,1,v,(int)n,SQLITE_TRANSIENT);sqlite3_bind_int64(q,2,e);sqlite3_bind_int64(q,3,e);sqlite3_bind_text(q,4,ns,-1,SQLITE_TRANSIENT);sqlite3_bind_text(q,5,key,-1,SQLITE_TRANSIENT);sqlite3_bind_int(q,6,expected);sqlite3_bind_int64(q,7,(long long)time(NULL));if(sqlite3_step(q)==SQLITE_DONE)rc=sqlite3_changes(s->db)==1?0:1;}if(q)sqlite3_finalize(q);pthread_mutex_unlock(&s->lock);*nv=expected+1;return rc;}
int store_delete(netd_store*s,const char*ns,const char*key,int expected){pthread_mutex_lock(&s->lock);sqlite3_stmt*q=NULL;int rc=-1;if(sqlite3_prepare_v2(s->db,"DELETE FROM records WHERE namespace=? AND key=? AND version=? AND (expires_at IS NULL OR expires_at>?)",-1,&q,NULL)==SQLITE_OK){sqlite3_bind_text(q,1,ns,-1,SQLITE_TRANSIENT);sqlite3_bind_text(q,2,key,-1,SQLITE_TRANSIENT);sqlite3_bind_int(q,3,expected);sqlite3_bind_int64(q,4,(long long)time(NULL));if(sqlite3_step(q)==SQLITE_DONE)rc=sqlite3_changes(s->db)==1?0:1;}if(q)sqlite3_finalize(q);pthread_mutex_unlock(&s->lock);return rc;}
int store_expire(netd_store*s,int batch,int*deleted){pthread_mutex_lock(&s->lock);sqlite3_stmt*q=NULL;int rc=-1;if(exec_sql(s,"BEGIN IMMEDIATE;"))goto out;if(sqlite3_prepare_v2(s->db,"DELETE FROM records WHERE rowid IN(SELECT rowid FROM records WHERE expires_at IS NOT NULL AND expires_at<=? ORDER BY namespace,key LIMIT ?)",-1,&q,NULL)!=SQLITE_OK)goto rollback;sqlite3_bind_int64(q,1,(long long)time(NULL));sqlite3_bind_int(q,2,batch);if(sqlite3_step(q)!=SQLITE_DONE)goto rollback;*deleted=sqlite3_changes(s->db);sqlite3_finalize(q);q=NULL;if(exec_sql(s,"COMMIT;"))goto rollback;rc=0;goto out;rollback:if(q)sqlite3_finalize(q);exec_sql(s,"ROLLBACK;");out:pthread_mutex_unlock(&s->lock);return rc;}
