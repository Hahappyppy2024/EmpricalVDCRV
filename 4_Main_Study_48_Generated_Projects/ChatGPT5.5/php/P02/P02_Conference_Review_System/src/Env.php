<?php
declare(strict_types=1);
namespace App;
final class Env{public static function load(string $path):void{if(!is_file($path))return;foreach(file($path,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){$line=trim($line);if($line===''||str_starts_with($line,'#')||!str_contains($line,'='))continue;[$k,$v]=array_map('trim',explode('=',$line,2));if(getenv($k)!==false)continue;$v=trim($v,"\"'");$_ENV[$k]=$v;putenv("$k=$v");}}}
