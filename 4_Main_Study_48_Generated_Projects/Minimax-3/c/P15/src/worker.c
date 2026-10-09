#include "netd/worker.h"
#include "netd/server.h"
#include "netd/util.h"

#include <pthread.h>
#include <stdatomic.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define NETD_QUEUE_CAPACITY 1024

typedef struct netd_worker_pool {
    netd_server_t *server;
    pthread_t *threads;
    int worker_count;

    netd_task_t queue[NETD_QUEUE_CAPACITY];
    int queue_head;
    int queue_tail;
    int queue_count;
    pthread_mutex_t queue_lock;
    pthread_cond_t queue_cond;

    atomic_int shutdown;
    atomic_int pending;
} netd_worker_pool_t;

static void *worker_thread_main(void *arg);

netd_worker_pool_t *netd_worker_pool_create(netd_server_t *server, int worker_count)
{
    if (server == NULL || worker_count <= 0) {
        return NULL;
    }
    netd_worker_pool_t *pool = calloc(1, sizeof(*pool));
    if (pool == NULL) {
        return NULL;
    }
    pool->server = server;
    pool->worker_count = worker_count;
    pool->threads = calloc((size_t)worker_count, sizeof(pthread_t));
    if (pool->threads == NULL) {
        free(pool);
        return NULL;
    }
    pthread_mutex_init(&pool->queue_lock, NULL);
    pthread_cond_init(&pool->queue_cond, NULL);
    atomic_store(&pool->shutdown, 0);
    atomic_store(&pool->pending, 0);
    pool->queue_head = 0;
    pool->queue_tail = 0;
    pool->queue_count = 0;
    return pool;
}

void netd_worker_pool_destroy(netd_worker_pool_t *pool)
{
    if (pool == NULL) {
        return;
    }
    free(pool->threads);
    pthread_mutex_destroy(&pool->queue_lock);
    pthread_cond_destroy(&pool->queue_cond);
    free(pool);
}

int netd_worker_pool_start(netd_worker_pool_t *pool)
{
    if (pool == NULL) {
        return -1;
    }
    for (int i = 0; i < pool->worker_count; i++) {
        if (pthread_create(&pool->threads[i], NULL, worker_thread_main, pool) != 0) {
            return -1;
        }
    }
    return 0;
}

void netd_worker_pool_set_shutdown(netd_worker_pool_t *pool)
{
    if (pool == NULL) {
        return;
    }
    atomic_store(&pool->shutdown, 1);
    pthread_mutex_lock(&pool->queue_lock);
    pthread_cond_broadcast(&pool->queue_cond);
    pthread_mutex_unlock(&pool->queue_lock);
}

void netd_worker_pool_stop(netd_worker_pool_t *pool)
{
    if (pool == NULL) {
        return;
    }
    netd_worker_pool_set_shutdown(pool);
    for (int i = 0; i < pool->worker_count; i++) {
        pthread_join(pool->threads[i], NULL);
    }
}

int netd_worker_pool_submit(netd_worker_pool_t *pool, netd_task_t *task)
{
    if (pool == NULL || task == NULL) {
        return -1;
    }
    pthread_mutex_lock(&pool->queue_lock);
    if (pool->queue_count >= NETD_QUEUE_CAPACITY) {
        pthread_mutex_unlock(&pool->queue_lock);
        return -1;
    }
    if (atomic_load(&pool->shutdown)) {
        pthread_mutex_unlock(&pool->queue_lock);
        return -1;
    }
    pool->queue[pool->queue_tail] = *task;
    pool->queue_tail = (pool->queue_tail + 1) % NETD_QUEUE_CAPACITY;
    pool->queue_count++;
    atomic_fetch_add(&pool->pending, 1);
    pthread_cond_signal(&pool->queue_cond);
    pthread_mutex_unlock(&pool->queue_lock);
    return 0;
}

int netd_worker_pool_pending(netd_worker_pool_t *pool)
{
    if (pool == NULL) {
        return 0;
    }
    return atomic_load(&pool->pending);
}

static int pool_dequeue(netd_worker_pool_t *pool, netd_task_t *out)
{
    pthread_mutex_lock(&pool->queue_lock);
    while (pool->queue_count == 0 && !atomic_load(&pool->shutdown)) {
        pthread_cond_wait(&pool->queue_cond, &pool->queue_lock);
    }
    if (pool->queue_count == 0) {
        pthread_mutex_unlock(&pool->queue_lock);
        return -1;
    }
    *out = pool->queue[pool->queue_head];
    pool->queue_head = (pool->queue_head + 1) % NETD_QUEUE_CAPACITY;
    pool->queue_count--;
    atomic_fetch_sub(&pool->pending, 1);
    pthread_mutex_unlock(&pool->queue_lock);
    return 0;
}

static void *worker_thread_main(void *arg)
{
    netd_worker_pool_t *pool = arg;
    netd_task_t task;
    while (pool_dequeue(pool, &task) == 0) {
        if (task.conn != NULL && task.conn->server != NULL) {
            netd_command_execute(task.conn->server, task.conn, &task.cmd);
        }
    }
    return NULL;
}