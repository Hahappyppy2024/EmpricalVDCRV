<?php
declare(strict_types=1);
namespace App\Service;

use App\Infrastructure\Audit;
use App\Infrastructure\HttpException;
use App\Infrastructure\Storage;
use App\Repository\AdminRepository;
use App\Repository\AuditRepository;
use App\Repository\FileRepository;
use App\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class AuditService
{
    public function __construct(
        private AuditRepository $audit,
        private UserRepository $users,
        private FileRepository $files,
        private AdminRepository $admin
    ) {
    }

    public function handle(ServerRequestInterface $req, Response $res, string $method, ?int $id): ResponseInterface
    {
        $user = $this->currentUser($req);
        if ($method === 'GET') {
            $isAdmin = $user['role'] === 'admin';
            $events = $isAdmin ? $this->audit->listEvents() : $this->audit->listForUser((int)$user['user_id']);
            $exports = $isAdmin ? $this->audit->listExportsAll() : $this->audit->listExportsForUser((int)$user['user_id']);
            return $this->json($res, ['events' => $events, 'exports' => $exports]);
        }
        $params = (array)$req->getParsedBody();
        if ($method === 'POST') {
            $export = $this->createExport($user, $params);
            return $this->json($res, $export, 201);
        }
        if ($method === 'PATCH') {
            if (!$id) {
                throw new HttpException(400, 'missing_export_id');
            }
            $this->handlePatch($user, $id, $params);
            return $this->json($res, ['status' => 'export_action_complete', 'export_id' => $id]);
        }
        throw new HttpException(405, 'method_not_allowed');
    }

    private function createExport(array $user, array $params): array
    {
        $format = (string)($params['format'] ?? 'csv');
        if (!in_array($format, ['csv', 'json'], true)) {
            throw new HttpException(422, 'invalid_format');
        }
        $filters = [
            'from_date' => $params['from_date'] ?? null,
            'to_date' => $params['to_date'] ?? null,
            'action' => $params['action'] ?? null,
        ];
        $exportId = $this->audit->createExport((int)$user['user_id'], $format, $filters);
        $rows = $user['role'] === 'admin' ? $this->audit->listEvents(500) : $this->audit->listForUser((int)$user['user_id'], 500);
        $rows = array_filter($rows, function ($row) use ($filters) {
            if (!empty($filters['from_date']) && $row['created_at'] < $filters['from_date']) {
                return false;
            }
            if (!empty($filters['to_date']) && $row['created_at'] > $filters['to_date']) {
                return false;
            }
            if (!empty($filters['action']) && $row['action'] !== $filters['action']) {
                return false;
            }
            return true;
        });
        $filename = 'audit_' . $exportId . '_' . date('Ymd_His') . '.' . $format;
        $content = $format === 'csv' ? $this->toCsv($rows) : json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $tmp = tempnam(sys_get_temp_dir(), 'audit');
        file_put_contents($tmp, $content);
        $stored = Storage::store($tmp, $filename);
        $fileId = $this->files->create([
            'folder_id' => null,
            'owner_id' => (int)$user['user_id'],
            'team_id' => null,
            'original_name' => $filename,
            'storage_name' => $stored['storage_name'],
            'description' => 'Audit export',
            'mime_type' => $stored['mime_type'],
            'size' => $stored['size'],
            'checksum' => $stored['checksum'],
            'purpose' => 'audit_export',
            'status' => 'active',
        ]);
        $this->audit->attachFile($exportId, $fileId);
        $this->audit->log($exportId, (int)$user['user_id'], 'create', true, ['format' => $format]);
        Audit::log((int)$user['user_id'], 'audit_export', 'audit_export', $exportId, ['format' => $format]);
        return [
            'status' => 'export_ready',
            'export_id' => $exportId,
            'file_id' => $fileId,
            'download_url' => '/audit/' . $fileId . '/download',
        ];
    }

    private function handlePatch(array $user, int $id, array $params): void
    {
        $export = $this->audit->findExport($id);
        if (!$export) {
            throw new HttpException(404, 'export_not_found');
        }
        if ((int)$export['user_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
            throw new HttpException(403, 'export_forbidden');
        }
        if (!empty($params['expire'])) {
            Database::pdo()->prepare('UPDATE audit_exports SET status = \'expired\', expires_at = datetime(\'now\') WHERE id = ?')->execute([$id]);
            $this->audit->log($id, (int)$user['user_id'], 'expire', true);
        }
    }

    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['id', 'user_id', 'action', 'entity_type', 'entity_id', 'details', 'ip_address', 'created_at']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['id'],
                $row['user_id'],
                $row['action'],
                $row['entity_type'],
                $row['entity_id'],
                $row['details'],
                $row['ip_address'],
                $row['created_at'],
            ]);
        }
        rewind($handle);
        return stream_get_contents($handle);
    }

    private function currentUser(ServerRequestInterface $req): array
    {
        $user = $req->getAttribute('user');
        if (!is_array($user)) {
            throw new HttpException(401, 'authentication_required');
        }
        return $user;
    }

    private function json(Response $res, array $payload, int $status = 200): Response
    {
        $res->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $res->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}