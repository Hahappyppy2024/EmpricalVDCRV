<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$file=__DIR__.$path;if($path!=='/'&&is_file($file))return false;if($path==='/'||$path==='/app'){$target=__DIR__.'/app.html';header('Content-Type: text/html; charset=utf-8');readfile($target);return true;}require __DIR__.'/index.php';
