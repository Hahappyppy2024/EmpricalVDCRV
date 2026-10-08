#define _POSIX_C_SOURCE 200809L
#include "netd.h"
#include <errno.h>
#include <poll.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <unistd.h>

static int send_line(int fd,const char*s){size_t n=strlen(s),off=0;while(off<n){ssize_t w=send(fd,s+off,n-off,MSG_NOSIGNAL);if(w<=0)return -1;off+=(size_t)w;}return 0;}
static int parse_long(const char*s,long min,long max,long*out){char*e=NULL;errno=0;long v=strtol(s,&e,10);if((errno!=0)|(s[0]==0)|(e==NULL)|(e!=NULL&&*e!=0)|(v<min)|(v>max))return -1;*out=v;return 0;}
static int split(char *line,char **f,int max){int n=0;char *save=NULL;for(char*t=strtok_r(line," ",&save);t;t=strtok_r(NULL," ",&save)){if(n>=max)return -1;f[n++]=t;}return n;}
static int authorized(netd_context*c,netd_session*s,const char*ns,int write){return store_has_grant(c->store,s->principal_id,s->role,ns,write);}

static int command(char*line,uint64_t cid,netd_session*s,netd_context*c,char*out,size_t cap){
 char *f[8];int n=split(line,f,8);if(n<1){snprintf(out,cap,"ERR EMPTY empty_command\n");return 0;}
 metrics_add(&c->metrics->commands,c->metrics,1);
 if(!strcmp(f[0],"QUIT")){if(n!=1)snprintf(out,cap,"ERR FIELDS invalid_field_count\n");else snprintf(out,cap,"OK BYE\n");return n==1?1:0;}
 if(!strcmp(f[0],"AUTH")){
   if(n!=2){snprintf(out,cap,"ERR FIELDS invalid_field_count\n");return 0;}
   if(s->authenticated){snprintf(out,cap,"ERR AUTH_STATE already_authenticated\n");return 0;}
   if(store_auth(c->store,f[1],s)){metrics_add(&c->metrics->auth_ok,c->metrics,1);snprintf(out,cap,"OK AUTH %s\n",role_name(s->role));audit_log(c->logger,"AUTH",cid,s,NULL,NULL,"OK");}
   else{metrics_add(&c->metrics->auth_failed,c->metrics,1);snprintf(out,cap,"ERR AUTH_INVALID invalid_token\n");audit_log(c->logger,"AUTH",cid,NULL,NULL,NULL,"DENIED");}return 0;
 }
 if(!s->authenticated){snprintf(out,cap,"ERR AUTH_REQUIRED authenticate_first\n");return 0;}
 if(!strcmp(f[0],"PING")){if(n!=1){snprintf(out,cap,"ERR FIELDS invalid_field_count\n");}else snprintf(out,cap,"OK PONG\n");return 0;}
 if(!strcmp(f[0],"HEALTH")){
   if(n!=1){snprintf(out,cap,"ERR FIELDS invalid_field_count\n");return 0;}
   if((s->role!=ROLE_MONITORING)&(s->role!=ROLE_ADMIN)){snprintf(out,cap,"ERR ACCESS_DENIED monitoring_role_required\n");return 0;}
   snprintf(out,cap,store_integrity(c->store)?"OK HEALTH READY\n":"OK HEALTH DEGRADED DB_PROBE\n");return 0;
 }
 if(!strcmp(f[0],"STATS")){
   if(n!=1){snprintf(out,cap,"ERR FIELDS invalid_field_count\n");return 0;}
   if((s->role!=ROLE_MONITORING)&(s->role!=ROLE_ADMIN)){snprintf(out,cap,"ERR ACCESS_DENIED monitoring_role_required\n");return 0;}
   pthread_mutex_lock(&c->metrics->lock);snprintf(out,cap,"OK STATS connections=%llu commands=%llu auth_ok=%llu auth_failed=%llu mutations=%llu expired=%llu rejected=%llu log_errors=%llu\n",(unsigned long long)c->metrics->connections,(unsigned long long)c->metrics->commands,(unsigned long long)c->metrics->auth_ok,(unsigned long long)c->metrics->auth_failed,(unsigned long long)c->metrics->mutations,(unsigned long long)c->metrics->expired_deleted,(unsigned long long)c->metrics->rejected_clients,(unsigned long long)c->metrics->log_errors);pthread_mutex_unlock(&c->metrics->lock);return 0;
 }
 if(n>=2&&!valid_identifier(f[1])){snprintf(out,cap,"ERR VALIDATION invalid_namespace\n");return 0;}
 if(!strcmp(f[0],"PUT")){
   if(n!=5){snprintf(out,cap,"ERR FIELDS invalid_field_count\n");return 0;}long ttl=0;if(!valid_identifier(f[2])||parse_long(f[3],0,c->config->max_ttl_seconds,&ttl)){snprintf(out,cap,"ERR VALIDATION invalid_put\n");return 0;}if(!authorized(c,s,f[1],1)){snprintf(out,cap,"ERR ACCESS_DENIED namespace\n");return 0;}unsigned char v[NETD_VALUE_MAX];size_t z=0;if(base64_decode(f[4],v,sizeof(v),&z)){snprintf(out,cap,"ERR BASE64 invalid_base64\n");return 0;}long long exp=0;int rc=store_put(c->store,f[1],f[2],ttl,v,z,&exp);if(!rc){snprintf(out,cap,"OK CREATED 1 %s%lld\n",exp?"":"NONE",exp);if(!exp)snprintf(out,cap,"OK CREATED 1 NONE\n");metrics_add(&c->metrics->mutations,c->metrics,1);audit_log(c->logger,"PUT",cid,s,f[1],f[2],"OK");}else if(rc==1)snprintf(out,cap,"ERR ALREADY_EXISTS key_exists\n");else snprintf(out,cap,"ERR DB database_error\n");return 0;
 }
 if(!strcmp(f[0],"GET")){
   if(n!=3||!valid_identifier(f[2])){snprintf(out,cap,"ERR FIELDS invalid_get\n");return 0;}if(!authorized(c,s,f[1],0)){snprintf(out,cap,"ERR ACCESS_DENIED namespace\n");return 0;}unsigned char v[NETD_VALUE_MAX];size_t z=0;int ver=0;long long exp=0;int rc=store_get(c->store,f[1],f[2],v,sizeof(v),&z,&ver,&exp);if(rc){snprintf(out,cap,"ERR NOT_FOUND record\n");return 0;}char b64[NETD_VALUE_MAX * 2];base64_encode(v,z,b64,sizeof(b64));snprintf(out,cap,"OK RECORD %d %.6000s %s%lld\n",ver,b64,exp?"":"NONE",exp);if(!exp)snprintf(out,cap,"OK RECORD %d %.6000s NONE\n",ver,b64);return 0;
 }
 if(!strcmp(f[0],"LIST")){
   if(n!=5){snprintf(out,cap,"ERR FIELDS invalid_field_count\n");return 0;}long lim=0;if(!valid_identifier(f[2])||parse_long(f[3],1,c->config->max_list_limit,&lim)||(!strcmp(f[4],"-")?0:!valid_identifier(f[4]))){snprintf(out,cap,"ERR VALIDATION invalid_list\n");return 0;}if(!authorized(c,s,f[1],0)){snprintf(out,cap,"ERR ACCESS_DENIED namespace\n");return 0;}char keys[4096];int count=store_list(c->store,f[1],f[2],(int)lim,!strcmp(f[4],"-")?"":f[4],keys,sizeof(keys));if(count<0)snprintf(out,cap,"ERR DB database_error\n");else snprintf(out,cap,"OK LIST %d %s\n",count,count?keys:"-");return 0;
 }
 if(!strcmp(f[0],"UPDATE")){
   if(n!=6){snprintf(out,cap,"ERR FIELDS invalid_field_count\n");return 0;}long ver=0,ttl=0;if(!valid_identifier(f[2])||parse_long(f[3],1,2147483647L,&ver)||parse_long(f[4],0,c->config->max_ttl_seconds,&ttl)){snprintf(out,cap,"ERR VALIDATION invalid_update\n");return 0;}if(!authorized(c,s,f[1],1)){snprintf(out,cap,"ERR ACCESS_DENIED namespace\n");return 0;}unsigned char v[NETD_VALUE_MAX];size_t z=0;if(base64_decode(f[5],v,sizeof(v),&z)){snprintf(out,cap,"ERR BASE64 invalid_base64\n");return 0;}int nv=0;int rc=store_update(c->store,f[1],f[2],(int)ver,ttl,v,z,&nv);if(!rc){snprintf(out,cap,"OK UPDATED %d\n",nv);metrics_add(&c->metrics->mutations,c->metrics,1);audit_log(c->logger,"UPDATE",cid,s,f[1],f[2],"OK");}else if(rc==1)snprintf(out,cap,"ERR CONFLICT version_or_missing\n");else snprintf(out,cap,"ERR DB database_error\n");return 0;
 }
 if(!strcmp(f[0],"DELETE")){
   if(n!=4){snprintf(out,cap,"ERR FIELDS invalid_field_count\n");return 0;}long ver=0;if(!valid_identifier(f[2])||parse_long(f[3],1,2147483647L,&ver)){snprintf(out,cap,"ERR VALIDATION invalid_delete\n");return 0;}if(!authorized(c,s,f[1],1)){snprintf(out,cap,"ERR ACCESS_DENIED namespace\n");return 0;}int rc=store_delete(c->store,f[1],f[2],(int)ver);if(!rc){snprintf(out,cap,"OK DELETED\n");metrics_add(&c->metrics->mutations,c->metrics,1);audit_log(c->logger,"DELETE",cid,s,f[1],f[2],"OK");}else if(rc==1)snprintf(out,cap,"ERR CONFLICT version_or_missing\n");else snprintf(out,cap,"ERR DB database_error\n");return 0;
 }
 snprintf(out,cap,"ERR UNKNOWN_COMMAND unsupported\n");return 0;
}

void protocol_handle_connection(int fd,uint64_t cid,netd_context*c){
 netd_session s={0};char buf[NETD_LINE_MAX];size_t used=0;time_t started=time(NULL),last=started;metrics_add(&c->metrics->connections,c->metrics,1);
 while(!*c->stopping){pthread_rwlock_rdlock(c->config_lock);int timeout=c->config->idle_timeout_seconds,maxbytes=c->config->max_command_bytes,auth_timeout=c->config->auth_timeout_seconds;pthread_rwlock_unlock(c->config_lock);time_t now=time(NULL);if((!s.authenticated&&now-started>=auth_timeout)||(now-last>=timeout))break;
   struct pollfd p={.fd=fd,.events=POLLIN};int wait=1000;int pr=poll(&p,1,wait);if(pr<0&&errno==EINTR)continue;if(pr<=0)continue;if(!(p.revents&POLLIN))break;unsigned char chunk[512];ssize_t got=recv(fd,chunk,sizeof(chunk),0);if(got<=0)break;
   for(ssize_t i=0;i<got;i++){if(chunk[i]==0){send_line(fd,"ERR NUL embedded_nul\n");goto done;}if(chunk[i]=='\n'){buf[used]=0;if(used&&buf[used-1]=='\r')buf[--used]=0;char out[NETD_LINE_MAX];int quit=command(buf,cid,&s,c,out,sizeof(out));if(send_line(fd,out))goto done;used=0;last=time(NULL);if(quit)goto done;}else{if(used+1>=(size_t)maxbytes){send_line(fd,"ERR LINE_TOO_LONG command_too_long\n");goto done;}buf[used++]=(char)chunk[i];}}
 }
done: audit_log(c->logger,"DISCONNECT",cid,&s,NULL,NULL,"OK");shutdown(fd,SHUT_RDWR);close(fd);
}
