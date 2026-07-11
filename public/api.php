<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$uriPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
$path = trim((string)preg_replace('~^/(?:inkwall/)?api(?:\.php)?/?~', '', $uriPath), '/');
$visitor = inkwall_visitor_hash();
inkwall_referrer_host();

if ($method === 'GET' && $path === 'messages') {
    $rows = inkwall_db()->query("SELECT * FROM inkwall_notes WHERE status = 'published' ORDER BY created_at DESC LIMIT 100")->fetchAll();
    inkwall_event('archive');
    inkwall_json(['messages' => array_map(static fn(array $row): array => inkwall_public_note($row, $visitor), $rows)]);
}

if ($method === 'POST' && $path === 'messages') {
    inkwall_rate_limit('publish', 5, 3600);
    $body = inkwall_body();
    if (!empty($body['website'])) inkwall_json(['error' => 'The note could not be accepted.'], 422);
    $name = inkwall_clean_text($body['name'] ?? '', 28) ?: 'Anonymous';
    $message = inkwall_clean_text($body['message'] ?? '', 120);
    if ($message === '') inkwall_json(['error' => 'Write a message first.'], 422);

    $id = preg_match('/^[a-f0-9-]{20,40}$/i', (string)($body['id'] ?? '')) ? (string)$body['id'] : bin2hex(random_bytes(16));
    $imageData = null; $imageMime = null; $imageName = null; $imageWidth = 0; $imageHeight = 0; $imageBytes = 0; $imageInverted = 0; $imageSignature = null;
    if (is_array($body['image'] ?? null)) {
        $image = $body['image'];
        if (!preg_match('~^data:image/(webp|png|jpeg);base64,([A-Za-z0-9+/=]+)$~', (string)($image['src'] ?? ''), $match)) inkwall_json(['error' => 'Only processed WebP, PNG or JPEG images are accepted.'], 422);
        $imageData = base64_decode($match[2], true);
        if ($imageData === false || strlen($imageData) > 480 * 1024) inkwall_json(['error' => 'The processed image is too large.'], 422);
        $info = @getimagesizefromstring($imageData);
        if (!$info || !in_array(($info['mime'] ?? ''), ['image/webp', 'image/png', 'image/jpeg'], true)) inkwall_json(['error' => 'The processed image is invalid.'], 422);
        $imageMime = (string)$info['mime']; $imageName = inkwall_clean_text($image['name'] ?? 'image', 96);
        $imageWidth = (int)$info[0]; $imageHeight = (int)$info[1]; $imageBytes = strlen($imageData);
        $imageInverted = !empty($image['inverted']) ? 1 : 0; $imageSignature = inkwall_clean_text($image['signature'] ?? '', 190);
    }
    $moderation = inkwall_ai_moderation($name, $message, $imageMime, $imageData);
    $flags = $moderation['flags'];
    $verdict = (string)($moderation['verdict'] ?? 'allow');
    $status = match ($verdict) {
        'reject' => 'rejected',
        'review' => 'held',
        default => 'published',
    };
    $bindings = is_array($body['bindings'] ?? null) ? $body['bindings'] : [];
    $layout = inkwall_layout($body['layout'] ?? []);
    $cleanBindings = [];
    foreach (array_slice($bindings, 0, 8, true) as $handle => $binding) {
        if (!preg_match('/^[a-z0-9_.-]{1,40}$/i', (string)$handle) || !is_array($binding)) continue;
        $url = (string)($binding['url'] ?? '');
        if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://'))) continue;
        $cleanBindings[(string)$handle] = ['platform' => inkwall_clean_text($binding['platform'] ?? '', 20), 'url' => substr($url, 0, 500)];
    }
    $now = inkwall_now();
    $stmt = inkwall_db()->prepare('INSERT INTO inkwall_notes (id, author_name, message_text, image_data, image_mime, image_name, image_width, image_height, image_bytes, image_inverted, image_signature, bindings_json, layout_json, show_favicons, status, moderation_flags, visitor_hash, country, referrer_host, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bindValue(1, $id); $stmt->bindValue(2, $name); $stmt->bindValue(3, $message);
    $stmt->bindValue(4, $imageData, $imageData === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
    $values = [$imageMime, $imageName, $imageWidth, $imageHeight, $imageBytes, $imageInverted, $imageSignature, json_encode($cleanBindings, JSON_UNESCAPED_SLASHES), json_encode($layout, JSON_UNESCAPED_SLASHES), !empty($body['showFavicons']) ? 1 : 0, $status, json_encode($flags), $visitor, inkwall_country(), inkwall_referrer_host(), $now, $now];
    foreach ($values as $index => $value) $stmt->bindValue($index + 5, $value);
    $stmt->execute();
    $ai = inkwall_db()->prepare('UPDATE inkwall_notes SET ai_verdict = ?, ai_model = ?, ai_flags_json = ?, ai_scores_json = ?, ai_review_json = ?, ai_error = ?, ai_reviewed_at = ? WHERE id = ?');
    $ai->execute([
        (string)($moderation['verdict'] ?? 'allow'),
        (string)($moderation['model'] ?? ''),
        json_encode($moderation['flags'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        json_encode($moderation['scores'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        json_encode($moderation['review'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        mb_substr((string)($moderation['error'] ?? ''), 0, 500),
        (string)($moderation['reviewed_at'] ?? $now),
        $id,
    ]);
    inkwall_ai_telemetry_submit($moderation, $status, $imageBytes > 0);
    if ($status === 'held') {
        $noteStmt = inkwall_db()->prepare('SELECT * FROM inkwall_notes WHERE id = ?');
        $noteStmt->execute([$id]); $note = $noteStmt->fetch();
        if (is_array($note)) inkwall_notify_review($note, $moderation);
        inkwall_event('review', $id, ['has_image' => $imageBytes > 0, 'flags' => $flags, 'ai_model' => (string)($moderation['model'] ?? '')]);
        inkwall_json(['status' => 'review', 'id' => $id, 'message' => 'This ink is waiting for manual review.'], 202);
    }
    if ($status === 'rejected') {
        inkwall_event('review', $id, ['has_image' => $imageBytes > 0, 'flags' => $flags, 'ai_model' => (string)($moderation['model'] ?? ''), 'status' => 'rejected']);
        inkwall_json(['status' => 'rejected', 'id' => $id, 'message' => 'This ink could not be accepted.'], 202);
    }
    inkwall_event('publish', $id, ['has_image' => $imageBytes > 0, 'ai_model' => (string)($moderation['model'] ?? '')]);
    if ($imageBytes > 0) inkwall_event('image_publish', $id, ['bytes' => $imageBytes]);
    inkwall_json(inkwall_public_note(inkwall_note_row($id), $visitor), 201);
}

if (preg_match('~^messages/([a-f0-9-]{20,40})/reports$~i', $path, $match) && $method === 'POST') {
    inkwall_rate_limit('report', 12, 3600);
    $noteId = $match[1];
    if (!inkwall_note_row($noteId)) inkwall_json(['error' => 'Note not found.'], 404);
    $body = inkwall_body();
    $reasons = ['spam' => 2, 'harassment' => 2, 'hate' => 1, 'threat' => 1, 'privacy' => 1, 'intellectual_property' => 1, 'impersonation' => 1, 'scam' => 1, 'other' => 2];
    $reason = (string)($body['reason'] ?? '');
    if (!isset($reasons[$reason])) inkwall_json(['error' => 'Invalid report reason.'], 422);
    $detail = inkwall_clean_text($body['detail'] ?? '', 240);
    try {
        $stmt = inkwall_db()->prepare('INSERT INTO inkwall_reports (note_id, reporter_hash, reason, detail, country, referrer_host, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$noteId, $visitor, $reason, $detail, inkwall_country(), inkwall_referrer_host(), inkwall_now()]);
    } catch (PDOException $error) {
        if (str_contains(strtolower($error->getMessage()), 'unique')) inkwall_json(['accepted' => false, 'duplicate' => true, 'hidden' => false]);
        throw $error;
    }
    $countStmt = inkwall_db()->prepare('SELECT COUNT(*) FROM inkwall_reports WHERE note_id = ? AND reason = ?');
    $countStmt->execute([$noteId, $reason]); $count = (int)$countStmt->fetchColumn();
    $hidden = $count >= $reasons[$reason];
    if ($hidden) {
        $hold = inkwall_db()->prepare("UPDATE inkwall_notes SET status = 'held', updated_at = ? WHERE id = ?");
        $hold->execute([inkwall_now(), $noteId]);
    }
    inkwall_event('report', $noteId, ['reason' => $reason, 'hidden' => $hidden]);
    inkwall_json(['accepted' => true, 'duplicate' => false, 'hidden' => $hidden, 'count' => $count]);
}

if (preg_match('~^messages/([a-f0-9-]{20,40})/reactions$~i', $path, $match) && $method === 'POST') {
    inkwall_rate_limit('reaction', 60, 3600);
    $noteId = $match[1];
    if (!inkwall_note_row($noteId)) inkwall_json(['error' => 'Note not found.'], 404);
    $body = inkwall_body(); $emoji = (string)($body['emoji'] ?? '');
    $allowed = ['❤️', '🔥', '👏', '💡', '😂', '🤝', '👀', '🚀'];
    if (!in_array($emoji, $allowed, true)) inkwall_json(['error' => 'Unsupported reaction.'], 422);
    $exists = inkwall_db()->prepare('SELECT id FROM inkwall_reactions WHERE note_id = ? AND reactor_hash = ? AND emoji = ?');
    $exists->execute([$noteId, $visitor, $emoji]); $reactionId = $exists->fetchColumn();
    if ($reactionId) {
        $delete = inkwall_db()->prepare('DELETE FROM inkwall_reactions WHERE id = ?'); $delete->execute([$reactionId]);
    } else {
        $insert = inkwall_db()->prepare('INSERT INTO inkwall_reactions (note_id, reactor_hash, emoji, created_at) VALUES (?, ?, ?, ?)'); $insert->execute([$noteId, $visitor, $emoji, inkwall_now()]);
    }
    inkwall_event('reaction', $noteId, ['emoji' => $emoji, 'active' => !$reactionId]);
    inkwall_json(['reactions' => inkwall_reaction_summary($noteId, $visitor)]);
}

inkwall_json(['error' => 'Endpoint not found.'], 404);
