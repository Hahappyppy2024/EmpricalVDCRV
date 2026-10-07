<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/core/Bootstrap.php';

init_db($config);
seed_admin($config);

echo "Database initialized and admin seeded.\n";
