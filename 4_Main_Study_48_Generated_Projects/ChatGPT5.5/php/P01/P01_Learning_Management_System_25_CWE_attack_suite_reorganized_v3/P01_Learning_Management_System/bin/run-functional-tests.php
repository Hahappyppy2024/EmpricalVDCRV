<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Tests\Functional\FunctionalTestCase;

$root=dirname(__DIR__);
$files=glob($root.'/exploit/Functional/LMS*.php')?:[];
sort($files,SORT_STRING);
$failed=0;$assertions=0;
foreach($files as $file){$test=new FunctionalTestCase($root);$name=basename($file,'.php');try{$scenario=require $file;$scenario($test);$assertions+=$test->assertions();echo "PASS {$name} ({$test->assertions()} assertions)\n";}catch(Throwable $e){$failed++;echo "FAIL {$name}: {$e->getMessage()}\n";}finally{$test->close();}}
echo sprintf("\n%d files, %d assertions, %d failures\n",count($files),$assertions,$failed);
exit($failed===0?0:1);
