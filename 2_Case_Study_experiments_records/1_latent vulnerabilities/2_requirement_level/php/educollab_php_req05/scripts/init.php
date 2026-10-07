<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/core/Bootstrap.php';

init_db($config);
seed_admin($config);
backfill_course_creator_memberships($config);

$storagePath = dirname(__DIR__) . '/storage/uploads';
if (!is_dir($storagePath)) {
    mkdir($storagePath, 0777, true);
}

echo "Database initialized, admin seeded, memberships backfilled, and upload storage prepared.\n";
