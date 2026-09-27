<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

require_login();
verify_csrf();

header('Content-Type: application/json; charset=utf-8');

try {
  $dir = __DIR__ . '/docs/signatures';
  if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true)) throw new RuntimeException('Impossible de créer /docs/signatures');
  }

  $dataUrl = (string)($_POST['sig_data'] ?? '');
  if ($dataUrl !== '') {
    if (strpos($dataUrl, 'data:image/png;base64,') !== 0) throw new RuntimeException('Signature invalide');
    $b64 = substr($dataUrl, strlen('data:image/png;base64,'));
    $bin = base64_decode($b64, true);
    if ($bin === false) throw new RuntimeException('Base64 invalide');
    if (strlen($bin) > 3 * 1024 * 1024) throw new RuntimeException('Signature trop lourde');

    $name = 'sig_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
    $path = $dir . '/' . $name;
    if (file_put_contents($path, $bin) === false) throw new RuntimeException('Échec écriture signature');

    echo json_encode(['ok' => true, 'url' => '/docs/signatures/' . $name], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if (!isset($_FILES['sig_file']) || !is_uploaded_file($_FILES['sig_file']['tmp_name'])) {
    throw new RuntimeException('Aucune signature envoyée');
  }

  $f = $_FILES['sig_file'];
  if (($f['size'] ?? 0) > 3 * 1024 * 1024) throw new RuntimeException('Fichier trop lourd');

  $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['png','jpg','jpeg'], true)) throw new RuntimeException('Format autorisé: png/jpg');

  $name = 'sig_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
  $path = $dir . '/' . $name;

  if (!move_uploaded_file($f['tmp_name'], $path)) throw new RuntimeException('Échec upload');

  echo json_encode(['ok' => true, 'url' => '/docs/signatures/' . $name], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
