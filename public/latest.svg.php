<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

$brand = inkwall_branding();
$theme = ($_GET['theme'] ?? $brand['theme']) === 'dark' ? 'dark' : 'light';
$requestedId = (string)($_GET['id'] ?? '');
$requestedToken = (string)($_GET['token'] ?? '');
$latestPublishedId = (string)(inkwall_db()->query("SELECT id FROM inkwall_notes WHERE status = 'published' ORDER BY created_at DESC LIMIT 1")->fetchColumn() ?: '');
if ($requestedId !== '' && preg_match('/^[a-f0-9-]{20,40}$/i', $requestedId)) {
    $stmt = inkwall_db()->prepare('SELECT * FROM inkwall_notes WHERE id = ? LIMIT 1');
    $stmt->execute([$requestedId]);
    $row = $stmt->fetch();
    if (is_array($row) && ($row['status'] ?? '') !== 'published' && !inkwall_verify_note_token($requestedId, $requestedToken)) {
        $row = false;
    }
} else {
    $row = inkwall_db()->query("SELECT * FROM inkwall_notes WHERE status = 'published' ORDER BY created_at DESC LIMIT 1")->fetch();
}
if (!is_array($row)) {
    $row = ['author_name' => $brand['owner_name'], 'message_text' => 'This surface is yours for a moment. Leave the next ink on my GitHub profile.', 'created_at' => inkwall_now(), 'image_data' => null, 'image_mime' => null];
}
$inkNumber = 0;
if (!empty($row['id'])) {
    if (($row['status'] ?? 'published') === 'published') {
        $stmt = inkwall_db()->prepare("SELECT COUNT(*) FROM inkwall_notes WHERE status = 'published' AND (created_at < ? OR (created_at = ? AND id <= ?))");
        $stmt->execute([(string)$row['created_at'], (string)$row['created_at'], (string)$row['id']]);
        $inkNumber = max(1, (int)$stmt->fetchColumn());
    } else {
        $stmt = inkwall_db()->prepare("SELECT COUNT(*) FROM inkwall_notes WHERE status = 'published' AND created_at < ?");
        $stmt->execute([(string)$row['created_at']]);
        $inkNumber = max(1, (int)$stmt->fetchColumn() + 1);
    }
}
function inkwall_svg_escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
function inkwall_svg_ink_text(string $value): string {
    $value = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FE00}-\x{FE0F}\x{E0100}-\x{E01EF}]/u', '', $value) ?? '';
    $value = preg_replace('/[\x{1F3FB}-\x{1F3FF}]/u', '', $value) ?? '';
    if (inkwall_env('INKWALL_SVG_EMOJI_STYLE', 'native') === 'mark') return preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '✶', $value) ?? $value;
    return $value;
}
function inkwall_svg_wrap_paragraph(string $text, int $limit): array {
    $words = preg_split('/\s+/u', trim($text)) ?: [];
    $lines = []; $line = '';
    foreach ($words as $word) {
        while (mb_strlen($word) > $limit) {
            if ($line !== '') { $lines[] = $line; $line = ''; }
            $lines[] = mb_substr($word, 0, $limit);
            $word = mb_substr($word, $limit);
        }
        if ($word === '') continue;
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if ($line !== '' && mb_strlen($candidate) > $limit) { $lines[] = $line; $line = $word; }
        else $line = $candidate;
    }
    if ($line !== '') $lines[] = $line;
    return $lines;
}
function inkwall_svg_lines(string $text, int $limit = 52, int $maxLines = 3): array {
    $all = [];
    foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", trim($text))) as $paragraph) {
        if (trim($paragraph) === '') { if ($all && end($all) !== '') $all[] = ''; continue; }
        array_push($all, ...inkwall_svg_wrap_paragraph($paragraph, $limit));
    }
    $overflow = count($all) > $maxLines;
    $lines = array_slice($all, 0, $maxLines);
    if ($overflow && $lines) {
        $last = array_key_last($lines);
        $lines[$last] = $lines[$last] === '' ? '…' : rtrim(mb_substr($lines[$last], 0, max(1, $limit - 1))) . '…';
    }
    return $lines ?: ['No public ink yet.'];
}
function inkwall_svg_corner_path(float $x, float $y, float $w, float $h, array $r): string {
    $tl = min((float)($r['tl'] ?? 0), $w / 2, $h / 2); $tr = min((float)($r['tr'] ?? 0), $w / 2, $h / 2);
    $br = min((float)($r['br'] ?? 0), $w / 2, $h / 2); $bl = min((float)($r['bl'] ?? 0), $w / 2, $h / 2);
    return sprintf(
        'M %.1f %.1f H %.1f Q %.1f %.1f %.1f %.1f V %.1f Q %.1f %.1f %.1f %.1f H %.1f Q %.1f %.1f %.1f %.1f V %.1f Q %.1f %.1f %.1f %.1f Z',
        $x + $tl, $y, $x + $w - $tr, $x + $w, $y, $x + $w, $y + $tr,
        $y + $h - $br, $x + $w, $y + $h, $x + $w - $br, $y + $h,
        $x + $bl, $x, $y + $h, $x, $y + $h - $bl,
        $y + $tl, $x, $y, $x + $tl, $y
    );
}
function inkwall_svg_social_url(string $platform, string $handle): string {
    $handle = ltrim($handle, '@');
    return match ($platform) {
        'instagram' => 'https://www.instagram.com/' . $handle,
        'threads' => 'https://www.threads.net/@' . $handle,
        'x' => 'https://x.com/' . $handle,
        'github' => 'https://github.com/' . $handle,
        'tiktok' => 'https://www.tiktok.com/@' . $handle,
        'youtube' => 'https://www.youtube.com/@' . $handle,
        'bluesky' => 'https://bsky.app/profile/' . $handle,
        'linkedin' => 'https://www.linkedin.com/in/' . $handle,
        default => '',
    };
}
function inkwall_svg_entities(string $message, array $bindings): array {
    if (!in_array(strtolower(inkwall_env('INKWALL_SVG_FOOTER_LINKS', '1')), ['1', 'true', 'yes', 'on'], true)) return [];
    $items = []; $seen = [];
    $platforms = ['instagram'=>'Instagram','threads'=>'Threads','x'=>'X','github'=>'GitHub','tiktok'=>'TikTok','youtube'=>'YouTube','bluesky'=>'Bluesky','linkedin'=>'LinkedIn','custom'=>'Link'];
    if (preg_match_all('/(?<![\pL\pN._-])@([a-z0-9][a-z0-9._-]{0,31})/iu', $message, $matches)) {
        foreach ($matches[1] as $handle) {
            $key = strtolower($handle); $binding = $bindings[$key] ?? null;
            if (!is_array($binding) || ($binding['platform'] ?? 'text') === 'text') continue;
            $platformKey = (string)($binding['platform'] ?? 'custom');
            $platform = $platforms[$platformKey] ?? 'Link';
            $label = $platform . ' · @' . $handle;
            $url = (string)($binding['url'] ?? '');
            if ($url === '') $url = inkwall_svg_social_url($platformKey, $handle);
            if (!isset($seen[$label])) { $items[] = ['label' => $label, 'url' => $url]; $seen[$label] = true; }
        }
    }
    if (preg_match_all('~(?:https?://|www\.)[^\s<]+|(?<!@)\b(?:[a-z0-9-]+\.)+[a-z]{2,24}(?:/[^\s<]*)?~iu', $message, $matches)) {
        foreach ($matches[0] as $raw) {
            $url = preg_match('~^https?://~i', $raw) ? $raw : 'https://' . $raw;
            $host = strtolower((string)(parse_url(rtrim($url, '.,!?;)'), PHP_URL_HOST) ?? ''));
            if ($host === '') continue;
            $label = 'Web · ' . preg_replace('/^www\./', '', $host);
            if (!isset($seen[$label])) { $items[] = ['label' => $label, 'url' => $url]; $seen[$label] = true; }
        }
    }
    return array_slice($items, 0, 2);
}
function inkwall_svg_ai_model_label(string $model): string {
    $parts = array_filter(array_map('trim', explode('+', $model)), static fn(string $part): bool => $part !== '' && $part !== 'local' && $part !== 'image:unchecked');
    $labels = [];
    foreach ($parts as $part) {
        $lower = strtolower($part);
        $label = match (true) {
            str_contains($lower, 'deepseek') => 'DeepSeek',
            str_contains($lower, 'openai') || str_contains($lower, 'omni') || str_contains($lower, 'gpt') => 'OpenAI',
            str_contains($lower, 'ollama') || str_contains($lower, 'qwen') || str_contains($lower, 'llama') => 'Ollama',
            str_contains($lower, 'no-vision') => '',
            default => preg_replace('/[^a-z0-9_.:-]+/i', ' ', $part) ?: '',
        };
        $label = trim($label);
        if ($label !== '' && !in_array($label, $labels, true)) $labels[] = $label;
    }
    return implode(' + ', array_slice($labels, 0, 2));
}
function inkwall_svg_badge_width(string $label): int {
    return max(46, min(230, 22 + (mb_strlen($label) * 7)));
}
$dark = $theme === 'dark';
$paper = $dark ? '#191916' : '#efefe9'; $ink = $dark ? '#f1f0e8' : '#171714'; $muted = $dark ? '#a9a89e' : '#66665f'; $accent = $brand['accent'];
$flags = array_map('strval', json_decode((string)($row['moderation_flags'] ?? '[]'), true) ?: []);
$showAdBadge = in_array('advertising', array_map('inkwall_ai_flag_key', $flags), true)
    && $brand['ad_badge']
    && in_array(strtolower(inkwall_env('INKWALL_SVG_AD_BADGE', '1')), ['1', 'true', 'yes', 'on'], true);
$aiModel = trim((string)($row['ai_model'] ?? ''));
$aiModelLabel = inkwall_svg_ai_model_label($aiModel);
$showReviewBadge = $brand['review_badge']
    && in_array(strtolower(inkwall_env('INKWALL_SVG_REVIEW_BADGE', '1')), ['1', 'true', 'yes', 'on'], true)
    && $aiModelLabel !== '';
$reviewBadgeLabel = '';
if ($showReviewBadge) {
    $reviewBadgeLabel = $brand['review_badge_mode'] === 'model'
        ? trim($brand['review_badge_model_prefix'] . ' ' . $aiModelLabel)
        : $brand['review_badge_text'];
}
$bindings = json_decode((string)($row['bindings_json'] ?? '{}'), true) ?: [];
$layout = inkwall_layout(json_decode((string)($row['layout_json'] ?? '{}'), true));
$showDots = $layout['texture'] === 'dots';
$hasImage = !empty($row['image_data']);
$media = $hasImage ? $layout['media'] : 'left';
$fontSize = (int)$layout['fontSize']; $fontWeight = !empty($layout['bold']) ? 800 : 560; $lineHeight = max(34, (int)round($fontSize * 1.35));
$textLeft = 62; $textRight = 1138; $textTop = 125; $lineLimit = max(30, (int)floor(1728 / max(1, $fontSize))); $maxLines = 24;
$imageX = 62; $imageY = 76; $imageWidth = 1076; $imageHeight = 617;
if ($media === 'left' || $media === 'right') {
    $imageX = $media === 'left' ? 62 : 838; $imageWidth = 300; $imageHeight = 172;
    $textLeft = $media === 'left' ? 405 : 62; $textRight = $media === 'left' ? 1138 : 795;
    $lineLimit = max(22, (int)floor(1060 / max(1, $fontSize)));
} elseif ($media === 'top') {
    $textTop = $imageY + $imageHeight + 54;
}
$textAnchor = $layout['align'] === 'center' ? 'middle' : ($layout['align'] === 'right' ? 'end' : 'start');
$messageX = $layout['align'] === 'center' ? (($textLeft + $textRight) / 2) : ($layout['align'] === 'right' ? $textRight : $textLeft);
$displayMessage = inkwall_svg_ink_text((string)$row['message_text']);
$displayAuthor = inkwall_svg_ink_text((string)$row['author_name']);
$lines = inkwall_svg_lines($displayMessage, $lineLimit, $maxLines);
$lastTextY = $textTop + ((max(1, count(array_filter($lines, static fn(string $line): bool => $line !== ''))) - 1) * $lineHeight);
$authorY = max($lastTextY + 56, $hasImage ? $imageY + $imageHeight + 50 : 0);
$svgHeight = max(340, $authorY + 86);
$entityY = $svgHeight - 48;
$entities = inkwall_svg_entities((string)$row['message_text'], $bindings);
$date = (new DateTimeImmutable((string)$row['created_at']))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y · H:i T');
$image = '';
if ($hasImage) {
    $imageMime = in_array(($row['image_mime'] ?? ''), ['image/webp', 'image/png', 'image/jpeg'], true) ? $row['image_mime'] : 'image/webp';
    $source = 'data:' . $imageMime . ';base64,' . base64_encode((string)$row['image_data']);
    $imagePath = inkwall_svg_corner_path($imageX, $imageY, $imageWidth, $imageHeight, $layout['radii']);
    $clipId = 'imageClip';
    $image = '<defs><clipPath id="' . $clipId . '"><path d="' . $imagePath . '"/></clipPath></defs><image href="' . $source . '" x="' . $imageX . '" y="' . $imageY . '" width="' . $imageWidth . '" height="' . $imageHeight . '" preserveAspectRatio="xMidYMid slice" clip-path="url(#' . $clipId . ')"/><path d="' . $imagePath . '" fill="none" stroke="' . $ink . '" stroke-width="2"/>';
}
$entityLabel = $entities ? implode('  ·  ', array_map(static fn(array $item): string => $item['label'], $entities)) . ' ↗' : $brand['site_label'] . ' · live';
$entityHref = ($entities && !empty($entities[0]['url']) && in_array(strtolower(inkwall_env('INKWALL_SVG_CLICKABLE_LINKS', '1')), ['1', 'true', 'yes', 'on'], true)) ? (string)$entities[0]['url'] : '';
$inkNumberText = str_pad((string)$inkNumber, 3, '0', STR_PAD_LEFT);
$isLatestPublishedInk = $latestPublishedId !== '' && !empty($row['id']) && hash_equals($latestPublishedId, (string)$row['id']) && (($row['status'] ?? 'published') === 'published');
$headerLabel = ($brand['svg_latest_label'] === 'all' || $isLatestPublishedInk || $inkNumber <= 0) ? 'LATEST PUBLIC INK' : 'INK #' . $inkNumberText;
$badgeX = 292;
$adBadgeWidth = $showAdBadge ? inkwall_svg_badge_width(strtoupper($brand['ad_badge_text'])) : 0;
$reviewBadgeWidth = $showReviewBadge ? inkwall_svg_badge_width($reviewBadgeLabel) : 0;
$entityX = 1138;
$entityAnchor = 'end';
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-cache, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');
?>
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="<?= $svgHeight ?>" viewBox="0 0 1200 <?= $svgHeight ?>" role="img" aria-labelledby="title desc">
  <title id="title">Latest InkWall note by <?= inkwall_svg_escape($displayAuthor) ?></title>
  <desc id="desc"><?= inkwall_svg_escape($displayMessage) ?></desc>
  <rect width="1200" height="<?= $svgHeight ?>" rx="24" fill="<?= $paper ?>"/>
  <?php if ($showDots): ?>
  <defs><pattern id="grain" width="23" height="23" patternUnits="userSpaceOnUse"><circle cx="4" cy="7" r=".55" fill="<?= $muted ?>"/><circle cx="18" cy="19" r=".32" fill="<?= $muted ?>"/></pattern></defs>
  <rect width="1200" height="<?= $svgHeight ?>" rx="24" fill="url(#grain)" opacity=".38"/>
  <?php endif ?>
  <rect x="24" y="24" width="1152" height="<?= $svgHeight - 48 ?>" rx="15" fill="none" stroke="<?= $ink ?>" stroke-width="2"/>
  <circle cx="62" cy="52" r="7" fill="<?= $accent ?>"/><text x="82" y="59" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="20" font-weight="700" letter-spacing="2" fill="<?= $ink ?>"><?= inkwall_svg_escape($headerLabel) ?></text>
  <?= $image ?>
  <g font-family="ui-monospace, SFMono-Regular, Consolas, monospace" fill="<?= $ink ?>">
    <?php foreach ($lines as $index => $line): if ($line !== ''): ?><text x="<?= $messageX ?>" y="<?= $textTop + ($index * $lineHeight) ?>" text-anchor="<?= $textAnchor ?>" font-size="<?= $fontSize ?>" font-weight="<?= $fontWeight ?>"><?= inkwall_svg_escape($line) ?></text><?php endif; endforeach ?>
    <text x="<?= $messageX ?>" y="<?= $authorY ?>" text-anchor="<?= $textAnchor ?>" font-size="21" font-weight="700"><?= inkwall_svg_escape($displayAuthor) ?></text>
  </g>
  <text x="1138" y="58" text-anchor="end" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="15" fill="<?= $muted ?>"><?= inkwall_svg_escape($date) ?></text>
  <?php if ($brand['svg_ink_number'] && $isLatestPublishedInk): ?><text x="62" y="<?= $entityY ?>" text-anchor="start" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="15" fill="<?= $muted ?>">Currently showing Ink</text><text x="249" y="<?= $entityY ?>" text-anchor="start" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="15" font-weight="800" fill="<?= $ink ?>">#</text><text x="261" y="<?= $entityY ?>" text-anchor="start" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="15" font-weight="800" fill="<?= $accent ?>"><?= inkwall_svg_escape($inkNumberText) ?></text><?php endif ?>
  <?php if ($showAdBadge): ?><g opacity=".82"><rect x="<?= $badgeX ?>" y="<?= $entityY - 17 ?>" width="<?= $adBadgeWidth ?>" height="22" rx="7" fill="<?= $paper ?>" stroke="<?= $accent ?>" stroke-width="1.3"/><text x="<?= $badgeX + ($adBadgeWidth / 2) ?>" y="<?= $entityY - 2 ?>" text-anchor="middle" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="10" font-weight="800" fill="<?= $accent ?>"><?= inkwall_svg_escape(strtoupper($brand['ad_badge_text'])) ?></text></g><?php endif ?>
  <?php if ($showReviewBadge): $reviewBadgeX = $badgeX + ($showAdBadge ? $adBadgeWidth + 10 : 0); ?><g opacity=".82"><rect x="<?= $reviewBadgeX ?>" y="<?= $entityY - 17 ?>" width="<?= $reviewBadgeWidth ?>" height="22" rx="7" fill="<?= $paper ?>" stroke="<?= $muted ?>" stroke-width="1"/><text x="<?= $reviewBadgeX + ($reviewBadgeWidth / 2) ?>" y="<?= $entityY - 2 ?>" text-anchor="middle" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="10" font-weight="800" fill="<?= $muted ?>"><?= inkwall_svg_escape($reviewBadgeLabel) ?></text></g><?php endif ?>
  <?php if ($entityHref !== ''): ?><a href="<?= inkwall_svg_escape($entityHref) ?>" target="_blank" rel="noopener noreferrer"><?php endif ?><text x="<?= $entityX ?>" y="<?= $entityY ?>" text-anchor="<?= $entityAnchor ?>" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="15" fill="<?= $muted ?>"><?= inkwall_svg_escape($entityLabel) ?></text><?php if ($entityHref !== ''): ?></a><?php endif ?>
</svg>
