<?php
declare(strict_types=1);

const INKWALL_ROOT = __DIR__;
const INKWALL_DATA_ROOT = __DIR__ . '/../data/inkwall';
const INKWALL_VISITOR_COOKIE = 'inkwall_visitor';
const INKWALL_REFERRER_COOKIE = 'inkwall_referrer';
const INKWALL_VERSION = '0.2.0';

function inkwall_now(): string {
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
}

function inkwall_env(string $key, string $default = ''): string {
    static $env = null;
    if ($env === null) {
        $env = [];
        foreach ([__DIR__ . '/../.env', __DIR__ . '/.env'] as $path) {
            if (!is_readable($path)) continue;
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k); $v = trim($v);
                if ($v !== '' && (
                    ($v[0] === '"' && str_ends_with($v, '"')) ||
                    ($v[0] === "'" && str_ends_with($v, "'"))
                )) { $v = substr($v, 1, -1); }
                $env[$k] = $v;
                if (getenv($k) === false) putenv($k . '=' . $v);
            }
        }
    }
    $value = $env[$key] ?? getenv($key);
    return is_string($value) && $value !== '' ? $value : $default;
}

function inkwall_env_bool(string $key, bool $default = false): bool {
    $value = strtolower(inkwall_env($key, $default ? '1' : '0'));
    if (in_array($value, ['1', 'true', 'yes', 'on'], true)) return true;
    if (in_array($value, ['0', 'false', 'no', 'off', 'disabled'], true)) return false;
    return $default;
}

function inkwall_branding(): array {
    static $brand = null;
    if (is_array($brand)) return $brand;
    $brand = [
        'accent' => '#d7422f',
        'paper_texture' => 'dots',
        'theme' => 'light',
        'ad_badge' => true,
        'ad_badge_text' => 'ADS',
        'review_badge' => true,
        'review_badge_mode' => 'auto',
        'review_badge_text' => 'Reviewed automatically',
        'review_badge_model_prefix' => 'Approved by',
        'owner_name' => 'Angus Uelsmann',
        'profile_url' => 'https://github.com/IamAngusU',
        'repository_url' => 'https://github.com/IamAngusU/InkWall',
        'site_url' => 'https://angusu.de/inkwall',
        'site_label' => 'angusu.de/inkwall',
        'image_rendering' => 'ink',
    ];
    $json = trim(inkwall_env('INKWALL_BRANDING_JSON', ''));
    if ($json !== '') {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            foreach ($decoded as $key => $value) {
                if (array_key_exists((string)$key, $brand)) $brand[(string)$key] = $value;
            }
        }
    }
    $map = [
        'accent' => 'INKWALL_BRAND_ACCENT',
        'paper_texture' => 'INKWALL_BRAND_PAPER_TEXTURE',
        'theme' => 'INKWALL_BRAND_THEME',
        'ad_badge_text' => 'INKWALL_BRAND_AD_BADGE_TEXT',
        'review_badge_mode' => 'INKWALL_BRAND_REVIEW_BADGE_MODE',
        'review_badge_text' => 'INKWALL_BRAND_REVIEW_BADGE_TEXT',
        'review_badge_model_prefix' => 'INKWALL_BRAND_REVIEW_BADGE_MODEL_PREFIX',
        'owner_name' => 'INKWALL_BRAND_OWNER_NAME',
        'profile_url' => 'INKWALL_BRAND_PROFILE_URL',
        'repository_url' => 'INKWALL_BRAND_REPOSITORY_URL',
        'site_url' => 'INKWALL_BRAND_SITE_URL',
        'site_label' => 'INKWALL_BRAND_SITE_LABEL',
        'image_rendering' => 'INKWALL_BRAND_IMAGE_RENDERING',
    ];
    foreach ($map as $key => $envKey) {
        $value = inkwall_env($envKey, '');
        if ($value !== '') $brand[$key] = $value;
    }
    if (inkwall_env('INKWALL_BRAND_AD_BADGE', '') !== '') $brand['ad_badge'] = inkwall_env_bool('INKWALL_BRAND_AD_BADGE', true);
    if (inkwall_env('INKWALL_BRAND_REVIEW_BADGE', '') !== '') $brand['review_badge'] = inkwall_env_bool('INKWALL_BRAND_REVIEW_BADGE', true);
    if (!is_string($brand['accent']) || !preg_match('/^#[0-9a-f]{6}$/i', $brand['accent'])) $brand['accent'] = '#d7422f';
    $brand['paper_texture'] = in_array($brand['paper_texture'], ['dots', 'clean'], true) ? $brand['paper_texture'] : 'dots';
    $brand['theme'] = in_array($brand['theme'], ['light', 'dark'], true) ? $brand['theme'] : 'light';
    $brand['image_rendering'] = in_array($brand['image_rendering'], ['ink', 'natural'], true) ? $brand['image_rendering'] : 'ink';
    $brand['ad_badge'] = (bool)$brand['ad_badge'];
    $brand['review_badge'] = (bool)$brand['review_badge'];
    $brand['review_badge_mode'] = in_array($brand['review_badge_mode'], ['auto', 'model'], true) ? $brand['review_badge_mode'] : 'auto';
    $brand['ad_badge_text'] = mb_substr(preg_replace('/[^a-z0-9 ]/i', '', (string)$brand['ad_badge_text']) ?? 'ADS', 0, 10) ?: 'ADS';
    foreach (['review_badge_text', 'review_badge_model_prefix'] as $key) {
        $brand[$key] = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$brand[$key]) ?? ''), 0, 42);
    }
    if ($brand['review_badge_text'] === '') $brand['review_badge_text'] = 'Reviewed automatically';
    if ($brand['review_badge_model_prefix'] === '') $brand['review_badge_model_prefix'] = 'Approved by';
    foreach (['owner_name', 'profile_url', 'repository_url', 'site_url', 'site_label', 'image_rendering'] as $key) $brand[$key] = trim((string)$brand[$key]);
    return $brand;
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
        ai_verdict TEXT NOT NULL DEFAULT 'allow',
        ai_model TEXT NOT NULL DEFAULT '',
        ai_flags_json TEXT NOT NULL DEFAULT '[]',
        ai_scores_json TEXT NOT NULL DEFAULT '{}',
        ai_review_json TEXT NOT NULL DEFAULT '{}',
        ai_error TEXT NOT NULL DEFAULT '',
        ai_reviewed_at TEXT NULL,
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS inkwall_ai_balance_checks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        provider TEXT NOT NULL,
        currency TEXT NOT NULL,
        total_balance REAL NOT NULL,
        spent_since_previous REAL NOT NULL DEFAULT 0,
        is_available INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inkwall_notes_status_created ON inkwall_notes(status, created_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inkwall_events_created ON inkwall_events(created_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inkwall_events_visitor ON inkwall_events(visitor_hash, created_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_inkwall_ai_balance_provider_created ON inkwall_ai_balance_checks(provider, currency, created_at DESC)');
    try { $pdo->exec("ALTER TABLE inkwall_notes ADD COLUMN layout_json TEXT NOT NULL DEFAULT '{}'"); } catch (Throwable) { /* Already migrated. */ }
    try { $pdo->exec("ALTER TABLE inkwall_notes ADD COLUMN ai_verdict TEXT NOT NULL DEFAULT 'allow'"); } catch (Throwable) { /* Already migrated. */ }
    try { $pdo->exec("ALTER TABLE inkwall_notes ADD COLUMN ai_model TEXT NOT NULL DEFAULT ''"); } catch (Throwable) { /* Already migrated. */ }
    try { $pdo->exec("ALTER TABLE inkwall_notes ADD COLUMN ai_flags_json TEXT NOT NULL DEFAULT '[]'"); } catch (Throwable) { /* Already migrated. */ }
    try { $pdo->exec("ALTER TABLE inkwall_notes ADD COLUMN ai_scores_json TEXT NOT NULL DEFAULT '{}'"); } catch (Throwable) { /* Already migrated. */ }
    try { $pdo->exec("ALTER TABLE inkwall_notes ADD COLUMN ai_review_json TEXT NOT NULL DEFAULT '{}'"); } catch (Throwable) { /* Already migrated. */ }
    try { $pdo->exec("ALTER TABLE inkwall_notes ADD COLUMN ai_error TEXT NOT NULL DEFAULT ''"); } catch (Throwable) { /* Already migrated. */ }
    try { $pdo->exec("ALTER TABLE inkwall_notes ADD COLUMN ai_reviewed_at TEXT NULL"); } catch (Throwable) { /* Already migrated. */ }
}

function inkwall_layout(mixed $input): array {
    $input = is_array($input) ? $input : [];
    $brand = inkwall_branding();
    $align = in_array(($input['align'] ?? ''), ['left', 'center', 'right'], true) ? $input['align'] : 'left';
    $media = in_array(($input['media'] ?? ''), ['top', 'left', 'right'], true) ? $input['media'] : 'left';
    $texture = in_array(($input['texture'] ?? ''), ['dots', 'clean'], true) ? $input['texture'] : $brand['paper_texture'];
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
    $allowed = ['view', 'archive', 'publish', 'image_publish', 'review', 'reaction', 'report', 'ai_moderation'];
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
    $text = (string)$value;
    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($text, Normalizer::FORM_C);
        if (is_string($normalized)) $text = $normalized;
    }
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    $text = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FE00}-\x{FE0F}\x{E0100}-\x{E01EF}]/u', '', $text) ?? '';
    $text = preg_replace('/[\x{1F3FB}-\x{1F3FF}]/u', '', $text) ?? '';
    $text = trim($text);
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

function inkwall_ai_moderation_enabled(): bool {
    $mode = strtolower(inkwall_env('INKWALL_AI_MODERATION', 'auto'));
    if (in_array($mode, ['0', 'false', 'off', 'disabled'], true)) return false;
    if (in_array($mode, ['1', 'true', 'on', 'enabled'], true)) return true;
    return inkwall_ai_provider() !== 'local';
}

function inkwall_ai_provider(): string {
    $provider = strtolower(inkwall_env('INKWALL_AI_PROVIDER', 'auto'));
    return inkwall_ai_normalize_provider($provider);
}

function inkwall_ai_normalize_provider(string $provider): string {
    $provider = strtolower(trim($provider));
    $allowed = ['auto', 'local', 'manual', 'openai', 'openai_moderation', 'deepseek', 'ollama'];
    if (!in_array($provider, $allowed, true)) $provider = 'auto';
    if ($provider !== 'auto') return $provider;
    if (inkwall_env('OPENAI_API_KEY') !== '') return 'openai_moderation';
    if (inkwall_env('DEEPSEEK_API_KEY') !== '') return 'deepseek';
    if (inkwall_env('INKWALL_OLLAMA_MODEL') !== '' || inkwall_env('OLLAMA_MODEL') !== '') return 'ollama';
    return 'local';
}

function inkwall_ai_channel_provider(string $channel): string {
    return inkwall_ai_channel_config($channel)['provider'];
}

function inkwall_ai_channel_config(string $channel): array {
    $channel = $channel === 'image' ? 'image' : 'text';
    $envKey = $channel === 'image' ? 'INKWALL_AI_IMAGE_PROVIDER' : 'INKWALL_AI_TEXT_PROVIDER';
    $modelKey = $channel === 'image' ? 'INKWALL_AI_IMAGE_MODEL' : 'INKWALL_AI_TEXT_MODEL';
    $json = trim(inkwall_env('INKWALL_AI_CHANNELS_JSON', ''));
    $settings = [];
    if ($json !== '') {
        $decoded = json_decode($json, true);
        if (is_array($decoded) && is_array($decoded[$channel] ?? null)) $settings = $decoded[$channel];
    }
    $rawProvider = strtolower(trim((string)($settings['provider'] ?? inkwall_env($envKey, ''))));
    if ($channel === 'image' && in_array($rawProvider, ['0', 'false', 'off', 'disabled', 'none'], true)) $rawProvider = 'manual';
    $provider = $rawProvider !== '' ? inkwall_ai_normalize_provider($rawProvider) : inkwall_ai_provider();
    $model = trim((string)($settings['model'] ?? inkwall_env($modelKey, '')));
    if ($model === '') {
        $model = match ($provider) {
            'openai', 'openai_moderation' => inkwall_env('INKWALL_AI_MODERATION_MODEL', 'omni-moderation-latest'),
            'deepseek' => inkwall_env('INKWALL_DEEPSEEK_MODEL', inkwall_env('INKWALL_AI_MODERATION_MODEL', 'deepseek-v4-flash')),
            'ollama' => inkwall_env('INKWALL_OLLAMA_MODEL', inkwall_env('OLLAMA_MODEL', 'qwen3:latest')),
            'manual' => 'manual',
            default => 'local',
        };
    }
    return ['channel' => $channel, 'provider' => $provider, 'model' => $model];
}

function inkwall_ai_provider_supports_images(string $provider): bool {
    if (in_array($provider, ['openai', 'openai_moderation'], true)) return true;
    if ($provider === 'deepseek') return in_array(strtolower(inkwall_env('INKWALL_DEEPSEEK_SEND_IMAGES', '0')), ['1', 'true', 'yes', 'on'], true);
    return false;
}

function inkwall_ai_review_url(): string {
    $base = rtrim(inkwall_env('INKWALL_ADMIN_URL', 'https://cockpit.angusu.de/admin/inkwall.php'), '/');
    return str_contains($base, '?') ? $base . '&status=held' : $base . '?status=held';
}

function inkwall_public_url(string $path = ''): string {
    $brand = inkwall_branding();
    $base = rtrim(inkwall_env('INKWALL_PUBLIC_URL', $brand['site_url']), '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function inkwall_note_token(string $noteId, string $purpose = 'preview'): string {
    return hash_hmac('sha256', $purpose . ':' . $noteId, inkwall_secret());
}

function inkwall_verify_note_token(string $noteId, string $token, string $purpose = 'preview'): bool {
    return hash_equals(inkwall_note_token($noteId, $purpose), $token);
}

function inkwall_ai_moderation(string $name, string $message, ?string $imageMime, ?string $imageData): array {
    $localFlags = inkwall_moderation($name, $message);
    $result = [
        'verdict' => $localFlags ? 'review' : 'allow',
        'flags' => $localFlags,
        'scores' => [],
        'model' => 'local',
        'error' => '',
        'reviewed_at' => inkwall_now(),
        'review' => [
            'text' => inkwall_ai_review_step('text', 'local', 'local', $localFlags ? 'review' : 'allow', $localFlags, [], 0, ''),
        ],
    ];
    if (!inkwall_ai_moderation_enabled()) return $result;

    $hasImage = $imageData !== null && $imageMime !== null;
    $textConfig = inkwall_ai_channel_config('text');
    $imageConfig = $hasImage ? inkwall_ai_channel_config('image') : ['provider' => 'manual', 'model' => 'manual'];
    if ($hasImage && $imageConfig['provider'] === $textConfig['provider'] && inkwall_ai_provider_supports_images($textConfig['provider'])) {
        return inkwall_ai_channel_moderation('text', $textConfig, $name, $message, $imageMime, $imageData, $localFlags);
    }

    $textResult = inkwall_ai_channel_moderation('text', $textConfig, $name, $message, null, null, $localFlags);
    if (!$hasImage) return $textResult;

    if (in_array($imageConfig['provider'], ['local', 'manual'], true)) {
        $imageFlags = in_array(strtolower(inkwall_env('INKWALL_AI_ALLOW_UNCHECKED_IMAGES', '0')), ['1', 'true', 'yes', 'on'], true) ? [] : ['image_unchecked'];
        return inkwall_ai_merge_results($textResult, inkwall_ai_channel_manual_result('image', $imageConfig, $imageFlags));
    }
    if (!inkwall_ai_provider_supports_images($imageConfig['provider'])) {
        $imageFlags = in_array(strtolower(inkwall_env('INKWALL_AI_ALLOW_UNCHECKED_IMAGES', '0')), ['1', 'true', 'yes', 'on'], true) ? [] : ['image_unchecked'];
        return inkwall_ai_merge_results($textResult, inkwall_ai_channel_manual_result('image', $imageConfig, $imageFlags, 'no-vision'));
    }

    $imageResult = inkwall_ai_channel_moderation('image', $imageConfig, $name, $message, $imageMime, $imageData, []);
    return inkwall_ai_merge_results($textResult, $imageResult);
}

function inkwall_ai_channel_moderation(string $channel, array $config, string $name, string $message, ?string $imageMime, ?string $imageData, array $localFlags): array {
    $start = microtime(true);
    $provider = (string)($config['provider'] ?? 'local');
    $model = (string)($config['model'] ?? '');
    $result = inkwall_ai_provider_moderation($provider, $model, $name, $message, $imageMime, $imageData, $localFlags);
    $latency = (int)round((microtime(true) - $start) * 1000);
    $result['review'] = [
        $channel => inkwall_ai_review_step(
            $channel,
            $provider,
            (string)($result['model'] ?? $model),
            (string)($result['verdict'] ?? 'review'),
            $result['flags'] ?? [],
            is_array($result['scores'] ?? null) ? $result['scores'] : [],
            $latency,
            (string)($result['error'] ?? '')
        ),
    ];
    return $result;
}

function inkwall_ai_provider_moderation(string $provider, string $model, string $name, string $message, ?string $imageMime, ?string $imageData, array $localFlags): array {
    if (in_array($provider, ['local', 'manual'], true)) return inkwall_ai_base_result($localFlags, $model !== '' ? $model : $provider);
    $quota = inkwall_ai_quota($provider, $imageData !== null && $imageMime !== null);
    if (!$quota['allowed']) {
        return inkwall_ai_result($localFlags, ['ai_rate_limited'], ['quota' => $quota['reason']], $provider, 'AI moderation quota reached');
    }

    return match ($provider) {
        'openai', 'openai_moderation' => inkwall_ai_openai_moderation($name, $message, $imageMime, $imageData, $localFlags, $model),
        'deepseek' => inkwall_ai_chat_moderation('deepseek', $name, $message, $imageMime, $imageData, $localFlags, $model),
        'ollama' => inkwall_ai_chat_moderation('ollama', $name, $message, $imageMime, $imageData, $localFlags, $model),
        default => inkwall_ai_base_result($localFlags),
    };
}

function inkwall_ai_merge_results(array ...$results): array {
    $flags = []; $scores = []; $models = []; $errors = []; $reviewedAt = inkwall_now(); $review = [];
    foreach ($results as $index => $result) {
        foreach (($result['flags'] ?? []) as $flag) {
            if ($flag !== '') $flags[] = (string)$flag;
        }
        if (is_array($result['scores'] ?? null)) $scores['part_' . ($index + 1)] = $result['scores'];
        if (is_array($result['review'] ?? null)) $review = array_merge($review, $result['review']);
        $model = trim((string)($result['model'] ?? ''));
        if ($model !== '' && !in_array($model, $models, true)) $models[] = $model;
        $error = trim((string)($result['error'] ?? ''));
        if ($error !== '') $errors[] = $error;
        if (!empty($result['reviewed_at'])) $reviewedAt = (string)$result['reviewed_at'];
    }
    $flags = array_values(array_unique($flags));
    $actions = [];
    foreach ($flags as $flag) $actions[inkwall_ai_flag_key((string)$flag)] = inkwall_ai_flag_action((string)$flag);
    $scores['actions'] = $actions;
    return [
        'verdict' => inkwall_ai_verdict_from_flags($flags),
        'flags' => $flags,
        'scores' => $scores,
        'model' => implode(' + ', $models),
        'error' => mb_substr(implode(' | ', $errors), 0, 500),
        'reviewed_at' => $reviewedAt,
        'review' => $review,
    ];
}

function inkwall_ai_channel_manual_result(string $channel, array $config, array $flags, string $reason = ''): array {
    $provider = (string)($config['provider'] ?? 'manual');
    $model = (string)($config['model'] ?? $provider);
    $result = inkwall_ai_result([], $flags, $reason !== '' ? ['reason' => $reason] : [], $model);
    $result['review'] = [
        $channel => inkwall_ai_review_step($channel, $provider, $model, (string)$result['verdict'], $result['flags'], $result['scores'], 0, ''),
    ];
    return $result;
}

function inkwall_ai_confidence(array $scores, array $flags, string $decision): float {
    if (isset($scores['confidence']) && is_numeric($scores['confidence'])) return max(0.0, min(1.0, (float)$scores['confidence']));
    $values = [];
    foreach ($scores as $value) {
        if (is_numeric($value)) $values[] = (float)$value;
    }
    if ($values) {
        $max = max($values);
        return max(0.0, min(1.0, $flags ? $max : 1.0 - $max));
    }
    return $decision === 'allow' && !$flags ? 1.0 : 0.75;
}

function inkwall_ai_review_step(string $channel, string $provider, string $model, string $decision, array $flags, array $scores, int $latencyMs, string $error): array {
    $flags = array_values(array_unique(array_map(static fn($flag): string => inkwall_ai_flag_key((string)$flag), $flags)));
    $flags = array_values(array_filter($flags, static fn(string $flag): bool => $flag !== ''));
    $step = [
        'provider' => $provider,
        'model' => $model,
        'decision' => in_array($decision, ['allow', 'review', 'reject'], true) ? $decision : 'review',
        'flags' => $flags,
        'confidence' => inkwall_ai_confidence($scores, $flags, $decision),
        'latency_ms' => max(0, $latencyMs),
    ];
    if ($error !== '') $step['error'] = mb_substr($error, 0, 180);
    return $step;
}

function inkwall_ai_base_result(array $localFlags, string $model = 'local', string $error = ''): array {
    return [
        'verdict' => $localFlags ? 'review' : 'allow',
        'flags' => array_values(array_unique($localFlags)),
        'scores' => [],
        'model' => $model,
        'error' => $error,
        'reviewed_at' => inkwall_now(),
    ];
}

function inkwall_ai_passive_flags(): array {
    $reviewUncheckedImages = in_array(strtolower(inkwall_env('INKWALL_AI_REVIEW_UNCHECKED_IMAGES', '0')), ['1', 'true', 'yes', 'on'], true);
    return $reviewUncheckedImages ? [] : ['image_unchecked'];
}

function inkwall_ai_flag_key(string $flag): string {
    $key = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($flag))) ?? '';
    $key = trim($key, '_');
    return match ($key) {
        'copyright', 'ip' => 'intellectual_property',
        'nudity', 'sexual', 'sexual_content', 'sexual_minors' => 'sexual_content',
        'self_harm', 'selfharm' => 'self_harm',
        'harassment_threatening' => 'harassment',
        default => $key,
    };
}

function inkwall_ai_flag_policy(): array {
    static $policy = null;
    if (is_array($policy)) return $policy;
    $policy = [
        'advertising' => 'hold',
        'harassment' => 'hold',
        'hate' => 'hold',
        'violence' => 'hold',
        'threat' => 'hold',
        'self_harm' => 'hold',
        'privacy' => 'hold',
        'doxxing' => 'hold',
        'spam' => 'hold',
        'scam' => 'hold',
        'impersonation' => 'hold',
        'intellectual_property' => 'hold',
        'sexual_content' => 'hold',
        'image_unchecked' => in_array('image_unchecked', inkwall_ai_passive_flags(), true) ? 'allow' : 'hold',
        'ai_unavailable' => 'hold',
        'ai_rate_limited' => 'hold',
        'ai_unparseable' => 'hold',
        'ai_review' => 'hold',
    ];

    $json = trim(inkwall_env('INKWALL_AI_FLAG_POLICY_JSON', ''));
    if ($json !== '') {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            foreach ($decoded as $flag => $action) {
                $key = inkwall_ai_flag_key((string)$flag);
                $value = strtolower((string)$action);
                if ($key !== '' && in_array($value, ['allow', 'hold', 'reject'], true)) $policy[$key] = $value;
            }
        }
    }

    foreach (array_keys($policy) as $flag) {
        $envKey = 'INKWALL_AI_FLAG_' . strtoupper($flag);
        $value = strtolower(inkwall_env($envKey, ''));
        if (in_array($value, ['allow', 'hold', 'reject'], true)) $policy[$flag] = $value;
    }
    return $policy;
}

function inkwall_ai_flag_action(string $flag): string {
    $key = inkwall_ai_flag_key($flag);
    $policy = inkwall_ai_flag_policy();
    return $policy[$key] ?? 'hold';
}

function inkwall_ai_verdict_from_flags(array $flags): string {
    $allowReject = in_array(strtolower(inkwall_env('INKWALL_AI_ALLOW_REJECT', '0')), ['1', 'true', 'yes', 'on'], true);
    $verdict = 'allow';
    foreach ($flags as $flag) {
        $action = inkwall_ai_flag_action((string)$flag);
        if ($action === 'reject') {
            if ($allowReject) return 'reject';
            $verdict = 'review';
        } elseif ($action === 'hold') {
            $verdict = 'review';
        }
    }
    return $verdict;
}

function inkwall_ai_result(array $localFlags, array $flags, array $scores, string $model, string $error = ''): array {
    $allFlags = array_values(array_unique(array_filter(array_merge($localFlags, $flags), static fn($value): bool => $value !== '')));
    $actions = [];
    foreach ($allFlags as $flag) $actions[inkwall_ai_flag_key((string)$flag)] = inkwall_ai_flag_action((string)$flag);
    return [
        'verdict' => inkwall_ai_verdict_from_flags($allFlags),
        'flags' => $allFlags,
        'scores' => array_merge($scores, ['actions' => $actions]),
        'model' => $model,
        'error' => mb_substr($error, 0, 500),
        'reviewed_at' => inkwall_now(),
    ];
}

function inkwall_ai_openai_moderation(string $name, string $message, ?string $imageMime, ?string $imageData, array $localFlags, string $modelOverride = ''): array {
    $apiKey = inkwall_env('OPENAI_API_KEY');
    $model = $modelOverride !== '' ? $modelOverride : inkwall_env('INKWALL_AI_MODERATION_MODEL', 'omni-moderation-latest');
    if ($apiKey === '') return inkwall_ai_result($localFlags, ['ai_unavailable'], [], $model, 'OpenAI API key missing');

    $input = [[
        'type' => 'text',
        'text' => "InkWall submission\nName: {$name}\nMessage: {$message}\n\nReturn safety signals only. Human decides any rejection.",
    ]];
    if ($imageData !== null && $imageMime !== null && in_array($imageMime, ['image/webp', 'image/png', 'image/jpeg'], true)) {
        $input[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $imageMime . ';base64,' . base64_encode($imageData)]];
    }

    $payload = json_encode(['model' => $model, 'input' => $input], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    try {
        $ch = curl_init('https://api.openai.com/v1/moderations');
        if ($ch === false) throw new RuntimeException('curl unavailable');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 8,
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if (!is_string($raw) || $raw === '' || $status < 200 || $status >= 300) {
            throw new RuntimeException($curlError !== '' ? $curlError : 'OpenAI moderation HTTP ' . $status);
        }
        $json = json_decode($raw, true);
        $first = is_array($json['results'] ?? null) ? ($json['results'][0] ?? []) : [];
        $categories = is_array($first['categories'] ?? null) ? $first['categories'] : [];
        $scores = is_array($first['category_scores'] ?? null) ? $first['category_scores'] : [];
        $aiFlags = [];
        foreach ($categories as $category => $flagged) {
            if ($flagged) $aiFlags[] = (string)$category;
        }
        $highScores = [];
        foreach ($scores as $category => $score) {
            if ((float)$score >= 0.72) $highScores[] = (string)$category;
        }
        return inkwall_ai_result($localFlags, array_values(array_unique(array_merge($aiFlags, $highScores))), $scores, (string)($json['model'] ?? $model));
    } catch (Throwable $error) {
        error_log('[inkwall_ai_moderation] ' . $error->getMessage());
        return inkwall_ai_result($localFlags, ['ai_unavailable'], [], $model, $error->getMessage());
    }
}

function inkwall_ai_env_int(string $key, int $default, int $min = 0, int $max = 100000): int {
    $raw = inkwall_env($key, (string)$default);
    if (!preg_match('/^\d+$/', $raw)) return $default;
    return max($min, min($max, (int)$raw));
}

function inkwall_ai_event_count(?string $visitor, int $windowSeconds): int {
    $since = (new DateTimeImmutable('-' . $windowSeconds . ' seconds', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
    if ($visitor === null) {
        $stmt = inkwall_db()->prepare("SELECT COUNT(*) FROM inkwall_events WHERE event_type = 'ai_moderation' AND created_at >= ?");
        $stmt->execute([$since]);
    } else {
        $stmt = inkwall_db()->prepare("SELECT COUNT(*) FROM inkwall_events WHERE event_type = 'ai_moderation' AND visitor_hash = ? AND created_at >= ?");
        $stmt->execute([$visitor, $since]);
    }
    return (int)$stmt->fetchColumn();
}

function inkwall_ai_quota(string $provider, bool $hasImage): array {
    $visitorLimit = inkwall_ai_env_int('INKWALL_AI_VISITOR_HOURLY_LIMIT', 5, 0);
    $globalHourLimit = inkwall_ai_env_int('INKWALL_AI_GLOBAL_HOURLY_LIMIT', 40, 0);
    $globalDayLimit = inkwall_ai_env_int('INKWALL_AI_GLOBAL_DAILY_LIMIT', $provider === 'deepseek' ? 0 : 160, 0);
    $imageHourLimit = inkwall_ai_env_int('INKWALL_AI_IMAGE_HOURLY_LIMIT', 12, 0);
    $visitor = inkwall_visitor_hash();

    if ($provider === 'deepseek') {
        $balance = inkwall_deepseek_balance_guard();
        if (!$balance['allowed']) return ['allowed' => false, 'reason' => $balance['reason']];
    }

    if ($visitorLimit > 0 && inkwall_ai_event_count($visitor, 3600) >= $visitorLimit) {
        return ['allowed' => false, 'reason' => 'visitor_hourly'];
    }
    if ($globalHourLimit > 0 && inkwall_ai_event_count(null, 3600) >= $globalHourLimit) {
        return ['allowed' => false, 'reason' => 'global_hourly'];
    }
    if ($globalDayLimit > 0 && inkwall_ai_event_count(null, 86400) >= $globalDayLimit) {
        return ['allowed' => false, 'reason' => 'global_daily'];
    }
    if ($hasImage && $imageHourLimit > 0) {
        $since = (new DateTimeImmutable('-3600 seconds', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
        $stmt = inkwall_db()->prepare("SELECT COUNT(*) FROM inkwall_events WHERE event_type = 'ai_moderation' AND created_at >= ? AND (meta_json LIKE '%\"has_image\":true%' OR meta_json LIKE '%\"has_image\":1%')");
        $stmt->execute([$since]);
        if ((int)$stmt->fetchColumn() >= $imageHourLimit) return ['allowed' => false, 'reason' => 'image_hourly'];
    }

    inkwall_event('ai_moderation', null, ['provider' => $provider, 'has_image' => $hasImage]);
    return ['allowed' => true, 'reason' => ''];
}

function inkwall_deepseek_balance_guard(): array {
    if (in_array(strtolower(inkwall_env('INKWALL_DEEPSEEK_BALANCE_GUARD', '1')), ['0', 'false', 'off', 'disabled'], true)) {
        return ['allowed' => true, 'reason' => ''];
    }
    $apiKey = inkwall_env('DEEPSEEK_API_KEY');
    if ($apiKey === '') return ['allowed' => false, 'reason' => 'deepseek_key_missing'];

    try {
        $status = inkwall_deepseek_balance_status(false);
        $minimum = (float)inkwall_env('INKWALL_DEEPSEEK_MIN_BALANCE_USD', '0.25');
        $dailyLimit = (float)inkwall_env('INKWALL_DEEPSEEK_DAILY_SPEND_LIMIT_USD', '0');
        $estimatedCall = (float)inkwall_env('INKWALL_DEEPSEEK_ESTIMATED_CALL_USD', '0.01');
        $spent24h = inkwall_ai_balance_spent('deepseek', 'USD', 86400);
        $allowed = $status['available'] && ($status['usd_balance'] === null || $status['usd_balance'] >= $minimum);
        $reason = !$status['available'] ? 'deepseek_unavailable' : (!$allowed ? 'deepseek_balance_low' : '');
        if ($allowed && $dailyLimit > 0 && ($spent24h + max(0.0, $estimatedCall)) > $dailyLimit) {
            $allowed = false;
            $reason = 'deepseek_daily_budget';
        }
        return $allowed ? ['allowed' => true, 'reason' => ''] : ['allowed' => false, 'reason' => $reason];
    } catch (Throwable $error) {
        error_log('[inkwall_deepseek_balance] ' . $error->getMessage());
        $failClosed = !in_array(strtolower(inkwall_env('INKWALL_DEEPSEEK_BALANCE_FAIL_CLOSED', '1')), ['0', 'false', 'off', 'disabled'], true);
        return ['allowed' => !$failClosed, 'reason' => $failClosed ? 'deepseek_balance_check_failed' : ''];
    }
}

function inkwall_deepseek_balance_status(bool $forceFresh = false): array {
    $cacheSeconds = inkwall_ai_env_int('INKWALL_DEEPSEEK_BALANCE_CACHE_SECONDS', 300, 0, 3600);
    $cachePath = INKWALL_DATA_ROOT . '/deepseek-balance.json';
    if (!$forceFresh && $cacheSeconds > 0 && is_readable($cachePath)) {
        $cached = json_decode((string)file_get_contents($cachePath), true);
        if (is_array($cached) && (time() - (int)($cached['checked_at'] ?? 0)) < $cacheSeconds) {
            return [
                'available' => !empty($cached['available'] ?? $cached['allowed'] ?? false),
                'usd_balance' => array_key_exists('usd_balance', $cached) ? (is_numeric($cached['usd_balance']) ? (float)$cached['usd_balance'] : null) : null,
            ];
        }
    }

    $apiKey = inkwall_env('DEEPSEEK_API_KEY');
    if ($apiKey === '') throw new RuntimeException('DeepSeek API key missing');
    $base = rtrim(inkwall_env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'), '/');
    $balance = inkwall_http_get_json($base . '/user/balance', ['Authorization: Bearer ' . $apiKey]);
    $available = !empty($balance['is_available']);
    $usdBalance = null;
    foreach (($balance['balance_infos'] ?? []) as $info) {
        if (is_array($info) && strtoupper((string)($info['currency'] ?? '')) === 'USD') {
            $usdBalance = (float)($info['total_balance'] ?? 0);
            inkwall_record_ai_balance('deepseek', 'USD', $usdBalance, $available);
            break;
        }
    }
    @file_put_contents($cachePath, json_encode(['checked_at' => time(), 'available' => $available, 'usd_balance' => $usdBalance], JSON_UNESCAPED_SLASHES), LOCK_EX);
    return ['available' => $available, 'usd_balance' => $usdBalance];
}

function inkwall_record_ai_balance(string $provider, string $currency, float $balance, bool $available): void {
    $previous = inkwall_db()->prepare('SELECT total_balance FROM inkwall_ai_balance_checks WHERE provider = ? AND currency = ? ORDER BY created_at DESC, id DESC LIMIT 1');
    $previous->execute([$provider, $currency]);
    $last = $previous->fetchColumn();
    $spent = is_numeric($last) ? max(0.0, (float)$last - $balance) : 0.0;
    $insert = inkwall_db()->prepare('INSERT INTO inkwall_ai_balance_checks (provider, currency, total_balance, spent_since_previous, is_available, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $insert->execute([$provider, $currency, $balance, $spent, $available ? 1 : 0, inkwall_now()]);
}

function inkwall_ai_balance_spent(string $provider, string $currency, int $windowSeconds): float {
    $since = (new DateTimeImmutable('-' . $windowSeconds . ' seconds', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
    $stmt = inkwall_db()->prepare('SELECT COALESCE(SUM(spent_since_previous), 0) FROM inkwall_ai_balance_checks WHERE provider = ? AND currency = ? AND created_at >= ?');
    $stmt->execute([$provider, $currency, $since]);
    return (float)$stmt->fetchColumn();
}

function inkwall_ai_chat_prompt(string $name, string $message, bool $hasImage, bool $canInspectImage): string {
    $imageLine = $hasImage
        ? ($canInspectImage ? 'The submission includes an image. Inspect it and include visual safety concerns in flags.' : 'The submission includes an image. If you cannot inspect images, set review for image_unchecked.')
        : 'No image is attached.';
    return "You are an InkWall moderation reviewer. You must never reject or block. Return only compact JSON with keys verdict, flags, and confidence. verdict must be allow or review. confidence must be a number from 0 to 1. Use review for harassment, hate, sexual content, violence, self-harm, doxxing/privacy, spam networks, scams, impersonation, intellectual property concerns, or uncertainty. Normal personal self-promotion, social handles, harmless links, and phrases like follow me are allowed unless they are spammy, deceptive, commercial advertising, or unsafe. {$imageLine}\n\nName: {$name}\nMessage: {$message}";
}

function inkwall_ai_parse_chat_json(string $raw): array {
    $raw = trim($raw);
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $raw, $match)) $raw = trim($match[1]);
    if (!str_starts_with($raw, '{') && preg_match('/\{.*\}/s', $raw, $match)) $raw = $match[0];
    $json = json_decode($raw, true);
    if (!is_array($json)) return ['flags' => ['ai_unparseable'], 'scores' => ['raw' => mb_substr($raw, 0, 500)]];
    $verdict = strtolower((string)($json['verdict'] ?? 'review'));
    $flags = is_array($json['flags'] ?? null) ? $json['flags'] : [];
    if ($verdict === 'review' && !$flags) $flags[] = 'ai_review';
    $scores = ['verdict' => $verdict];
    if (isset($json['confidence']) && is_numeric($json['confidence'])) $scores['confidence'] = max(0.0, min(1.0, (float)$json['confidence']));
    return [
        'flags' => array_values(array_unique(array_map(static fn($value): string => preg_replace('/[^a-z0-9_.-]+/i', '_', strtolower((string)$value)) ?: 'ai_review', $flags))),
        'scores' => $scores,
    ];
}

function inkwall_ai_chat_moderation(string $provider, string $name, string $message, ?string $imageMime, ?string $imageData, array $localFlags, string $modelOverride = ''): array {
    $hasImage = $imageData !== null && $imageMime !== null;
    $deepSeekVision = $provider === 'deepseek' && in_array(strtolower(inkwall_env('INKWALL_DEEPSEEK_SEND_IMAGES', '0')), ['1', 'true', 'yes', 'on'], true);
    $reviewUncheckedImages = !in_array(strtolower(inkwall_env('INKWALL_AI_ALLOW_UNCHECKED_IMAGES', '0')), ['1', 'true', 'yes', 'on'], true);
    $imageFlags = ($hasImage && !$deepSeekVision && $reviewUncheckedImages) ? ['image_unchecked'] : [];

    try {
        if ($provider === 'deepseek') {
            $apiKey = inkwall_env('DEEPSEEK_API_KEY');
            $model = $modelOverride !== '' ? $modelOverride : inkwall_env('INKWALL_DEEPSEEK_MODEL', inkwall_env('INKWALL_AI_MODERATION_MODEL', 'deepseek-v4-flash'));
            if ($apiKey === '') return inkwall_ai_result($localFlags, ['ai_unavailable'], [], $model, 'DeepSeek API key missing');
            $base = rtrim(inkwall_env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'), '/');
            $userContent = inkwall_ai_chat_prompt($name, $message, $hasImage, $deepSeekVision);
            if ($hasImage && $deepSeekVision && in_array($imageMime, ['image/webp', 'image/png', 'image/jpeg'], true)) {
                $userContent = [
                    ['type' => 'text', 'text' => $userContent],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $imageMime . ';base64,' . base64_encode((string)$imageData)]],
                ];
            }
            $payload = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Return JSON only. Never output block or reject.'],
                    ['role' => 'user', 'content' => $userContent],
                ],
                'temperature' => 0,
                'max_tokens' => 180,
                'response_format' => ['type' => 'json_object'],
            ];
            $raw = inkwall_http_json($base . '/chat/completions', $payload, ['Authorization: Bearer ' . $apiKey]);
            $content = (string)($raw['choices'][0]['message']['content'] ?? '');
            $parsed = inkwall_ai_parse_chat_json($content);
            inkwall_deepseek_track_balance_after_call();
            return inkwall_ai_result($localFlags, array_merge($parsed['flags'], $imageFlags), $parsed['scores'], $model);
        }

        $model = $modelOverride !== '' ? $modelOverride : inkwall_env('INKWALL_OLLAMA_MODEL', inkwall_env('OLLAMA_MODEL', 'qwen3:latest'));
        $base = rtrim(inkwall_env('INKWALL_OLLAMA_URL', inkwall_env('OLLAMA_HOST', 'http://127.0.0.1:11434')), '/');
        $payload = [
            'model' => $model,
            'stream' => false,
            'format' => 'json',
            'options' => ['temperature' => 0],
            'messages' => [
                ['role' => 'system', 'content' => 'Return JSON only. Never output block or reject.'],
                ['role' => 'user', 'content' => inkwall_ai_chat_prompt($name, $message, $hasImage, false)],
            ],
        ];
        $raw = inkwall_http_json($base . '/api/chat', $payload);
        $content = (string)($raw['message']['content'] ?? '');
        $parsed = inkwall_ai_parse_chat_json($content);
        return inkwall_ai_result($localFlags, array_merge($parsed['flags'], $imageFlags), $parsed['scores'], 'ollama:' . $model);
    } catch (Throwable $error) {
        error_log('[inkwall_ai_chat_moderation] ' . $provider . ': ' . $error->getMessage());
        return inkwall_ai_result($localFlags, ['ai_unavailable'], [], $provider, $error->getMessage());
    }
}

function inkwall_deepseek_track_balance_after_call(): void {
    if (!in_array(strtolower(inkwall_env('INKWALL_DEEPSEEK_TRACK_BALANCE_AFTER_CALL', '1')), ['1', 'true', 'yes', 'on'], true)) return;
    try {
        inkwall_deepseek_balance_status(true);
    } catch (Throwable $error) {
        error_log('[inkwall_deepseek_balance_after_call] ' . $error->getMessage());
    }
}

function inkwall_ai_telemetry_submit(array $moderation, string $status, bool $hasImage): void {
    if (!in_array(strtolower(inkwall_env('INKWALL_SHARE_AI_METADATA', '1')), ['1', 'true', 'yes', 'on'], true)) return;
    $endpoint = trim(inkwall_env('INKWALL_AI_METADATA_ENDPOINT', 'https://angusu.de/inkwall/telemetry.php'));
    if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL)) return;
    $review = is_array($moderation['review'] ?? null) ? $moderation['review'] : [];
    $channels = [];
    foreach (['text', 'image'] as $channel) {
        if (!is_array($review[$channel] ?? null)) continue;
        $step = $review[$channel];
        $channels[$channel] = [
            'provider' => mb_substr((string)($step['provider'] ?? ''), 0, 40),
            'model' => mb_substr((string)($step['model'] ?? ''), 0, 80),
            'decision' => mb_substr((string)($step['decision'] ?? ''), 0, 16),
            'flags' => array_values(array_slice(array_map('strval', is_array($step['flags'] ?? null) ? $step['flags'] : []), 0, 12)),
            'confidence' => isset($step['confidence']) && is_numeric($step['confidence']) ? max(0.0, min(1.0, (float)$step['confidence'])) : null,
            'latency_ms' => max(0, min(60000, (int)($step['latency_ms'] ?? 0))),
        ];
    }
    if (!$channels) return;
    $payload = [
        'project' => 'inkwall',
        'version' => INKWALL_VERSION,
        'status' => in_array($status, ['published', 'held', 'rejected'], true) ? $status : 'other',
        'has_image' => $hasImage,
        'channels' => $channels,
        'created_at' => inkwall_now(),
    ];
    $ch = curl_init($endpoint);
    if ($ch === false) return;
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT_MS => 300,
        CURLOPT_TIMEOUT_MS => 700,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function inkwall_http_json(string $url, array $payload, array $headers = []): array {
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('curl unavailable');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if (!is_string($raw) || $raw === '' || $status < 200 || $status >= 300) {
        throw new RuntimeException($error !== '' ? $error : 'HTTP ' . $status);
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) throw new RuntimeException('Invalid JSON response');
    return $json;
}

function inkwall_http_get_json(string $url, array $headers = []): array {
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('curl unavailable');
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 6,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if (!is_string($raw) || $raw === '' || $status < 200 || $status >= 300) {
        throw new RuntimeException($error !== '' ? $error : 'HTTP ' . $status);
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) throw new RuntimeException('Invalid JSON response');
    return $json;
}

function inkwall_notify_review(array $note, array $moderation): void {
    $to = inkwall_env('INKWALL_REVIEW_EMAIL', 'marv.uelsmann@gmail.com');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
    $flags = implode(', ', $moderation['flags'] ?: ['manual review']);
    $noteId = (string)$note['id'];
    $previewUrl = inkwall_public_url('latest.svg.php?id=' . rawurlencode($noteId) . '&token=' . inkwall_note_token($noteId) . '&theme=light');
    $reviewUrl = inkwall_ai_review_url();
    $subject = 'InkWall Review: ' . $flags;
    $safe = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<!doctype html><html><body style="margin:0;padding:24px;background:#f3f2ec;color:#171714;font-family:Arial,sans-serif">'
        . '<h1 style="font-size:20px;margin:0 0 12px">InkWall review needed</h1>'
        . '<p><strong>ID:</strong> ' . $safe($noteId) . '<br><strong>From:</strong> ' . $safe($note['author_name']) . '<br><strong>Flags:</strong> ' . $safe($flags) . '<br><strong>AI:</strong> ' . $safe($moderation['model'] ?? '') . '<br><strong>Image:</strong> ' . (((int)($note['image_bytes'] ?? 0) > 0) ? 'yes' : 'no') . '</p>'
        . '<p style="white-space:pre-wrap">' . $safe($note['message_text']) . '</p>'
        . '<p><img src="' . $safe($previewUrl) . '" alt="InkWall preview" style="display:block;width:100%;max-width:900px;height:auto;border:1px solid #171714;border-radius:10px"></p>'
        . '<p><a href="' . $safe($reviewUrl) . '">Open review queue</a> · <a href="' . $safe($previewUrl) . '">Open signed SVG preview</a></p>'
        . '</body></html>';
    $text = "InkWall review needed\n\nID: {$noteId}\nFrom: " . (string)$note['author_name'] . "\nFlags: {$flags}\nAI: " . (string)($moderation['model'] ?? '') . "\nImage: " . (((int)($note['image_bytes'] ?? 0) > 0) ? 'yes' : 'no') . "\n\n" . (string)$note['message_text'] . "\n\nPreview: {$previewUrl}\nReview: {$reviewUrl}\n";
    $from = inkwall_env('MAIL_FROM', inkwall_env('MAIL_USERNAME', 'hello@angusu.de'));
    $phpMailerPath = dirname(__DIR__) . '/PHPMailer-master/src/PHPMailer.php';
    if (is_readable($phpMailerPath)) {
        require_once dirname(__DIR__) . '/PHPMailer-master/src/Exception.php';
        require_once $phpMailerPath;
        require_once dirname(__DIR__) . '/PHPMailer-master/src/SMTP.php';
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = inkwall_env('MAIL_HOST', 'smtp.hostinger.com');
            $mail->SMTPAuth = true;
            $mail->Username = inkwall_env('MAIL_USERNAME', $from);
            $mail->Password = inkwall_env('MAIL_PASSWORD');
            $mail->SMTPSecure = inkwall_env('MAIL_ENCRYPTION', 'ssl') === 'tls' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = (int)inkwall_env('MAIL_PORT', '465');
            $mail->setFrom($from, inkwall_env('MAIL_FROM_NAME', 'InkWall'));
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text;
            $mail->send();
            return;
        } catch (Throwable $error) {
            error_log('[inkwall_notify_review] SMTP: ' . $error->getMessage());
        }
    }
    @mail($to, $subject, $html, "From: {$from}\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8");
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
