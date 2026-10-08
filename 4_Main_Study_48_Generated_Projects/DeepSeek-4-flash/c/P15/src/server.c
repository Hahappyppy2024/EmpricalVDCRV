/*
 * server.c -- accept loop, bounded pthread worker pool, per-connection
 * parser and authentication state, live reload, and graceful shutdown.
 */
#include "server.h"

#include <errno.h>
#include <fcntl.h>
#include <poll.h>
#include <stdatomic.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <unistd.h>

#include "commands.h"
#include "expiry.h"
#include "metrics.h"
#include "netd.h"
#include "sigs.h"
#include "storage.h"
#include "util.h"

#define READ_CHUNK 8192
#define CONN_INIT_CAP 256
#define WORKER_INIT_CAP 8
#define SEND_DEADLINE_MS 5000

static int write_int_full(int fd, int val) {
    unsigned char buf[4];
    buf[0] = (unsigned char)(val & 0xff);
    buf[1] = (unsigned char)((val >> 8) & 0xff);
    buf[2] = (unsigned char)((val >> 16) & 0xff);
    buf[3] = (unsigned char)((val >> 24) & 0xff);
    size_t off = 0;
    while (off < sizeof(buf)) {
        ssize_t n = write(fd, buf + off, sizeof(buf) - off);
        if (n < 0) {
            if (errno == EINTR) continue;
            return -1;
        }
        off += (size_t)n;
    }
    return 0;
}

/* ------------------------------------------------------------------ */
/* Live configuration (reload-safe).                                   */
/* ------------------------------------------------------------------ */

static pthread_mutex_t g_cfg_lock = PTHREAD_MUTEX_INITIALIZER;
static netd_config_t g_cfg;
static atomic_int g_stopping = 0;

void server_cfg_snapshot(netd_config_t *out) {
    pthread_mutex_lock(&g_cfg_lock);
    *out = g_cfg;
    pthread_mutex_unlock(&g_cfg_lock);
}

void server_cfg_apply(const netd_config_t *nu) {
    pthread_mutex_lock(&g_cfg_lock);
    g_cfg = *nu;
    pthread_mutex_unlock(&g_cfg_lock);
}

int server_is_stopping(void) {
    return atomic_load_explicit(&g_stopping, memory_order_relaxed);
}

void server_request_shutdown(void) {
    atomic_store_explicit(&g_stopping, 1, memory_order_relaxed);
}

/* ------------------------------------------------------------------ */
/* Per-worker structures.                                              */
/* ------------------------------------------------------------------ */

typedef struct netd_worker {
    int idx;
    int inbox_fd; /* read end, polled at slot 0 */
    int inbox_w;  /* write end, owned by the acceptor */
    pthread_t thread;
    struct pollfd *pfds;
    netd_conn_t **conns;
    int nfds;
    int cap;
    sqlite3 *db;
    netd_logger_t *lg;
    int busy_ms_applied;
    char *respbuf;
    size_t resplen;
    uint64_t conn_seq;
    int run;
} netd_worker_t;

struct netd_server {
    netd_config_t cfg;
    char config_path[PATH_MAX];
    int listener_fd;
    int signal_fd;
    netd_logger_t *lg;
    sqlite3 *main_db;
    sqlite3 *expiry_db;
    netd_worker_t *workers;
    int nworkers;
    int next_worker;
    pthread_t expiry_thread;
    pthread_mutex_t expiry_mutex;
    pthread_cond_t expiry_cond;
    uint64_t conn_seq;
};

/* ------------------------------------------------------------------ */
/* Connection helpers.                                                 */
/* ------------------------------------------------------------------ */

static int conn_append(netd_conn_t *c, const char *data, size_t n,
                       int max_cmd) {
    /* The per-connection buffer is fixed at connect time; a reload that
     * raises max_command_bytes applies to new connections. */
    size_t cap = c->incap;
    if ((size_t)max_cmd + 2 < cap) cap = (size_t)max_cmd + 2;
    if (c->inlen + n > cap) {
        size_t room = cap - c->inlen;
        if (room > 0) {
            memcpy(c->inbuf + c->inlen, data, room);
            c->inlen += room;
        }
        return 1; /* overlong */
    }
    memcpy(c->inbuf + c->inlen, data, n);
    c->inlen += n;
    return 0;
}

static void conn_free(netd_conn_t *c) {
    if (c == NULL) return;
    if (c->fd >= 0) close(c->fd);
    free(c->inbuf);
    free(c);
}

static netd_conn_t *conn_new(int fd, uint64_t id, int worker_idx,
                             int max_cmd) {
    netd_conn_t *c = calloc(1, sizeof(*c));
    if (c == NULL) return NULL;
    c->fd = fd;
    c->id = id;
    c->worker_idx = worker_idx;
    c->incap = (size_t)max_cmd + 2;
    if (c->incap < CONN_INIT_CAP) c->incap = CONN_INIT_CAP;
    c->inbuf = malloc(c->incap);
    if (c->inbuf == NULL) {
        free(c);
        return NULL;
    }
    c->inlen = 0;
    c->authenticated = 0;
    c->role = NETD_ROLE_CLIENT;
    c->opened_at = util_now_epoch();
    c->last_activity = c->opened_at;
    return c;
}

static void conn_peer(netd_conn_t *c, char *out, size_t outlen) {
    snprintf(out, outlen, "%llu", (unsigned long long)c->id);
}

/* ------------------------------------------------------------------ */
/* Worker: connection lifecycle.                                       */
/* ------------------------------------------------------------------ */

static int worker_grow(netd_worker_t *w);
static void worker_close_conn(netd_worker_t *w, int idx, const char *reason);

static int worker_grow(netd_worker_t *w) {
    int newcap = w->cap * 2;
    struct pollfd *np = realloc(w->pfds, (size_t)newcap * sizeof(*np));
    if (np == NULL) return -1;
    w->pfds = np;
    netd_conn_t **nc = realloc(w->conns, (size_t)newcap * sizeof(*nc));
    if (nc == NULL) return -1;
    w->conns = nc;
    memset(w->pfds + w->cap, 0, (size_t)(newcap - w->cap) * sizeof(*np));
    memset(w->conns + w->cap, 0, (size_t)(newcap - w->cap) * sizeof(*nc));
    w->cap = newcap;
    return 0;
}

static int worker_add_conn(netd_worker_t *w, int fd) {
    if (w->nfds >= w->cap) {
        if (worker_grow(w) != 0) return -1;
    }
    uint64_t id = ++w->conn_seq;
    int max_cmd = 0;
    {
        netd_config_t c;
        server_cfg_snapshot(&c);
        max_cmd = c.max_command_bytes;
    }
    netd_conn_t *c = conn_new(fd, id, w->idx, max_cmd);
    if (c == NULL) return -1;
    c->opened_at = util_now_epoch();
    c->last_activity = c->opened_at;

    w->pfds[w->nfds].fd = fd;
    w->pfds[w->nfds].events = POLLIN;
    w->pfds[w->nfds].revents = 0;
    w->conns[w->nfds] = c;
    w->nfds++;

    char cid[32];
    conn_peer(c, cid, sizeof(cid));
    log_event(w->lg, EV_CONN_OPEN, "OK", cid, "-", "-", "-", NULL);

    /* Reap connections whose peer already closed (short-lived probes) so the
     * connection-limit accounting reflects them immediately. */
    {
        char pbuf[1];
        ssize_t pr = recv(fd, pbuf, 1, MSG_PEEK);
        if (pr == 0) {
            worker_close_conn(w, w->nfds - 1, "client_close");
        }
    }
    return 0;
}

static void worker_close_conn(netd_worker_t *w, int idx, const char *reason) {
    netd_conn_t *c = w->conns[idx];
    if (c == NULL) return;

    char cid[32];
    conn_peer(c, cid, sizeof(cid));
    /* Reflect the close in the connection-limit counter before any
     * potentially slow bookkeeping (SQLite session close, logging) so the
     * acceptor's limit check stays accurate under rapid connect/close
     * patterns. */
    metric_dec(M_CURRENT_CONNECTIONS);
    if (c->authenticated) {
        storage_session_close(w->db, c->id, reason, util_now_epoch());
    }
    log_event(w->lg, EV_CONN_CLOSE, "OK", cid,
              c->authenticated ? c->principal_name : "-", "-", "-",
              "reason=%s", reason);

    conn_free(c);

    w->nfds--;
    if (idx != w->nfds) {
        w->pfds[idx] = w->pfds[w->nfds];
        w->conns[idx] = w->conns[w->nfds];
    }
    w->pfds[w->nfds].fd = -1;
    w->conns[w->nfds] = NULL;
}

/* ------------------------------------------------------------------ */
/* Worker: line processing and command execution.                      */
/* ------------------------------------------------------------------ */

static int ensure_resp(netd_worker_t *w, size_t need) {
    if (w->resplen >= need) return 0;
    size_t ncap = w->resplen == 0 ? 8192 : w->resplen;
    while (ncap < need) ncap *= 2;
    char *nb = realloc(w->respbuf, ncap);
    if (nb == NULL) return -1;
    w->respbuf = nb;
    w->resplen = ncap;
    return 0;
}

static int conn_send(netd_conn_t *c, const char *resp, size_t len) {
    ssize_t n = util_write_all(c->fd, resp, len, SEND_DEADLINE_MS);
    if (n > 0) metric_add(M_BYTES_SENT, (uint64_t)n);
    return n < 0 ? -1 : 0;
}

/*
 * Process all complete lines currently buffered for connection at `idx`.
 * Returns 1 when the connection must be closed, 0 to keep it open.
 */
static int worker_process_lines(netd_worker_t *w, int idx,
                                const netd_config_t *cfg) {
    netd_conn_t *c = w->conns[idx];
    int max_cmd = cfg->max_command_bytes;

    for (;;) {
        char *nl = memchr(c->inbuf, '\n', c->inlen);
        if (nl == NULL) break;

        size_t ll = (size_t)(nl - c->inbuf);
        if (ll > 0 && c->inbuf[ll - 1] == '\r') {
            c->inbuf[ll - 1] = '\0';
            ll--;
        } else {
            c->inbuf[ll] = '\0';
        }

        int has_nul = 0;
        for (size_t k = 0; k < ll; k++) {
            if (c->inbuf[k] == '\0') {
                has_nul = 1;
                break;
            }
        }
        if (has_nul) {
            static const char r[] = CODE_ERR_NUL_REJECTED "\n";
            conn_send(c, r, sizeof(r) - 1);
            return 1;
        }
        if (ll > (size_t)max_cmd) {
            static const char r[] = CODE_ERR_LINE_TOO_LONG "\n";
            conn_send(c, r, sizeof(r) - 1);
            return 1;
        }

        c->last_activity = util_now_epoch();

        if (server_is_stopping()) {
            static const char r[] = CODE_ERR_SHUTDOWN "\n";
            conn_send(c, r, sizeof(r) - 1);
        } else {
            size_t need = (size_t)max_cmd * 2 + 4096;
            size_t list_need = (size_t)cfg->list_max_limit *
                                   (NETD_KEY_MAX + 2) + 1024;
            if (list_need > need) need = list_need;
            if (ensure_resp(w, need) != 0) return 1;
            w->respbuf[0] = '\0';

            netd_runtime_t rt;
            memset(&rt, 0, sizeof(rt));
            rt.cfg = *cfg;
            rt.db = w->db;
            rt.lg = w->lg;
            rt.conn_id = c->id;
            rt.principal = c->authenticated ? c->principal_name : NULL;
            rt.role = c->role;

            netd_cmd_rc_t rc = cmd_execute(&rt, c, c->inbuf, w->respbuf,
                                           w->resplen);
            metric_inc(M_COMMANDS_TOTAL);
            if (rc == NETD_CMD_FATAL) return 1;
            if (conn_send(c, w->respbuf, strlen(w->respbuf)) != 0) return 1;
            if (rc == NETD_CMD_CLOSE) {
                c->quit_seen = 1;
                return 1;
            }
        }

        size_t rem = c->inlen - (size_t)(nl - c->inbuf) - 1;
        memmove(c->inbuf, nl + 1, rem);
        c->inlen = rem;
    }

    if (c->overlong || c->inlen > (size_t)max_cmd) {
        static const char r[] = CODE_ERR_LINE_TOO_LONG "\n";
        conn_send(c, r, sizeof(r) - 1);
        return 1;
    }
    c->overlong = 0;
    return 0;
}

static void worker_read_conn(netd_worker_t *w, int idx,
                             const netd_config_t *cfg) {
    netd_conn_t *c = w->conns[idx];
    if (c == NULL) return;

    char buf[READ_CHUNK];
    for (;;) {
        ssize_t n = recv(c->fd, buf, sizeof(buf), 0);
        if (n > 0) {
            metric_add(M_BYTES_RECEIVED, (uint64_t)n);
            if (conn_append(c, buf, (size_t)n, cfg->max_command_bytes) != 0) {
                c->overlong = 1;
            }
            if (worker_process_lines(w, idx, cfg) != 0) {
                worker_close_conn(w, idx, c->quit_seen ? "quit" : "error");
                return;
            }
        } else if (n == 0) {
            worker_close_conn(w, idx, "client_close");
            return;
        } else {
            if (errno == EAGAIN || errno == EWOULDBLOCK) return;
            if (errno == EINTR) continue;
            worker_close_conn(w, idx, "error");
            return;
        }
    }
}

/* Enforce authentication and idle timeouts across the worker's conns. */
static void worker_timeouts(netd_worker_t *w, const netd_config_t *cfg) {
    int64_t now = util_now_epoch();
    for (int i = w->nfds - 1; i >= 1; i--) {
        netd_conn_t *c = w->conns[i];
        if (c == NULL) continue;
        if (!c->authenticated &&
            now - c->opened_at >= cfg->auth_timeout_seconds) {
            char cid[32];
            conn_peer(c, cid, sizeof(cid));
            static const char r[] = CODE_ERR_AUTH_TIMEOUT "\n";
            conn_send(c, r, sizeof(r) - 1);
            log_event(w->lg, EV_AUTH_TIMEOUT, "ERR", cid, "-", "-", "-", NULL);
            worker_close_conn(w, i, "auth_timeout");
        } else if (cfg->idle_timeout_seconds > 0 &&
                   now - c->last_activity >= cfg->idle_timeout_seconds) {
            worker_close_conn(w, i, "idle");
        }
    }
}

/* During shutdown: reject any pending complete lines and close all conns. */
static void worker_shutdown_close_all(netd_worker_t *w,
                                      const netd_config_t *cfg) {
    for (int i = w->nfds - 1; i >= 1; i--) {
        netd_conn_t *c = w->conns[i];
        if (c == NULL) continue;
        /* Reject buffered complete lines with a stable shutdown outcome. */
        (void)worker_process_lines(w, i, cfg);
        worker_close_conn(w, i, "shutdown");
    }
}

/* ------------------------------------------------------------------ */
/* Worker thread.                                                      */
/* ------------------------------------------------------------------ */

static int compute_poll_timeout(netd_worker_t *w, const netd_config_t *cfg) {
    if (server_is_stopping()) return 100;
    int64_t now = util_now_epoch();
    int min_ms = 1000;
    for (int i = 1; i < w->nfds; i++) {
        netd_conn_t *c = w->conns[i];
        if (c == NULL) continue;
        int64_t deadline;
        if (!c->authenticated) {
            deadline = c->opened_at + cfg->auth_timeout_seconds;
        } else if (cfg->idle_timeout_seconds > 0) {
            deadline = c->last_activity + cfg->idle_timeout_seconds;
        } else {
            continue;
        }
        int64_t remain = (deadline - now) * 1000;
        if (remain < 0) remain = 0;
        if (remain < min_ms) min_ms = (int)remain;
        if (min_ms == 0) break;
    }
    return min_ms;
}

static void worker_drain_inbox(netd_worker_t *w) {
    for (;;) {
        int fd = -1;
        ssize_t n = read(w->inbox_fd, &fd, sizeof(fd));
        if (n == (ssize_t)sizeof(fd)) {
            if (worker_add_conn(w, fd) != 0) {
                close(fd);
                metric_dec(M_CURRENT_CONNECTIONS);
                metric_dec(M_CONNECTIONS_TOTAL);
            }
            continue;
        }
        break;
    }
}

static void *worker_main(void *arg) {
    netd_worker_t *w = arg;

    while (!server_is_stopping() || w->nfds > 1) {
        netd_config_t cfg;
        server_cfg_snapshot(&cfg);

        if (w->db != NULL && w->busy_ms_applied != cfg.database_busy_timeout_ms) {
            sqlite3_busy_timeout(w->db, cfg.database_busy_timeout_ms);
            w->busy_ms_applied = cfg.database_busy_timeout_ms;
        }

        int timeout = compute_poll_timeout(w, &cfg);
        int pr = poll(w->pfds, (nfds_t)w->nfds, timeout);
        if (pr < 0) {
            if (errno == EINTR) continue;
            break;
        }

        if (w->pfds[0].revents & POLLIN) {
            worker_drain_inbox(w);
        }

        for (int i = w->nfds - 1; i >= 1; i--) {
            if (w->conns[i] == NULL) continue;
            short rev = w->pfds[i].revents;
            if (rev & (POLLIN | POLLHUP | POLLERR)) {
                if (server_is_stopping()) {
                    worker_close_conn(w, i, "shutdown");
                    continue;
                }
                worker_read_conn(w, i, &cfg);
            }
        }

        if (server_is_stopping()) {
            worker_shutdown_close_all(w, &cfg);
            continue;
        }

        worker_timeouts(w, &cfg);
    }

    /* Safety net: close anything left (only possible after a poll error). */
    {
        netd_config_t cfg;
        server_cfg_snapshot(&cfg);
        worker_shutdown_close_all(w, &cfg);
    }
    return NULL;
}

/* ------------------------------------------------------------------ */
/* Expiry worker (implemented in expiry.c).                            */
/* ------------------------------------------------------------------ */

static void *expiry_thread_main(void *arg) {
    netd_server_t *srv = arg;
    netd_expiry_ctx_t ctx;
    ctx.db = srv->expiry_db;
    ctx.lg = srv->lg;
    ctx.mutex = &srv->expiry_mutex;
    ctx.cond = &srv->expiry_cond;
    ctx.stopping = &g_stopping;
    ctx.cfg_snapshot = server_cfg_snapshot;
    expiry_main(&ctx);
    return NULL;
}

/* ------------------------------------------------------------------ */
/* Reload (SIGHUP) on the main thread.                                 */
/* ------------------------------------------------------------------ */

static void do_reload(netd_server_t *srv) {
    char err[512];
    netd_config_t nu;
    if (config_load(srv->config_path, &nu, err, sizeof(err)) != 0) {
        metric_inc(M_RELOAD_FAIL);
        log_event(srv->lg, EV_CONFIG_REJECT, "ERR", "-", "-", "-", "-",
                  "reason=%s", err);
        return;
    }

    netd_config_t cur;
    server_cfg_snapshot(&cur);

    char changed[256];
    if (config_nonreloadable_changed(&cur, &nu, changed, sizeof(changed)) != 0) {
        metric_inc(M_RELOAD_FAIL);
        log_event(srv->lg, EV_RESTART_NEEDED, "ERR", "-", "-", "-", "-",
                  "keys=%s", changed);
        return;
    }

    nu.generation = cur.generation + 1;
    server_cfg_apply(&nu);
    metric_inc(M_RELOAD_COUNT);
    storage_config_generation(srv->main_db, nu.generation, srv->config_path,
                              util_now_epoch());
    char dump[1024];
    config_dump(&nu, dump, sizeof(dump));
    log_event(srv->lg, EV_CONFIG_RELOAD, "OK", "-", "-", "-", "-",
              "generation=%d %s", nu.generation, dump);
}

/* ------------------------------------------------------------------ */
/* Accept loop (main thread).                                          */
/* ------------------------------------------------------------------ */

static int accept_conns(netd_server_t *srv) {
    for (;;) {
        if (server_is_stopping()) break;
        int fd = accept(srv->listener_fd, NULL, NULL);
        if (fd < 0) {
            if (errno == EINTR) continue;
            if (errno == EAGAIN || errno == EWOULDBLOCK) break;
            return -1;
        }

        int fl = fcntl(fd, F_GETFL, 0);
        fcntl(fd, F_SETFL, fl | O_NONBLOCK);

        metric_inc(M_CURRENT_CONNECTIONS);
        metric_inc(M_CONNECTIONS_TOTAL);

        netd_config_t cfg;
        server_cfg_snapshot(&cfg);
        if (metric_get(M_CURRENT_CONNECTIONS) > (uint64_t)cfg.max_clients) {
            static const char r[] = CODE_ERR_BUSY "\n";
            util_write_all(fd, r, sizeof(r) - 1, 3000);
            close(fd);
            metric_dec(M_CURRENT_CONNECTIONS);
            metric_inc(M_REJECTED_CONNECTIONS);
            log_event(srv->lg, EV_LIMIT_HIT, "BUSY", "-", "-", "-", "-",
                      "max_clients=%d", cfg.max_clients);
            continue;
        }

        netd_worker_t *w = &srv->workers[srv->next_worker];
        srv->next_worker = (srv->next_worker + 1) % srv->nworkers;
        if (write_int_full(w->inbox_w, fd) != 0) {
            close(fd);
            metric_dec(M_CURRENT_CONNECTIONS);
            return -1;
        }
    }
    return 0;
}

static int accept_loop(netd_server_t *srv) {
    struct pollfd p[2];
    p[0].fd = srv->listener_fd;
    p[0].events = POLLIN;
    p[1].fd = srv->signal_fd;
    p[1].events = POLLIN;

    while (!server_is_stopping()) {
        int pr = poll(p, 2, 1000);
        if (pr < 0) {
            if (errno == EINTR) continue;
            break;
        }
        if (p[1].revents & POLLIN) {
            int sig = signal_poll(srv->signal_fd);
            if (sig == NETD_SIG_STOP) {
                server_request_shutdown();
            } else if (sig == NETD_SIG_RELOAD) {
                do_reload(srv);
            } else if (sig == NETD_SIG_REOPEN) {
                if (log_reopen(srv->lg) != 0) {
                    log_event(srv->lg, EV_LOG_ERROR, "ERR", "-", "-", "-", "-",
                              "reason=reopen_failed");
                }
            }
        }
        if (p[0].revents & POLLIN) {
            if (accept_conns(srv) != 0) break;
        }
    }
    return 0;
}

/* ------------------------------------------------------------------ */
/* Server lifecycle.                                                   */
/* ------------------------------------------------------------------ */

netd_server_t *server_create(const netd_config_t *cfg, const char *config_path,
                             sqlite3 *main_db, netd_logger_t *lg,
                             int listener_fd, int signal_fd,
                             char *err, size_t errlen) {
    netd_server_t *srv = calloc(1, sizeof(*srv));
    if (srv == NULL) {
        snprintf(err, errlen, "out of memory");
        return NULL;
    }
    srv->cfg = *cfg;
    snprintf(srv->config_path, sizeof(srv->config_path), "%s", config_path);
    srv->listener_fd = listener_fd;
    srv->signal_fd = signal_fd;
    srv->lg = lg;
    srv->main_db = main_db;
    srv->nworkers = cfg->worker_count;
    srv->next_worker = 0;
    pthread_mutex_init(&srv->expiry_mutex, NULL);
    pthread_cond_init(&srv->expiry_cond, NULL);
    metrics_set_started(util_mono_ms());

    srv->workers = calloc((size_t)srv->nworkers, sizeof(netd_worker_t));
    if (srv->workers == NULL) {
        snprintf(err, errlen, "out of memory");
        server_destroy(srv);
        return NULL;
    }

    for (int i = 0; i < srv->nworkers; i++) {
        netd_worker_t *w = &srv->workers[i];
        int p[2];
        if (pipe(p) != 0) {
            snprintf(err, errlen, "pipe failed: %s", strerror(errno));
            server_destroy(srv);
            return NULL;
        }
        w->idx = i;
        w->inbox_fd = p[0];
        w->inbox_w = p[1];
        /* Non-blocking so worker_drain_inbox never stalls on an empty pipe. */
        int fl = fcntl(w->inbox_fd, F_GETFL, 0);
        fcntl(w->inbox_fd, F_SETFL, fl | O_NONBLOCK);
        w->cap = WORKER_INIT_CAP;
        w->pfds = calloc((size_t)w->cap, sizeof(*w->pfds));
        w->conns = calloc((size_t)w->cap, sizeof(*w->conns));
        if (w->pfds == NULL || w->conns == NULL) {
            snprintf(err, errlen, "out of memory");
            server_destroy(srv);
            return NULL;
        }
        w->nfds = 1;
        w->pfds[0].fd = w->inbox_fd;
        w->pfds[0].events = POLLIN;
        w->lg = lg;
        w->busy_ms_applied = -1;

        char cerr[256];
        w->db = storage_open_thread(cfg, cerr, sizeof(cerr));
        if (w->db == NULL) {
            snprintf(err, errlen, "worker %d: %s", i, cerr);
            server_destroy(srv);
            return NULL;
        }
    }

    char cerr[256];
    srv->expiry_db = storage_open_thread(cfg, cerr, sizeof(cerr));
    if (srv->expiry_db == NULL) {
        snprintf(err, errlen, "expiry worker: %s", cerr);
        server_destroy(srv);
        return NULL;
    }

    server_cfg_apply(cfg);

    for (int i = 0; i < srv->nworkers; i++) {
        if (pthread_create(&srv->workers[i].thread, NULL, worker_main,
                           &srv->workers[i]) != 0) {
            snprintf(err, errlen, "cannot create worker thread %d", i);
            server_destroy(srv);
            return NULL;
        }
    }
    if (pthread_create(&srv->expiry_thread, NULL, expiry_thread_main, srv) != 0) {
        snprintf(err, errlen, "cannot create expiry thread");
        server_destroy(srv);
        return NULL;
    }

    return srv;
}

void server_destroy(netd_server_t *srv) {
    if (srv == NULL) return;
    if (srv->workers != NULL) {
        for (int i = 0; i < srv->nworkers; i++) {
            netd_worker_t *w = &srv->workers[i];
            if (w->inbox_fd >= 0) close(w->inbox_fd);
            if (w->inbox_w >= 0) close(w->inbox_w);
            if (w->db != NULL) sqlite3_close(w->db);
            if (w->pfds != NULL) {
                for (int j = 1; j < w->nfds; j++) conn_free(w->conns[j]);
                free(w->pfds);
            }
            free(w->conns);
            free(w->respbuf);
        }
        free(srv->workers);
    }
    if (srv->expiry_db != NULL) sqlite3_close(srv->expiry_db);
    pthread_cond_destroy(&srv->expiry_cond);
    pthread_mutex_destroy(&srv->expiry_mutex);
    free(srv);
}

int server_run(netd_server_t *srv) {
    int64_t start = util_mono_ms();
    log_event(srv->lg, EV_STARTUP, "OK", "-", "-", "-", "-", "mode=serve");

    accept_loop(srv);

    /* Graceful shutdown. */
    log_event(srv->lg, EV_SHUTDOWN, "OK", "-", "-", "-", "-", "phase=begin");
    close(srv->listener_fd);
    srv->listener_fd = -1;

    for (int i = 0; i < srv->nworkers; i++) {
        unsigned char wake = 1;
        ssize_t n = write(srv->workers[i].inbox_w, &wake, 1);
        (void)n;
    }

    for (int i = 0; i < srv->nworkers; i++) {
        pthread_join(srv->workers[i].thread, NULL);
    }

    pthread_mutex_lock(&srv->expiry_mutex);
    pthread_cond_signal(&srv->expiry_cond);
    pthread_mutex_unlock(&srv->expiry_mutex);
    pthread_join(srv->expiry_thread, NULL);

    storage_metrics_snapshot(srv->main_db, util_now_epoch());

    int64_t elapsed = util_mono_ms() - start;
    log_event(srv->lg, EV_SHUTDOWN, "OK", "-", "-", "-", "-",
              "phase=complete elapsed_ms=%lld", (long long)elapsed);
    return 0;
}
