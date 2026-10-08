#define _POSIX_C_SOURCE 200809L
#include "netd.h"
#include <string.h>

int logger_open(netd_logger *l, const netd_config *c, netd_metrics *m) {
    memset(l,0,sizeof(*l)); pthread_mutex_init(&l->lock,NULL); l->flush=c->log_flush; l->metrics=m;
    snprintf(l->path,sizeof(l->path),"%s",c->log_path); l->file=fopen(l->path,"a");
    if (!l->file && !c->strict_audit) l->file=stderr;
    return l->file ? 0 : -1;
}
int logger_reopen(netd_logger *l) {
    pthread_mutex_lock(&l->lock); FILE *n=fopen(l->path,"a");
    if (!n) { pthread_mutex_unlock(&l->lock); metrics_add(&l->metrics->log_errors,l->metrics,1); return -1; }
    if (l->file && l->file != stderr) fclose(l->file);
    l->file=n; pthread_mutex_unlock(&l->lock); return 0;
}
void logger_close(netd_logger *l) { pthread_mutex_lock(&l->lock); if(l->file && l->file != stderr) fclose(l->file); l->file=NULL; pthread_mutex_unlock(&l->lock); pthread_mutex_destroy(&l->lock); }
void audit_log(netd_logger *l,const char *event,uint64_t cid,const netd_session *s,const char *ns,const char *key,const char *outcome) {
    char clean_ns[65]="-",clean_key[65]="-"; if(ns&&valid_identifier(ns)) snprintf(clean_ns,sizeof(clean_ns),"%s",ns); if(key&&valid_identifier(key)) snprintf(clean_key,sizeof(clean_key),"%s",key);
    pthread_mutex_lock(&l->lock); time_t now=time(NULL); int rc=fprintf(l->file,"ts=%lld event=%s cid=%llu principal=%ld ns=%s key=%s outcome=%s\n",(long long)now,event,(unsigned long long)cid,s?s->principal_id:0,clean_ns,clean_key,outcome);
    if(l->flush) fflush(l->file);
    pthread_mutex_unlock(&l->lock);
    if(rc<0) metrics_add(&l->metrics->log_errors,l->metrics,1);
}
