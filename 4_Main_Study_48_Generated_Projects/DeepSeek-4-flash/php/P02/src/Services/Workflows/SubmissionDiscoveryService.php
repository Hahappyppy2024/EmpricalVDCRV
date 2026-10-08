<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Repositories\ReviewerAssignmentRepository;
use App\Repositories\SubmissionDiscoveryRepository;
use App\Services\RoleGuard;
use App\Services\Validator;
use App\Services\WorkflowException;

final class SubmissionDiscoveryService implements WorkflowInterface
{
    public function __construct(
        private readonly SubmissionDiscoveryRepository $repo,
        private readonly PaperSubmissionService $submissions
    ) {
    }

    public function listFor(array $user, array $query): array
    {
        $recent = RoleGuard::isChair($user)
            ? $this->repo->findAll([], 'id DESC', 50)
            : $this->repo->findAll(['user_id' => (int) $user['id']], 'id DESC', 50);

        return [
            'matches' => $this->search($user, $query),
            'recent_searches' => $recent,
        ];
    }

    public function search(array $user, array $query): array
    {
        $matches = $this->submissions->visibleSubmissions($user, [
            'q' => (string) ($query['q'] ?? ''),
            'status' => (string) ($query['status'] ?? ''),
        ]);
        return array_map(
            static fn (array $m): array => [
                'id' => $m['id'],
                'title' => $m['title'],
                'abstract' => $m['abstract'],
                'keywords' => $m['keywords'],
                'status' => $m['status'],
                'author_id' => $m['author_id'],
                'created_at' => $m['created_at'],
            ],
            $matches
        );
    }

    public function show(array $user, int $id): array
    {
        $record = $this->repo->getById($id);
        if ($record === null) {
            throw new WorkflowException('not_found', 404, [], 'Discovery record not found.');
        }
        if (!RoleGuard::isChair($user) && (int) $record['user_id'] !== (int) $user['id']) {
            throw new WorkflowException('permission_error', 403, [], 'You may only view your own discovery records.');
        }
        return $record;
    }

    public function create(array $user, array $input): array
    {
        $input = Validator::trimArray($input);
        $query = $input['search_query'] ?? $input['q'] ?? '';
        $status = $input['status_filter'] ?? $input['status'] ?? '';

        $matches = $this->search($user, ['q' => $query, 'status' => $status]);

        $id = $this->repo->insert([
            'user_id' => (int) $user['id'],
            'search_query' => (string) $query,
            'status_filter' => (string) $status,
            'result_count' => count($matches),
        ]);

        return [
            'record' => $this->repo->getById($id),
            'matches' => $matches,
        ];
    }

    public function update(array $user, int $id, array $input): array
    {
        $record = $this->repo->getById($id);
        if ($record === null) {
            throw new WorkflowException('not_found', 404, [], 'Discovery record not found.');
        }
        if (!RoleGuard::isChair($user) && (int) $record['user_id'] !== (int) $user['id']) {
            throw new WorkflowException('permission_error', 403, [], 'You may only update your own discovery records.');
        }
        $data = [];
        foreach (['search_query', 'status_filter'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = (string) $input[$field];
            }
        }
        if ($data === []) {
            throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
        }
        $this->repo->update($id, $data);
        return $this->repo->getById($id);
    }
}
