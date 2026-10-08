<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Repositories\BulkExportRepository;
use App\Services\ExportService;
use App\Services\RealtimeService;
use App\Services\RoleGuard;
use App\Services\WorkflowException;

final class BulkExportsService implements WorkflowInterface
{
    public function __construct(
        private readonly BulkExportRepository $repo,
        private readonly ExportService $exporter,
        private readonly RealtimeService $realtime
    ) {
    }

    public function listFor(array $user, array $query): array
    {
        if (!RoleGuard::isChair($user)) {
            return ['exports' => []];
        }
        return ['exports' => $this->repo->withDetails()];
    }

    public function show(array $user, int $id): array
    {
        $export = $this->repo->getById($id);
        if ($export === null) {
            throw new WorkflowException('not_found', 404, [], 'Export not found.');
        }
        RoleGuard::require($user, ['chair', 'admin']);
        return $export;
    }

    public function create(array $user, array $input): array
    {
        RoleGuard::require($user, ['chair', 'admin']);
        $exportType = (string) ($input['export_type'] ?? '');
        $format = (string) ($input['format'] ?? '');

        $result = $this->exporter->generate($exportType, $format, (int) $user['id']);
        $export = $this->repo->getById($result['export_id']);
        $this->realtime->publish('export.generated', [
            'export_id' => $result['export_id'],
            'export_type' => $exportType,
            'format' => $format,
        ]);
        return $export;
    }

    public function update(array $user, int $id, array $input): array
    {
        RoleGuard::require($user, ['chair', 'admin']);
        $export = $this->repo->getById($id);
        if ($export === null) {
            throw new WorkflowException('not_found', 404, [], 'Export not found.');
        }
        if (!isset($input['status'])) {
            throw new WorkflowException('validation_error', 422, [], 'No updatable fields provided.');
        }
        $status = (string) $input['status'];
        if (!in_array($status, ['pending', 'completed', 'failed'], true)) {
            throw new WorkflowException('validation_error', 422, ['fields' => ['status' => 'invalid status']]);
        }
        $data = ['status' => $status];
        if ($status === 'completed') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }
        $this->repo->update($id, $data);
        return $this->repo->getById($id);
    }

    public function download(array $user, int $id): array
    {
        $export = $this->show($user, $id);
        if ($export['status'] !== 'completed' || empty($export['file_path']) || !is_file($export['file_path'])) {
            throw new WorkflowException('not_found', 404, [], 'Export file is not available.');
        }
        $format = $export['format'];
        $mime = $format === 'pdf' ? 'application/pdf' : 'text/csv';
        return [
            'path' => $export['file_path'],
            'name' => basename($export['file_path']),
            'mime' => $mime,
        ];
    }
}
