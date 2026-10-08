<?php
declare(strict_types=1);

use Tests\Functional\FunctionalTestCase;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$files = glob($root . '/tests/Functional/CONF*Test.php') ?: [];
sort($files);
$failures = [];
$assertions = 0;

foreach ($files as $file) {
    $case = new FunctionalTestCase($root);
    $name = basename($file, '.php');
    try {
        $test = require $file;
        if (!$test instanceof Closure) {
            throw new RuntimeException($name . ' must return a Closure.');
        }
        $test($case);
        echo "PASS {$name} ({$case->assertions()} assertions)\n";
    } catch (Throwable $error) {
        $failures[] = [$name, $error->getMessage()];
        echo "FAIL {$name}: {$error->getMessage()}\n";
    } finally {
        $assertions += $case->assertions();
        $case->close();
    }
}

echo "\n" . count($files) . ' use-case files, ' . $assertions . ' assertions, ' . count($failures) . " failures.\n";
exit($failures ? 1 : 0);
