<?php

declare(strict_types=1);

namespace P13\Services;

use P13\Database;
use P13\Validation;

/**
 * Frontend API integration and errors service (MAIL-12): records the API
 * response states exercised from the browser (delivery failures, invalid
 * recipients, permission errors).
 */
final class FrontendApiService
{
    public function __construct(private Database $db)
    {
    }

    public function list(int $userId): array
    {
        $items = $this->db->select(
            'SELECT id, scenario, request_payload, response_status, response_message, resolved, created_at
               FROM frontend_api_errors
              WHERE user_id = ?
              ORDER BY id DESC
              LIMIT 200',
            [$userId]
        );
        foreach ($items as &$item) {
            $item['request_payload'] = json_decode((string) $item['request_payload'], true) ?: [];
        }
        unset($item);
        return ['items' => $items, 'total' => count($items)];
    }

    /**
     * @return array{ok: bool, id?: int, errors?: array<string, string>}
     */
    public function record(int $userId, array $data): array
    {
        $errors = Validation::required($data, ['scenario']);
        if (!empty($errors)) {
            return ['ok' => false, 'errors' => $errors];
        }
        if (!Validation::stringLen($data['scenario'], 120) || !Validation::stringLen($data['response_message'] ?? '', 255)) {
            return ['ok' => false, 'errors' => ['record' => 'Record fields exceed maximum length.']];
        }
        $status = (int) ($data['response_status'] ?? 0);
        if (!Validation::intRange($status, 0, 599)) {
            return ['ok' => false, 'errors' => ['response_status' => 'Invalid HTTP status.']];
        }
        $this->db->execute(
            'INSERT INTO frontend_api_errors (user_id, scenario, request_payload, response_status, response_message, resolved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime(\'now\'))',
            [
                $userId,
                $data['scenario'],
                json_encode($data['request_payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $status,
                $data['response_message'] ?? '',
                isset($data['resolved']) && $data['resolved'] ? 1 : 0,
            ]
        );
        return ['ok' => true, 'id' => $this->db->lastInsertId()];
    }

    /**
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function update(int $recordId, int $userId, array $data): array
    {
        $row = $this->db->row('SELECT * FROM frontend_api_errors WHERE id = ? AND user_id = ?', [$recordId, $userId]);
        if ($row === null) {
            return ['ok' => false, 'errors' => ['record' => 'Unknown or out-of-scope record.']];
        }
        $resolved = isset($data['resolved']) ? (int) $data['resolved'] : (int) $row['resolved'];
        $this->db->execute(
            'UPDATE frontend_api_errors SET response_message = ?, resolved = ? WHERE id = ?',
            [$data['response_message'] ?? $row['response_message'], $resolved, $recordId]
        );
        return ['ok' => true];
    }
}
