<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
$id = (string)($_GET['id'] ?? '');
if (!preg_match('/^[a-f0-9-]{20,40}$/i', $id)) { http_response_code(404); exit; }
$stmt = inkwall_db()->prepare("SELECT image_data, image_mime FROM inkwall_notes WHERE id = ? AND status = 'published' AND image_data IS NOT NULL");
$stmt->execute([$id]); $row = $stmt->fetch();
if (!is_array($row)) { http_response_code(404); exit; }
$mime = in_array(($row['image_mime'] ?? ''), ['image/webp', 'image/png', 'image/jpeg'], true) ? $row['image_mime'] : 'image/webp';
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
echo $row['image_data'];
