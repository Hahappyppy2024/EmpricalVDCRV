<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Repositories\AccountAccessRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\RoleGuard;
use App\Services\Validator;
use App\Services\WorkflowException;

final class AccountAccessRecoveryService implements WorkflowInterface
{
    public function __construct(
        private readonly AccountAccessRepository $repo,
        private readonly UserRepository $users,
        private readonly AuthService $auth
    ) {
    }

    public function listFor(array $user, array $query): array
    {
        if (RoleGuard::isAdmin($user)) {
            return ['records' => $this->repo->findAll([], 'id DESC', 200)];
        }
        return ['records' => $this->repo->findByUser((int) $user['id'])];
    }

    public function show(array $user, int $id): array
    {
        $record = $this->repo->getById($id);
        if ($record === null) {
            throw new WorkflowException('not_found', 404, [], 'Account access record not found.');
        }
        if (!RoleGuard::isAdmin($user) && (int) $record['user_id'] !== (int) $user['id']) {
            throw new WorkflowException('permission_error', 403, [], 'You may only view your own account records.');
        }
        return $this->publicRecord($record);
    }

    public function create(array $user, array $input): array
    {
        $kind = (string) ($input['kind'] ?? '');

        if ($kind === 'reset_request') {
            $record = $this->auth->requestPasswordReset((string) ($input['email'] ?? ''));
            return $this->publicRecord($record);
        }

        if (!in_array($kind, ['recovery', 'login', 'signout'], true)) {
            throw new WorkflowException('validation_error', 422, ['fields' => ['kind' => 'invalid kind']]);
        }

        $status = in_array($kind, ['login', 'signout'], true) ? 'completed' : 'pending';
        $id = $this->repo->insert([
            'user_id' => (int) $user['id'],
            'kind' => $kind,
            'payload' => (string) ($input['payload'] ?? ''),
            'status' => $status,
        ]);
        return $this->publicRecord($this->repo->getById($id));
    }

    public function update(array $user, int $id, array $input): array
    {
        $record = $this->repo->getById($id);
        if ($record === null) {
            throw new WorkflowException('not_found', 404, [], 'Account access record not found.');
        }
        $isOwner = (int) $record['user_id'] === (int) $user['id'];
        if (!RoleGuard::isAdmin($user) && !$isOwner) {
            throw new WorkflowException('permission_error', 403, [], 'You may only update your own account records.');
        }

        $data = [];
        if (isset($input['status'])) {
            $status = (string) $input['status'];
            if (!in_array($status, ['pending', 'used', 'expired', 'completed'], true)) {
                throw new WorkflowException('validation_error', 422, ['fields' => ['status' => 'invalid status']]);
            }
            $data['status'] = $status;
        }
        if (isset($input['payload'])) {
            $data['payload'] = (string) $input['payload'];
        }
        if ($data === []) {
            throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
        }
        $this->repo->update($id, $data);
        return $this->publicRecord($this->repo->getById($id));
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function publicRecord(array $record): array
    {
        unset($record['token']);
        return $record;
    }
}
