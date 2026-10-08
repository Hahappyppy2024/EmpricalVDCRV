<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function __construct(private readonly \PDO $db)
    {
    }

    public function health(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $status = 'ok';
        $users = 0;
        try {
            $users = (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        } catch (\Throwable $e) {
            $status = 'db_error';
        }
        $payload = [
            'ok' => $status === 'ok',
            'status' => $status,
            'database' => $status,
            'users_seeded' => $users,
            'time' => date('Y-m-d H:i:s'),
        ];
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($payload));
        return $response;
    }
}
