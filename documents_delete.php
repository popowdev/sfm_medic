<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';

require_login();

$u = current_user();
$isAdmin = $u && (($u['role'] ?? '') === 'admin');

if (!$isAdmin) {
  http_response_code(403);
  exit('Accès refusé.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Méthode non autorisée.');
}

verify_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  exit('ID invalide.');
}

$stmt = db()->prepare("DELETE FROM documents WHERE id = ? LIMIT 1");
$stmt->execute([$id]);

header('Location: /documents.php');
exit;