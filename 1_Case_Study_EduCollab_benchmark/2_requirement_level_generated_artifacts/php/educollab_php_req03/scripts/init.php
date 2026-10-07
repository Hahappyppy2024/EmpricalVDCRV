<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/core/Bootstrap.php';

init_db($config);
seed_admin($config);
backfill_course_creator_memberships($config);

echo "Database initialized, admin seeded, and course creator memberships backfilled.\n";
