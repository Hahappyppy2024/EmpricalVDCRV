#include "netd/signals.h"
#include "netd/signals_platform.h"
#include "netd/server.h"

#include <signal.h>
#include <stdatomic.h>
#include <string.h>

static netd_server_t *g_server = NULL;
static atomic_int g_reload_flag = 0;
static atomic_int g_log_reopen_flag = 0;
static atomic_int g_shutdown_flag = 0;

#ifndef _WIN32
static void signal_handler(int sig)
{
    if (sig == SIGHUP) {
        atomic_store(&g_reload_flag, 1);
    } else if (sig == SIGUSR1) {
        atomic_store(&g_log_reopen_flag, 1);
    } else if (sig == SIGTERM || sig == SIGINT) {
        atomic_store(&g_shutdown_flag, 1);
    }
    if (g_server != NULL) {
        atomic_store(&g_server->reload_flag, atomic_load(&g_reload_flag));
        atomic_store(&g_server->log_reopen_flag, atomic_load(&g_log_reopen_flag));
        atomic_store(&g_server->stop_flag, atomic_load(&g_shutdown_flag));
    }
}
#endif

void netd_signals_request_shutdown(void)
{
    atomic_store(&g_shutdown_flag, 1);
    if (g_server != NULL) {
        atomic_store(&g_server->stop_flag, 1);
    }
}

void netd_signals_install(netd_server_t *server)
{
    g_server = server;
#ifdef _WIN32
    netd_signals_install_windows();
#else
    struct sigaction sa;
    memset(&sa, 0, sizeof(sa));
    sa.sa_handler = signal_handler;
    sigemptyset(&sa.sa_mask);
    sa.sa_flags = SA_RESTART;
    sigaction(SIGTERM, &sa, NULL);
    sigaction(SIGINT, &sa, NULL);
    sigaction(SIGHUP, &sa, NULL);
    sigaction(SIGUSR1, &sa, NULL);

    struct sigaction ign;
    memset(&ign, 0, sizeof(ign));
    ign.sa_handler = SIG_IGN;
    sigemptyset(&ign.sa_mask);
    sigaction(SIGPIPE, &ign, NULL);
#endif
}

void netd_signals_uninstall(void)
{
#ifdef _WIN32
    netd_signals_uninstall_windows();
#else
    struct sigaction dfl;
    memset(&dfl, 0, sizeof(dfl));
    dfl.sa_handler = SIG_DFL;
    sigemptyset(&dfl.sa_mask);
    sigaction(SIGTERM, &dfl, NULL);
    sigaction(SIGINT, &dfl, NULL);
    sigaction(SIGHUP, &dfl, NULL);
    sigaction(SIGUSR1, &dfl, NULL);
    sigaction(SIGPIPE, &dfl, NULL);
#endif
    g_server = NULL;
}

bool netd_signals_reload_requested(void)
{
    return atomic_load(&g_reload_flag) != 0;
}

bool netd_signals_log_reopen_requested(void)
{
    return atomic_load(&g_log_reopen_flag) != 0;
}

bool netd_signals_shutdown_requested(void)
{
    return atomic_load(&g_shutdown_flag) != 0;
}

void netd_signals_clear_reload(void)
{
    atomic_store(&g_reload_flag, 0);
    if (g_server != NULL) {
        atomic_store(&g_server->reload_flag, 0);
    }
}

void netd_signals_clear_log_reopen(void)
{
    atomic_store(&g_log_reopen_flag, 0);
    if (g_server != NULL) {
        atomic_store(&g_server->log_reopen_flag, 0);
    }
}

void netd_signals_clear_shutdown(void)
{
    atomic_store(&g_shutdown_flag, 0);
    if (g_server != NULL) {
        atomic_store(&g_server->stop_flag, 0);
    }
}