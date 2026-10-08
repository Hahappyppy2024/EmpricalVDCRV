<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$file=__DIR__.$path;if($path!=='/'&&is_file($file))return false;if($path==='/'||$path==='/app'){header('Content-Type: text/html; charset=utf-8');readfile(__DIR__.'/app.html');return true;}require __DIR__.'/index.php';
