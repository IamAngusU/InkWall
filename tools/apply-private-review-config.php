<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$options = getopt('', ['env:', 'input:']);
$envPath = (string)($options['env'] ?? '');
$inputPath = (string)($options['input'] ?? '');
if ($envPath === '' || $inputPath === '' || !is_file($inputPath)) {
    fwrite(STDERR, "Usage: php apply-private-review-config.php --env=/path/.env --input=/path/pair.env\n");
    exit(1);
}

$allowed = [
    'INKWALL_AI_MODERATION',
    'INKWALL_AI_CLOUD_ENABLED',
    'INKWALL_AI_TEXT_CLOUD_ENABLED',
    'INKWALL_AI_IMAGE_CLOUD_ENABLED',
    'INKWALL_AI_PROVIDER',
    'INKWALL_AI_TEXT_PROVIDER',
    'INKWALL_AI_TEXT_MODEL',
    'DEEPSEEK_API_KEY',
    'DEEPSEEK_BASE_URL',
    'INKWALL_DEEPSEEK_MODEL',
    'INKWALL_DEEPSEEK_BALANCE_GUARD',
    'INKWALL_DEEPSEEK_BALANCE_FAIL_CLOSED',
    'INKWALL_DEEPSEEK_FAIL_OPEN',
    'INKWALL_DEEPSEEK_DAILY_SPEND_LIMIT_USD',
    'INKWALL_AI_IMAGE_PROVIDER',
    'INKWALL_AI_IMAGE_MODEL',
    'OPENAI_API_KEY',
    'INKWALL_OPENAI_VISION_MODEL',
    'INKWALL_OPENAI_VISION_DETAIL',
    'INKWALL_OPENAI_VISION_FAIL_OPEN',
    'INKWALL_OPENAI_DAILY_SPEND_LIMIT_USD',
    'INKWALL_REMOTE_REVIEW',
    'INKWALL_REMOTE_REVIEW_ENDPOINT',
    'INKWALL_REMOTE_REVIEW_SECRET',
    'INKWALL_REMOTE_REVIEW_ENCRYPT',
    'INKWALL_REMOTE_REVIEW_ENCRYPTION_KEY',
    'INKWALL_REMOTE_REVIEW_FAIL_OPEN',
    'INKWALL_REMOTE_REVIEW_SEND_TEXT',
    'INKWALL_REMOTE_REVIEW_SEND_IMAGE',
    'INKWALL_REMOTE_REVIEW_TIMEOUT_SECONDS',
];

$updates = [];
foreach (file($inputPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if ($line === '' || str_starts_with(ltrim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $key = trim($key);
    if (!in_array($key, $allowed, true)) {
        fwrite(STDERR, "Unsupported setting: {$key}\n");
        exit(1);
    }
    if (str_contains($value, "\n") || str_contains($value, "\r")) {
        fwrite(STDERR, "Invalid setting value.\n");
        exit(1);
    }
    $updates[$key] = $value;
}

if ($updates === []) {
    fwrite(STDERR, "No private review settings found.\n");
    exit(1);
}

$directory = dirname($envPath);
if (!is_dir($directory) || !is_writable($directory)) {
    fwrite(STDERR, "Environment directory is not writable: {$directory}\n");
    exit(1);
}

$lines = is_file($envPath) ? (file($envPath, FILE_IGNORE_NEW_LINES) ?: []) : [];
if (is_file($envPath)) {
    $backup = $envPath . '.backup.' . gmdate('Ymd-His');
    if (!copy($envPath, $backup)) {
        fwrite(STDERR, "Could not back up environment file.\n");
        exit(1);
    }
}

$seen = [];
foreach ($lines as &$line) {
    if (!str_contains($line, '=')) continue;
    [$key] = explode('=', $line, 2);
    $key = trim($key);
    if (!array_key_exists($key, $updates)) continue;
    $line = $key . '=' . $updates[$key];
    $seen[$key] = true;
}
unset($line);

foreach ($updates as $key => $value) {
    if (!isset($seen[$key])) $lines[] = $key . '=' . $value;
}

$content = implode("\n", $lines) . "\n";
if (file_put_contents($envPath, $content, LOCK_EX) === false) {
    fwrite(STDERR, "Could not update environment file.\n");
    exit(1);
}
@chmod($envPath, 0600);
fwrite(STDOUT, "Private review connection configured.\n");
