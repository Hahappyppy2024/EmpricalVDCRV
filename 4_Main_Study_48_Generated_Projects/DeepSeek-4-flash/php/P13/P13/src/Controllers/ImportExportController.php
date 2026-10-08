<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Database;
use P13\Session;
use P13\Services\ImportExportService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Import/export controller (MAIL-11).
 */
final class ImportExportController extends ApiController
{
    public function __construct(
        Database $db,
        Session $session,
        private ImportExportService $service
    ) {
        parent::__construct($db, $session);
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->ok($response, ['jobs' => $this->service->list($this->userId())]);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = is_array($request->getParsedBody()) ? $request->getParsedBody() : json_body($request);
        $kind = (string) ($data['kind'] ?? '');

        if ($kind === 'export') {
            $entityType = (string) ($data['entity_type'] ?? 'contacts');
            $result = $this->service->export($this->userId(), $entityType, $this->user());
        } elseif ($kind === 'import') {
            $uploads = $request->getUploadedFiles();
            $file = $uploads['file'] ?? null;
            if (!$file instanceof \Psr\Http\Message\UploadedFileInterface) {
                return $this->error($response, 'Missing import file.', 422);
            }
            $result = $this->service->importContacts($this->userId(), $file);
        } else {
            return $this->error($response, 'A valid kind (import or export) is required.', 422);
        }

        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, $result, 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $result = $this->service->update((int) $args['id'], $this->userId(), $this->body($request));
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['job' => $result['job'], 'message' => 'Job updated.']);
    }
}
