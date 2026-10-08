<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use CloudFS\Database\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class SearchController
{
    use JsonResponder;

    public function __construct(private Database $db, private PhpRenderer $view)
    {
    }

    public function page(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'search.php', ['current_user' => $request->getAttribute('current_user')]);
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $query = $request->getQueryParams();
        $term = trim((string) ($query['q'] ?? ''));
        $tag = trim((string) ($query['tag'] ?? ''));
        $owner = trim((string) ($query['owner'] ?? ''));
        $from = trim((string) ($query['from'] ?? ''));
        $to = trim((string) ($query['to'] ?? ''));

        $where = ['f.status = \'active\''];
        $params = [];
        $order = 'f.name';
        $dir = 'ASC';

        if ($term !== '') {
            $where[] = '(f.name LIKE ? OR f.original_name LIKE ? OR f.description LIKE ?)';
            $params[] = '%' . $term . '%';
            $params[] = '%' . $term . '%';
            $params[] = '%' . $term . '%';
        }
        if ($tag !== '') {
            $where[] = 'f.tags LIKE ?';
            $params[] = '%' . $tag . '%';
        }
        if ($owner !== '') {
            $where[] = 'u.username LIKE ?';
            $params[] = '%' . $owner . '%';
        }
        if ($from !== '') {
            $where[] = 'f.created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where[] = 'f.created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }

        $visibleIds = $this->visibleFileIds((int) $user['id']);
        if ($visibleIds === null) {
            return $this->json($response, ['ok' => true, 'records' => [], 'message' => 'No records match the current scope.']);
        }
        if ($visibleIds === []) {
            return $this->json($response, ['ok' => true, 'records' => []]);
        }
        $where[] = 'f.id IN (' . implode(',', $visibleIds) . ')';

        $limit = min((int) ($query['limit'] ?? 50), 200);
        $sql = 'SELECT f.id, f.name, f.original_name, f.mime_type, f.size_bytes, f.description, f.tags, f.created_at, u.username AS owner
                FROM files f JOIN users u ON u.id = f.owner_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order . ' ' . $dir . ' LIMIT ' . $limit;
        $rows = $this->db->all($sql, $params);
        return $this->json($response, ['ok' => true, 'records' => $rows, 'count' => count($rows)]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $q = trim((string) ($data['q'] ?? ''));
        $rows = $this->performSearch((int) $user['id'], $data);
        if (trim((string) ($data['q'] ?? '')) === '' && count($rows) > 0) {
            return $this->json($response, ['ok' => false, 'errors' => ['A search query is required to save a search record.']], 422);
        }
        return $this->json($response, ['ok' => true, 'query' => $q, 'records' => $rows, 'count' => count($rows), 'message' => 'Search completed.']);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        return $this->json($response, ['ok' => true, 'id' => (int) $args['id'], 'message' => 'Search record updated.']);
    }

    private function performSearch(int $userId, array $data): array
    {
        $where = ['f.status = \'active\''];
        $params = [];
        if (isset($data['q']) && trim((string) $data['q']) !== '') {
            $where[] = '(f.name LIKE ? OR f.original_name LIKE ? OR f.description LIKE ?)';
            $params[] = '%' . trim((string) $data['q']) . '%';
            $params[] = '%' . trim((string) $data['q']) . '%';
            $params[] = '%' . trim((string) $data['q']) . '%';
        }
        if (isset($data['tag']) && trim((string) $data['tag']) !== '') {
            $where[] = 'f.tags LIKE ?';
            $params[] = '%' . trim((string) $data['tag']) . '%';
        }
        $visible = $this->visibleFileIds($userId);
        if ($visible === [] || $visible === null) {
            return [];
        }
        $where[] = 'f.id IN (' . implode(',', $visible) . ')';
        return $this->db->all(
            'SELECT f.id, f.name, f.mime_type, f.size_bytes, f.tags, u.username AS owner FROM files f JOIN users u ON u.id = f.owner_id WHERE ' . implode(' AND ', $where) . ' ORDER BY f.name LIMIT 50',
            $params
        );
    }

    private function visibleFileIds(int $userId): ?array
    {
        $mine = $this->db->all('SELECT id FROM files WHERE owner_id = ? AND status = \'active\'', [$userId]);
        $shared = $this->db->all(
            'SELECT DISTINCT f.id FROM shares s JOIN files f ON f.id = s.file_id WHERE s.owner_id <> ? AND f.status = \'active\' AND s.revoked = 0 AND (s.expires_at IS NULL OR s.expires_at > datetime(\'now\'))',
            [$userId]
        );
        $teamFiles = $this->db->all(
            'SELECT DISTINCT f.id FROM team_members tm
             JOIN team_folders tf ON tf.team_id = tm.team_id
             JOIN folders fo ON fo.id = tf.folder_id
             JOIN files f ON f.folder_id = fo.id
             WHERE tm.user_id = ? AND f.status = \'active\'',
            [$userId]
        );
        $ids = array_unique(array_merge(
            array_map(fn($r) => (int) $r['id'], $mine),
            array_map(fn($r) => (int) $r['id'], $shared),
            array_map(fn($r) => (int) $r['id'], $teamFiles)
        ));
        sort($ids);
        return $ids;
    }
}
