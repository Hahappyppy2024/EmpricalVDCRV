<?php
declare(strict_types=1);

namespace LMS\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Stream;

/**
 * Serves static assets from /public/assets.
 */
final class AssetController
{
    public function serve(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $file = basename((string)$args['file']);
        $path = dirname(__DIR__, 2) . '/public/assets/' . $file;
        if (!is_file($path)) {
            return $response->withStatus(404);
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            default => 'text/plain',
        };
        return $response
            ->withHeader('Content-Type', $mime)
            ->withHeader('Content-Length', (string)filesize($path))
            ->withBody(new Stream(fopen($path, 'rb')));
    }
}
