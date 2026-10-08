#define _POSIX_C_SOURCE 200809L
#include "netd.h"
#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <unistd.h>

static void usage(void){fprintf(stderr,"usage: netd --config PATH [--foreground|--daemon|--init-db|--reset-db]\n");}
int main(int argc,char**argv){const char*path=NULL;enum{NONE,FG,DAEMON,INIT,RESET}mode=NONE;for(int i=1;i<argc;i++){if(!strcmp(argv[i],"--config")&&i+1<argc)path=argv[++i];else if(!strcmp(argv[i],"--foreground"))mode=FG;else if(!strcmp(argv[i],"--daemon"))mode=DAEMON;else if(!strcmp(argv[i],"--init-db"))mode=INIT;else if(!strcmp(argv[i],"--reset-db"))mode=RESET;else{usage();return 2;}}if(!path||mode==NONE){usage();return 2;}netd_config cfg;char err[128];if(config_load(path,&cfg,err,sizeof(err))){fprintf(stderr,"%s\n",err);return 2;}
 if(mode==INIT||mode==RESET){netd_store s;if(store_open(&s,&cfg,err,sizeof(err))||store_initialize(&s,mode==RESET,err,sizeof(err))){fprintf(stderr,"%s\n",err);return 1;}store_close(&s);printf("OK DATABASE %s\n",mode==RESET?"RESET":"INITIALIZED");return 0;}
 if(mode==FG)return server_run(path,&cfg,-1);
 int pipefd[2];if(pipe(pipefd)){perror("pipe");return 1;}pid_t pid=fork();if(pid<0)return 1;if(pid>0){close(pipefd[1]);char ready=0;ssize_t n=read(pipefd[0],&ready,1);close(pipefd[0]);return n==1&&ready=='1'?0:1;}close(pipefd[0]);if(setsid()<0)_exit(1);pid=fork();if(pid<0)_exit(1);if(pid>0)_exit(0);FILE *nullf=fopen("/dev/null","r+");if(nullf){int nfd=fileno(nullf);(void)dup2(nfd,STDIN_FILENO);(void)dup2(nfd,STDOUT_FILENO);(void)dup2(nfd,STDERR_FILENO);if(nfd>STDERR_FILENO)fclose(nullf);}int rc=server_run(path,&cfg,pipefd[1]);_exit(rc);
}
