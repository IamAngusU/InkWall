<?php
declare(strict_types=1);

$secret = (string)getenv('INKWALL_PRIVATE_REVIEW_SECRET');
$encryptionKey = (string)getenv('INKWALL_PRIVATE_REVIEW_ENCRYPTION_KEY');
$root = (string)(getenv('INKWALL_PRIVATE_REVIEW_DIR') ?: (__DIR__ . '/../data/private-review-inbox'));
$defaultVerdict = strtolower((string)(getenv('INKWALL_PRIVATE_REVIEW_DEFAULT') ?: 'review'));
$maxSkew = max(30, (int)(getenv('INKWALL_PRIVATE_REVIEW_MAX_SKEW') ?: 300));

function respond(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit;
}

function header_value(string $name): string {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    respond(['error' => 'POST required'], 405);
}
if ($secret === '') respond(['error' => 'Receiver secret missing'], 500);

$raw = (string)file_get_contents('php://input');
$timestamp = header_value('X-InkWall-Timestamp');
$signature = header_value('X-InkWall-Signature');
if (!preg_match('/^\d{10}$/', $timestamp) || abs(time() - (int)$timestamp) > $maxSkew) {
    respond(['error' => 'Invalid timestamp'], 401);
}
if (!str_starts_with($signature, 'sha256=')) respond(['error' => 'Invalid signature'], 401);
$expected = 'sha256=' . base64_encode(hash_hmac('sha256', $timestamp . '.' . $raw, $secret, true));
if (!hash_equals($expected, $signature)) respond(['error' => 'Invalid signature'], 401);

$payload = json_decode($raw, true);
if (!is_array($payload)) respond(['error' => 'Invalid JSON'], 400);
if (!empty($payload['encrypted'])) {
    $payload = decrypt_payload($payload, $encryptionKey !== '' ? $encryptionKey : $secret);
}

$noteId = preg_replace('/[^a-z0-9-]+/i', '_', (string)($payload['note_id'] ?? 'unknown')) ?: 'unknown';
$stamp = gmdate('Ymd-His');
$jobDir = rtrim($root, '/\\') . '/' . $stamp . '-' . substr($noteId, 0, 48);
if (!is_dir($jobDir) && !mkdir($jobDir, 0700, true) && !is_dir($jobDir)) {
    respond(['error' => 'Cannot create job directory'], 500);
}

file_put_contents($jobDir . '/payload.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", LOCK_EX);
$content = is_array($payload['content'] ?? null) ? $payload['content'] : [];
file_put_contents($jobDir . '/name.txt', (string)($content['name'] ?? '') . "\n", LOCK_EX);
file_put_contents($jobDir . '/message.txt', (string)($content['message'] ?? '') . "\n", LOCK_EX);
if (is_array($content['image'] ?? null)) {
    $image = $content['image'];
    $data = base64_decode((string)($image['base64'] ?? ''), true);
    $mime = strtolower((string)($image['mime'] ?? ''));
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => 'bin',
    };
    if (is_string($data) && $data !== '') file_put_contents($jobDir . '/image.' . $ext, $data, LOCK_EX);
}

$decision = null;
$command = trim((string)(getenv('INKWALL_PRIVATE_REVIEW_COMMAND') ?: ''));
if ($command !== '') {
    $output = [];
    $status = 0;
    exec($command . ' ' . escapeshellarg($jobDir), $output, $status);
    $decoded = json_decode(implode("\n", $output), true);
    if ($status === 0 && is_array($decoded)) $decision = $decoded;
}

if (!is_array($decision)) {
    $decision = [
        'verdict' => in_array($defaultVerdict, ['allow', 'review'], true) ? $defaultVerdict : 'review',
        'flags' => $defaultVerdict === 'review' ? ['private_review_pending'] : [],
        'confidence' => 0.5,
        'model' => 'private-review',
    ];
}

$verdict = strtolower((string)($decision['verdict'] ?? 'review'));
$flags = is_array($decision['flags'] ?? null) ? array_values(array_map('strval', $decision['flags'])) : [];
$response = [
    'verdict' => in_array($verdict, ['allow', 'review'], true) ? $verdict : 'review',
    'flags' => array_values(array_slice($flags, 0, 20)),
    'confidence' => isset($decision['confidence']) && is_numeric($decision['confidence']) ? max(0.0, min(1.0, (float)$decision['confidence'])) : 0.5,
    'model' => substr((string)($decision['model'] ?? 'private-review'), 0, 80),
    'stored_at' => $jobDir,
];
file_put_contents($jobDir . '/decision.json', json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", LOCK_EX);
respond($response);

function decrypt_payload(array $envelope, string $rawKey): array {
    if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
        respond(['error' => 'Sodium unavailable'], 500);
    }
    if ((string)($envelope['alg'] ?? '') !== 'xchacha20poly1305') respond(['error' => 'Unsupported encryption'], 400);
    $nonce = base64_decode((string)($envelope['nonce'] ?? ''), true);
    $cipher = base64_decode((string)($envelope['ciphertext'] ?? ''), true);
    if (!is_string($nonce) || !is_string($cipher)) respond(['error' => 'Invalid encrypted payload'], 400);
    $key = derive_key($rawKey);
    if ($key === null) respond(['error' => 'Encryption key missing'], 500);
    $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, 'inkwall-remote-review-v1', $nonce, $key);
    if (!is_string($plain)) respond(['error' => 'Cannot decrypt payload'], 401);
    $payload = json_decode($plain, true);
    if (!is_array($payload)) respond(['error' => 'Invalid decrypted JSON'], 400);
    return $payload;
}

function derive_key(string $raw): ?string {
    if ($raw === '') return null;
    if (str_starts_with($raw, 'base64:')) {
        $decoded = base64_decode(substr($raw, 7), true);
        if (is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) return $decoded;
    }
    return hash('sha256', $raw, true);
}
