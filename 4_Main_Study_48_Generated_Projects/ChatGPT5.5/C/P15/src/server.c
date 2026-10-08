#define _POSIX_C_SOURCE 200809L
#include "netd.h"
#include <arpa/inet.h>
#include <errno.h>
#include <fcntl.h>
#include <poll.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/file.h>
#include <sys/socket.h>
#include <unistd.h>

typedef struct {int *fds;int cap,head,tail,count,closed;pthread_mutex_t lock;pthread_cond_t ready;netd_context*ctx;uint64_t next_id;} queue_t;
static volatile sig_atomic_t stop_flag=0,reload_flag=0,reopen_flag=0;
static void on_signal(int sig){if(sig==SIGHUP){reload_flag=1;reopen_flag=1;}else stop_flag=1;}
static int queue_push(queue_t*q,int fd){pthread_mutex_lock(&q->lock);if(q->count==q->cap){pthread_mutex_unlock(&q->lock);return -1;}q->fds[q->tail]=fd;q->tail=(q->tail+1)%q->cap;q->count++;pthread_cond_signal(&q->ready);pthread_mutex_unlock(&q->lock);return 0;}
static void *worker(void*arg){queue_t*q=arg;for(;;){pthread_mutex_lock(&q->lock);while(!q->count&&!q->closed)pthread_cond_wait(&q->ready,&q->lock);if(!q->count&&q->closed){pthread_mutex_unlock(&q->lock);break;}int fd=q->fds[q->head];q->head=(q->head+1)%q->cap;q->count--;uint64_t id=++q->next_id;pthread_mutex_unlock(&q->lock);protocol_handle_connection(fd,id,q->ctx);}return NULL;}
static int pid_lock(const char*path){int fd=open(path,O_RDWR|O_CREAT,0644);if(fd<0||flock(fd,LOCK_EX|LOCK_NB)){if(fd>=0)close(fd);return -1;}if(ftruncate(fd,0)||dprintf(fd,"%ld\n",(long)getpid())<0){close(fd);return -1;}return fd;}

int server_run(const char *config_path,netd_config*cfg,int ready_fd){
 int pidfd=pid_lock(cfg->pid_file);if(pidfd<0){fprintf(stderr,"START PID_LOCK\n");return 1;}netd_metrics metrics;metrics_init(&metrics);netd_logger logger;if(logger_open(&logger,cfg,&metrics)){fprintf(stderr,"START LOG_OPEN\n");unlink(cfg->pid_file);close(pidfd);return 1;}netd_store store;char err[128];if(store_open(&store,cfg,err,sizeof(err))||store_initialize(&store,0,err,sizeof(err))||!store_integrity(&store)){fprintf(stderr,"START %s\n",err);logger_close(&logger);unlink(cfg->pid_file);close(pidfd);return 1;}
 int listener=socket(AF_INET,SOCK_STREAM,0);int one=1;setsockopt(listener,SOL_SOCKET,SO_REUSEADDR,&one,sizeof(one));struct sockaddr_in a={.sin_family=AF_INET,.sin_port=htons((uint16_t)cfg->port)};inet_pton(AF_INET,cfg->bind_address,&a.sin_addr);if(listener<0||bind(listener,(struct sockaddr*)&a,sizeof(a))||listen(listener,cfg->max_clients)){fprintf(stderr,"START BIND\n");if(listener>=0)close(listener);store_close(&store);logger_close(&logger);unlink(cfg->pid_file);close(pidfd);return 1;}
 struct sigaction sa={0};sa.sa_handler=on_signal;sigemptyset(&sa.sa_mask);sigaction(SIGTERM,&sa,NULL);sigaction(SIGINT,&sa,NULL);sigaction(SIGHUP,&sa,NULL);
 pthread_rwlock_t cfglock;pthread_rwlock_init(&cfglock,NULL);netd_context ctx={cfg,&cfglock,&store,&logger,&metrics,&stop_flag};queue_t q={0};q.cap=cfg->max_clients;q.fds=calloc((size_t)q.cap,sizeof(int));q.ctx=&ctx;pthread_mutex_init(&q.lock,NULL);pthread_cond_init(&q.ready,NULL);pthread_t *threads=calloc((size_t)cfg->worker_count,sizeof(*threads));for(int i=0;i<cfg->worker_count;i++)pthread_create(&threads[i],NULL,worker,&q);
 audit_log(&logger,"READY",0,NULL,NULL,NULL,"OK");if(ready_fd>=0){char ok='1';ssize_t wrote=write(ready_fd,&ok,1);if(wrote!=1)fprintf(stderr,"READY_NOTIFY_FAILED\n");close(ready_fd);}printf("READY %s:%d\n",cfg->bind_address,cfg->port);fflush(stdout);time_t last_expiry=time(NULL);int active=0;
 while(!stop_flag){if(reload_flag){reload_flag=0;netd_config n;char e[128];if(config_load(config_path,&n,e,sizeof(e)))audit_log(&logger,"CONFIG_RELOAD",0,NULL,NULL,NULL,"INVALID");else if(config_reload_compatible(cfg,&n,e,sizeof(e)))audit_log(&logger,"CONFIG_RELOAD",0,NULL,NULL,NULL,"RESTART_REQUIRED");else{pthread_rwlock_wrlock(&cfglock);*cfg=n;pthread_rwlock_unlock(&cfglock);audit_log(&logger,"CONFIG_RELOAD",0,NULL,NULL,NULL,"OK");}if(reopen_flag){reopen_flag=0;(void)logger_reopen(&logger);}}
   time_t now=time(NULL);if(now-last_expiry>=cfg->expiry_scan_interval_seconds){int d=0;if(!store_expire(&store,cfg->expiry_batch_size,&d)&&d)metrics_add(&metrics.expired_deleted,&metrics,(uint64_t)d);last_expiry=now;}
   struct pollfd p={.fd=listener,.events=POLLIN};int pr=poll(&p,1,100);if(pr<0&&errno==EINTR)continue;if(pr<=0)continue;int fd=accept(listener,NULL,NULL);if(fd<0)continue;pthread_mutex_lock(&q.lock);active=q.count;pthread_mutex_unlock(&q.lock);if(active>=cfg->max_clients||queue_push(&q,fd)){metrics_add(&metrics.rejected_clients,&metrics,1);(void)send(fd,"ERR BUSY max_clients\n",21,MSG_NOSIGNAL);close(fd);}}
 close(listener);pthread_mutex_lock(&q.lock);q.closed=1;pthread_cond_broadcast(&q.ready);pthread_mutex_unlock(&q.lock);for(int i=0;i<cfg->worker_count;i++)pthread_join(threads[i],NULL);audit_log(&logger,"SHUTDOWN",0,NULL,NULL,NULL,"OK");free(threads);free(q.fds);pthread_cond_destroy(&q.ready);pthread_mutex_destroy(&q.lock);pthread_rwlock_destroy(&cfglock);store_close(&store);logger_close(&logger);unlink(cfg->pid_file);close(pidfd);pthread_mutex_destroy(&metrics.lock);return 0;
}
