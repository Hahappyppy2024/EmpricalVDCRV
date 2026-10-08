<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Database;
use P13\Session;
use P13\Services\AttachmentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Attachment handling controller (MAIL-05): upload, list, download.
 */
final class AttachmentHandlingController extends ApiController
{
    public function __construct(
        Database $db,
        Session $session,
        private AttachmentService $service
    ) {
        parent::__construct($db, $session);
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->ok($response, ['attachments' => $this->service->list($this->userId())]);
    }

    public function create(Request $request, Response $response): Response
    {
        $uploads = $request->getUploadedFiles();
        $file = $uploads['file'] ?? null;
        if (!$file instanceof \Psr\Http\Message\UploadedFileInterface) {
            return $this->error($response, 'Missing file.', 422);
        }
        $data = $this->body($request);
        $messageId = isset($data['message_id']) && (int) $data['message_id'] > 0 ? (int) $data['message_id'] : null;
        $result = $this->service->upload($this->userId(), $file, $messageId);
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['file' => $result['file'], 'message' => 'Attachment stored.'], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $this->body($request);
        $result = $this->service->update((int) $args['id'], $this->userId(), (string) ($data['status'] ?? ''));
        if (!$result['ok']) {
            return $this->error($response, 'Validation failed.', 422, ['errors' => $result['errors']]);
        }
        return $this->ok($response, ['attachment' => $result['attachment'], 'message' => 'Attachment updated.']);
    }

    public function download(Request $request, Response $response, array $args): Response
    {
        $resolved = $this->service->download((int) $args['id'], $this->userId());
        if ($resolved === null) {
            return $this->error($response, 'Unknown or out-of-scope file.', 404);
        }
        $file = $resolved['file'];
        $stream = fopen($resolved['path'], 'rb');
        if ($stream === false) {
            return $this->error($response, 'File is unavailable.', 500);
        }
        $body = new \Slim\Psr7\Stream($stream);
        return $response
            ->withHeader('Content-Type', (string) $file['mime_type'])
            ->withHeader('Content-Disposition', 'attachment; filename="' . $file['original_name'] . '"')
            ->withHeader('Content-Length', (string) $file['size'])
            ->withBody($body);
    }
}
