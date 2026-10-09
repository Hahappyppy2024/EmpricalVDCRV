#include "netd/server.h"

#include "netd/audit.h"
#include "netd/auth.h"
#include "netd/base64.h"
#include "netd/common.h"
#include "netd/config.h"
#include "netd/connection.h"
#include "netd/expiry.h"
#include "netd/log.h"
#include "netd/metrics.h"
#include "netd/pidfile.h"
#include "netd/protocol.h"
#include "netd/signals.h"
#include "netd/storage.h"
#include "netd/util.h"
#include "netd/worker.h"

#include <arpa/inet.h>
#include <errno.h>
#include <fcntl.h>
#include <netinet/in.h>
#include <netinet/tcp.h>
#include <poll.h>
#include <pthread.h>
#include <signal.h>
#include <stdatomic.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <unistd.h>

static int set_nonblocking(int fd)
{
    int flags = fcntl(fd, F_GETFL, 0);
    if (flags < 0) {
        return -1;
    }
    if (fcntl(fd, F_SETFL, flags | O_NONBLOCK) < 0) {
        return -1;
    }
    return 0;
}

static int close_fd_safe(int *fd)
{
    if (*fd < 0) {
        return 0;
    }
    int rc = close(*fd);
    *fd = -1;
    return rc;
}

netd_server_t *netd_server_create(const netd_config_t *cfg)
{
    if (cfg == NULL) {
        return NULL;
    }
    netd_server_t *s = calloc(1, sizeof(*s));
    if (s == NULL) {
        return NULL;
    }
    s->config = *cfg;
    s->max_connections = cfg->max_clients;
    s->listen_fd = -1;
    s->pid_fd = -1;
    s->stored_pid = 0;
    s->started_at = netd_now_seconds();
    s->uptime_start = s->started_at;
    s->current_generation = 1;
    s->last_log_flush = s->started_at;
    atomic_store(&s->stop_flag, 0);
    atomic_store(&s->reload_flag, 0);
    atomic_store(&s->log_reopen_flag, 0);
    s->connections = calloc((size_t)s->max_connections, sizeof(netd_connection_t));
    if (s->connections == NULL) {
        free(s);
        return NULL;
    }
    for (int i = 0; i < s->max_connections; i++) {
        s->connections[i].fd = -1;
        s->connections[i].in_use = false;
    }
    return s;
}

void netd_server_set_config_path(netd_server_t *server, const char *path)
{
    if (server == NULL || path == NULL) {
        return;
    }
    netd_str_copy(server->config_path, sizeof(server->config_path), path);
}

void netd_server_destroy(netd_server_t *server)
{
    if (server == NULL) {
        return;
    }
    if (server->expiry != NULL) {
        netd_expiry_worker_stop(server->expiry);
        netd_expiry_worker_destroy(server->expiry);
    }
    if (server->workers != NULL) {
        netd_worker_pool_stop(server->workers);
        netd_worker_pool_destroy(server->workers);
    }
    if (server->connections != NULL) {
        for (int i = 0; i < server->max_connections; i++) {
            if (server->connections[i].in_use) {
                netd_connection_destroy(&server->connections[i]);
            }
        }
        free(server->connections);
    }
    if (server->storage != NULL) {
        netd_storage_close(server->storage);
    }
    if (server->audit != NULL) {
        netd_audit_destroy(server->audit);
    }
    if (server->logger != NULL) {
        netd_logger_destroy(server->logger);
    }
    if (server->metrics != NULL) {
        netd_metrics_destroy(server->metrics);
    }
    if (server->auth != NULL) {
        netd_auth_destroy(server->auth);
    }
    close_fd_safe(&server->listen_fd);
    if (server->pid_fd >= 0) {
        netd_pid_release(server->config.pid_file, server->pid_fd,
                         server->stored_pid);
    }
    free(server);
}

int netd_server_start(netd_server_t *server)
{
    if (server == NULL) {
        return -1;
    }

    server->logger = netd_logger_create(server->config.log_path,
                                        server->config.strict_audit);
    if (server->logger == NULL) {
        fprintf(stderr, "cannot create logger at %s\n",
                server->config.log_path);
        return -1;
    }
    netd_logger_set_console(server->logger, true);

    server->audit = netd_audit_create(server->config.audit_log_path,
                                     server->config.strict_audit);
    if (server->audit == NULL) {
        fprintf(stderr, "cannot create audit log at %s\n",
                server->config.audit_log_path);
        return -1;
    }
    netd_audit_bind_logger(server->audit, server->logger);

    server->metrics = netd_metrics_create();
    if (server->metrics == NULL) {
        return -1;
    }

    server->auth = netd_auth_create();
    if (server->auth == NULL) {
        return -1;
    }

    server->storage = netd_storage_open(server->config.database_path,
                                       server->config.database_busy_timeout_ms);
    if (server->storage == NULL) {
        netd_logger_write(server->logger, NETD_LOG_LEVEL_ERROR,
                          NETD_EVENT_STARTUP, NETD_EVENT_OUTCOME_FAIL,
                          "storage open failed path=%s",
                          server->config.database_path);
        return -1;
    }
    if (netd_storage_init_schema(server->storage) != 0) {
        netd_logger_write(server->logger, NETD_LOG_LEVEL_ERROR,
                          NETD_EVENT_STARTUP, NETD_EVENT_OUTCOME_FAIL,
                          "storage schema init failed");
        return -1;
    }
    if (netd_storage_seed(server->storage) != 0) {
        netd_logger_write(server->logger, NETD_LOG_LEVEL_ERROR,
                          NETD_EVENT_STARTUP, NETD_EVENT_OUTCOME_FAIL,
                          "storage seed failed");
        return -1;
    }
    netd_storage_health_probe(server->storage);

    int existing_pid = 0;
    netd_pid_status_t pst = NETD_PID_OK;
    server->pid_fd = netd_pid_acquire(server->config.pid_file,
                                      &existing_pid, &pst);
    if (server->pid_fd < 0) {
        netd_logger_write(server->logger, NETD_LOG_LEVEL_ERROR,
                          NETD_EVENT_STARTUP, NETD_EVENT_OUTCOME_FAIL,
                          "pid acquire failed status=%s existing_pid=%d",
                          netd_pid_status_string(pst), existing_pid);
        return -1;
    }
    server->stored_pid = (int)getpid();

    int sfd = socket(AF_INET, SOCK_STREAM | SOCK_CLOEXEC, 0);
    if (sfd < 0) {
        netd_logger_write(server->logger, NETD_LOG_LEVEL_ERROR,
                          NETD_EVENT_STARTUP, NETD_EVENT_OUTCOME_FAIL,
                          "socket create failed errno=%d", errno);
        return -1;
    }
    int one = 1;
    setsockopt(sfd, SOL_SOCKET, SO_REUSEADDR, &one, sizeof(one));
    struct sockaddr_in addr;
    memset(&addr, 0, sizeof(addr));
    addr.sin_family = AF_INET;
    addr.sin_port = htons((uint16_t)server->config.port);
    if (inet_pton(AF_INET, server->config.bind_address, &addr.sin_addr) != 1) {
        netd_logger_write(server->logger, NETD_LOG_LEVEL_ERROR,
                          NETD_EVENT_STARTUP, NETD_EVENT_OUTCOME_FAIL,
                          "invalid bind address %s",
                          server->config.bind_address);
        close(sfd);
        return -1;
    }
    if (bind(sfd, (struct sockaddr *)&addr, sizeof(addr)) != 0) {
        netd_logger_write(server->logger, NETD_LOG_LEVEL_ERROR,
                          NETD_EVENT_STARTUP, NETD_EVENT_OUTCOME_FAIL,
                          "bind failed port=%d errno=%d",
                          server->config.port, errno);
        close(sfd);
        return -1;
    }
    if (listen(sfd, NETD_LISTENER_BACKLOG) != 0) {
        netd_logger_write(server->logger, NETD_LOG_LEVEL_ERROR,
                          NETD_EVENT_STARTUP, NETD_EVENT_OUTCOME_FAIL,
                          "listen failed errno=%d", errno);
        close(sfd);
        return -1;
    }
    set_nonblocking(sfd);
    server->listen_fd = sfd;

    server->workers = netd_worker_pool_create(server,
                                              server->config.worker_count);
    if (server->workers == NULL) {
        return -1;
    }
    if (netd_worker_pool_start(server->workers) != 0) {
        return -1;
    }

    server->expiry = netd_expiry_worker_create(server);
    if (server->expiry == NULL) {
        return -1;
    }
    if (netd_expiry_worker_start(server->expiry) != 0) {
        return -1;
    }

    int64_t instance_id = 0;
    netd_storage_register_instance(server->storage, (int)getpid(), &instance_id);
    netd_storage_record_config_generation(server->storage,
                                          server->config.generation,
                                          "startup");

    netd_logger_write(server->logger, NETD_LOG_LEVEL_INFO,
                      NETD_EVENT_READY, NETD_EVENT_OUTCOME_OK,
                      "ready bind=%s port=%d workers=%d max_clients=%d pid=%d",
                      server->config.bind_address,
                      server->config.port,
                      server->config.worker_count,
                      server->config.max_clients,
                      (int)getpid());
    netd_server_audit_event(server, NETD_EVENT_READY, NETD_EVENT_OUTCOME_OK,
                            NULL, 0, NULL, NULL,
                            "daemon started");

    netd_signals_install(server);
    return 0;
}

static netd_connection_t *find_free_connection(netd_server_t *server)
{
    for (int i = 0; i < server->max_connections; i++) {
        if (!server->connections[i].in_use) {
            netd_connection_t *c = &server->connections[i];
            if (netd_connection_init(c, -1, NULL) != 0) {
                return NULL;
            }
            c->server = server;
            return c;
        }
    }
    return NULL;
}

static void close_connection(netd_server_t *server, netd_connection_t *c)
{
    if (c == NULL) {
        return;
    }
    /* Brief attempt to coordinate with the worker. After a small bounded
     * wait, close regardless so shutdown cannot hang on a stuck worker. */
    if (c->lock_initialized) {
        for (int i = 0; i < 10; i++) {
            if (pthread_mutex_trylock(&c->lock) == 0) {
                pthread_mutex_unlock(&c->lock);
                break;
            }
            netd_sleep_ms(10);
        }
    }
    netd_metrics_inc(server->metrics, NETD_METRIC_CONNECTIONS_CLOSED, 1);
    netd_connection_destroy(c);
}

static void do_accept(netd_server_t *server)
{
    while (1) {
        struct sockaddr_in peer;
        socklen_t plen = sizeof(peer);
        int fd = accept(server->listen_fd, (struct sockaddr *)&peer, &plen);
        if (fd < 0) {
            if (errno == EAGAIN || errno == EWOULDBLOCK) {
                return;
            }
            if (errno == EINTR) {
                continue;
            }
            netd_logger_write(server->logger, NETD_LOG_LEVEL_WARN,
                              NETD_EVENT_LIMIT_REJECT, NETD_EVENT_OUTCOME_FAIL,
                              "accept failed errno=%d", errno);
            return;
        }
        set_nonblocking(fd);
        int one = 1;
        setsockopt(fd, IPPROTO_TCP, TCP_NODELAY, &one, sizeof(one));

        netd_connection_t *c = find_free_connection(server);
        if (c == NULL) {
            netd_metrics_inc(server->metrics, NETD_METRIC_CONNECTIONS_CLOSED, 1);
            const char *msg = "ERR BUSY server at capacity\n";
            (void)write(fd, msg, strlen(msg));
            close(fd);
            netd_logger_write(server->logger, NETD_LOG_LEVEL_WARN,
                              NETD_EVENT_LIMIT_REJECT, NETD_EVENT_OUTCOME_FAIL,
                              "connection rejected capacity=%d",
                              server->config.max_clients);
            netd_server_audit_event(server, NETD_EVENT_LIMIT_REJECT,
                                    NETD_EVENT_OUTCOME_FAIL,
                                    NULL, 0, NULL, NULL, "max_clients");
            return;
        }
        c->fd = fd;
        c->state = NETD_CONN_UNAUTH;
        netd_metrics_inc(server->metrics, NETD_METRIC_CONNECTIONS_ACCEPTED, 1);
        netd_logger_write(server->logger, NETD_LOG_LEVEL_DEBUG,
                          NETD_EVENT_STARTUP, NETD_EVENT_OUTCOME_OK,
                          "accepted connection id=%s", c->id);
    }
}

static ssize_t read_some(int fd, char *buf, size_t cap)
{
    ssize_t n = read(fd, buf, cap);
    if (n < 0) {
        if (errno == EAGAIN || errno == EWOULDBLOCK) {
            return -2;
        }
        if (errno == EINTR) {
            return -3;
        }
        return -1;
    }
    return n;
}

static int flush_response(netd_server_t *server, netd_connection_t *c)
{
    if (c == NULL || c->out_len == 0) {
        return 0;
    }
    ssize_t n = write(c->fd, c->out_buf, c->out_len);
    if (n < 0) {
        if (errno == EAGAIN || errno == EWOULDBLOCK) {
            return 0;
        }
        return -1;
    }
    netd_metrics_add_bytes_out(server->metrics, (size_t)n);
    if ((size_t)n < c->out_len) {
        memmove(c->out_buf, c->out_buf + n, c->out_len - (size_t)n);
        c->out_len -= (size_t)n;
        c->out_buf[c->out_len] = '\0';
        return 0;
    }
    c->out_len = 0;
    c->out_buf[0] = '\0';
    return 0;
}

static void handle_client_input(netd_server_t *server, netd_connection_t *c)
{
    char buf[4096];
    ssize_t n = read_some(c->fd, buf, sizeof(buf));
    if (n == -1) {
        netd_connection_close(c);
        return;
    }
    if (n == -2) {
        return;
    }
    if (n == -3) {
        return;
    }
    if (n == 0) {
        netd_connection_close(c);
        return;
    }
    netd_metrics_add_bytes_in(server->metrics, (size_t)n);

    size_t max_bytes = (size_t)server->config.max_command_bytes;
    if (c->parser.buf_len + (size_t)n + 1 > max_bytes) {
        const char *resp = "ERR PROTOCOL_TOO_LONG line exceeds limit\n";
        netd_connection_append_response(c, resp);
        netd_connection_close(c);
        return;
    }
    if (netd_parser_append(&c->parser, buf, (size_t)n, max_bytes) != NETD_PARSE_INCOMPLETE) {
        const char *resp = "ERR PROTOCOL_INVALID_LINE bad bytes\n";
        netd_connection_append_response(c, resp);
        netd_connection_close(c);
        return;
    }

    char *lines[16];
    size_t lens[16];
    int count = 0;
    netd_parser_extract_lines(&c->parser, lines, lens, 16, &count);
    for (int i = 0; i < count; i++) {
        netd_task_t task;
        memset(&task, 0, sizeof(task));

        netd_command_t cmd;
        int prc = netd_command_parse(lines[i], lens[i], &cmd);
        if (prc != NETD_PARSE_OK) {
            char resp[128];
            snprintf(resp, sizeof(resp), "ERR PROTOCOL %s",
                     netd_parse_status_string((netd_parse_status_t)prc));
            netd_connection_append_response(c, resp);
            continue;
        }
        task.cmd = cmd;
        task.conn = c;
        c->last_activity = netd_now_seconds();
        if (netd_worker_pool_submit(server->workers, &task) != 0) {
            const char *resp = "ERR BUSY worker queue full\n";
            netd_connection_append_response(c, resp);
        }
    }

    /* After parsing, try to flush any response that a worker may have
     * already produced. The poll loop will only wake up for POLLOUT when
     * the kernel send buffer changes state, not when out_buf becomes
     * non-empty. */
    if (c->out_len > 0) {
        flush_response(server, c);
    }
}

static void handle_client_writable(netd_server_t *server, netd_connection_t *c)
{
    if (flush_response(server, c) != 0) {
        netd_connection_close(c);
        return;
    }
    if (c->out_pending_close && c->out_len == 0) {
        netd_connection_close(c);
        return;
    }
}

static int build_pollfds(netd_server_t *server, struct pollfd *pfds)
{
    pfds[0].fd = server->listen_fd;
    pfds[0].events = POLLIN;
    int n = 1;
    for (int i = 0; i < server->max_connections; i++) {
        if (server->connections[i].in_use) {
            short events = POLLIN;
            if (server->connections[i].out_len > 0) {
                events |= POLLOUT;
            }
            pfds[n].fd = server->connections[i].fd;
            pfds[n].events = events;
            n++;
        }
    }
    return n;
}

static netd_connection_t *pollfd_to_connection(netd_server_t *server, int fd)
{
    for (int i = 0; i < server->max_connections; i++) {
        if (server->connections[i].in_use && server->connections[i].fd == fd) {
            return &server->connections[i];
        }
    }
    return NULL;
}

int netd_server_run(netd_server_t *server)
{
    if (server == NULL) {
        return -1;
    }
    struct pollfd *pfds = calloc((size_t)(server->max_connections + 1),
                                 sizeof(struct pollfd));
    if (pfds == NULL) {
        return -1;
    }

    /* Outer loop runs until stop_flag is set AND all connections are
     * closed. During shutdown we keep polling so that EOF on lingering
     * sockets is detected promptly. */
    bool stopping = false;
    while (1) {
        bool stop_requested = atomic_load(&server->stop_flag) != 0;
        if (stop_requested) {
            stopping = true;
        }

        if (atomic_load(&server->reload_flag)) {
            netd_server_reload(server);
        }
        if (atomic_load(&server->log_reopen_flag)) {
            netd_server_reopen_logs(server);
        }

        int n = build_pollfds(server, pfds);
        int idle_ms = server->config.idle_timeout_seconds * 1000;
        /* Short poll interval so worker-produced output is flushed promptly.
         * Longer idle timeouts are still enforced separately. */
        int timeout_ms = stopping ? 100 : 50;
        (void)idle_ms;
        int rc = poll(pfds, (nfds_t)n, timeout_ms);
        if (rc < 0) {
            if (errno == EINTR) {
                continue;
            }
            free(pfds);
            return -1;
        }

        if (rc > 0 && (pfds[0].revents & (POLLIN | POLLERR | POLLHUP))) {
            if (!stopping) {
                do_accept(server);
            }
        }
        for (int i = 1; i < n; i++) {
            short re = pfds[i].revents;
            if (re == 0) {
                continue;
            }
            netd_connection_t *c = pollfd_to_connection(server, pfds[i].fd);
            if (c == NULL) {
                continue;
            }
            if ((re & (POLLERR | POLLHUP | POLLNVAL)) != 0) {
                netd_connection_close(c);
                continue;
            }
            if ((re & POLLIN) != 0) {
                if (stopping) {
                    /* During shutdown, refuse to parse new commands. */
                    netd_connection_close(c);
                } else {
                    handle_client_input(server, c);
                }
            }
            if (c->out_pending_close && c->out_len == 0) {
                close_connection(server, c);
                continue;
            }
            if ((re & POLLOUT) != 0) {
                handle_client_writable(server, c);
            }
            if (c->out_pending_close && c->out_len == 0) {
                close_connection(server, c);
            }
        }

        /* After processing events, also try to flush any pending output
         * that workers may have produced during this poll cycle. */
        for (int i = 0; i < server->max_connections; i++) {
            if (server->connections[i].in_use &&
                server->connections[i].out_len > 0) {
                handle_client_writable(server, &server->connections[i]);
            }
        }

        int64_t now = netd_now_seconds();
        for (int i = 0; i < server->max_connections; i++) {
            netd_connection_t *c = &server->connections[i];
            if (!c->in_use) {
                continue;
            }
            int64_t idle_limit = server->config.idle_timeout_seconds;
            if (stopping) {
                /* Force-close all remaining connections during shutdown. */
                netd_connection_close(c);
                if (c->out_len == 0) {
                    close_connection(server, c);
                }
                continue;
            }
            if (c->state == NETD_CONN_UNAUTH || c->state == NETD_CONN_NEW) {
                if (idle_limit > 0 && now - c->last_activity > idle_limit) {
                    netd_connection_append_response(c,
                        "ERR AUTH_TIMEOUT idle unauthenticated closed\n");
                    netd_server_audit_event(server,
                                            NETD_EVENT_AUTH_TIMEOUT,
                                            NETD_EVENT_OUTCOME_FAIL,
                                            c->id, 0, NULL, NULL, "idle");
                    netd_connection_close(c);
                    if (c->out_len == 0) {
                        close_connection(server, c);
                    }
                }
            } else if (now - c->last_activity > idle_limit * 2) {
                netd_connection_close(c);
                if (c->out_len == 0) {
                    close_connection(server, c);
                }
            }
        }

        /* Exit when stopping and no more active connections. */
        if (stopping) {
            int active = 0;
            for (int i = 0; i < server->max_connections; i++) {
                if (server->connections[i].in_use) {
                    active++;
                }
            }
            if (active == 0 && netd_worker_pool_pending(server->workers) == 0) {
                break;
            }
        }
    }

    free(pfds);
    return 0;
}

int netd_server_stop(netd_server_t *server, int timeout_seconds)
{
    if (server == NULL) {
        return -1;
    }
    atomic_store(&server->stop_flag, 1);

    netd_logger_write(server->logger, NETD_LOG_LEVEL_INFO,
                      NETD_EVENT_SHUTDOWN, NETD_EVENT_OUTCOME_OK,
                      "shutdown requested timeout=%d", timeout_seconds);

    /* Close the listener immediately so no new connections are accepted. */
    if (server->listen_fd >= 0) {
        close(server->listen_fd);
        server->listen_fd = -1;
    }

    /* Wait for the run loop to drain all connections and workers. */
    int64_t deadline = netd_now_seconds() + (int64_t)timeout_seconds;
    while (netd_now_seconds() < deadline) {
        bool active = false;
        for (int i = 0; i < server->max_connections; i++) {
            if (server->connections[i].in_use) {
                active = true;
                break;
            }
        }
        if (!active && netd_worker_pool_pending(server->workers) == 0) {
            break;
        }
        netd_sleep_ms(50);
    }

    if (server->expiry != NULL) {
        netd_expiry_worker_stop(server->expiry);
    }
    if (server->workers != NULL) {
        netd_worker_pool_stop(server->workers);
    }

    for (int i = 0; i < server->max_connections; i++) {
        if (server->connections[i].in_use) {
            netd_connection_destroy(&server->connections[i]);
        }
    }

    netd_logger_write(server->logger, NETD_LOG_LEVEL_INFO,
                      NETD_EVENT_SHUTDOWN, NETD_EVENT_OUTCOME_OK,
                      "shutdown complete");
    netd_server_audit_event(server, NETD_EVENT_SHUTDOWN, NETD_EVENT_OUTCOME_OK,
                            NULL, 0, NULL, NULL, "stopped");

    netd_pid_remove_file(server->config.pid_file);
    return 0;
}

int netd_server_reload(netd_server_t *server)
{
    if (server == NULL) {
        return -1;
    }
    if (server->config_path[0] == '\0') {
        netd_logger_write(server->logger, NETD_LOG_LEVEL_ERROR,
                          NETD_EVENT_CONFIG_RELOAD, NETD_EVENT_OUTCOME_FAIL,
                          "reload failed no config path");
        netd_signals_clear_reload();
        return -1;
    }
    netd_config_t new_cfg;
    netd_config_status_t st = NETD_CONFIG_OK;
    char err[256];
    if (netd_config_load(server->config_path, &new_cfg, &st, err,
                         sizeof(err)) != 0) {
        netd_logger_write(server->logger, NETD_LOG_LEVEL_ERROR,
                          NETD_EVENT_CONFIG_RELOAD, NETD_EVENT_OUTCOME_FAIL,
                          "reload failed status=%s err=%s",
                          netd_config_status_string(st), err);
        netd_server_audit_event(server, NETD_EVENT_CONFIG_RELOAD,
                                NETD_EVENT_OUTCOME_FAIL,
                                NULL, 0, NULL, NULL,
                                netd_config_status_string(st));
        netd_signals_clear_reload();
        return -1;
    }
    if (strcmp(new_cfg.bind_address, server->config.bind_address) != 0 ||
        new_cfg.port != server->config.port ||
        strcmp(new_cfg.database_path, server->config.database_path) != 0 ||
        strcmp(new_cfg.pid_file, server->config.pid_file) != 0) {
        netd_logger_write(server->logger, NETD_LOG_LEVEL_WARN,
                          NETD_EVENT_CONFIG_RELOAD, NETD_EVENT_OUTCOME_FAIL,
                          "non-reloadable change requires restart");
        netd_server_audit_event(server, NETD_EVENT_CONFIG_RELOAD,
                                NETD_EVENT_OUTCOME_FAIL,
                                NULL, 0, NULL, NULL, "RESTART_REQUIRED");
        netd_signals_clear_reload();
        return -1;
    }

    server->config.worker_count = new_cfg.worker_count;
    server->config.idle_timeout_seconds = new_cfg.idle_timeout_seconds;
    server->config.max_command_bytes = new_cfg.max_command_bytes;
    server->config.expiry_scan_interval_seconds =
        new_cfg.expiry_scan_interval_seconds;
    server->config.expiry_batch_size = new_cfg.expiry_batch_size;
    server->config.shutdown_timeout_seconds = new_cfg.shutdown_timeout_seconds;
    server->config.strict_audit = new_cfg.strict_audit;
    server->config.database_busy_timeout_ms = new_cfg.database_busy_timeout_ms;
    netd_str_copy(server->config.log_path, sizeof(server->config.log_path),
                  new_cfg.log_path);
    netd_str_copy(server->config.audit_log_path,
                  sizeof(server->config.audit_log_path),
                  new_cfg.audit_log_path);
    server->current_generation++;

    netd_storage_record_config_generation(server->storage,
                                          (uint64_t)server->current_generation,
                                          "reload");

    netd_logger_write(server->logger, NETD_LOG_LEVEL_INFO,
                      NETD_EVENT_CONFIG_RELOAD, NETD_EVENT_OUTCOME_OK,
                      "reload complete generation=%lld",
                      (long long)server->current_generation);
    netd_server_audit_event(server, NETD_EVENT_CONFIG_RELOAD,
                            NETD_EVENT_OUTCOME_OK, NULL, 0, NULL, NULL,
                            "generation");
    netd_signals_clear_reload();
    return 0;
}

int netd_server_reopen_logs(netd_server_t *server)
{
    if (server == NULL) {
        return -1;
    }
    if (server->logger != NULL) {
        netd_logger_write(server->logger, NETD_LOG_LEVEL_INFO,
                          NETD_EVENT_LOG_REOPEN, NETD_EVENT_OUTCOME_OK,
                          "log reopen before rotate");
        netd_logger_reopen(server->logger);
    }
    if (server->audit != NULL) {
        netd_audit_reopen(server->audit);
        netd_audit_emit(server->audit, netd_now_seconds(),
                        NETD_EVENT_LOG_REOPEN, NETD_EVENT_OUTCOME_OK,
                        NULL, 0, NULL, NULL, "audit reopened");
    }
    netd_signals_clear_log_reopen();
    return 0;
}

void netd_server_log_event(netd_server_t *server,
                           const char *event,
                           const char *outcome,
                           const char *details)
{
    if (server == NULL || server->logger == NULL) {
        return;
    }
    netd_log_level_t level = NETD_LOG_LEVEL_INFO;
    if (netd_str_equal(outcome, NETD_EVENT_OUTCOME_FAIL)) {
        level = NETD_LOG_LEVEL_WARN;
    }
    netd_logger_write(server->logger, level, event, outcome,
                      details ? details : "");
}

void netd_server_audit_event(netd_server_t *server,
                             const char *event_type,
                             const char *outcome,
                             const char *connection_id,
                             int64_t principal_id,
                             const char *namespace,
                             const char *key,
                             const char *details)
{
    if (server == NULL || server->audit == NULL) {
        return;
    }
    netd_audit_emit(server->audit, netd_now_seconds(),
                    event_type, outcome, connection_id,
                    principal_id, namespace, key, details);
}

bool netd_server_is_stopping(const netd_server_t *server)
{
    if (server == NULL) {
        return true;
    }
    return atomic_load((atomic_int *)&server->stop_flag) != 0;
}

/* Forward declarations for command handlers */
static int handle_auth(netd_server_t *server, netd_connection_t *c,
                       const netd_command_t *cmd);
static int handle_quit(netd_server_t *server, netd_connection_t *c,
                       const netd_command_t *cmd);
static int handle_put(netd_server_t *server, netd_connection_t *c,
                      const netd_command_t *cmd);
static int handle_get(netd_server_t *server, netd_connection_t *c,
                      const netd_command_t *cmd);
static int handle_list(netd_server_t *server, netd_connection_t *c,
                       const netd_command_t *cmd);
static int handle_update(netd_server_t *server, netd_connection_t *c,
                         const netd_command_t *cmd);
static int handle_delete(netd_server_t *server, netd_connection_t *c,
                         const netd_command_t *cmd);
static int handle_ping(netd_server_t *server, netd_connection_t *c,
                       const netd_command_t *cmd);
static int handle_health(netd_server_t *server, netd_connection_t *c,
                         const netd_command_t *cmd);
static int handle_stats(netd_server_t *server, netd_connection_t *c,
                        const netd_command_t *cmd);

int netd_command_execute(netd_server_t *server, netd_connection_t *c,
                         const netd_command_t *cmd)
{
    if (server == NULL || c == NULL || cmd == NULL) {
        return -1;
    }
    netd_metrics_inc(server->metrics, NETD_METRIC_COMMANDS_TOTAL, 1);
    if (netd_server_is_stopping(server)) {
        const char *resp = "ERR SHUTTING_DOWN server stopping\n";
        netd_connection_append_response(c, resp);
        return 0;
    }
    pthread_mutex_lock(&c->lock);
    int rc;
    switch (cmd->kind) {
    case NETD_CMD_AUTH:
        rc = handle_auth(server, c, cmd);
        break;
    case NETD_CMD_QUIT:
        rc = handle_quit(server, c, cmd);
        break;
    case NETD_CMD_PUT:
        rc = handle_put(server, c, cmd);
        break;
    case NETD_CMD_GET:
        rc = handle_get(server, c, cmd);
        break;
    case NETD_CMD_LIST:
        rc = handle_list(server, c, cmd);
        break;
    case NETD_CMD_UPDATE:
        rc = handle_update(server, c, cmd);
        break;
    case NETD_CMD_DELETE:
        rc = handle_delete(server, c, cmd);
        break;
    case NETD_CMD_PING:
        rc = handle_ping(server, c, cmd);
        break;
    case NETD_CMD_HEALTH:
        rc = handle_health(server, c, cmd);
        break;
    case NETD_CMD_STATS:
        rc = handle_stats(server, c, cmd);
        break;
    default:
        netd_connection_append_response(c,
                                       "ERR PROTOCOL INVALID_LINE unknown command\n");
        rc = -1;
        break;
    }
    pthread_mutex_unlock(&c->lock);
    return rc;
}

static int require_role(netd_server_t *server, netd_connection_t *c,
                        const char **required, int required_count,
                        const char *resource)
{
    if (!netd_connection_is_authenticated(c)) {
        netd_connection_append_response(c,
                                       "ERR AUTH_REQUIRED authentication required\n");
        return -1;
    }
    const char *role = netd_connection_role(c);
    for (int i = 0; i < required_count; i++) {
        if (strcmp(role, required[i]) == 0) {
            return 0;
        }
    }
    char resp[128];
    snprintf(resp, sizeof(resp), "ERR AUTH_FORBIDDEN role=%s cannot access %s",
             role, resource);
    netd_connection_append_response(c, resp);
    (void)server;
    return -1;
}

static int handle_auth(netd_server_t *server, netd_connection_t *c,
                       const netd_command_t *cmd)
{
    (void)server;
    if (c->state != NETD_CONN_UNAUTH && c->state != NETD_CONN_NEW) {
        netd_connection_append_response(c, "ERR AUTH_REQUIRED already authenticated\n");
        return -1;
    }
    const char *token = cmd->fields[0];
    if (!netd_str_is_valid_token(token, 256)) {
        netd_connection_append_response(c, "ERR AUTH_INVALID token format\n");
        netd_server_audit_event(server, NETD_EVENT_AUTH_FAIL,
                                NETD_EVENT_OUTCOME_FAIL,
                                c->id, 0, NULL, NULL, "format");
        netd_metrics_inc(server->metrics, NETD_METRIC_AUTH_FAILED, 1);
        c->auth_attempts++;
        return -1;
    }
    char hash_hex[65];
    if (netd_auth_hash_token(server->auth, token, hash_hex, sizeof(hash_hex)) != 0) {
        netd_connection_append_response(c, "ERR INTERNAL hash failure\n");
        return -1;
    }
    netd_access_token_t atk;
    netd_principal_t prin;
    netd_storage_status_t st = netd_storage_find_token(server->storage,
                                                      hash_hex, &atk, &prin);
    if (st == NETD_STORAGE_ERR_NOT_FOUND) {
        netd_connection_append_response(c, "ERR AUTH_INVALID unknown token\n");
        netd_server_audit_event(server, NETD_EVENT_AUTH_FAIL,
                                NETD_EVENT_OUTCOME_FAIL,
                                c->id, 0, NULL, NULL, "unknown");
        netd_metrics_inc(server->metrics, NETD_METRIC_AUTH_FAILED, 1);
        c->auth_attempts++;
        return -1;
    }
    if (st != NETD_STORAGE_OK) {
        netd_connection_append_response(c, "ERR INTERNAL auth storage failure\n");
        return -1;
    }
    if (strcmp(atk.status, NETD_TOKEN_STATUS_REVOKED) == 0) {
        netd_connection_append_response(c, "ERR AUTH_REVOKED token revoked\n");
        netd_server_audit_event(server, NETD_EVENT_AUTH_FAIL,
                                NETD_EVENT_OUTCOME_FAIL,
                                c->id, prin.id, NULL, NULL, "revoked");
        netd_metrics_inc(server->metrics, NETD_METRIC_AUTH_FAILED, 1);
        c->auth_attempts++;
        return -1;
    }
    if (strcmp(atk.status, NETD_TOKEN_STATUS_EXPIRED) == 0 ||
        (atk.expires_at > 0 && netd_now_seconds() >= atk.expires_at)) {
        netd_connection_append_response(c, "ERR AUTH_EXPIRED token expired\n");
        netd_server_audit_event(server, NETD_EVENT_AUTH_FAIL,
                                NETD_EVENT_OUTCOME_FAIL,
                                c->id, prin.id, NULL, NULL, "expired");
        netd_metrics_inc(server->metrics, NETD_METRIC_AUTH_FAILED, 1);
        c->auth_attempts++;
        return -1;
    }
    c->principal_id = prin.id;
    netd_str_copy(c->role, sizeof(c->role), atk.role);
    if (strcmp(atk.role, NETD_ROLE_ADMINISTRATOR) == 0) {
        c->state = NETD_CONN_AUTH_ADMIN;
    } else if (strcmp(atk.role, NETD_ROLE_MONITORING) == 0) {
        c->state = NETD_CONN_AUTH_MONITORING;
    } else {
        c->state = NETD_CONN_AUTH_CLIENT;
    }
    char resp[128];
    snprintf(resp, sizeof(resp), "OK AUTH %s principal=%s", atk.role, prin.name);
    netd_connection_append_response(c, resp);
    netd_server_audit_event(server, NETD_EVENT_AUTH_OK, NETD_EVENT_OUTCOME_OK,
                            c->id, prin.id, NULL, NULL, atk.role);
    netd_metrics_inc(server->metrics, NETD_METRIC_AUTH_SUCCESS, 1);
    return 0;
}

static int handle_quit(netd_server_t *server, netd_connection_t *c,
                       const netd_command_t *cmd)
{
    (void)cmd;
    const char *resp = "OK BYE\n";
    netd_connection_append_response(c, resp);
    netd_connection_close(c);
    netd_server_audit_event(server, "QUIT", NETD_EVENT_OUTCOME_OK,
                            c->id, c->principal_id, NULL, NULL, "client_close");
    return 0;
}

static int handle_ping(netd_server_t *server, netd_connection_t *c,
                       const netd_command_t *cmd)
{
    (void)cmd;
    const char *allowed[] = {NETD_ROLE_CLIENT, NETD_ROLE_MONITORING,
                              NETD_ROLE_ADMINISTRATOR};
    if (require_role(server, c, allowed, 3, "PING") != 0) {
        return -1;
    }
    netd_connection_append_response(c, "OK PONG\n");
    return 0;
}

static int handle_health(netd_server_t *server, netd_connection_t *c,
                         const netd_command_t *cmd)
{
    (void)cmd;
    const char *allowed[] = {NETD_ROLE_MONITORING, NETD_ROLE_ADMINISTRATOR};
    if (require_role(server, c, allowed, 2, "HEALTH") != 0) {
        return -1;
    }
    netd_storage_status_t st = netd_storage_health_probe(server->storage);
    if (st == NETD_STORAGE_OK && !netd_server_is_stopping(server)) {
        netd_connection_append_response(c, "OK HEALTH OK reason=ready\n");
    } else if (st != NETD_STORAGE_OK) {
        char resp[128];
        snprintf(resp, sizeof(resp),
                 "OK HEALTH DEGRADED reason=db_status_%s",
                 netd_storage_status_string(st));
        netd_connection_append_response(c, resp);
    } else {
        netd_connection_append_response(c,
                                       "OK HEALTH STOPPING reason=stopping\n");
    }
    return 0;
}

static int handle_stats(netd_server_t *server, netd_connection_t *c,
                        const netd_command_t *cmd)
{
    (void)cmd;
    const char *allowed[] = {NETD_ROLE_MONITORING, NETD_ROLE_ADMINISTRATOR};
    if (require_role(server, c, allowed, 2, "STATS") != 0) {
        return -1;
    }
    char buf[2048];
    int64_t uptime = netd_now_seconds() - server->started_at;
    netd_metrics_format_stats(server->metrics,
                              server->config.bind_address,
                              server->config.port,
                              server->config.worker_count,
                              server->config.max_clients,
                              server->config.max_command_bytes,
                              server->current_generation,
                              uptime,
                              buf, sizeof(buf));
    char resp[2200];
    snprintf(resp, sizeof(resp), "OK STATS %s\n", buf);
    netd_connection_append_response(c, resp);
    return 0;
}

static int decode_value(const char *b64, size_t b64_len, size_t max_decoded,
                        unsigned char **out, size_t *out_len,
                        char *err, size_t err_size)
{
    if (netd_base64_is_valid(b64, b64_len) != 0) {
        snprintf(err, err_size, "base64 invalid");
        return -1;
    }
    size_t max_len = netd_base64_decoded_max_len(b64_len);
    if (max_len > max_decoded) {
        snprintf(err, err_size, "value too large");
        return -1;
    }
    unsigned char *buf = malloc(max_len);
    if (buf == NULL) {
        snprintf(err, err_size, "oom");
        return -1;
    }
    size_t got = 0;
    if (netd_base64_decode(b64, b64_len, buf, max_len, &got) != 0) {
        free(buf);
        snprintf(err, err_size, "base64 decode failed");
        return -1;
    }
    *out = buf;
    *out_len = got;
    return 0;
}

static int handle_put(netd_server_t *server, netd_connection_t *c,
                      const netd_command_t *cmd)
{
    const char *allowed[] = {NETD_ROLE_CLIENT, NETD_ROLE_ADMINISTRATOR};
    if (require_role(server, c, allowed, 2, "PUT") != 0) {
        return -1;
    }
    const char *namespace = cmd->fields[0];
    const char *key = cmd->fields[1];
    const char *ttl_s = cmd->fields[2];
    const char *b64 = cmd->fields[3];
    size_t b64_len = cmd->field_lens[3];

    if (!netd_str_is_valid_namespace(namespace, 64)) {
        netd_connection_append_response(c,
                                       "ERR INVALID_NAMESPACE namespace format\n");
        return -1;
    }
    if (!netd_str_is_valid_key(key, 128)) {
        netd_connection_append_response(c, "ERR INVALID_KEY key format\n");
        return -1;
    }
    int64_t ttl = 0;
    if (!netd_parse_int(ttl_s, &ttl)) {
        netd_connection_append_response(c, "ERR INVALID_TTL not integer\n");
        return -1;
    }
    if (ttl < 0 || ttl > 31536000) {
        netd_connection_append_response(c, "ERR INVALID_TTL out of range\n");
        return -1;
    }

    bool allowed_ns = false;
    if (netd_storage_check_namespace_access(server->storage,
                                            c->principal_id,
                                            c->role,
                                            namespace,
                                            NETD_PERM_WRITE,
                                            &allowed_ns) != NETD_STORAGE_OK ||
        !allowed_ns) {
        netd_connection_append_response(c,
                                       "ERR ACCESS_DENIED namespace write\n");
        return -1;
    }
    unsigned char *value = NULL;
    size_t value_len = 0;
    char err[128];
    size_t max_decoded = (size_t)server->config.max_command_bytes;
    if (decode_value(b64, b64_len, max_decoded, &value, &value_len,
                     err, sizeof(err)) != 0) {
        char resp[160];
        snprintf(resp, sizeof(resp), "ERR INVALID_BASE64 %s", err);
        netd_connection_append_response(c, resp);
        return -1;
    }
    int64_t version = 0;
    int64_t expires_at = 0;
    int64_t now = netd_now_seconds();
    netd_storage_status_t st = netd_storage_record_put(server->storage,
                                                       namespace, key,
                                                       value, value_len,
                                                       ttl, now,
                                                       &version, &expires_at);
    free(value);
    if (st == NETD_STORAGE_ERR_EXISTS) {
        netd_connection_append_response(c,
                                       "ERR ALREADY_EXISTS record exists\n");
        netd_server_audit_event(server, NETD_EVENT_RECORD_CREATE,
                                NETD_EVENT_OUTCOME_FAIL,
                                c->id, c->principal_id, namespace, key,
                                "already_exists");
        return -1;
    }
    if (st != NETD_STORAGE_OK) {
        netd_connection_append_response(c,
                                       "ERR INTERNAL record put failed\n");
        return -1;
    }
    char resp[160];
    if (expires_at > 0) {
        snprintf(resp, sizeof(resp), "OK CREATED %lld %lld",
                 (long long)version, (long long)expires_at);
    } else {
        snprintf(resp, sizeof(resp), "OK CREATED %lld NONE",
                 (long long)version);
    }
    netd_connection_append_response(c, resp);
    netd_server_audit_event(server, NETD_EVENT_RECORD_CREATE,
                            NETD_EVENT_OUTCOME_OK,
                            c->id, c->principal_id, namespace, key,
                            "created");
    netd_metrics_inc(server->metrics, NETD_METRIC_RECORDS_PUT, 1);
    return 0;
}

static int handle_get(netd_server_t *server, netd_connection_t *c,
                      const netd_command_t *cmd)
{
    const char *allowed[] = {NETD_ROLE_CLIENT, NETD_ROLE_MONITORING,
                              NETD_ROLE_ADMINISTRATOR};
    if (require_role(server, c, allowed, 3, "GET") != 0) {
        return -1;
    }
    const char *namespace = cmd->fields[0];
    const char *key = cmd->fields[1];
    if (!netd_str_is_valid_namespace(namespace, 64)) {
        netd_connection_append_response(c,
                                       "ERR INVALID_NAMESPACE namespace format\n");
        return -1;
    }
    if (!netd_str_is_valid_key(key, 128)) {
        netd_connection_append_response(c, "ERR INVALID_KEY key format\n");
        return -1;
    }
    bool allowed_ns = false;
    if (netd_storage_check_namespace_access(server->storage,
                                            c->principal_id,
                                            c->role,
                                            namespace,
                                            NETD_PERM_READ,
                                            &allowed_ns) != NETD_STORAGE_OK ||
        !allowed_ns) {
        netd_connection_append_response(c,
                                       "ERR ACCESS_DENIED namespace read\n");
        return -1;
    }
    int64_t now = netd_now_seconds();
    netd_record_t rec;
    bool expired = false;
    netd_storage_status_t st = netd_storage_record_get(server->storage,
                                                       namespace, key, now,
                                                       &rec, &expired);
    if (st == NETD_STORAGE_ERR_NOT_FOUND) {
        netd_connection_append_response(c, "OK NOTFOUND\n");
        netd_storage_record_free(&rec);
        return 0;
    }
    if (st != NETD_STORAGE_OK) {
        netd_connection_append_response(c, "ERR INTERNAL record get failed\n");
        netd_storage_record_free(&rec);
        return -1;
    }
    char b64_buf[8192];
    size_t b64_len = 0;
    if (rec.value_len > 0) {
        size_t need = netd_base64_encoded_len(rec.value_len);
        if (need > sizeof(b64_buf)) {
            netd_storage_record_free(&rec);
            netd_connection_append_response(c, "ERR TOO_LARGE value too large\n");
            return -1;
        }
        netd_base64_encode(rec.value, rec.value_len,
                           b64_buf, sizeof(b64_buf), &b64_len);
    }
    b64_buf[b64_len] = '\0';
    char resp[9000];
    snprintf(resp, sizeof(resp), "OK VALUE %lld %s",
             (long long)rec.version, b64_buf);
    netd_connection_append_response(c, resp);
    netd_storage_record_free(&rec);
    netd_metrics_inc(server->metrics, NETD_METRIC_RECORDS_GET, 1);
    return 0;
}

static int handle_list(netd_server_t *server, netd_connection_t *c,
                       const netd_command_t *cmd)
{
    const char *allowed[] = {NETD_ROLE_CLIENT, NETD_ROLE_MONITORING,
                              NETD_ROLE_ADMINISTRATOR};
    if (require_role(server, c, allowed, 3, "LIST") != 0) {
        return -1;
    }
    const char *namespace = cmd->fields[0];
    const char *prefix = cmd->fields[1];
    const char *limit_s = cmd->fields[2];
    const char *after = cmd->fields[3];

    if (!netd_str_is_valid_namespace(namespace, 64)) {
        netd_connection_append_response(c,
                                       "ERR INVALID_NAMESPACE namespace format\n");
        return -1;
    }
    int64_t limit = 0;
    if (!netd_parse_int(limit_s, &limit)) {
        netd_connection_append_response(c, "ERR BAD_REQUEST limit not integer\n");
        return -1;
    }
    if (limit < 1 || limit > 1024) {
        netd_connection_append_response(c, "ERR LIMIT_EXCEEDED limit out of range\n");
        return -1;
    }
    if (!netd_str_is_valid_key(prefix, 128) && prefix[0] != '\0') {
        netd_connection_append_response(c, "ERR INVALID_KEY prefix\n");
        return -1;
    }
    bool allowed_ns = false;
    if (netd_storage_check_namespace_access(server->storage,
                                            c->principal_id,
                                            c->role,
                                            namespace,
                                            NETD_PERM_READ,
                                            &allowed_ns) != NETD_STORAGE_OK ||
        !allowed_ns) {
        netd_connection_append_response(c,
                                       "ERR ACCESS_DENIED namespace read\n");
        return -1;
    }
    netd_record_list_entry_t *entries = calloc((size_t)limit,
                                               sizeof(netd_record_list_entry_t));
    if (entries == NULL) {
        netd_connection_append_response(c, "ERR INTERNAL oom\n");
        return -1;
    }
    int got = 0;
    bool truncated = false;
    int64_t now = netd_now_seconds();
    netd_storage_status_t st = netd_storage_record_list(server->storage,
                                                       namespace,
                                                       prefix,
                                                       after,
                                                       (int)limit,
                                                       1024,
                                                       now,
                                                       entries,
                                                       &got,
                                                       &truncated);
    if (st != NETD_STORAGE_OK) {
        free(entries);
        netd_connection_append_response(c, "ERR INTERNAL list failed\n");
        return -1;
    }
    char *resp = malloc(8192);
    if (resp == NULL) {
        free(entries);
        netd_connection_append_response(c, "ERR INTERNAL oom\n");
        return -1;
    }
    size_t off = 0;
    int n = snprintf(resp + off, 8192 - off, "OK KEYS %d", got);
    if (n > 0) {
        off += (size_t)n;
    }
    for (int i = 0; i < got && off < 8192 - 256; i++) {
        n = snprintf(resp + off, 8192 - off, " %s", entries[i].key);
        if (n > 0) {
            off += (size_t)n;
        }
    }
    if (got > 0 && truncated) {
        n = snprintf(resp + off, 8192 - off, " NEXT %s",
                     entries[got - 1].key);
    } else {
        n = snprintf(resp + off, 8192 - off, " NEXT -");
    }
    if (n > 0) {
        off += (size_t)n;
    }
    if (off + 1 < 8192) {
        resp[off++] = '\n';
        resp[off] = '\0';
    }
    netd_connection_append_response(c, resp);
    free(resp);
    free(entries);
    netd_metrics_inc(server->metrics, NETD_METRIC_LIST_OPS, 1);
    return 0;
}

static int handle_update(netd_server_t *server, netd_connection_t *c,
                         const netd_command_t *cmd)
{
    const char *allowed[] = {NETD_ROLE_CLIENT, NETD_ROLE_ADMINISTRATOR};
    if (require_role(server, c, allowed, 2, "UPDATE") != 0) {
        return -1;
    }
    const char *namespace = cmd->fields[0];
    const char *key = cmd->fields[1];
    const char *ver_s = cmd->fields[2];
    const char *ttl_s = cmd->fields[3];
    const char *b64 = cmd->fields[4];
    size_t b64_len = cmd->field_lens[4];
    if (!netd_str_is_valid_namespace(namespace, 64)) {
        netd_connection_append_response(c,
                                       "ERR INVALID_NAMESPACE namespace format\n");
        return -1;
    }
    if (!netd_str_is_valid_key(key, 128)) {
        netd_connection_append_response(c, "ERR INVALID_KEY key format\n");
        return -1;
    }
    int64_t expected = 0;
    if (!netd_parse_int(ver_s, &expected) || expected < 1) {
        netd_connection_append_response(c,
                                       "ERR BAD_REQUEST expected version\n");
        return -1;
    }
    int64_t ttl = 0;
    if (!netd_parse_int(ttl_s, &ttl)) {
        netd_connection_append_response(c, "ERR INVALID_TTL not integer\n");
        return -1;
    }
    if (ttl < 0 || ttl > 31536000) {
        netd_connection_append_response(c, "ERR INVALID_TTL out of range\n");
        return -1;
    }
    bool allowed_ns = false;
    if (netd_storage_check_namespace_access(server->storage,
                                            c->principal_id,
                                            c->role,
                                            namespace,
                                            NETD_PERM_WRITE,
                                            &allowed_ns) != NETD_STORAGE_OK ||
        !allowed_ns) {
        netd_connection_append_response(c,
                                       "ERR ACCESS_DENIED namespace write\n");
        return -1;
    }
    unsigned char *value = NULL;
    size_t value_len = 0;
    char err[128];
    size_t max_decoded = (size_t)server->config.max_command_bytes;
    if (decode_value(b64, b64_len, max_decoded, &value, &value_len,
                     err, sizeof(err)) != 0) {
        char resp[160];
        snprintf(resp, sizeof(resp), "ERR INVALID_BASE64 %s", err);
        netd_connection_append_response(c, resp);
        return -1;
    }
    int64_t version = 0;
    int64_t expires_at = 0;
    int64_t now = netd_now_seconds();
    netd_storage_status_t st = netd_storage_record_update(server->storage,
                                                          namespace, key,
                                                          expected,
                                                          value, value_len,
                                                          ttl, now,
                                                          &version, &expires_at);
    free(value);
    if (st == NETD_STORAGE_ERR_NOT_FOUND) {
        netd_connection_append_response(c, "ERR NOT_FOUND record not found\n");
        netd_server_audit_event(server, NETD_EVENT_RECORD_UPDATE,
                                NETD_EVENT_OUTCOME_FAIL,
                                c->id, c->principal_id, namespace, key,
                                "not_found");
        return -1;
    }
    if (st == NETD_STORAGE_ERR_CONFLICT) {
        netd_connection_append_response(c, "ERR CONFLICT version mismatch\n");
        netd_server_audit_event(server, NETD_EVENT_RECORD_UPDATE,
                                NETD_EVENT_OUTCOME_FAIL,
                                c->id, c->principal_id, namespace, key,
                                "conflict");
        return -1;
    }
    if (st != NETD_STORAGE_OK) {
        netd_connection_append_response(c, "ERR INTERNAL update failed\n");
        return -1;
    }
    char resp[160];
    if (expires_at > 0) {
        snprintf(resp, sizeof(resp), "OK UPDATED %lld %lld",
                 (long long)version, (long long)expires_at);
    } else {
        snprintf(resp, sizeof(resp), "OK UPDATED %lld NONE",
                 (long long)version);
    }
    netd_connection_append_response(c, resp);
    netd_server_audit_event(server, NETD_EVENT_RECORD_UPDATE,
                            NETD_EVENT_OUTCOME_OK,
                            c->id, c->principal_id, namespace, key,
                            "updated");
    netd_metrics_inc(server->metrics, NETD_METRIC_RECORDS_UPDATED, 1);
    return 0;
}

static int handle_delete(netd_server_t *server, netd_connection_t *c,
                         const netd_command_t *cmd)
{
    const char *allowed[] = {NETD_ROLE_CLIENT, NETD_ROLE_ADMINISTRATOR};
    if (require_role(server, c, allowed, 2, "DELETE") != 0) {
        return -1;
    }
    const char *namespace = cmd->fields[0];
    const char *key = cmd->fields[1];
    const char *ver_s = cmd->fields[2];
    if (!netd_str_is_valid_namespace(namespace, 64)) {
        netd_connection_append_response(c,
                                       "ERR INVALID_NAMESPACE namespace format\n");
        return -1;
    }
    if (!netd_str_is_valid_key(key, 128)) {
        netd_connection_append_response(c, "ERR INVALID_KEY key format\n");
        return -1;
    }
    int64_t expected = 0;
    if (!netd_parse_int(ver_s, &expected) || expected < 1) {
        netd_connection_append_response(c,
                                       "ERR BAD_REQUEST expected version\n");
        return -1;
    }
    bool allowed_ns = false;
    if (netd_storage_check_namespace_access(server->storage,
                                            c->principal_id,
                                            c->role,
                                            namespace,
                                            NETD_PERM_WRITE,
                                            &allowed_ns) != NETD_STORAGE_OK ||
        !allowed_ns) {
        netd_connection_append_response(c,
                                       "ERR ACCESS_DENIED namespace write\n");
        return -1;
    }
    int64_t now = netd_now_seconds();
    netd_storage_status_t st = netd_storage_record_delete(server->storage,
                                                          namespace, key,
                                                          expected, now);
    if (st == NETD_STORAGE_ERR_NOT_FOUND) {
        netd_connection_append_response(c, "ERR NOT_FOUND record not found\n");
        netd_server_audit_event(server, NETD_EVENT_RECORD_DELETE,
                                NETD_EVENT_OUTCOME_FAIL,
                                c->id, c->principal_id, namespace, key,
                                "not_found");
        return -1;
    }
    if (st == NETD_STORAGE_ERR_CONFLICT) {
        netd_connection_append_response(c, "ERR CONFLICT version mismatch\n");
        netd_server_audit_event(server, NETD_EVENT_RECORD_DELETE,
                                NETD_EVENT_OUTCOME_FAIL,
                                c->id, c->principal_id, namespace, key,
                                "conflict");
        return -1;
    }
    if (st != NETD_STORAGE_OK) {
        netd_connection_append_response(c, "ERR INTERNAL delete failed\n");
        return -1;
    }
    netd_connection_append_response(c, "OK DELETED\n");
    netd_server_audit_event(server, NETD_EVENT_RECORD_DELETE,
                            NETD_EVENT_OUTCOME_OK,
                            c->id, c->principal_id, namespace, key,
                            "deleted");
    netd_metrics_inc(server->metrics, NETD_METRIC_RECORDS_DELETED, 1);
    return 0;
}