<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use App\Env;use App\Seeder;
$root=dirname(__DIR__);Env::load($root.'/.env');$path=$_ENV['DB_PATH']??getenv('DB_PATH')?:'var/conference.sqlite';$upload=$_ENV['UPLOAD_DIR']??getenv('UPLOAD_DIR')?:'var/manuscripts';$absolute=str_starts_with($path,'/')?$path:$root.'/'.$path;if(in_array('--if-missing',$argv,true)&&is_file($absolute)){echo"Database already exists: $absolute\n";exit(0);}Seeder::reset($root,$path,$upload);echo"Database reset and seeded: $absolute\n";
