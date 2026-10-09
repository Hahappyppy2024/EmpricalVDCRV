#ifndef NETD_SIGNALS_H
#define NETD_SIGNALS_H

#include <stdbool.h>

typedef struct netd_server netd_server_t;

void netd_signals_install(netd_server_t *server);
void netd_signals_uninstall(void);

bool netd_signals_reload_requested(void);
bool netd_signals_log_reopen_requested(void);
bool netd_signals_shutdown_requested(void);

void netd_signals_clear_reload(void);
void netd_signals_clear_log_reopen(void);
void netd_signals_clear_shutdown(void);

void netd_signals_request_shutdown(void);

#endif