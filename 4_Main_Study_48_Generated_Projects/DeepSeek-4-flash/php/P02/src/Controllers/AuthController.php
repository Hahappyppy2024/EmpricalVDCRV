<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\SessionService;
use App\Services\WorkflowException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly SessionService $sessions,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly array $config
    ) {
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $request->getParsedBody() ?: [];
        try {
            $user = $this->auth->register(
                (string) ($input['name'] ?? ''),
                (string) ($input['email'] ?? ''),
                (string) ($input['password'] ?? ''),
                (string) ($input['role'] ?? 'author')
            );
            $token = $this->sessions->start($user, $this->userAgent($request), $this->ip($request));
            $response = $this->withSessionCookie($response, $token);
            return $this->json($response, 201, ['ok' => true, 'data' => ['user' => $this->publicUser($user)]]);
        } catch (WorkflowException $e) {
            return $this->error($response, $e);
        }
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $request->getParsedBody() ?: [];
        try {
            $user = $this->auth->login(
                (string) ($input['email'] ?? ''),
                (string) ($input['password'] ?? '')
            );
            $token = $this->sessions->start($user, $this->userAgent($request), $this->ip($request));
            $response = $this->withSessionCookie($response, $token);
            return $this->json($response, 200, ['ok' => true, 'data' => ['user' => $this->publicUser($user)]]);
        } catch (WorkflowException $e) {
            return $this->error($response, $e);
        }
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $cookies = $request->getCookieParams();
        $token = $cookies[$this->config['session_name']] ?? null;
        if ($token !== null) {
            $this->sessions->destroy($token);
            $user = $request->getAttribute('user');
            if ($user !== null) {
                $this->auth->signout((int) $user['id']);
            }
        }
        $response = $this->clearSessionCookie($response);
        return $this->json($response, 200, ['ok' => true, 'data' => ['signed_out' => true]]);
    }

    public function reset(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $request->getParsedBody() ?: [];
        try {
            $record = $this->auth->requestPasswordReset((string) ($input['email'] ?? ''));
            return $this->json($response, 200, [
                'ok' => true,
                'data' => [
                    'record_id' => $record['id'],
                    'message' => 'If an account exists for that email, a reset token has been issued.',
                    'reset_token' => $record['plain_token'] ?? null,
                ],
            ]);
        } catch (WorkflowException $e) {
            return $this->error($response, $e);
        }
    }

    public function resetConfirm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $request->getParsedBody() ?: [];
        try {
            $user = $this->auth->confirmPasswordReset(
                (string) ($input['email'] ?? ''),
                (string) ($input['token'] ?? ''),
                (string) ($input['password'] ?? '')
            );
            return $this->json($response, 200, [
                'ok' => true,
                'data' => ['message' => 'Your password has been reset. You can now sign in.'],
            ]);
        } catch (WorkflowException $e) {
            return $this->error($response, $e);
        }
    }

    private function withSessionCookie(ResponseInterface $response, string $token): ResponseInterface
    {
        $lifetime = (int) $this->config['session_lifetime'];
        $cookie = $this->config['session_name'] . '=' . $token
            . '; Path=/; HttpOnly; SameSite=Lax; Max-Age=' . $lifetime;
        return $response->withHeader('Set-Cookie', $cookie);
    }

    private function clearSessionCookie(ResponseInterface $response): ResponseInterface
    {
        $cookie = $this->config['session_name'] . '=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0';
        return $response->withHeader('Set-Cookie', $cookie);
    }

    private function userAgent(ServerRequestInterface $request): string
    {
        return (string) ($request->getHeaderLine('User-Agent') ?: '');
    }

    private function ip(ServerRequestInterface $request): string
    {
        return (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '');
    }

    private function publicUser(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }

    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response = $response->withStatus($status)->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($payload));
        return $response;
    }

    private function error(ResponseInterface $response, WorkflowException $e): ResponseInterface
    {
        return $this->json($response, $e->status, [
            'ok' => false,
            'error' => [
                'code' => $e->errorCode,
                'status' => $e->status,
                'message' => $e->getMessage(),
                'details' => $e->details,
            ],
        ]);
    }
}
