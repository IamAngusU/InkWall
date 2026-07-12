<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

$id = preg_match('/^[a-f0-9-]{20,40}$/i', (string)($_GET['id'] ?? '')) ? (string)$_GET['id'] : '';
$theme = ($_GET['theme'] ?? inkwall_branding()['theme']) === 'dark' ? 'dark' : 'light';

try {
    if (!class_exists(Imagick::class)) throw new RuntimeException('Imagick is unavailable.');

    $_GET['theme'] = $theme;
    if ($id !== '') $_GET['id'] = $id;
    ob_start();
    require __DIR__ . '/latest.svg.php';
    $svg = (string)ob_get_clean();
    if ($svg === '') throw new RuntimeException('SVG render failed.');

    $source = new Imagick();
    $source->setBackgroundColor(new ImagickPixel('transparent'));
    $source->readImageBlob($svg);
    $source->setImageFormat('png32');
    $source->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);

    $sourceWidth = max(1, $source->getImageWidth());
    $sourceHeight = max(1, $source->getImageHeight());
    $targetWidth = 1200;
    $targetHeight = 630;
    $padding = 42;
    $scale = min(($targetWidth - ($padding * 2)) / $sourceWidth, ($targetHeight - ($padding * 2)) / $sourceHeight, 1.35);
    $renderWidth = max(1, (int)round($sourceWidth * $scale));
    $renderHeight = max(1, (int)round($sourceHeight * $scale));
    $source->resizeImage($renderWidth, $renderHeight, Imagick::FILTER_LANCZOS, 1);

    $paper = $theme === 'dark' ? '#171a17' : '#f3f2ec';
    $canvas = new Imagick();
    $canvas->newImage($targetWidth, $targetHeight, new ImagickPixel($paper), 'png');
    $canvas->setImageFormat('png');
    $canvas->compositeImage($source, Imagick::COMPOSITE_OVER, (int)(($targetWidth - $renderWidth) / 2), (int)(($targetHeight - $renderHeight) / 2));

    header_remove();
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=300');
    header('X-Content-Type-Options: nosniff');
    echo $canvas->getImagesBlob();
} catch (Throwable) {
    header_remove();
    http_response_code(302);
    $fallback = 'latest.svg.php' . ($id !== '' ? '?id=' . rawurlencode($id) . '&theme=' . rawurlencode($theme) : '?theme=' . rawurlencode($theme));
    header('Location: ' . $fallback);
}
