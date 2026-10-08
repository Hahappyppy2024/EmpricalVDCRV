<?php

declare(strict_types=1);

/**
 * Starts the local PHP development server for the application.
 * Usage: php bin/serve.php  (env: HOST, PORT)
 */

$rootDir = dirname(__DIR__);
$host = getenv('HOST') ?: '0.0.0.0';
$port = getenv('PORT') ?: '8080';

$cmd = sprintf(
    'php -S %s:%s -t %s %s',
    escapeshellarg($host),
    escapeshellarg($port),
    escapeshellarg($rootDir . '/public'),
    escapeshellarg($rootDir . '/public/router.php')
);

echo "Hosting Control Panel dev server: http://localhost:{$port}\n";
echo "Press Ctrl+C to stop.\n\n";

passthru($cmd);
