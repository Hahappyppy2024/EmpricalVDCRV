<?php

declare(strict_types=1);

namespace P13\Services;

use P13\Database;
use P13\Validation;

/**
 * Filters and rules service (MAIL-07): mailbox rules for folders, labels
 * and forwarding.
 */
final class RuleService
{
    public function __construct(private Database $db)
    {
    }

    public function list(int $userId): array
    {
        $items = $this->db->select(
            'SELECT id, name, match_field, match_operator, match_value, action_type, action_value, enabled, position, created_at, updated_at
               FROM mail_rules
              WHERE user_id = ?
              ORDER BY position ASC, id ASC',
            [$userId]
        );
        return ['items' => $items, 'total' => count($items)];
    }

    /**
     * @return array{ok: bool, id?: int, rule?: array<string, mixed>, errors?: array<string, string>}
     */
    public function create(int $userId, array $data): array
    {
        $errors = Validation::required($data, ['name', 'match_field', 'match_value', 'action_type']);
        if (!empty($errors)) {
            return ['ok' => false, 'errors' => $errors];
        }
        if (!Validation::in($data['match_field'], ['from', 'to', 'subject', 'body'])) {
            return ['ok' => false, 'errors' => ['match_field' => 'Invalid match field.']];
        }
        if (!Validation::in($data['match_operator'] ?? 'contains', ['contains', 'equals'])) {
            return ['ok' => false, 'errors' => ['match_operator' => 'Invalid match operator.']];
        }
        if (!Validation::in($data['action_type'], ['folder', 'label', 'forward', 'delete'])) {
            return ['ok' => false, 'errors' => ['action_type' => 'Invalid action type.']];
        }
        if ($data['action_type'] === 'forward' && !Validation::email($data['action_value'] ?? '')) {
            return ['ok' => false, 'errors' => ['action_value' => 'Forward actions require a valid target address.']];
        }
        $this->db->execute(
            'INSERT INTO mail_rules (user_id, name, match_field, match_operator, match_value, action_type, action_value, enabled, position, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'), datetime(\'now\'))',
            [
                $userId,
                $data['name'],
                $data['match_field'],
                $data['match_operator'] ?? 'contains',
                $data['match_value'],
                $data['action_type'],
                $data['action_value'] ?? '',
                isset($data['enabled']) && $data['enabled'] ? 1 : 1,
                (int) ($data['position'] ?? 0),
            ]
        );
        $id = $this->db->lastInsertId();
        $rule = $this->db->row('SELECT * FROM mail_rules WHERE id = ?', [$id]);
        return ['ok' => true, 'id' => $id, 'rule' => $rule];
    }

    /**
     * @return array{ok: bool, rule?: array<string, mixed>, errors?: array<string, string>}
     */
    public function update(int $ruleId, int $userId, array $data): array
    {
        $rule = $this->db->row('SELECT * FROM mail_rules WHERE id = ? AND user_id = ?', [$ruleId, $userId]);
        if ($rule === null) {
            return ['ok' => false, 'errors' => ['rule' => 'Unknown or out-of-scope rule.']];
        }
        $name = $data['name'] ?? $rule['name'];
        if ($name === '' || !Validation::stringLen($name, 120)) {
            return ['ok' => false, 'errors' => ['name' => 'Rule name is required (max 120 chars).']];
        }
        $this->db->execute(
            'UPDATE mail_rules
                SET name = ?, match_field = ?, match_operator = ?, match_value = ?,
                    action_type = ?, action_value = ?, enabled = ?, position = ?,
                    updated_at = datetime(\'now\')
              WHERE id = ?',
            [
                $name,
                $data['match_field'] ?? $rule['match_field'],
                $data['match_operator'] ?? $rule['match_operator'],
                $data['match_value'] ?? $rule['match_value'],
                $data['action_type'] ?? $rule['action_type'],
                $data['action_value'] ?? $rule['action_value'],
                isset($data['enabled']) ? (int) $data['enabled'] : (int) $rule['enabled'],
                $data['position'] ?? $rule['position'],
                $ruleId,
            ]
        );
        $updated = $this->db->row('SELECT * FROM mail_rules WHERE id = ?', [$ruleId]);
        return ['ok' => true, 'rule' => $updated];
    }
}
