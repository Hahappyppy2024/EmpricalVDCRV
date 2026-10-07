<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/core/Bootstrap.php';

init_db($config);
$seeded = seed_admin($config);

if ($seeded) {
    echo "Database initialized and bootstrap admin provisioned.\n";
    exit;
}

echo "Database initialized. Set BOOTSTRAP_ADMIN_USERNAME and BOOTSTRAP_ADMIN_PASSWORD to provision the bootstrap admin.\n";