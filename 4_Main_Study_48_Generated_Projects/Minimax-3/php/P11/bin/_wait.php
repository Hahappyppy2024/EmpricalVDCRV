<?php
$port = (int)($argv[1] ?? 0);
$sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 2);
if ($sock) { fclose($sock); exit(0); }
exit(1);