<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

$id = preg_match('/^[a-f0-9-]{20,40}$/i', (string)($_GET['id'] ?? '')) ? (string)$_GET['id'] : '';
$theme = ($_GET['theme'] ?? inkwall_branding()['theme']) === 'dark' ? 'dark' : 'light';

function inkwall_share_prepare_svg_images(string $svg, array &$temporaryFiles): string {
    if (!str_contains($svg, 'data:image/')) return $svg;
    if (!str_contains($svg, 'xmlns:xlink=')) {
        $svg = preg_replace('/<svg\b/', '<svg xmlns:xlink="http://www.w3.org/1999/xlink"', $svg, 1) ?? $svg;
    }
    return preg_replace_callback(
        '~<image\b([^>]*?)\bhref="data:image/(webp|png|jpeg);base64,([^"]+)"([^>]*)>~i',
        static function (array $match) use (&$temporaryFiles): string {
            $data = base64_decode($match[3], true);
            if ($data === false || $data === '') return $match[0];
            $extension = strtolower($match[2]) === 'jpeg' ? 'jpg' : strtolower($match[2]);
            $path = tempnam(sys_get_temp_dir(), 'inkwall-share-');
            if ($path === false) return $match[0];
            $target = $path . '.' . $extension;
            if (!@rename($path, $target)) $target = $path;
            if (@file_put_contents($target, $data) === false) {
                @unlink($target);
                return $match[0];
            }
            $temporaryFiles[] = $target;
            $href = 'file://' . $target;
            $safeHref = htmlspecialchars($href, ENT_QUOTES | ENT_XML1, 'UTF-8');
            return '<image' . $match[1] . 'href="' . $safeHref . '" xlink:href="' . $safeHref . '"' . $match[4] . '>';
        },
        $svg
    ) ?? $svg;
}

function inkwall_share_svg_images(string $svg): array {
    if (!preg_match_all('~<image\b[^>]*\bhref="data:image/(webp|png|jpeg);base64,([^"]+)"[^>]*>~i', $svg, $matches, PREG_SET_ORDER)) return [];
    $images = [];
    foreach ($matches as $match) {
        $tag = $match[0];
        $data = base64_decode($match[2], true);
        if ($data === false || $data === '') continue;
        $read = static function (string $name) use ($tag): float {
            return preg_match('~\b' . preg_quote($name, '~') . '="([0-9.]+)"~', $tag, $value) ? (float)$value[1] : 0.0;
        };
        $x = $read('x'); $y = $read('y'); $width = $read('width'); $height = $read('height');
        if ($width <= 0 || $height <= 0) continue;
        $images[] = ['data' => $data, 'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height];
    }
    return $images;
}

function inkwall_share_overlay_svg_images(Imagick $source, array $images): void {
    foreach ($images as $image) {
        $photo = new Imagick();
        $photo->readImageBlob($image['data']);
        $photo->setImageFormat('png32');
        $photo->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
        $targetWidth = max(1, (int)round($image['width']));
        $targetHeight = max(1, (int)round($image['height']));
        $sourceWidth = max(1, $photo->getImageWidth());
        $sourceHeight = max(1, $photo->getImageHeight());
        $scale = max($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $coverWidth = max(1, (int)ceil($sourceWidth * $scale));
        $coverHeight = max(1, (int)ceil($sourceHeight * $scale));
        $photo->resizeImage($coverWidth, $coverHeight, Imagick::FILTER_LANCZOS, 1);
        $cropX = max(0, (int)floor(($coverWidth - $targetWidth) / 2));
        $cropY = max(0, (int)floor(($coverHeight - $targetHeight) / 2));
        $photo->cropImage($targetWidth, $targetHeight, $cropX, $cropY);
        $photo->setImagePage(0, 0, 0, 0);
        $inset = 2;
        if ($targetWidth > ($inset * 2) && $targetHeight > ($inset * 2)) {
            $photo->resizeImage($targetWidth - ($inset * 2), $targetHeight - ($inset * 2), Imagick::FILTER_LANCZOS, 1);
            $source->compositeImage($photo, Imagick::COMPOSITE_OVER, (int)round($image['x']) + $inset, (int)round($image['y']) + $inset);
        } else {
            $source->compositeImage($photo, Imagick::COMPOSITE_OVER, (int)round($image['x']), (int)round($image['y']));
        }
        $photo->clear();
        $photo->destroy();
    }
}

try {
    if (!class_exists(Imagick::class)) throw new RuntimeException('Imagick is unavailable.');

    $_GET['theme'] = $theme;
    if ($id !== '') $_GET['id'] = $id;
    ob_start();
    require __DIR__ . '/latest.svg.php';
    $svg = (string)ob_get_clean();
    if ($svg === '') throw new RuntimeException('SVG render failed.');

    $svgImages = inkwall_share_svg_images($svg);
    $temporaryFiles = [];
    $svg = inkwall_share_prepare_svg_images($svg, $temporaryFiles);

    $source = new Imagick();
    try {
        $source->setBackgroundColor(new ImagickPixel('transparent'));
        $source->readImageBlob($svg);
        $source->setImageFormat('png32');
        $source->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
        if ($svgImages) inkwall_share_overlay_svg_images($source, $svgImages);
    } finally {
        foreach ($temporaryFiles as $temporaryFile) @unlink($temporaryFile);
    }

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
    header($id === '' ? 'Cache-Control: no-store, max-age=0, must-revalidate' : 'Cache-Control: public, max-age=300');
    header('X-Content-Type-Options: nosniff');
    echo $canvas->getImagesBlob();
} catch (Throwable) {
    header_remove();
    http_response_code(302);
    $fallback = 'latest.svg.php' . ($id !== '' ? '?id=' . rawurlencode($id) . '&theme=' . rawurlencode($theme) : '?theme=' . rawurlencode($theme));
    header('Location: ' . $fallback);
}
