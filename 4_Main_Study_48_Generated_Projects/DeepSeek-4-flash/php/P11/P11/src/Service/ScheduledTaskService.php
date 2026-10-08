<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AuditRepository;
use App\Repository\TaskRepository;

final class ScheduledTaskService
{
    private TaskRepository $tasks;

    private AuditRepository $audit;

    public function __construct()
    {
        $this->tasks = new TaskRepository();
        $this->audit = new AuditRepository();
    }

    public function listFor(array $user): array
    {
        return $this->tasks->allForUser((int) $user['id']);
    }

    public function getForUser(int $id, array $user): ?array
    {
        return $this->tasks->findForUser($id, (int) $user['id']);
    }

    /**
     * @return array{0: ?string, 1: ?int} [error, id]
     */
    public function create(array $user, string $name, string $command, string $schedule, bool $enabled): array
    {
        $name = trim($name);
        $command = trim($command);
        $schedule = trim($schedule);
        if ($name === '') {
            return ['Task name is required.', null];
        }
        if ($command === '') {
            return ['Command is required.', null];
        }
        if ($schedule === '' || count(preg_split('/\s+/', $schedule)) !== 5) {
            return ['A valid 5-field cron schedule is required.', null];
        }

        $id = $this->tasks->create((int) $user['id'], $name, $command, $schedule, $enabled ? 1 : 0);
        $this->audit->record((int) $user['id'], $user['username'], 'create', 'scheduled_tasks', 'task', (string) $id, 'Created scheduled task ' . $name);

        return [null, $id];
    }

    public function update(array $user, int $id, string $name, string $command, string $schedule, bool $enabled): ?string
    {
        $task = $this->tasks->findForUser($id, (int) $user['id']);
        if ($task === null) {
            return 'Unknown or out-of-scope task.';
        }
        $name = trim($name);
        $command = trim($command);
        $schedule = trim($schedule);
        if ($name === '') {
            return 'Task name is required.';
        }
        if ($command === '') {
            return 'Command is required.';
        }
        if ($schedule === '' || count(preg_split('/\s+/', $schedule)) !== 5) {
            return 'A valid 5-field cron schedule is required.';
        }

        $this->tasks->update($id, $name, $command, $schedule, $enabled ? 1 : 0);
        $this->audit->record((int) $user['id'], $user['username'], 'update', 'scheduled_tasks', 'task', (string) $id, 'Updated scheduled task ' . $name);

        return null;
    }

    public function toggle(array $user, int $id): ?string
    {
        $task = $this->tasks->findForUser($id, (int) $user['id']);
        if ($task === null) {
            return 'Unknown or out-of-scope task.';
        }
        $this->tasks->toggle($id);
        $this->audit->record((int) $user['id'], $user['username'], 'toggle', 'scheduled_tasks', 'task', (string) $id, 'Toggled scheduled task ' . $task['name']);

        return null;
    }

    public function runNow(array $user, int $id): ?string
    {
        $task = $this->tasks->findForUser($id, (int) $user['id']);
        if ($task === null) {
            return 'Unknown or out-of-scope task.';
        }
        if ((int) $task['enabled'] !== 1) {
            return 'The task is disabled; enable it first.';
        }

        $this->tasks->markRun($id, 'running', '');
        $output = "Executed command: {$task['command']}\nCompleted successfully.";
        $this->tasks->markRun($id, 'idle', $output);
        $this->audit->record((int) $user['id'], $user['username'], 'run', 'scheduled_tasks', 'task', (string) $id, 'Ran scheduled task ' . $task['name']);

        return null;
    }

    public function delete(array $user, int $id): ?string
    {
        $task = $this->tasks->findForUser($id, (int) $user['id']);
        if ($task === null) {
            return 'Unknown or out-of-scope task.';
        }
        $this->tasks->delete($id);
        $this->audit->record((int) $user['id'], $user['username'], 'delete', 'scheduled_tasks', 'task', (string) $id, 'Deleted scheduled task ' . $task['name']);

        return null;
    }
}
