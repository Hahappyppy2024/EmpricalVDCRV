<?php
declare(strict_types=1);
namespace App\Service;

use App\Infrastructure\HttpException;
use App\Repository\FileRepository;
use App\Repository\SearchRepository;
use App\Repository\TeamRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class SearchService
{
    public function __construct(
        private FileRepository $files,
        private SearchRepository $searches,
        private TeamRepository $teams
    ) {
    }

    public function handle(ServerRequestInterface $req, Response $res, string $method, ?int $id): ResponseInterface
    {
        $user = $this->currentUser($req);
        if ($method === 'GET') {
            $query = $req->getQueryParams();
            $filters = [
                'q' => $query['q'] ?? null,
                'owner' => $query['owner'] ?? null,
                'tag' => $query['tag'] ?? null,
                'from_date' => $query['from_date'] ?? null,
                'to_date' => $query['to_date'] ?? null,
                'owner_id' => (int)$user['user_id'],
            ];
            $results = $this->files->search($filters, $this->userTeamIds((int)$user['user_id']));
            $saved = $this->searches->listForUser((int)$user['user_id']);
            return $this->json($res, ['results' => $results, 'saved' => $saved]);
        }
        $params = (array)$req->getParsedBody();
        if ($method === 'POST') {
            $savedId = $this->searches->create([
                'user_id' => (int)$user['user_id'],
                'name' => (string)($params['name'] ?? 'Saved search'),
                'query' => $params['q'] ?? null,
                'owner' => $params['owner'] ?? null,
                'tag' => $params['tag'] ?? null,
                'from_date' => $params['from_date'] ?? null,
                'to_date' => $params['to_date'] ?? null,
            ]);
            return $this->json($res, ['status' => 'search_saved', 'search_id' => $savedId], 201);
        }
        if ($method === 'PATCH') {
            if (!$id) {
                throw new HttpException(400, 'missing_search_id');
            }
            $search = $this->searches->find($id);
            if (!$search) {
                throw new HttpException(404, 'search_not_found');
            }
            if ((int)$search['user_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                throw new HttpException(403, 'search_forbidden');
            }
            if (!empty($params['delete'])) {
                $this->searches->delete($id);
                return $this->json($res, ['status' => 'search_deleted']);
            }
            throw new HttpException(400, 'unsupported_operation');
        }
        throw new HttpException(405, 'method_not_allowed');
    }

    private function currentUser(ServerRequestInterface $req): array
    {
        $user = $req->getAttribute('user');
        if (!is_array($user)) {
            throw new HttpException(401, 'authentication_required');
        }
        return $user;
    }

    private function userTeamIds(int $userId): array
    {
        $teams = $this->teams->listForUser($userId);
        return array_map(static fn($t) => (int)$t['id'], $teams);
    }

    private function json(Response $res, array $payload, int $status = 200): Response
    {
        $res->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $res->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}