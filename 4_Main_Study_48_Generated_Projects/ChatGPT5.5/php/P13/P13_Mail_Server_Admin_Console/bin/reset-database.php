<?php
declare(strict_types=1);require dirname(__DIR__).'/vendor/autoload.php';$root=dirname(__DIR__);$path=getenv('DB_PATH')?:$root.'/var/mail_console.sqlite';App\Seeder::reset($root,$path);echo"Database reset: {$path}\n";
