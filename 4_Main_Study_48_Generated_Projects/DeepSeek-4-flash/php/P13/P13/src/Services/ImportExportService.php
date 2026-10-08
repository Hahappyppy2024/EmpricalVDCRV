<?php

declare(strict_types=1);

namespace P13\Services;

use P13\Audit;
use P13\Database;
use P13\Storage;

/**
 * Import/export service (MAIL-11): CSV contact imports and CSV exports of
 * contacts, mailbox data or audit logs. Files are stored locally.
 */
final class ImportExportService
{
    public function __construct(private Database $db, private Storage $storage, private Audit $audit)
    {
    }

    public function list(int $userId): array
    {
        $items = $this->db->select(
            'SELECT i.id, i.kind, i.entity_type, i.status, i.counts, i.error, i.created_at, i.updated_at,
                    f.original_name AS file_name, f.id AS file_id
               FROM import_export i
               LEFT JOIN stored_files f ON f.id = i.stored_file_id
              WHERE i.user_id = ?
              ORDER BY i.id DESC',
            [$userId]
        );
        return ['items' => $items, 'total' => count($items)];
    }

    /**
     * Export a CSV document.
     *
     * @return array{ok: bool, job?: array<string, mixed>, file?: array<string, mixed>, message?: string, errors?: array<string, string>}
     */
    public function export(int $userId, string $entityType, ?array $actor = null): array
    {
        $content = match ($entityType) {
            'contacts' => $this->contactsCsv($userId),
            'mailbox' => $this->mailboxCsv($userId),
            'audit' => $this->auditCsv($actor),
            default => null,
        };
        if ($content === null) {
            return ['ok' => false, 'errors' => ['entity_type' => 'Unsupported export type.']];
        }
        if ($entityType === 'audit' && ($actor === null || $actor['role'] !== 'system_admin')) {
            return ['ok' => false, 'errors' => ['entity_type' => 'Audit export requires the system admin role.']];
        }
        $filename = $entityType . '_export_' . date('Ymd_His') . '.csv';
        $stored = $this->storage->storeContent($content, $userId, 'export', $filename, 'text/csv');
        if (!$stored['ok']) {
            return ['ok' => false, 'errors' => ['file' => $stored['error']]];
        }
        $this->db->execute(
            'INSERT INTO import_export (user_id, kind, entity_type, status, stored_file_id, counts, created_at, updated_at)
             VALUES (?, \'export\', ?, \'done\', ?, ?, datetime(\'now\'), datetime(\'now\'))',
            [$userId, $entityType, $stored['id'], substr_count($content, "\n")]
        );
        $job = $this->db->row('SELECT * FROM import_export WHERE id = ?', [$this->db->lastInsertId()]);
        $this->audit->log($userId, null, null, 'export.create', 'ImportExport', (string) $job['id'], ['entity_type' => $entityType]);
        return ['ok' => true, 'job' => $job, 'file' => $stored['file'], 'message' => 'Export created.'];
    }

    /**
     * Import contacts from an uploaded CSV.
     *
     * @return array{ok: bool, job?: array<string, mixed>, message?: string, errors?: array<string, string>}
     */
    public function importContacts(int $userId, \Psr\Http\Message\UploadedFileInterface $file): array
    {
        $stored = $this->storage->storeUpload($file, $userId, 'import');
        if (!$stored['ok']) {
            return ['ok' => false, 'errors' => ['file' => $stored['error']]];
        }
        $path = $this->storage->pathFor((int) $stored['id'], $userId);
        if ($path === null) {
            return ['ok' => false, 'errors' => ['file' => 'Stored import file is unavailable.']];
        }
        $lines = file($path['path'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return ['ok' => false, 'errors' => ['file' => 'Unable to read import file.']];
        }
        $count = 0;
        foreach ($lines as $idx => $line) {
            if ($idx === 0 && stripos($line, 'email') !== false) {
                continue;
            }
            $fields = str_getcsv($line);
            $email = trim((string) ($fields[0] ?? ''));
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $this->db->execute(
                'INSERT INTO contacts (owner_user_id, first_name, last_name, email, phone, organization, notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, datetime(\'now\'), datetime(\'now\'))',
                [
                    $userId,
                    trim((string) ($fields[1] ?? '')),
                    trim((string) ($fields[2] ?? '')),
                    $email,
                    trim((string) ($fields[3] ?? '')),
                    trim((string) ($fields[4] ?? '')),
                    'imported',
                ]
            );
            $count++;
        }
        $this->db->execute(
            'INSERT INTO import_export (user_id, kind, entity_type, status, stored_file_id, counts, created_at, updated_at)
             VALUES (?, \'import\', \'contacts\', \'done\', ?, ?, datetime(\'now\'), datetime(\'now\'))',
            [$userId, $stored['id'], $count]
        );
        $job = $this->db->row('SELECT * FROM import_export WHERE id = ?', [$this->db->lastInsertId()]);
        $this->audit->log($userId, null, null, 'import.create', 'ImportExport', (string) $job['id'], ['entity_type' => 'contacts', 'count' => $count]);
        return ['ok' => true, 'job' => $job, 'message' => "Imported {$count} contacts."];
    }

    /**
     * Generic PATCH: update job metadata/status.
     *
     * @return array{ok: bool, job?: array<string, mixed>, errors?: array<string, string>}
     */
    public function update(int $jobId, int $userId, array $data): array
    {
        $job = $this->db->row('SELECT * FROM import_export WHERE id = ? AND user_id = ?', [$jobId, $userId]);
        if ($job === null) {
            return ['ok' => false, 'errors' => ['job' => 'Unknown or out-of-scope job.']];
        }
        $status = $data['status'] ?? $job['status'];
        if (!in_array($status, ['pending', 'done', 'failed'], true)) {
            return ['ok' => false, 'errors' => ['status' => 'Invalid job status.']];
        }
        $this->db->execute(
            'UPDATE import_export SET status = ?, error = ?, updated_at = datetime(\'now\') WHERE id = ?',
            [$status, $data['error'] ?? $job['error'], $jobId]
        );
        $updated = $this->db->row('SELECT * FROM import_export WHERE id = ?', [$jobId]);
        return ['ok' => true, 'job' => $updated];
    }

    private function contactsCsv(int $userId): string
    {
        $rows = $this->db->select(
            'SELECT email, first_name, last_name, phone, organization FROM contacts WHERE owner_user_id = ? ORDER BY id ASC',
            [$userId]
        );
        $out = "email,first_name,last_name,phone,organization\n";
        foreach ($rows as $r) {
            $out .= implode(',', [
                $r['email'],
                $r['first_name'],
                $r['last_name'],
                $r['phone'],
                $r['organization'],
            ]) . "\n";
        }
        return $out;
    }

    private function mailboxCsv(int $userId): string
    {
        $rows = $this->db->select(
            'SELECT m.from_address, m.to_address, m.subject, m.body, m.created_at, f.name AS folder
               FROM messages m
               JOIN folders f ON f.id = m.folder_id
              WHERE m.user_id = ?
              ORDER BY m.id DESC',
            [$userId]
        );
        $out = "folder,from,to,subject,body,created_at\n";
        foreach ($rows as $r) {
            $out .= implode(',', [
                $r['folder'],
                $r['from_address'],
                $r['to_address'],
                str_replace("\n", ' ', $r['subject']),
                str_replace("\n", ' ', mb_substr((string) $r['body'], 0, 200)),
                $r['created_at'],
            ]) . "\n";
        }
        return $out;
    }

    private function auditCsv(?array $actor): string
    {
        if ($actor === null || $actor['role'] !== 'system_admin') {
            return "error\npermission_denied\n";
        }
        $rows = $this->db->select(
            'SELECT created_at, actor_username, actor_role, action, entity_type, entity_id, ip_address FROM audit_events ORDER BY id DESC LIMIT 500'
        );
        $out = "created_at,actor_username,actor_role,action,entity_type,entity_id,ip_address\n";
        foreach ($rows as $r) {
            $out .= implode(',', [
                $r['created_at'],
                $r['actor_username'] ?? '',
                $r['actor_role'],
                $r['action'],
                $r['entity_type'],
                $r['entity_id'] ?? '',
                $r['ip_address'],
            ]) . "\n";
        }
        return $out;
    }
}
