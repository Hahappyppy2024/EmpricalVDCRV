<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\DatabaseRepository;
use App\Services\AuditLogger;
use App\Services\Flash;
use App\Services\Validator;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DatabaseManagementController
{
    public function __construct(private DatabaseRepository $databases) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $items = $this->databases->listForUser((int)$user['id']);
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['items' => $items]);
        return View::render($response, 'databases', ['user' => $user, 'items' => $items, 'flash' => Flash::pull()]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $body = (array)$request->getParsedBody();
        $dbName = trim((string)($body['db_name'] ?? ''));
        $dbUser = trim((string)($body['db_user'] ?? ''));
        $dbPass = (string)($body['db_pass'] ?? '');

        if (!Validator::slug($dbName) || !Validator::slug($dbUser) || strlen($dbPass) < 6) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'validation'], 422);
            Flash::set('error', 'Invalid db name, user, or password (min 6 chars)'); return View::redirect($response, '/databases?error=validation');
        }
        if ($this->databases->existsForUser((int)$user['id'], $dbName)) {
            if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['error' => 'duplicate'], 409);
            Flash::set('error', 'Database already exists'); return View::redirect($response, '/databases?error=duplicate');
        }
        $hash = password_hash($dbPass, PASSWORD_BCRYPT);
        $id = $this->databases->create((int)$user['id'], $dbName, $dbUser, $hash);
        AuditLogger::log((int)$user['id'], $user['role'], 'database.create', 'database', $id, $dbName, $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['id' => $id, 'status' => 'ok'], 201);
        Flash::set('success', "Database $dbName created");
        return View::redirect($response, '/databases');
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $db = $this->databases->findOwned((int)$args['id'], (int)$user['id']);
        if (!$db) return View::json($response, ['error' => 'not_found'], 404);
        $body = (array)$request->getParsedBody();
        $new = (string)($body['db_pass'] ?? '');
        if (strlen($new) < 6) return View::json($response, ['error' => 'invalid_password'], 422);
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $this->databases->updatePassword((int)$db['id'], (int)$user['id'], $hash);
        AuditLogger::log((int)$user['id'], $user['role'], 'database.password.change', 'database', (int)$db['id'], $db['db_name'], $this->ip($request));
        return View::json($response, ['status' => 'ok']);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $db = $this->databases->findOwned((int)$args['id'], (int)$user['id']);
        if (!$db) return View::json($response, ['error' => 'not_found'], 404);
        $this->databases->delete((int)$db['id'], (int)$user['id']);
        AuditLogger::log((int)$user['id'], $user['role'], 'database.delete', 'database', (int)$db['id'], $db['db_name'], $this->ip($request));
        if (str_starts_with($request->getUri()->getPath(), '/api/')) return View::json($response, ['status' => 'ok']);
        Flash::set('success', 'Database removed');
        return View::redirect($response, '/databases');
    }

    private function ip(ServerRequestInterface $request): string
    {
        $p = $request->getServerParams();
        return $p['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}