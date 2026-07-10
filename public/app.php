<?php
declare(strict_types=1);

const INKWALL_ROOT = __DIR__;
const INKWALL_DATA_ROOT = __DIR__ . '/../data/inkwall';
const INKWALL_VISITOR_COOKIE = 'inkwall_visitor';
const INKWALL_REFERRER_COOKIE = 'inkwall_referrer';

function inkwall_now(): string {
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
}

function inkwall_secret(): string {
    static $secret = null;
    if (is_string($secret)) return $secret;
    if (!is_dir(INKWALL_DATA_ROOT) && !mkdir(INKWALL_DATA_ROOT, 0750, true) && !is_dir(INKWALL_DATA_ROOT)) {
        throw new RuntimeException('InkWall data directory is unavailable.');
    }
    $path = INKWALL_DATA_ROOT . '/secret.key';
    if (is_readable($path)) {
        $secret = trim((string)file_get_contents($path));
    } else {
        $secret = bin2hex(random_bytes(32));
        file_put_contents($path, $secret, LOCK_EX);
        @chmod($path, 0600);
    }
    return $secret;
}

function inkwall_cookie_options(int $expires): array {
    return [
        'expires' => $expires,
        'path' => '/inkwall/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function inkwall_visitor_hash(): string {
    $raw = trim((string)($_COOKIE[INKWALL_VISITOR_COOKIE] ?? ''));
    if (!preg_match('/^[a-f0-9]{32}$/', $raw)) {
        $raw = bin2hex(random_bytes(16));
        if (!headers_sent()) setcookie(INKWALL_VISITOR_COOKIE, $raw, inkwall_cookie_options(time() + 31536000));
        $_COOKIE[INKWALL_VISITOR_COOKIE] = $raw;
    }
    return hash_hmac('sha256', $raw, inkwall_secret());
}

function inkwall_referrer_host(): string {
    $stored = strtolower(trim((string)($_COOKIE[INKWALL_REFERRER_COOKIE] ?? '')));
    if ($stored !== '' && preg_match('/^(direct|[a-z0-9.-]{1,190})$/', $stored)) return $stored;

    $referrer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    $host = strtolower((string)(parse_url($referrer, PHP_URL_HOST) ?? ''));
    $ownHost = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
    $normalized = ($host === '' || $host === $ownHost || str_ends_with($host, '.angusu.de')) ? 'direct' : substr($host, 0, 190);
    if (!headers_sent()) setcookie(INKWALL_REFERRER_COOKIE, $normalized, inkwall_cookie_options(time() + 86400));
    $_COOKIE[INKWALL_REFERRER_COOKIE] = $normalized;
    return $normalized;
}

function inkwall_country(): string {
    foreach (['HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_EDGE_COUNTRY', 'HTTP_X_AZURE_CLIENTIPCOUNTRY'] as $key) {
        $value = strtoupper(trim((string)($_SERVER[$key] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $value)) return $value;
    }
    return 'unknown';
}

function inkwall_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    if (!is_dir(INKWALL_DATA_ROOT) && !mkdir(INKWALL_DATA_ROOT, 0750, true) && !is_dir(INKWALL_DATA_ROOT)) {
        throw new RuntimeException('InkWall data directory is unavailable.');
    }
    $pdo = new PDO('sqlite:' . INKWALL_DATA_ROOT . '/inkwall.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    inkwall_migrate($pdo);
    return $pdo;
}

function inkwall_migrate(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS inkwall_notes (
        id TEXT PRIMARY KEY,
        author_name TEXT NOT NULL,
        message_text TEXT NOT NULL,
        image_data BLOB NULL,
        image_mime TEXT NULL,
        image_name TEXT NULL,
        image_width INTEGER NOT NULL DEFAULT 0,
        image_height INTEGER NOT NULL DEFAULT 0,
        image_bytes INTEGER NOT NULL DEFAULT 0,
        image_inverted INTEGER NOT NULL DEFAULT 0,
        image_signature TEXT NULL,
        bindings_json TEXT NOT NULL DEFAULT '{}',
        layout_json TEXT NOT NULL DEFAULT '{}',
        show_favicons INTEGER NOT NULL DEFAULT 1,
        status TEXT NOT NULL DEFAULT 'published',
        moderation_flags TEXT NOT NULL DEFAULT '[]',
        visitor_hash TEXT NOT NULL,
        country TEXT NOT NULL,
        referrer_host TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inkwall_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        note_id TEXT NOT NULL,
        reporter_hash TEXT NOT NULL,
        reason TEXT NOT NULL,
        detail TEXT NOT NULL DEFAULT '',
        country TEXT NOT NULL,
        referrer_host TEXT NOT NULL,
        created_at TEXT NOT NULL,
        UNIQUE(note_id, reporter_hash),
        FOREIGN KEY(note_id) REFERENCES inkwall_notes(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inkwall_reactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        note_id TEXT NOT NULL,
        reactor_hash TEXT NOT NULL,
        emoji TEXT NOT NULL,
        created_at TEXT NOT NULL,
        UNIQUE(note_id, reactor_hash, emoji),
        FOREIGN KEY(note_id) REFERENCES inkwall_notes(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inkwall_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        visitor_hash TEXT NOT NULL,
        event_type TEXT NOT NULL,
        note_id TEXT NULL,
        country TEXT NOT NULL,
        referrer_host TEXT NOT NULL,
        meta_json TEXT NOT NULL DEFAULT '{}',
        created_at TEXT NOT NULL
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inkwall_notes_status_created ON inkwall_notes(status, created_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inkwall_events_created ON inkwall_events(created_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inkwall_events_visitor ON inkwall_events(visitor_hash, created_at DESC)');
    try { $pdo->exec("ALTER TABLE inkwall_notes ADD COLUMN layout_json TEXT NOT NULL DEFAULT '{}'"); } catch (Throwable) { /* Already migrated. */ }
}

function inkwall_layout(mixed $input): array {
    $input = is_array($input) ? $input : [];
    $align = in_array(($input['align'] ?? ''), ['left', 'center', 'right'], true) ? $input['align'] : 'left';
    $media = in_array(($input['media'] ?? ''), ['top', 'left', 'right'], true) ? $input['media'] : 'left';
    $texture = in_array(($input['texture'] ?? ''), ['dots', 'clean'], true) ? $input['texture'] : 'dots';
    $fontSize = max(24, min(42, (int)($input['fontSize'] ?? 32)));
    $bold = array_key_exists('bold', $input) ? (bool)$input['bold'] : true;
    $radiusMode = ($input['radiusMode'] ?? '') === 'custom' ? 'custom' : 'all';
    $radiusAll = max(0, min(42, (int)($input['radiusAll'] ?? 0)));
    $rawRadii = is_array($input['radii'] ?? null) ? $input['radii'] : [];
    $radii = [
        'tl' => max(0, min(42, (int)($rawRadii['tl'] ?? $radiusAll))),
        'tr' => max(0, min(42, (int)($rawRadii['tr'] ?? $radiusAll))),
        'br' => max(0, min(42, (int)($rawRadii['br'] ?? $radiusAll))),
        'bl' => max(0, min(42, (int)($rawRadii['bl'] ?? $radiusAll))),
    ];
    if ($radiusMode === 'all') $radii = ['tl' => $radiusAll, 'tr' => $radiusAll, 'br' => $radiusAll, 'bl' => $radiusAll];
    return [
        'align' => $align, 'media' => $media, 'texture' => $texture,
        'fontSize' => $fontSize, 'bold' => $bold,
        'radiusMode' => $radiusMode, 'radiusAll' => $radiusAll, 'radii' => $radii,
    ];
}

function inkwall_event(string $type, ?string $noteId = null, array $meta = []): void {
    $allowed = ['view', 'archive', 'publish', 'image_publish', 'reaction', 'report'];
    if (!in_array($type, $allowed, true)) return;
    $stmt = inkwall_db()->prepare('INSERT INTO inkwall_events (visitor_hash, event_type, note_id, country, referrer_host, meta_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        inkwall_visitor_hash(), $type, $noteId, inkwall_country(), inkwall_referrer_host(),
        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), inkwall_now(),
    ]);
}

function inkwall_begin_public_request(string $event): void {
    inkwall_referrer_host();
    inkwall_visitor_hash();
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') inkwall_event($event);
}

function inkwall_json(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function inkwall_body(): array {
    $raw = (string)file_get_contents('php://input');
    if (strlen($raw) > 900000) inkwall_json(['error' => 'Payload too large.'], 413);
    $data = json_decode($raw, true);
    if (!is_array($data)) inkwall_json(['error' => 'Invalid JSON body.'], 400);
    return $data;
}

function inkwall_clean_text(mixed $value, int $max): string {
    $text = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$value) ?? '');
    return mb_substr($text, 0, $max);
}

function inkwall_moderation(string $name, string $message): array {
    $flags = [];
    $combined = mb_strtolower($name . ' ' . $message);
    $patterns = [
        'threat' => '/\b(kill|murder|shoot|bomb|töte|toten|ermorde|erschieß)\b/ui',
        'hate' => '/\b(nigger|kike|faggot|judensau)\b/ui',
        'privacy' => '/\b(?:\d[ -]*?){13,19}\b/u',
        'spam' => '/(?:https?:\/\/){3,}/ui',
    ];
    foreach ($patterns as $flag => $pattern) if (preg_match($pattern, $combined)) $flags[] = $flag;
    return $flags;
}

function inkwall_rate_limit(string $action, int $limit, int $windowSeconds): void {
    $cutoff = (new DateTimeImmutable("-{$windowSeconds} seconds", new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
    $stmt = inkwall_db()->prepare('SELECT COUNT(*) FROM inkwall_events WHERE visitor_hash = ? AND event_type = ? AND created_at >= ?');
    $stmt->execute([inkwall_visitor_hash(), $action, $cutoff]);
    if ((int)$stmt->fetchColumn() >= $limit) inkwall_json(['error' => 'Please wait before trying again.'], 429);
}

function inkwall_note_row(string $id): ?array {
    $stmt = inkwall_db()->prepare("SELECT * FROM inkwall_notes WHERE id = ? AND status = 'published'");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function inkwall_reaction_summary(string $noteId, string $visitorHash): array {
    $stmt = inkwall_db()->prepare('SELECT emoji, COUNT(*) AS count, MAX(CASE WHEN reactor_hash = ? THEN 1 ELSE 0 END) AS reacted FROM inkwall_reactions WHERE note_id = ? GROUP BY emoji ORDER BY count DESC');
    $stmt->execute([$visitorHash, $noteId]);
    return array_map(static fn(array $row): array => ['emoji' => $row['emoji'], 'count' => (int)$row['count'], 'reacted' => (bool)$row['reacted']], $stmt->fetchAll());
}

function inkwall_public_note(array $row, string $visitorHash): array {
    $image = null;
    if ((int)$row['image_bytes'] > 0) {
        $image = [
            'src' => '/inkwall/media.php?id=' . rawurlencode($row['id']),
            'width' => (int)$row['image_width'], 'height' => (int)$row['image_height'],
            'bytes' => (int)$row['image_bytes'], 'name' => $row['image_name'] ?: 'image',
            'mime' => $row['image_mime'] ?: 'image/webp',
            'inverted' => (bool)$row['image_inverted'], 'signature' => $row['image_signature'] ?: '',
        ];
    }
    return [
        'id' => $row['id'], 'name' => $row['author_name'], 'message' => $row['message_text'],
        'image' => $image, 'bindings' => json_decode($row['bindings_json'], true) ?: [],
        'layout' => inkwall_layout(json_decode((string)($row['layout_json'] ?? '{}'), true)),
        'showFavicons' => (bool)$row['show_favicons'], 'reportable' => true, 'prepared' => false,
        'reactions' => inkwall_reaction_summary($row['id'], $visitorHash), 'createdAt' => $row['created_at'],
    ];
}
