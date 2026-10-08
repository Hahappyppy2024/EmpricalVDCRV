<?php

declare(strict_types=1);

namespace App\Modules;

use App\BaseService;
use App\Validator;

/**
 * SYS-10 Health check targets.
 *
 * Persistent entities: User, Session, HealthCheckTargets (health targets).
 * Users configure HTTP/TCP health checks for internal services. Checks run
 * against a deterministic local adapter (no external network required).
 */
final class HealthCheckTargetsService extends BaseService
{
    public const PROTOCOLS = ['http', 'tcp'];

    public function list(array $filters, array $user): array
    {
        $sql = 'SELECT ht.*, u.username AS created_by_name
                FROM health_targets ht LEFT JOIN users u ON u.id = ht.created_by WHERE 1 = 1';
        $params = [];
        if (!empty($filters['protocol'])) {
            $sql .= ' AND ht.protocol = :protocol';
            $params['protocol'] = $filters['protocol'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND ht.status = :status';
            $params['status'] = $filters['status'];
        }
        $sql .= ' ORDER BY ht.name ASC';

        return $this->db->fetchAll($sql, $params);
    }

    public function item(int $id, array $user): array
    {
        return $this->db->fetchOne(
            'SELECT ht.*, u.username AS created_by_name
             FROM health_targets ht LEFT JOIN users u ON u.id = ht.created_by WHERE ht.id = ?',
            [$id]
        ) ?? throw new \App\NotFoundException('Health target not found.');
    }

    public function create(array $data, array $user): array
    {
        (new Validator())
            ->required($data, 'name', 'target')
            ->string(['name' => $data['name'] ?? ''], 'name', 100, true)
            ->string(['target' => $data['target'] ?? ''], 'target', 200, true)
            ->oneOf($data, 'protocol', self::PROTOCOLS, true)
            ->number($data, 'port', 1, 65535, false)
            ->number($data, 'interval_seconds', 5, 86400, false)
            ->unique($this->db, 'health_targets', 'name', trim((string) ($data['name'] ?? '')))
            ->throwIfInvalid();

        $id = $this->db->insert('health_targets', [
            'name' => trim((string) $data['name']),
            'protocol' => $data['protocol'],
            'target' => trim((string) $data['target']),
            'port' => isset($data['port']) && $data['port'] !== '' ? (int) $data['port'] : null,
            'interval_seconds' => (int) ($data['interval_seconds'] ?? 60),
            'status' => 'unknown',
            'last_code' => null,
            'last_latency_ms' => null,
            'last_checked_at' => null,
            'created_by' => (int) $user['id'],
            'created_at' => $this->db->now(),
        ]);

        return $this->item($id, $user);
    }

    public function update(int $id, array $data, array $user): array
    {
        $row = $this->requireRow('health_targets', $id);

        $validator = new Validator();
        $updates = [];
        if (array_key_exists('name', $data)) {
            $validator->string(['name' => $data['name']], 'name', 100, true)->unique($this->db, 'health_targets', 'name', $data['name'], $id);
            $updates['name'] = trim((string) $data['name']);
        }
        if (array_key_exists('target', $data)) {
            $validator->string(['target' => $data['target']], 'target', 200, true);
            $updates['target'] = trim((string) $data['target']);
        }
        if (array_key_exists('protocol', $data)) {
            $validator->oneOf($data, 'protocol', self::PROTOCOLS, true);
            $updates['protocol'] = $data['protocol'];
        }
        if (array_key_exists('port', $data)) {
            $validator->number($data, 'port', 1, 65535);
            $updates['port'] = $data['port'] !== '' ? (int) $data['port'] : null;
        }
        if (array_key_exists('interval_seconds', $data)) {
            $validator->number($data, 'interval_seconds', 5, 86400);
            $updates['interval_seconds'] = (int) $data['interval_seconds'];
        }
        $validator->throwIfInvalid();
        if ($updates !== []) {
            $this->db->update('health_targets', $updates, ['id' => $id]);
        }

        return $this->item($id, $user);
    }

    public function check(int $id, array $user): array
    {
        $row = $this->requireRow('health_targets', $id);
        [$status, $code, $latency] = $this->probe($row);
        $this->db->update('health_targets', [
            'status' => $status,
            'last_code' => $code,
            'last_latency_ms' => $latency,
            'last_checked_at' => $this->db->now(),
        ], ['id' => $id]);
        $this->log($user, 'health_check', 'health_check_targets', 'Target "' . $row['name'] . '" probed: ' . $status . '.');

        return $this->item($id, $user);
    }

    /**
     * Deterministic local probe adapter.
     *
     * - HTTP targets on 127.0.0.1/localhost are simulated by port:
     *   8787 -> 200/up, 8788 -> 503/down. Any other HTTP target is probed
     *   with a real request and a 2 second timeout.
     * - TCP targets are probed with a real connect and a 1 second timeout.
     */
    private function probe(array $row): array
    {
        $start = hrtime(true);
        $protocol = (string) $row['protocol'];
        $target = (string) $row['target'];
        $port = $row['port'] !== null ? (int) $row['port'] : 80;

        $isLocalSim = false;
        $host = parse_url($target, PHP_URL_HOST) ?: $target;
        $localHost = in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true);

        if ($protocol === 'http' && $localHost && in_array($port, [8787, 8788], true)) {
            $code = $port === 8787 ? 200 : 503;
            $latency = (int) (hrtime(true) - $start) / 1000;

            return [$code === 200 ? 'up' : 'down', $code, $latency];
        }

        if ($protocol === 'http') {
            $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true, 'user_agent' => 'p16-health-check']]);
            $body = @file_get_contents($target, false, $ctx);
            $latency = (int) ((hrtime(true) - $start) / 1000000);
            $code = 0;
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $code = (int) $m[1];
            } elseif ($body !== false) {
                $code = 200;
            }

            return [$code >= 200 && $code < 400 ? 'up' : 'down', $code, $latency];
        }

        // TCP probe
        $fp = @fsockopen($host, $port, $errno, $errstr, 1);
        $latency = (int) ((hrtime(true) - $start) / 1000000);
        if ($fp !== false) {
            fclose($fp);

            return ['up', 0, $latency];
        }

        return ['down', 0, $latency];
    }

    public function delete(int $id, array $user): void
    {
        $this->requireRow('health_targets', $id);
        $this->db->delete('health_targets', ['id' => $id]);
    }
}
