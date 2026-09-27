<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../csrf.php';

require_login();

$u = current_user();
if (!is_array($u) || (($u['role'] ?? '') !== 'admin')) {
  http_response_code(403);
  exit('Accès refusé.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Méthode non autorisée.');
}

start_session();
$token = (string)($_POST['csrf'] ?? '');
if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
  http_response_code(403);
  exit('CSRF invalide.');
}

$userId = (int)($_POST['user_id'] ?? 0);
if ($userId <= 0) {
  http_response_code(400);
  exit('Utilisateur invalide.');
}

if ($userId === (int)($u['id'] ?? 0)) {
  http_response_code(400);
  exit('Tu ne peux pas supprimer ton propre compte.');
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
  $pdo->beginTransaction();

  $pdo->prepare("UPDATE salary_shifts SET ended_at=NOW() WHERE user_id=? AND ended_at IS NULL")
      ->execute([$userId]);

  $pdo->prepare("DELETE FROM personnel_formations_assign WHERE user_id=?")
      ->execute([$userId]);

  $pdo->prepare("DELETE FROM appointments WHERE user_id=? OR created_by=?")
      ->execute([$userId, $userId]);

  $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$userId]);

  $pdo->commit();

  header('Location: /admin/users_manage.php?deleted=1');
  exit;

} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Erreur lors de la suppression.\n";
  echo $e->getMessage();
  exit;
}
