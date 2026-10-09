<?php
declare(strict_types=1);

namespace LMS\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * CSRF helpers backed by the per-session token store.
 */
final class Csrf
{
    public function __construct(private Session $session) {}

    public function token(): string
    {
        return $this->session->csrfToken();
    }

    public function verify(ServerRequestInterface $request): bool
    {
        $expected = $this->session->csrfToken();
        $supplied = $request->getHeaderLine('X-CSRF-Token')
            ?: ($request->getParsedBody()['_csrf'] ?? '');
        if (!is_string($supplied) || $supplied === '') {
            return false;
        }
        return hash_equals($expected, $supplied);
    }
}
