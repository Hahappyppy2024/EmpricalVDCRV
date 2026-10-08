<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Repositories\FrontendApiRepository;
use App\Services\RoleGuard;
use App\Services\Validator;
use App\Services\WorkflowException;

final class FrontendApiService implements WorkflowInterface
{
    private const CATALOG = [
        'auth_required' => ['status' => 401, 'message' => 'You must be signed in to continue.', 'code' => 'auth_required'],
        'permission_error' => ['status' => 403, 'message' => 'You are not allowed to perform this action.', 'code' => 'permission_error'],
        'not_found' => ['status' => 404, 'message' => 'The requested record was not found.', 'code' => 'not_found'],
        'validation_error' => ['status' => 422, 'message' => 'Please fix the highlighted fields and try again.', 'code' => 'validation_error'],
        'conflict_error' => ['status' => 409, 'message' => 'The operation conflicts with existing data.', 'code' => 'conflict_error'],
        'phase_error' => ['status' => 409, 'message' => 'This action is not allowed during the current conference phase.', 'code' => 'phase_error'],
        'auth_error' => ['status' => 401, 'message' => 'Incorrect email or password.', 'code' => 'auth_error'],
        'internal_error' => ['status' => 500, 'message' => 'An unexpected error occurred. Please try again later.', 'code' => 'internal_error'],
    ];

    public function __construct(private readonly FrontendApiRepository $repo)
    {
    }

    public function listFor(array $user, array $query): array
    {
        $reports = RoleGuard::isAdmin($user)
            ? $this->repo->findAll([], 'id DESC', 200)
            : $this->repo->findAll(['user_id' => (int) $user['id']], 'id DESC', 200);
        return [
            'catalog' => array_values(self::CATALOG),
            'reports' => $reports,
        ];
    }

    public function show(array $user, int $id): array
    {
        $report = $this->repo->getById($id);
        if ($report === null) {
            throw new WorkflowException('not_found', 404, [], 'Error report not found.');
        }
        if (!RoleGuard::isAdmin($user) && (int) $report['user_id'] !== (int) $user['id']) {
            throw new WorkflowException('permission_error', 403, [], 'You may only view your own error reports.');
        }
        return $report;
    }

    public function create(array $user, array $input): array
    {
        $errors = Validator::requireFields($input, ['endpoint', 'error_code', 'error_message']);
        if ($errors !== []) {
            throw new WorkflowException('validation_error', 422, ['fields' => $errors]);
        }
        $code = (string) $input['error_code'];
        if (!isset(self::CATALOG[$code])) {
            $code = 'validation_error';
        }
        $id = $this->repo->insert([
            'user_id' => (int) $user['id'],
            'endpoint' => (string) $input['endpoint'],
            'error_code' => $code,
            'error_message' => (string) $input['error_message'],
            'detail' => (string) ($input['detail'] ?? ''),
            'status' => 'reported',
        ]);
        return $this->repo->getById($id);
    }

    public function update(array $user, int $id, array $input): array
    {
        $report = $this->repo->getById($id);
        if ($report === null) {
            throw new WorkflowException('not_found', 404, [], 'Error report not found.');
        }
        if (!RoleGuard::isAdmin($user) && (int) $report['user_id'] !== (int) $user['id']) {
            throw new WorkflowException('permission_error', 403, [], 'You may only update your own error reports.');
        }
        if (!isset($input['status'])) {
            throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
        }
        $status = (string) $input['status'];
        if (!in_array($status, ['reported', 'acknowledged', 'resolved'], true)) {
            throw new WorkflowException('validation_error', 422, ['fields' => ['status' => 'invalid status']]);
        }
        $this->repo->update($id, ['status' => $status]);
        return $this->repo->getById($id);
    }
}
