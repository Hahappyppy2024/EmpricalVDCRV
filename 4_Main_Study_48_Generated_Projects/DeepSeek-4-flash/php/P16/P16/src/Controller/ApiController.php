<?php

declare(strict_types=1);

namespace App\Controller;

use App\AppException;
use App\ForbiddenException;
use App\Middleware\AuthMiddleware;
use App\ModuleService;
use App\Modules\ApiTokenManagerService;
use App\Modules\BackupManagerService;
use App\Modules\HealthCheckTargetsService;
use App\Modules\LogViewerService;
use App\Modules\ServerDashboardService;
use App\Modules\AuditLogsAndAdminOperationsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\UploadedFile;

final class ApiController
{
    /** @var array<string, ModuleService> */
    private array $services;

    /** @param array<string, ModuleService> $services */
    public function __construct(array $services)
    {
        $this->services = $services;
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args, string $module): ResponseInterface
    {
        $user = $request->getAttribute('user') ?? [];
        $rows = $this->service($module)->list($request->getQueryParams(), $user);

        return $this->json($response, ['ok' => true, 'data' => $rows]);
    }

    public function item(ServerRequestInterface $request, ResponseInterface $response, array $args, string $module): ResponseInterface
    {
        $user = $request->getAttribute('user') ?? [];
        $row = $this->service($module)->item((int) $args['id'], $user);

        return $this->json($response, ['ok' => true, 'data' => $row]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args, string $module): ResponseInterface
    {
        $user = $request->getAttribute('user') ?? [];
        $data = $request->getParsedBody() ?? [];
        $row = $this->service($module)->create($data, $user);

        return $this->json($response, ['ok' => true, 'message' => 'Created successfully.', 'data' => $row], 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args, string $module): ResponseInterface
    {
        $user = $request->getAttribute('user') ?? [];
        $data = $request->getParsedBody() ?? [];
        $row = $this->service($module)->update((int) $args['id'], $data, $user);

        return $this->json($response, ['ok' => true, 'message' => 'Updated successfully.', 'data' => $row]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args, string $module): ResponseInterface
    {
        $user = $request->getAttribute('user') ?? [];
        $this->service($module)->delete((int) $args['id'], $user);

        return $this->json($response, ['ok' => true, 'message' => 'Deleted successfully.']);
    }

    // ---- module specific endpoints ----

    public function dashboardLatest(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        /** @var ServerDashboardService $service */
        $service = $this->services['server_dashboard'];

        return $this->json($response, ['ok' => true, 'data' => $service->latest()]);
    }

    public function logFiles(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        /** @var LogViewerService $service */
        $service = $this->services['log_viewer'];

        return $this->json($response, ['ok' => true, 'data' => $service->files()]);
    }

    public function logPreview(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        /** @var LogViewerService $service */
        $service = $this->services['log_viewer'];
        $params = $request->getQueryParams();
        $file = (string) ($params['file'] ?? '');
        if ($file === '') {
            return $this->json($response, ['ok' => false, 'error' => 'A log file must be selected.', 'code' => 'VALIDATION'], 422);
        }
        $rows = $service->list(['file_name' => $file, 'q' => (string) ($request->getQueryParams()['q'] ?? '')], $request->getAttribute('user') ?? []);

        return $this->json($response, ['ok' => true, 'data' => $rows]);
    }

    public function logDownload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        /** @var LogViewerService $service */
        $service = $this->services['log_viewer'];
        $file = (string) ($request->getQueryParams()['file'] ?? '');
        try {
            $content = $service->fileContent($file);
        } catch (\App\ValidationException $e) {
            return $this->json($response, ['ok' => false, 'error' => $e->getMessage(), 'code' => 'VALIDATION'], 422);
        }
        $response->getBody()->write($content);

        return $response
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($file) . '"');
    }

    public function backupUpload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        /** @var BackupManagerService $service */
        $service = $this->services['backup_manager'];
        $user = $request->getAttribute('user') ?? [];

        $data = $request->getParsedBody() ?? [];
        $uploaded = $request->getUploadedFiles();
        $file = $uploaded['file'] ?? null;
        if ($file instanceof UploadedFile && $file->getError() === UPLOAD_ERR_OK) {
            $data['_file'] = [
                'name' => $file->getClientFilename(),
                'size' => $file->getSize(),
                'error' => UPLOAD_ERR_OK,
                '_content' => (string) $file->getStream(),
            ];
        } else {
            $data['_file'] = ['error' => UPLOAD_ERR_NO_FILE, 'name' => '', 'size' => 0];
        }
        $row = $service->upload($data, $user);

        return $this->json($response, ['ok' => true, 'message' => 'Backup uploaded.', 'data' => $row], 201);
    }

    public function backupDownload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        /** @var BackupManagerService $service */
        $service = $this->services['backup_manager'];
        $payload = $service->downloadPayload((int) $args['id'], $request->getAttribute('user') ?? []);
        $response->getBody()->write($payload['content']);

        return $response
            ->withHeader('Content-Type', $payload['mime'])
            ->withHeader('Content-Disposition', 'attachment; filename="' . $payload['filename'] . '"');
    }

    public function backupRestore(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        /** @var BackupManagerService $service */
        $service = $this->services['backup_manager'];
        $row = $service->restore((int) $args['id'], $request->getAttribute('user') ?? []);

        return $this->json($response, ['ok' => true, 'message' => 'Backup restored.', 'data' => $row]);
    }

    public function healthCheck(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        /** @var HealthCheckTargetsService $service */
        $service = $this->services['health_check_targets'];
        $row = $service->check((int) $args['id'], $request->getAttribute('user') ?? []);

        return $this->json($response, ['ok' => true, 'message' => 'Health check completed.', 'data' => $row]);
    }

    public function tokenCreate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        /** @var ApiTokenManagerService $service */
        $service = $this->services['api_token_manager'];
        $result = $service->create($request->getParsedBody() ?? [], $request->getAttribute('user') ?? []);

        return $this->json($response, [
            'ok' => true,
            'message' => 'Token created. Copy it now; it will not be shown again.',
            'data' => $result['token'],
            'plain_token' => $result['plain_token'],
        ], 201);
    }

    public function auditUsers(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        /** @var AuditLogsAndAdminOperationsService $service */
        $service = $this->services['audit_logs_and_admin_operations'];

        return $this->json($response, ['ok' => true, 'data' => $service->users()]);
    }

    public function auditOperators(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        /** @var AuditLogsAndAdminOperationsService $service */
        $service = $this->services['audit_logs_and_admin_operations'];

        return $this->json($response, ['ok' => true, 'data' => $service->operators()]);
    }

    private function service(string $module): ModuleService
    {
        return $this->services[$module] ?? throw new \App\NotFoundException('Unknown module.');
    }

    private function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
