<?php
declare(strict_types=1);

if (!extension_loaded('xdebug') || !str_contains((string)ini_get('xdebug.mode'), 'coverage')) {
    fwrite(STDERR, "Xdebug coverage mode is required.\n");
    exit(2);
}

$root = dirname(__DIR__);
$output = $argv[1] ?? $root . '/var/coverage.json';
xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [$root . '/src/']);
xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);
register_shutdown_function(static function () use ($root, $output): void {
    file_put_contents($output, json_encode([
        'project' => basename($root),
        'root' => $root,
        'php_version' => PHP_VERSION,
        'xdebug_version' => phpversion('xdebug'),
        'generated_at' => gmdate(DATE_ATOM),
        'coverage' => xdebug_get_code_coverage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
});
require $root . '/bin/run-functional-tests.php';
