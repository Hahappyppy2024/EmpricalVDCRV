<?php
declare(strict_types=1);

$publicDir = __DIR__;
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$path = $publicDir . $uri;
if ($uri !== '/' && file_exists($path) && !is_dir($path)) {
    return false;
}
require $publicDir . '/index.php';