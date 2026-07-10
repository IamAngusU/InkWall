<?php
declare(strict_types=1);
$theme = ($_GET['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
$action = in_array(($_GET['action'] ?? 'live'), ['live', 'repo', 'profile'], true) ? (string)$_GET['action'] : 'live';
$dark = $theme === 'dark';
$paper = $dark ? '#191916' : '#efefe9';
$ink = $dark ? '#f1f0e8' : '#171714';
$muted = $dark ? '#a9a89e' : '#66665f';
$label = match ($action) { 'repo' => 'VIEW REPOSITORY', 'profile' => 'VIEW LIVE PROFILE', default => 'OPEN LIVE SURFACE' };
$detail = match ($action) { 'repo' => 'SOURCE / README', 'profile' => 'GITHUB / IAMANGUSU', default => 'LEAVE THE NEXT INK' };
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-cache, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');
?>
<svg xmlns="http://www.w3.org/2000/svg" width="420" height="76" viewBox="0 0 420 76" role="img" aria-label="<?= $label ?>">
  <rect x="1" y="1" width="418" height="74" rx="13" fill="<?= $paper ?>" stroke="<?= $ink ?>" stroke-width="2"/>
  <defs><pattern id="grain" width="19" height="19" patternUnits="userSpaceOnUse"><circle cx="4" cy="6" r=".35" fill="<?= $muted ?>"/><circle cx="15" cy="16" r=".2" fill="<?= $muted ?>"/></pattern></defs>
  <rect x="2" y="2" width="416" height="72" rx="12" fill="url(#grain)" opacity=".18"/>
  <circle cx="26" cy="27" r="5" fill="#d7422f"/>
  <text x="42" y="32" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="16" font-weight="700" letter-spacing="1.5" fill="<?= $ink ?>"><?= $label ?></text>
  <text x="42" y="55" font-family="ui-monospace, SFMono-Regular, Consolas, monospace" font-size="10" letter-spacing="1.2" fill="<?= $muted ?>"><?= $detail ?></text>
  <path d="M378 29h16m-6-6 6 6-6 6" fill="none" stroke="<?= $ink ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
