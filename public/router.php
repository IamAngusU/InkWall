<?php
declare(strict_types=1);

$path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) return false;
if (preg_match('~^/(?:inkwall/)?api(?:/|$)~', $path)) { require __DIR__ . '/api.php'; return true; }
if (in_array($path, ['/latest.svg', '/inkwall/latest.svg'], true)) { require __DIR__ . '/latest.svg.php'; return true; }
if (in_array($path, ['/', '/index.php', '/inkwall', '/inkwall/'], true)) { require __DIR__ . '/index.php'; return true; }
http_response_code(404);
echo 'Not found';
