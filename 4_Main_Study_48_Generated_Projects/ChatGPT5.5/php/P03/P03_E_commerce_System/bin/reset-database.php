<?php
declare(strict_types=1);
$root=dirname(__DIR__);require$root.'/vendor/autoload.php';\App\Env::load($root.'/.env');
$db=$_ENV['DB_PATH']??'var/shop.sqlite';$uploads=$_ENV['UPLOAD_DIR']??'var/product-images';\App\Seeder::reset($root,$db,$uploads);
echo "Database reset with deterministic fixtures.\n";
