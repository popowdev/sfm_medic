<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

require_login();
verify_csrf();

header('Content-Type: application/json; charset=utf-8');

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
  }

  $docId = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
  $dataUrl = (string)($_POST['png_data'] ?? '');

  if ($docId <= 0) throw new RuntimeException('doc_id manquant');
  if ($dataUrl === '' || strpos($dataUrl, 'data:image/png;base64,') !== 0) {
    throw new RuntimeException('PNG invalide');
  }

  $b64 = substr($dataUrl, strlen('data:image/png;base64,'));
  $bin = base64_decode($b64, true);
  if ($bin === false) throw new RuntimeException('Base64 invalide');

  if (strlen($bin) > 10 * 1024 * 1024) throw new RuntimeException('Image trop lourde');

  $dir = __DIR__ . '/docs';
  if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true)) throw new RuntimeException('Impossible de créer /docs');
  }

  $name = 'doc_' . $docId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
  $path = $dir . '/' . $name;

  if (file_put_contents($path, $bin) === false) {
    throw new RuntimeException('Échec écriture PNG');
  }

  $url = '/docs/' . $name;
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? '';
  $full = $host !== '' ? ($scheme . '://' . $host . $url) : $url;

  echo json_encode(['ok' => true, 'url' => $url, 'full_url' => $full], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
