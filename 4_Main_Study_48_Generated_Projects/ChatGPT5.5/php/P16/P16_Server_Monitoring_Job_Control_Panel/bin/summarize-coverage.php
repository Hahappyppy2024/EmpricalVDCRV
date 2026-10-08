<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$input = $argv[1] ?? $root . '/var/coverage.json';
$payload = json_decode((string)file_get_contents($input), true, 512, JSON_THROW_ON_ERROR);
$totals = ['lineHit' => 0, 'lineTotal' => 0, 'branchHit' => 0, 'branchTotal' => 0, 'pathHit' => 0, 'pathTotal' => 0];
$files = [];
foreach (glob($root . '/src/*.php') ?: [] as $source) {
    $item = $payload['coverage'][$source] ?? ['lines' => [], 'functions' => []];
    $row = ['file' => 'src/' . basename($source), 'lineHit' => 0, 'lineTotal' => 0, 'branchHit' => 0, 'branchTotal' => 0, 'pathHit' => 0, 'pathTotal' => 0];
    foreach ($item['lines'] as $hit) {
        if ($hit !== -2) {
            $row['lineTotal']++;
        }
        if ($hit > 0) {
            $row['lineHit']++;
        }
    }
    foreach ($item['functions'] as $function) {
        $branches = array_values($function['branches'] ?? []);
        $paths = array_values($function['paths'] ?? []);
        $row['branchTotal'] += count($branches);
        $row['branchHit'] += count(array_filter($branches, fn(array $branch) => ($branch['hit'] ?? 0) > 0));
        $row['pathTotal'] += count($paths);
        $row['pathHit'] += count(array_filter($paths, fn(array $path) => ($path['hit'] ?? 0) > 0));
    }
    foreach (array_keys($totals) as $key) {
        $totals[$key] += $row[$key];
    }
    $files[] = $row;
}
$percent = fn(int $hit, int $total) => $total ? round($hit * 100 / $total, 2) : null;
$summary = [
    'project' => $payload['project'],
    'phpVersion' => $payload['php_version'],
    'xdebugVersion' => $payload['xdebug_version'],
    ...$totals,
    'linePercent' => $percent($totals['lineHit'], $totals['lineTotal']),
    'branchPercent' => $percent($totals['branchHit'], $totals['branchTotal']),
    'pathPercent' => $percent($totals['pathHit'], $totals['pathTotal']),
];
file_put_contents($root . '/var/coverage-summary.json', json_encode(['summary' => $summary, 'files' => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
if (($summary['branchPercent'] ?? 0) < 85) {
    exit(1);
}
