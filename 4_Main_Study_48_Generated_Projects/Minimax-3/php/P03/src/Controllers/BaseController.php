<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shop\Auth\SessionManager;
use Shop\Support\Csrf;
use Shop\Support\FlashBag;
use Shop\Support\View;
use Shop\Models\CartRepository;

abstract class BaseController
{
    protected function render(Response $response, string $template, array $data = []): Response
    {
        $user = SessionManager::user();
        $cartCount = 0;
        if ($user) {
            $cartCount = count(CartRepository::items(CartRepository::ensureCart((int)$user['id'])));
        }
        $defaults = [
            'page_title' => 'P03 E-commerce',
            'user' => $user,
            'csrf' => Csrf::token(),
            'flash_html' => FlashBag::render(),
            'cart_count' => $cartCount,
        ];
        $renderData = array_merge($defaults, $data);
        $html = View::render($template, $renderData);
        $response->getBody()->write($html);
        return $response;
    }

    protected function json(Response $response, mixed $payload, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    protected function redirect(Response $response, string $location, int $status = 302): Response
    {
        return $response->withHeader('Location', $location)->withStatus($status);
    }

    protected function input(Request $request, string $key, mixed $default = null): mixed
    {
        $body = (array)$request->getParsedBody();
        foreach ([$key, "\xEF\xBB\xBF" . $key] as $candidate) {
            if (array_key_exists($candidate, $body)) {
                return $body[$candidate];
            }
        }
        $query = $request->getQueryParams();
        if (array_key_exists($key, $query)) {
            return $query[$key];
        }
        return $default;
    }

    protected function allInput(Request $request): array
    {
        return (array)$request->getParsedBody();
    }

    protected function jsonBody(Request $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed) && $parsed !== []) {
            return $parsed;
        }
        $raw = (string)$request->getBody();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function verifyCsrf(Request $request): bool
    {
        $token = $this->input($request, '_csrf');
        return Csrf::check(is_string($token) ? $token : null);
    }

    protected function requireAuth(Request $request): array
    {
        $user = SessionManager::user();
        if ($user === null) {
            $accept = (string)$request->getHeaderLine('Accept');
            if (str_contains($accept, 'application/json') || str_starts_with($request->getUri()->getPath(), '/api/')) {
                throw new HttpJsonException(401, ['error' => 'authentication_required']);
            }
            SessionManager::flash('error', 'Please sign in to continue.');
            throw new HttpRedirectException('/login');
        }
        return $user;
    }

    protected function requireRole(Request $request, string $role): array
    {
        $user = $this->requireAuth($request);
        $allowed = SessionManager::allowedRoles($role);
        if (!in_array($user['role'], $allowed, true)) {
            $accept = (string)$request->getHeaderLine('Accept');
            if (str_contains($accept, 'application/json') || str_starts_with($request->getUri()->getPath(), '/api/')) {
                throw new HttpJsonException(403, ['error' => 'forbidden']);
            }
            SessionManager::flash('error', 'You do not have permission for that action.');
            throw new HttpRedirectException('/');
        }
        return $user;
    }

    private function renderer(): void
    {
        // no-op: BaseController renders templates directly via Shop\Support\View.
    }
}