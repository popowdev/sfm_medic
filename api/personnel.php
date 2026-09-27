<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../csrf.php';

header('Content-Type: application/json; charset=utf-8');

function json_error(string $msg, int $code = 400, array $extra = []): void {
  http_response_code($code);
  echo json_encode(['error' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
  exit;
}
function json_ok(array $data = []): void {
  echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
  exit;
}
function read_json(): array {
  $raw = file_get_contents('php://input') ?: '';
  if (trim($raw) === '') return [];
  $d = json_decode($raw, true);
  if (!is_array($d)) json_error('JSON invalide.', 400);
  return $d;
}
function personnel_csrf_ok(string $token): bool {
  start_session();
  if ($token === '') return false;
  if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) return false;
  return hash_equals($_SESSION['csrf'], $token);
}

const GRADE_RATES = [
  'Ambulancier'      => 600,
  'Chef Ambulancier' => 700,
  'Infirmier'        => 800,
  'Infirmier en chef'=> 900,
  'Médecin'          => 1000,
  'Médecin en chef'  => 1100,
  'Chef d\'équipe'   => 1200,
  'Co-Directeur'     => 1300,
  'Directeur'        => 1500,
];

function require_personnel_access(PDO $pdo): void {
  start_session();
  require_login();

  $u    = current_user();
  $role = (string)($u['role'] ?? '');
  $uid  = (int)($u['id'] ?? 0);

  if (in_array($role, ['admin', 'owner', 'director'], true)) return;

  $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id=? AND can_supervise=1 LIMIT 1");
  $stmt->execute([$uid]);
  if ($stmt->fetchColumn()) return;

  json_error('Accès refusé.', 403);
}

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  require_personnel_access($pdo);

  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  if ($method === 'GET') {
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

    $formations = $pdo->query("
      SELECT id, emoji, name FROM personnel_formations ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    if ($userId > 0) {
      $stmt = $pdo->prepare("SELECT id, username, role, grade, can_supervise FROM users WHERE id=? LIMIT 1");
      $stmt->execute([$userId]);
      $person = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$person) json_error('Utilisateur introuvable.', 404);

      $stmt = $pdo->prepare("SELECT formation_id FROM personnel_formations_assign WHERE user_id=? ORDER BY formation_id ASC");
      $stmt->execute([$userId]);
      $assignedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

      json_ok([
        'person' => [
          'id'           => (int)$person['id'],
          'username'     => (string)$person['username'],
          'role'         => (string)($person['role'] ?? ''),
          'grade'        => (string)($person['grade'] ?? ''),
          'can_supervise'=> (int)($person['can_supervise'] ?? 0),
        ],
        'user_id'      => $userId,
        'formations'   => $formations,
        'assigned_ids' => $assignedIds,
      ]);
    }

    $rows = $pdo->query("
      SELECT id, username, role, grade, can_supervise
      FROM users
      WHERE is_active = 1
      ORDER BY username ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $people = [];
    foreach ($rows as $r) {
      $grade    = (string)($r['grade'] ?? '');
      $people[] = [
        'id'           => (int)$r['id'],
        'username'     => (string)$r['username'],
        'role'         => (string)($r['role'] ?? ''),
        'grade'        => $grade,
        'can_supervise'=> (int)($r['can_supervise'] ?? 0),
        'hourly_rate'  => GRADE_RATES[$grade] ?? 0,
      ];
    }

    json_ok(['people' => $people, 'grades' => GRADE_RATES, 'formations' => $formations]);
  }

  if ($method !== 'POST') json_error('Méthode non autorisée.', 405);

  $data = read_json();
  $csrf = isset($data['csrf']) ? (string)$data['csrf'] : '';
  if (!personnel_csrf_ok($csrf)) json_error('Requête invalide (CSRF).', 403);

  $action = (string)($data['action'] ?? '');

  if ($action === 'update_user') {
    $userId      = (int)($data['user_id'] ?? 0);
    $grade       = trim((string)($data['grade'] ?? ''));
    $canSupervise= !empty($data['can_supervise']) ? 1 : 0;

    if ($userId <= 0) json_error('user_id invalide.', 400);
    if ($grade !== '' && !array_key_exists($grade, GRADE_RATES)) json_error('Grade invalide.', 400);

    $stmt = $pdo->prepare("UPDATE users SET grade=?, can_supervise=?, dispatch_role=? WHERE id=?");
    $stmt->execute([
      $grade !== '' ? $grade : null,
      $canSupervise,
      $grade !== '' ? $grade : null,
      $userId
    ]);

    json_ok(['user_id' => $userId, 'grade' => $grade, 'can_supervise' => $canSupervise]);
  }

  if ($action === 'save_formations') {
    $userId      = (int)($data['user_id'] ?? 0);
    $formationIds= $data['formation_ids'] ?? [];

    if ($userId <= 0) json_error('user_id invalide.', 400);
    if (!is_array($formationIds)) json_error('formation_ids invalide.', 400);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id=? AND is_active=1");
    $stmt->execute([$userId]);
    if ((int)$stmt->fetchColumn() <= 0) json_error('Utilisateur introuvable.', 404);

    $cleanIds = [];
    foreach ($formationIds as $fid) {
      $fid = (int)$fid;
      if ($fid > 0) $cleanIds[$fid] = $fid;
    }
    $cleanIds = array_values($cleanIds);

    if ($cleanIds) {
      $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
      $stmt = $pdo->prepare("SELECT id FROM personnel_formations WHERE id IN ($placeholders)");
      $stmt->execute($cleanIds);
      $validIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
      sort($validIds); sort($cleanIds);
      if ($validIds !== $cleanIds) json_error('Une ou plusieurs formations sont invalides.', 400);
    }

    $pdo->beginTransaction();
    try {
      $pdo->prepare("DELETE FROM personnel_formations_assign WHERE user_id=?")->execute([$userId]);

      if ($cleanIds) {
        $ins = $pdo->prepare("INSERT INTO personnel_formations_assign (user_id, formation_id) VALUES (?,?)");
        foreach ($cleanIds as $fid) $ins->execute([$userId, $fid]);
      }

      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      json_error('Erreur base de données.', 500, ['detail' => $e->getMessage()]);
    }

    json_ok(['user_id' => $userId, 'formation_ids' => $cleanIds]);
  }

  json_error('Action inconnue.', 400);

} catch (Throwable $e) {
  json_error('Erreur serveur.', 500, ['detail' => $e->getMessage()]);
}
