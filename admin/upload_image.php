<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../csrf.php';

require_admin();
start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit;
}

$uploadsDir = __DIR__ . '/../uploads';
if (!is_dir($uploadsDir)) {
  http_response_code(500);
  echo json_encode(['error' => 'Uploads directory missing']);
  exit;
}

if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
  http_response_code(400);
  echo json_encode(['error' => 'No file']);
  exit;
}

$f = $_FILES['file'];

if ($f['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  echo json_encode(['error' => 'Upload error: ' . $f['error']]);
  exit;
}

 
$maxBytes = 5 * 1024 * 1024;
if ($f['size'] > $maxBytes) {
  http_response_code(400);
  echo json_encode(['error' => 'File too large (max 5MB)']);
  exit;
}

 
$fi = new finfo(FILEINFO_MIME_TYPE);
$mime = $fi->file($f['tmp_name']);

$allowed = [
  'image/png'  => 'png',
  'image/jpeg' => 'jpg',
  'image/webp' => 'webp',
  'image/gif'  => 'gif',
];

if (!isset($allowed[$mime])) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid image type']);
  exit;
}

$ext = $allowed[$mime];
$name = bin2hex(random_bytes(16)) . '.' . $ext;
$dest = $uploadsDir . '/' . $name;

if (!move_uploaded_file($f['tmp_name'], $dest)) {
  http_response_code(500);
  echo json_encode(['error' => 'Failed to save file']);
  exit;
}

 
$url = '/uploads/' . $name;

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['location' => $url]);