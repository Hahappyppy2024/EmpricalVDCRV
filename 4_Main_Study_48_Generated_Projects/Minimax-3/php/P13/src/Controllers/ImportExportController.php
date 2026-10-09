<?php
declare(strict_types=1);

namespace MailServer\Controllers;

use MailServer\Auth\SessionService;
use MailServer\Helpers\ResponseHelper;
use MailServer\Repositories\ContactRepository;
use MailServer\Repositories\FileRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;
use Slim\Views\PhpRenderer;

class ImportExportController
{
    public function __construct(
        private FileRepository $files,
        private ContactRepository $contacts,
        private SessionService $session,
        private PhpRenderer $view,
        private string $exportDir
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $jobs = $this->files->jobsFor((int) $user['id']);
        return $this->view->render($response, 'import_export.php', [
            'user' => $user,
            'jobs' => $jobs,
            'flash' => null,
        ]);
    }

    public function importContacts(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;
        $jobs = $this->files->jobsFor((int) $user['id']);
        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return $this->view->render($response->withStatus(422), 'import_export.php', [
                'user' => $user, 'jobs' => $jobs, 'flash' => ['ok' => false, 'msg' => 'Please choose a CSV file.'],
            ]);
        }
        $mime = $file->getClientMediaType();
        $ext = strtolower(pathinfo($file->getClientFilename() ?? '', PATHINFO_EXTENSION));
        if (!in_array($mime, ['text/csv', 'text/plain', 'application/vnd.ms-excel'], true) && $ext !== 'csv') {
            return $this->view->render($response->withStatus(415), 'import_export.php', [
                'user' => $user, 'jobs' => $jobs, 'flash' => ['ok' => false, 'msg' => 'CSV files only.'],
            ]);
        }
        $content = (string) $file->getStream()->getContents();
        $rows = [];
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $content);
        rewind($fh);
        $header = fgetcsv($fh);
        if (!$header) {
            return $this->view->render($response->withStatus(422), 'import_export.php', [
                'user' => $user, 'jobs' => $jobs, 'flash' => ['ok' => false, 'msg' => 'Empty CSV.'],
            ]);
        }
        while (($row = fgetcsv($fh)) !== false) {
            $assoc = array_combine($header, array_pad($row, count($header), null));
            $rows[] = [
                'name' => trim((string) ($assoc['name'] ?? '')),
                'email' => trim((string) ($assoc['email'] ?? '')),
                'organization' => trim((string) ($assoc['organization'] ?? '')),
                'phone' => trim((string) ($assoc['phone'] ?? '')),
                'notes' => trim((string) ($assoc['notes'] ?? '')),
            ];
        }
        fclose($fh);
        $count = $this->contacts->bulkCreate((int) $user['id'], $rows);
        $stored = 'import_' . bin2hex(random_bytes(6)) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientFilename());
        $path = rtrim($this->exportDir, '/\\') . DIRECTORY_SEPARATOR . $stored;
        copy($file->getStream()->getMetadata('uri') ?: '', $path);
        $jobId = $this->files->createJob((int) $user['id'], 'import_contacts', 'csv', $file->getClientFilename(), $stored, 'completed', $count);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'import.contacts', 'import_export_job', (string) $jobId, $count . ' rows', $ip);
        return ResponseHelper::redirect($response, '/mail/import-export?imported=' . $count);
    }

    public function exportContacts(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $items = $this->contacts->listFor((int) $user['id']);
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['name', 'email', 'organization', 'phone', 'notes']);
        foreach ($items as $i) {
            fputcsv($fh, [$i['name'], $i['email'], $i['organization'], $i['phone'], $i['notes']]);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        $filename = 'contacts_' . $user['username'] . '_' . gmdate('Ymd_His') . '.csv';
        $stored = $filename;
        $path = rtrim($this->exportDir, '/\\') . DIRECTORY_SEPARATOR . $stored;
        file_put_contents($path, $csv);
        $jobId = $this->files->createJob((int) $user['id'], 'export_contacts', 'csv', $filename, $stored, 'completed', count($items));
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'export.contacts', 'import_export_job', (string) $jobId, count($items) . ' rows', $ip);
        $response = new Response();
        $response->getBody()->write($csv);
        return $response->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $job = $this->files->findJob((int) $user['id'], $id);
        if (!$job) {
            return $this->view->render($response->withStatus(404), 'import_export.php', [
                'user' => $user, 'jobs' => $this->files->jobsFor((int) $user['id']),
                'flash' => ['ok' => false, 'msg' => 'Job not found.'],
            ]);
        }
        $path = rtrim($this->exportDir, '/\\') . DIRECTORY_SEPARATOR . $job['storage_path'];
        if (!is_file($path)) {
            return $this->view->render($response->withStatus(410), 'import_export.php', [
                'user' => $user, 'jobs' => $this->files->jobsFor((int) $user['id']),
                'flash' => ['ok' => false, 'msg' => 'Stored file missing.'],
            ]);
        }
        $stream = fopen($path, 'rb');
        $body = new \Slim\Psr7\Stream($stream);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'export.download', 'import_export_job', (string) $id, $job['filename'], $ip);
        return $response->withBody($body)
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $job['filename'] . '"');
    }

    public function apiGet(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $jobs = $this->files->jobsFor((int) $user['id']);
        return ResponseHelper::json($response, ['jobs' => $jobs]);
    }

    public function apiPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;
        $data = (array) $request->getParsedBody();
        $kind = (string) ($data['kind'] ?? 'import_contacts');
        if ($kind === 'export_contacts') {
            return $this->apiExport($request, $response, $user);
        }
        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return ResponseHelper::validationError($response, ['file' => 'required']);
        }
        $mime = $file->getClientMediaType();
        $ext = strtolower(pathinfo($file->getClientFilename() ?? '', PATHINFO_EXTENSION));
        if (!in_array($mime, ['text/csv', 'text/plain', 'application/vnd.ms-excel'], true) && $ext !== 'csv') {
            return ResponseHelper::stableError($response, 'unsupported_media_type', 415);
        }
        $content = (string) $file->getStream()->getContents();
        $rows = [];
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $content);
        rewind($fh);
        $header = fgetcsv($fh);
        if (!$header) {
            return ResponseHelper::stableError($response, 'empty_file', 422);
        }
        while (($row = fgetcsv($fh)) !== false) {
            $assoc = array_combine($header, array_pad($row, count($header), null));
            $rows[] = [
                'name' => trim((string) ($assoc['name'] ?? '')),
                'email' => trim((string) ($assoc['email'] ?? '')),
                'organization' => trim((string) ($assoc['organization'] ?? '')),
                'phone' => trim((string) ($assoc['phone'] ?? '')),
                'notes' => trim((string) ($assoc['notes'] ?? '')),
            ];
        }
        fclose($fh);
        $count = $this->contacts->bulkCreate((int) $user['id'], $rows);
        $stored = 'import_' . bin2hex(random_bytes(6)) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientFilename());
        $jobId = $this->files->createJob((int) $user['id'], 'import_contacts', 'csv', $file->getClientFilename(), $stored, 'completed', $count);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'import.contacts.api', 'import_export_job', (string) $jobId, $count . ' rows', $ip);
        return ResponseHelper::json($response, ['ok' => true, 'imported' => $count, 'job_id' => $jobId], 201);
    }

    private function apiExport(ServerRequestInterface $request, ResponseInterface $response, array $user): ResponseInterface
    {
        $items = $this->contacts->listFor((int) $user['id']);
        $rows = [];
        foreach ($items as $i) {
            $rows[] = [
                'name' => $i['name'],
                'email' => $i['email'],
                'organization' => $i['organization'],
                'phone' => $i['phone'],
                'notes' => $i['notes'],
            ];
        }
        $filename = 'contacts_' . $user['username'] . '_' . gmdate('Ymd_His') . '.csv';
        $stored = $filename;
        $path = rtrim($this->exportDir, '/\\') . DIRECTORY_SEPARATOR . $stored;
        $fh = fopen($path, 'w');
        fputcsv($fh, array_keys($rows[0] ?? ['name', 'email', 'organization', 'phone', 'notes']));
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        fclose($fh);
        $jobId = $this->files->createJob((int) $user['id'], 'export_contacts', 'csv', $filename, $stored, 'completed', count($items));
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'export.contacts.api', 'import_export_job', (string) $jobId, count($items) . ' rows', $ip);
        return ResponseHelper::json($response, ['ok' => true, 'job_id' => $jobId, 'rows' => count($items), 'filename' => $filename]);
    }

    public function apiPatch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $job = $this->files->findJob((int) $user['id'], $id);
        if (!$job) {
            return ResponseHelper::stableError($response, 'not_found_or_forbidden', 404);
        }
        $data = (array) $request->getParsedBody();
        $note = (string) ($data['note'] ?? '');
        $pdo = \MailServer\Database\Database::connection();
        $pdo->prepare('UPDATE import_export_jobs SET error_message = COALESCE(error_message, "") || ? WHERE id = ?')->execute([' [note] ' . $note, $id]);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'import_export.annotate', 'import_export_job', (string) $id, $note, $ip);
        return ResponseHelper::json($response, ['ok' => true]);
    }
}