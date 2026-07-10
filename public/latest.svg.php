<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

$theme = ($_GET['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
$row = inkwall_db()->query("SELECT * FROM inkwall_notes WHERE status = 'published' ORDER BY created_at DESC LIMIT 1")->fetch();
if (!is_array($row)) {
    $row = ['author_name' => 'Angus Uelsmann', 'message_text' => 'This surface is yours for a moment. Leave the next ink on my GitHub profile.', 'created_at' => inkwall_now(), 'image_data' => null, 'image_mime' => null];
}
function inkwall_svg_escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
function inkwall_svg_lines(string $text, int $limit = 52): array {
    $words = preg_split('/\s+/u', trim($text)) ?: [];
    $lines = []; $line = '';
    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (mb_strlen($candidate) > $limit && $line !== '') { $lines[] = $line; $line = $word; }
        else $line = $candidate;
    }
    if ($line !== '') $lines[] = $line;
    return array_slice($lines, 0, 3);
}
$dark = $theme === 'dark';
$paper = $dark ? '#191916' : '#efefe9'; $ink = $dark ? '#f1f0e8' : '#171714'; $muted = $dark ? '#a9a89e' : '#66665f'; $red = '#d7422f';
$hasImage = !empty($row['image_data']); $messageX = $hasImage ? 405 : 62; $lineLimit = $hasImage ? 44 : 74;
$lines = inkwall_svg_lines((string)$row['message_text'], $lineLimit);
$date = (new DateTimeImmutable((string)$row['created_at']))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y · H:i T');
$image = '';
if ($hasImage) {
    $source = 'data:image/webp;base64,' . base64_encode((string)$row['image_data']);
    $image = '<image href="' . $source . '" x="62" y="76" width="300" height="205" preserveAspectRatio="xMidYMid slice"/><rect x="62" y="76" width="300" height="205" fill="none" stroke="' . $ink . '" stroke-width="2"/>';
}
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
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
    <?php foreach ($lines as $index => $line): ?><text x="<?= $messageX ?>" y="<?= 125 + ($index * 48) ?>" font-size="34" font-weight="700"><?= inkwall_svg_escape($line) ?></text><?php endforeach ?>
    <text x="<?= $messageX ?>" y="<?= $hasImage ? 276 : 285 ?>" font-size="21" font-weight="700">— <?= inkwall_svg_escape((string)$row['author_name']) ?></text>
  </g>
  <text x="1138" y="58" text-anchor="end" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="15" fill="<?= $muted ?>"><?= inkwall_svg_escape($date) ?></text>
  <text x="1138" y="292" text-anchor="end" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="16" fill="<?= $muted ?>">angusu.de/inkwall · 60s cache</text>
</svg>
