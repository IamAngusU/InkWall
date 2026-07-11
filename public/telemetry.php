<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo '{"ok":false}';
    exit;
}

$raw = (string)file_get_contents('php://input');
if ($raw === '' || strlen($raw) > 8192) {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

$body = json_decode($raw, true);
if (!is_array($body) || (string)($body['project'] ?? '') !== 'inkwall') {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

$channels = is_array($body['channels'] ?? null) ? $body['channels'] : [];
$clean = [];
foreach (['text', 'image'] as $channel) {
    if (!is_array($channels[$channel] ?? null)) continue;
    $item = $channels[$channel];
    $flags = is_array($item['flags'] ?? null) ? array_values(array_slice(array_map('strval', $item['flags']), 0, 12)) : [];
    $clean[$channel] = [
        'provider' => mb_substr(preg_replace('/[^a-z0-9_.:-]+/i', '', (string)($item['provider'] ?? '')) ?? '', 0, 40),
        'model' => mb_substr(preg_replace('/[^a-z0-9_.:+-]+/i', '', (string)($item['model'] ?? '')) ?? '', 0, 80),
        'decision' => mb_substr(preg_replace('/[^a-z]+/i', '', (string)($item['decision'] ?? '')) ?? '', 0, 16),
        'flags' => array_values(array_filter(array_map(static fn(string $flag): string => inkwall_ai_flag_key($flag), $flags))),
        'confidence' => isset($item['confidence']) && is_numeric($item['confidence']) ? max(0.0, min(1.0, (float)$item['confidence'])) : null,
        'latency_ms' => max(0, min(60000, (int)($item['latency_ms'] ?? 0))),
    ];
}
if (!$clean) {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

$path = INKWALL_DATA_ROOT . '/telemetry.sqlite';
$pdo = new PDO('sqlite:' . $path, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("CREATE TABLE IF NOT EXISTS inkwall_telemetry (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    version TEXT NOT NULL,
    status TEXT NOT NULL,
    has_image INTEGER NOT NULL DEFAULT 0,
    channels_json TEXT NOT NULL,
    created_at TEXT NOT NULL
)");
$stmt = $pdo->prepare('INSERT INTO inkwall_telemetry (version, status, has_image, channels_json, created_at) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([
    mb_substr((string)($body['version'] ?? ''), 0, 24),
    in_array((string)($body['status'] ?? ''), ['published', 'held', 'rejected'], true) ? (string)$body['status'] : 'other',
    !empty($body['has_image']) ? 1 : 0,
    json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    inkwall_now(),
]);

echo '{"ok":true}';
