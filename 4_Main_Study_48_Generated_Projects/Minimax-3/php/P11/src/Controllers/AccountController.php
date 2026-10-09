<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Services\Flash;
use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AccountController
{
    public function __construct(private UserRepository $users) {}

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $full = $this->users->findById((int)$user['id']);
        return View::render($response, 'account', ['user' => $full, 'flash' => Flash::pull()]);
    }
}