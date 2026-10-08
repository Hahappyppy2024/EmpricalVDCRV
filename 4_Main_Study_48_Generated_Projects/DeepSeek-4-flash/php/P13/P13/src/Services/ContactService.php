<?php

declare(strict_types=1);

namespace P13\Services;

use P13\Audit;
use P13\Database;
use P13\Validation;

/**
 * Contact management service (MAIL-06): create, edit, import-ready search.
 * Privileged mutations are audited.
 */
final class ContactService
{
    public function __construct(private Database $db, private Audit $audit)
    {
    }

    public function list(int $userId, string $q = '', int $limit = 200): array
    {
        $where = ['owner_user_id = ?'];
        $params = [$userId];
        if ($q !== '') {
            $where[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR organization LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $clause = implode(' AND ', $where);
        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM contacts WHERE ' . $clause, $params);
        $items = $this->db->select(
            "SELECT id, first_name, last_name, email, phone, organization, notes, created_at, updated_at
               FROM contacts
              WHERE {$clause}
              ORDER BY last_name ASC, first_name ASC
              LIMIT ?",
            array_merge($params, [$limit])
        );
        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{ok: bool, id?: int, contact?: array<string, mixed>, errors?: array<string, string>}
     */
    public function create(int $userId, array $data): array
    {
        $errors = Validation::required($data, ['email']);
        if (!empty($errors)) {
            return ['ok' => false, 'errors' => $errors];
        }
        if (!Validation::email($data['email'])) {
            return ['ok' => false, 'errors' => ['email' => 'A valid email is required.']];
        }
        if (!Validation::stringLen($data['email'], 255) || !Validation::stringLen($data['first_name'] ?? '', 80) || !Validation::stringLen($data['last_name'] ?? '', 80)) {
            return ['ok' => false, 'errors' => ['contact' => 'Contact fields exceed maximum length.']];
        }
        $this->db->execute(
            'INSERT INTO contacts (owner_user_id, first_name, last_name, email, phone, organization, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, datetime(\'now\'), datetime(\'now\'))',
            [
                $userId,
                $data['first_name'] ?? '',
                $data['last_name'] ?? '',
                $data['email'],
                $data['phone'] ?? '',
                $data['organization'] ?? '',
                $data['notes'] ?? '',
            ]
        );
        $id = $this->db->lastInsertId();
        $contact = $this->db->row('SELECT * FROM contacts WHERE id = ?', [$id]);
        $this->audit->log($userId, null, null, 'contact.create', 'Contact', (string) $id, [
            'email' => $data['email'],
            'name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
        ]);
        return ['ok' => true, 'id' => $id, 'contact' => $contact];
    }

    /**
     * @return array{ok: bool, contact?: array<string, mixed>, errors?: array<string, string>}
     */
    public function update(int $contactId, int $userId, array $data): array
    {
        $contact = $this->db->row('SELECT * FROM contacts WHERE id = ? AND owner_user_id = ?', [$contactId, $userId]);
        if ($contact === null) {
            return ['ok' => false, 'errors' => ['contact' => 'Unknown or out-of-scope contact.']];
        }
        $email = $data['email'] ?? $contact['email'];
        if (!Validation::email($email)) {
            return ['ok' => false, 'errors' => ['email' => 'A valid email is required.']];
        }
        $this->db->execute(
            'UPDATE contacts SET first_name = ?, last_name = ?, email = ?, phone = ?, organization = ?, notes = ?, updated_at = datetime(\'now\') WHERE id = ?',
            [
                $data['first_name'] ?? $contact['first_name'],
                $data['last_name'] ?? $contact['last_name'],
                $email,
                $data['phone'] ?? $contact['phone'],
                $data['organization'] ?? $contact['organization'],
                $data['notes'] ?? $contact['notes'],
                $contactId,
            ]
        );
        $updated = $this->db->row('SELECT * FROM contacts WHERE id = ?', [$contactId]);
        $this->audit->log($userId, null, null, 'contact.update', 'Contact', (string) $contactId, ['email' => $email]);
        return ['ok' => true, 'contact' => $updated];
    }
}
