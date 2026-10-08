#define _POSIX_C_SOURCE 200809L
#include "netd.h"
#include <arpa/inet.h>
#include <errno.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <unistd.h>

int main(int argc,char**argv){if(argc!=4||strcmp(argv[2],"--config")||(strcmp(argv[1],"status")&&strcmp(argv[1],"stop"))){fprintf(stderr,"usage: netctl status|stop --config PATH\n");return 2;}netd_config c;char err[128];if(config_load(argv[3],&c,err,sizeof(err))){fprintf(stderr,"%s\n",err);return 2;}FILE*f=fopen(c.pid_file,"r");long pid=0;if(f){if(fscanf(f,"%ld",&pid)!=1)pid=0;fclose(f);}if(pid<=1||kill((pid_t)pid,0)){printf("NOT_RUNNING\n");return 3;}if(!strcmp(argv[1],"stop")){if(kill((pid_t)pid,SIGTERM)){perror("kill");return 1;}printf("STOP_REQUESTED %ld\n",pid);return 0;}int fd=socket(AF_INET,SOCK_STREAM,0);struct sockaddr_in a={.sin_family=AF_INET,.sin_port=htons((uint16_t)c.port)};inet_pton(AF_INET,c.bind_address,&a.sin_addr);if(fd<0||connect(fd,(struct sockaddr*)&a,sizeof(a))){if(fd>=0)close(fd);printf("NOT_READY pid=%ld\n",pid);return 4;}close(fd);printf("RUNNING pid=%ld READY\n",pid);return 0;}
