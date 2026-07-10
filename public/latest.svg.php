<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

$theme = ($_GET['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
$row = inkwall_db()->query("SELECT * FROM inkwall_notes WHERE status = 'published' ORDER BY created_at DESC LIMIT 1")->fetch();
if (!is_array($row)) {
    $row = ['author_name' => 'Angus Uelsmann', 'message_text' => 'This surface is yours for a moment. Leave the next ink on my GitHub profile.', 'created_at' => inkwall_now(), 'image_data' => null, 'image_mime' => null];
}
function inkwall_svg_escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
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
function inkwall_svg_entities(string $message, array $bindings): array {
    $labels = []; $seen = [];
    $platforms = ['instagram'=>'Instagram','threads'=>'Threads','x'=>'X','github'=>'GitHub','tiktok'=>'TikTok','youtube'=>'YouTube','bluesky'=>'Bluesky','linkedin'=>'LinkedIn','custom'=>'Link'];
    if (preg_match_all('/(?<![\pL\pN._-])@([a-z0-9][a-z0-9._-]{0,31})/iu', $message, $matches)) {
        foreach ($matches[1] as $handle) {
            $key = strtolower($handle); $binding = $bindings[$key] ?? null;
            if (!is_array($binding) || ($binding['platform'] ?? 'text') === 'text') continue;
            $platform = $platforms[$binding['platform'] ?? 'custom'] ?? 'Link';
            $label = $platform . ' · @' . $handle;
            if (!isset($seen[$label])) { $labels[] = $label; $seen[$label] = true; }
        }
    }
    if (preg_match_all('~(?:https?://|www\.)[^\s<]+|\b(?:[a-z0-9-]+\.)+[a-z]{2,24}(?:/[^\s<]*)?~iu', $message, $matches)) {
        foreach ($matches[0] as $raw) {
            $url = preg_match('~^https?://~i', $raw) ? $raw : 'https://' . $raw;
            $host = strtolower((string)(parse_url(rtrim($url, '.,!?;)'), PHP_URL_HOST) ?? ''));
            if ($host === '') continue;
            $label = 'Web · ' . preg_replace('/^www\./', '', $host);
            if (!isset($seen[$label])) { $labels[] = $label; $seen[$label] = true; }
        }
    }
    return array_slice($labels, 0, 2);
}
$dark = $theme === 'dark';
$paper = $dark ? '#191916' : '#efefe9'; $ink = $dark ? '#f1f0e8' : '#171714'; $muted = $dark ? '#a9a89e' : '#66665f'; $red = '#d7422f';
$bindings = json_decode((string)($row['bindings_json'] ?? '{}'), true) ?: [];
$layout = inkwall_layout(json_decode((string)($row['layout_json'] ?? '{}'), true));
$hasImage = !empty($row['image_data']);
$media = $hasImage ? ($layout['media'] === 'auto' ? 'top' : $layout['media']) : 'auto';
$textLeft = 62; $textRight = 1138; $textTop = 125; $lineLimit = 54; $maxLines = 3; $fontSize = 32; $lineHeight = 46;
$imageX = 62; $imageY = 76; $imageWidth = 1076; $imageHeight = 86;
if ($media === 'left' || $media === 'right') {
    $imageX = $media === 'left' ? 62 : 838; $imageWidth = 300; $imageHeight = 172;
    $textLeft = $media === 'left' ? 405 : 62; $textRight = $media === 'left' ? 1138 : 795;
    $lineLimit = 34; $fontSize = 29; $lineHeight = 42;
} elseif ($media === 'top') {
    $textTop = 195; $maxLines = 2; $fontSize = 27; $lineHeight = 38;
}
$textAnchor = $layout['align'] === 'center' ? 'middle' : ($layout['align'] === 'right' ? 'end' : 'start');
$messageX = $layout['align'] === 'center' ? (($textLeft + $textRight) / 2) : ($layout['align'] === 'right' ? $textRight : $textLeft);
$lines = inkwall_svg_lines((string)$row['message_text'], $lineLimit, $maxLines);
$entities = inkwall_svg_entities((string)$row['message_text'], $bindings);
$date = (new DateTimeImmutable((string)$row['created_at']))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y · H:i T');
$image = '';
if ($hasImage) {
    $source = 'data:image/webp;base64,' . base64_encode((string)$row['image_data']);
    $image = '<image href="' . $source . '" x="' . $imageX . '" y="' . $imageY . '" width="' . $imageWidth . '" height="' . $imageHeight . '" preserveAspectRatio="xMidYMid slice"/><rect x="' . $imageX . '" y="' . $imageY . '" width="' . $imageWidth . '" height="' . $imageHeight . '" fill="none" stroke="' . $ink . '" stroke-width="2"/>';
}
$entityLabel = $entities ? implode('  ·  ', $entities) . ' ↗' : 'angusu.de/inkwall · live';
$entityX = $layout['align'] === 'right' ? 62 : 1138;
$entityAnchor = $layout['align'] === 'right' ? 'start' : 'end';
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-cache, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');
?>
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="340" viewBox="0 0 1200 340" role="img" aria-labelledby="title desc">
  <title id="title">Latest InkWall note by <?= inkwall_svg_escape((string)$row['author_name']) ?></title>
  <desc id="desc"><?= inkwall_svg_escape((string)$row['message_text']) ?></desc>
  <rect width="1200" height="340" rx="24" fill="<?= $paper ?>"/>
  <defs><pattern id="grain" width="23" height="23" patternUnits="userSpaceOnUse"><circle cx="4" cy="7" r=".45" fill="<?= $muted ?>"/><circle cx="18" cy="19" r=".25" fill="<?= $muted ?>"/></pattern></defs>
  <rect width="1200" height="340" rx="24" fill="url(#grain)" opacity=".22"/>
  <rect x="24" y="24" width="1152" height="292" rx="15" fill="none" stroke="<?= $ink ?>" stroke-width="2"/>
  <circle cx="62" cy="52" r="7" fill="<?= $red ?>"/><text x="82" y="59" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="20" font-weight="700" letter-spacing="2" fill="<?= $ink ?>">LATEST PUBLIC INK</text>
  <?= $image ?>
  <g font-family="ui-monospace, SFMono-Regular, Consolas, monospace" fill="<?= $ink ?>">
    <?php foreach ($lines as $index => $line): if ($line !== ''): ?><text x="<?= $messageX ?>" y="<?= $textTop + ($index * $lineHeight) ?>" text-anchor="<?= $textAnchor ?>" font-size="<?= $fontSize ?>" font-weight="700"><?= inkwall_svg_escape($line) ?></text><?php endif; endforeach ?>
    <text x="<?= $messageX ?>" y="285" text-anchor="<?= $textAnchor ?>" font-size="21" font-weight="700">— <?= inkwall_svg_escape((string)$row['author_name']) ?></text>
  </g>
  <text x="1138" y="58" text-anchor="end" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="15" fill="<?= $muted ?>"><?= inkwall_svg_escape($date) ?></text>
  <text x="<?= $entityX ?>" y="292" text-anchor="<?= $entityAnchor ?>" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="15" fill="<?= $muted ?>"><?= inkwall_svg_escape($entityLabel) ?></text>
</svg>
