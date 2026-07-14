<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$jobDir = (string)($argv[1] ?? '');
$executable = trim((string)getenv('INKWALL_CONTEXTBRIDGE_EXE'));
$config = trim((string)getenv('INKWALL_CONTEXTBRIDGE_CONFIG'));

if ($jobDir === '' || !is_dir($jobDir)) {
    fwrite(STDERR, "Usage: php private-review-contextbridge.php /path/to/job\n");
    exit(1);
}

if ($executable === '' || !is_file($executable) || $config === '' || !is_file($config)) {
    echo fallback_decision('contextbridge_not_configured');
    exit(0);
}

$pipes = [];
$process = proc_open(
    [$executable, 'review', '--config', $config, '--job-dir', $jobDir],
    [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ],
    $pipes,
    dirname($executable),
    null,
    ['bypass_shell' => true]
);

if (!is_resource($process)) {
    echo fallback_decision('contextbridge_start_failed');
    exit(0);
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$status = proc_close($process);

$decision = is_string($stdout) ? json_decode(trim($stdout), true) : null;
if ($status !== 0 || !is_array($decision)) {
    if (is_string($stderr) && trim($stderr) !== '') fwrite(STDERR, trim($stderr) . "\n");
    echo fallback_decision('contextbridge_invalid_response');
    exit(0);
}

echo json_encode($decision, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

function fallback_decision(string $flag): string {
    return json_encode([
        'verdict' => 'review',
        'flags' => [$flag],
        'confidence' => 0.4,
        'model' => 'contextbridge',
    ], JSON_UNESCAPED_SLASHES) . "\n";
}
