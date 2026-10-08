<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Repositories\ConferencePhasesRepository;
use App\Services\RealtimeService;
use App\Services\RoleGuard;
use App\Services\Validator;
use App\Services\WorkflowException;

final class ConferencePhasesService implements WorkflowInterface
{
    public function __construct(
        private readonly ConferencePhasesRepository $repo,
        private readonly RealtimeService $realtime
    ) {
    }

    public function listFor(array $user, array $query): array
    {
        return ['phases' => $this->repo->findAll([], 'id ASC')];
    }

    public function show(array $user, int $id): array
    {
        $phase = $this->repo->getById($id);
        if ($phase === null) {
            throw new WorkflowException('not_found', 404, [], 'Conference phase not found.');
        }
        return $phase;
    }

    public function create(array $user, array $input): array
    {
        RoleGuard::require($user, ['chair', 'admin']);
        $errors = Validator::validate(
            $input,
            [
                'name' => 'required|in:submission,review,rebuttal,decision',
                'status' => 'required|in:open,closed',
                'start_date' => 'required',
                'end_date' => 'required',
            ]
        );
        if ($errors !== []) {
            throw new WorkflowException('validation_error', 422, ['fields' => $errors]);
        }
        if ($this->repo->findByName((string) $input['name']) !== null) {
            throw new WorkflowException('conflict_error', 409, [], 'A phase with that name already exists; use PATCH to update it.');
        }
        $id = $this->repo->insert([
            'name' => (string) $input['name'],
            'status' => (string) $input['status'],
            'start_date' => (string) $input['start_date'],
            'end_date' => (string) $input['end_date'],
        ]);
        $phase = $this->repo->getById($id);
        $this->realtime->publish('phase.changed', ['phase_id' => $id, 'name' => $phase['name'], 'status' => $phase['status']]);
        return $phase;
    }

    public function update(array $user, int $id, array $input): array
    {
        RoleGuard::require($user, ['chair', 'admin']);
        $phase = $this->repo->getById($id);
        if ($phase === null) {
            throw new WorkflowException('not_found', 404, [], 'Conference phase not found.');
        }
        $data = [];
        foreach (['status', 'start_date', 'end_date'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = (string) $input[$field];
            }
        }
        if (isset($input['name'])) {
            $name = (string) $input['name'];
            if (!in_array($name, ['submission', 'review', 'rebuttal', 'decision'], true)) {
                throw new WorkflowException('validation_error', 422, ['fields' => ['name' => 'invalid name']]);
            }
            $existing = $this->repo->findByName($name);
            if ($existing !== null && (int) $existing['id'] !== $id) {
                throw new WorkflowException('conflict_error', 409, [], 'A phase with that name already exists.');
            }
            $data['name'] = $name;
        }
        if (isset($data['status']) && !in_array($data['status'], ['open', 'closed'], true)) {
            throw new WorkflowException('validation_error', 422, ['fields' => ['status' => 'invalid status']]);
        }
        if ($data === []) {
            throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
        }
        $this->repo->update($id, $data);
        $phase = $this->repo->getById($id);
        $this->realtime->publish('phase.changed', ['phase_id' => $id, 'name' => $phase['name'], 'status' => $phase['status']]);
        return $phase;
    }
}
