<?php

declare(strict_types=1);

ini_set('memory_limit', '512M');

if (!function_exists('phpdbg_start_oplog') || !function_exists('phpdbg_end_oplog') || !function_exists('phpdbg_get_executable')) {
    throw new RuntimeException('tests/coverage.php must be run under phpdbg.');
}

ob_start();
phpdbg_start_oplog();
require __DIR__ . '/run.php';
$testOutput = ob_get_clean();
$executed = phpdbg_end_oplog();
$executable = phpdbg_get_executable();
$srcRoot = dirname(__DIR__) . '/src/';
$report = [];
$totalExecutable = 0;
$totalCovered = 0;

foreach ($executable as $file => $lines) {
    if (strpos($file, $srcRoot) !== 0) {
        continue;
    }

    $executableLines = array_map('intval', array_keys($lines));
    sort($executableLines);
    $executedLines = array_map('intval', array_keys($executed[$file] ?? []));
    sort($executedLines);

    $coveredLines = array_values(array_intersect($executableLines, $executedLines));
    $uncoveredLines = array_values(array_diff($executableLines, $executedLines));

    $coveredCount = count($coveredLines);
    $executableCount = count($executableLines);
    $coveragePct = $executableCount === 0 ? 100.0 : round(($coveredCount / $executableCount) * 100, 2);

    $totalExecutable += $executableCount;
    $totalCovered += $coveredCount;

    $report[] = [
        'file' => substr($file, strlen(dirname(__DIR__)) + 1),
        'coverage_pct' => $coveragePct,
        'covered' => $coveredCount,
        'total' => $executableCount,
        'uncovered_lines' => $uncoveredLines,
    ];
}

usort($report, static function (array $left, array $right): int {
    $byCoverage = $left['coverage_pct'] <=> $right['coverage_pct'];
    if ($byCoverage !== 0) {
        return $byCoverage;
    }

    return strcmp($left['file'], $right['file']);
});

$overallCoverage = $totalExecutable === 0 ? 100.0 : round(($totalCovered / $totalExecutable) * 100, 2);
$summaryLines = [];
$summaryLines[] = '# Coverage';
$summaryLines[] = '';
$summaryLines[] = sprintf('Overall: **%.2f%%** (%d/%d)', $overallCoverage, $totalCovered, $totalExecutable);
$summaryLines[] = '';
$summaryLines[] = '| File | Coverage | Covered |';
$summaryLines[] = '| --- | ---: | ---: |';

foreach ($report as $row) {
    $summaryLines[] = sprintf('| `%s` | %.2f%% | %d/%d |', $row['file'], $row['coverage_pct'], $row['covered'], $row['total']);
}

$summary = implode(PHP_EOL, $summaryLines) . PHP_EOL;

$jsonPath = getenv('COVERAGE_JSON');
if (is_string($jsonPath) && $jsonPath !== '') {
    file_put_contents($jsonPath, json_encode([
        'overall' => [
            'coverage_pct' => $overallCoverage,
            'covered' => $totalCovered,
            'total' => $totalExecutable,
        ],
        'files' => $report,
    ], JSON_PRETTY_PRINT));
}

$summaryPath = getenv('COVERAGE_SUMMARY');
if (is_string($summaryPath) && $summaryPath !== '') {
    file_put_contents($summaryPath, $summary);
}

echo $testOutput;
echo sprintf('Overall coverage: %.2f%% (%d/%d)%s', $overallCoverage, $totalCovered, $totalExecutable, PHP_EOL);

$minCoverage = getenv('COVERAGE_MIN');
if (is_string($minCoverage) && $minCoverage !== '') {
    $minimum = (float) $minCoverage;
    if ($overallCoverage < $minimum) {
        throw new RuntimeException(sprintf('Coverage %.2f%% is below the required %.2f%%.', $overallCoverage, $minimum));
    }
}
