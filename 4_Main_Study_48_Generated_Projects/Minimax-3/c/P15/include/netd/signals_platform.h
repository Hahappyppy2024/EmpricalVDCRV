#ifndef NETD_SIGNALS_PLATFORM_H
#define NETD_SIGNALS_PLATFORM_H

#ifdef _WIN32
#include <windows.h>

static BOOL WINAPI win_signal_handler(DWORD sig)
{
    if (sig == CTRL_C_EVENT || sig == CTRL_BREAK_EVENT) {
        extern void netd_signals_request_shutdown(void);
        netd_signals_request_shutdown();
        return TRUE;
    }
    return FALSE;
}

static int netd_signals_install_windows(void)
{
    return SetConsoleCtrlHandler(win_signal_handler, TRUE) ? 0 : -1;
}

static void netd_signals_uninstall_windows(void)
{
    SetConsoleCtrlHandler(win_signal_handler, FALSE);
}
#else
int netd_signals_install_windows(void);
void netd_signals_uninstall_windows(void);
#endif

#endif