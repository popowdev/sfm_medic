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
function ward_csrf_ok(string $token): bool {
  start_session();
  if ($token === '') return false;
  if (!isset($_SESSION['csrf'])) return false;
  return hash_equals($_SESSION['csrf'], $token);
}

start_session();
require_login();

$u       = current_user();
$uid     = (int)($u['id'] ?? 0);
$isAdmin = ($u['role'] ?? '') === 'admin';

try {
  $pdo    = db();
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  if ($method === 'GET') {
    $services = $pdo->query("
      SELECT id, name, color, icon, sort_order
      FROM ward_services ORDER BY sort_order ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $rooms = $pdo->query("
      SELECT r.id, r.service_id, r.number, r.label, r.beds, r.status, r.sort_order
      FROM ward_rooms r
      ORDER BY r.service_id ASC, r.sort_order ASC, r.number ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $patients = $pdo->query("
      SELECT p.id, p.room_id, p.name, p.reason, p.practitioner,
             p.status, p.notes, p.admitted_at, p.created_by,
             u.username AS created_by_name
      FROM ward_patients p
      LEFT JOIN users u ON u.id = p.created_by
      WHERE p.discharged_at IS NULL
      ORDER BY p.admitted_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $patientsByRoom = [];
    foreach ($patients as $p) {
      $patientsByRoom[(int)$p['room_id']][] = $p;
    }

    foreach ($rooms as &$r) {
      $rid = (int)$r['id'];
      $r['patients']  = $patientsByRoom[$rid] ?? [];
      $r['occupied']  = count($r['patients']);
      $r['available'] = max(0, (int)$r['beds'] - $r['occupied']);
    }
    unset($r);

    json_ok([
      'services' => $services,
      'rooms'    => $rooms,
    ]);
  }

  if ($method !== 'POST') json_error('Méthode non autorisée.', 405);

  $data  = read_json();
  $csrf  = (string)($data['csrf'] ?? '');
  if (!ward_csrf_ok($csrf)) json_error('Requête invalide (CSRF).', 403);

  $action = (string)($data['action'] ?? '');

  if ($action === 'create_service') {
    if (!$isAdmin) json_error('Accès refusé.', 403);
    $name  = trim((string)($data['name']  ?? ''));
    $color = trim((string)($data['color'] ?? '#3b9eff'));
    $icon  = trim((string)($data['icon']  ?? '🏥'));
    if ($name === '') json_error('Nom requis.', 400);

    $stmt = $pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM ward_services");
    $next = (int)$stmt->fetchColumn();
    $pdo->prepare("INSERT INTO ward_services (name,color,icon,sort_order) VALUES (?,?,?,?)")
        ->execute([$name, $color, $icon, $next]);
    json_ok(['id' => (int)$pdo->lastInsertId()]);
  }

  if ($action === 'edit_service') {
    if (!$isAdmin) json_error('Accès refusé.', 403);
    $id    = (int)($data['id'] ?? 0);
    $name  = trim((string)($data['name']  ?? ''));
    $color = trim((string)($data['color'] ?? ''));
    $icon  = trim((string)($data['icon']  ?? ''));
    if ($id <= 0 || $name === '') json_error('Données invalides.', 400);
    $pdo->prepare("UPDATE ward_services SET name=?,color=?,icon=? WHERE id=?")
        ->execute([$name, $color, $icon, $id]);
    json_ok();
  }

  if ($action === 'delete_service') {
    if (!$isAdmin) json_error('Accès refusé.', 403);
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) json_error('ID manquant.', 400);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ward_rooms WHERE service_id=?");
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() > 0) json_error('Supprimez d\'abord toutes les chambres de ce service.', 400);
    $pdo->prepare("DELETE FROM ward_services WHERE id=?")->execute([$id]);
    json_ok();
  }

  if ($action === 'create_room') {
    if (!$isAdmin) json_error('Accès refusé.', 403);
    $serviceId = (int)($data['service_id'] ?? 0);
    $number    = trim((string)($data['number'] ?? ''));
    $label     = trim((string)($data['label']  ?? ''));
    $beds      = max(1, (int)($data['beds'] ?? 1));
    if ($serviceId <= 0 || $number === '') json_error('Service et numéro requis.', 400);

    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM ward_rooms WHERE service_id=?");
    $stmt->execute([$serviceId]);
    $next = (int)$stmt->fetchColumn();

    $pdo->prepare("INSERT INTO ward_rooms (service_id,number,label,beds,sort_order) VALUES (?,?,?,?,?)")
        ->execute([$serviceId, $number, $label ?: null, $beds, $next]);
    json_ok(['id' => (int)$pdo->lastInsertId()]);
  }

  if ($action === 'edit_room') {
    if (!$isAdmin) json_error('Accès refusé.', 403);
    $id    = (int)($data['id']    ?? 0);
    $number= trim((string)($data['number'] ?? ''));
    $label = trim((string)($data['label']  ?? ''));
    $beds  = max(1, (int)($data['beds'] ?? 1));
    $status= (string)($data['status'] ?? 'clean');
    if ($id <= 0 || $number === '') json_error('Données invalides.', 400);
    if (!in_array($status, ['clean','dirty','maintenance'])) $status = 'clean';
    $pdo->prepare("UPDATE ward_rooms SET number=?,label=?,beds=?,status=? WHERE id=?")
        ->execute([$number, $label ?: null, $beds, $status, $id]);
    json_ok();
  }

  if ($action === 'set_room_status') {
    $id     = (int)($data['id']     ?? 0);
    $status = (string)($data['status'] ?? 'clean');
    if ($id <= 0) json_error('ID manquant.', 400);
    if (!in_array($status, ['clean','dirty','maintenance'])) json_error('Statut invalide.', 400);
    $pdo->prepare("UPDATE ward_rooms SET status=? WHERE id=?")->execute([$status, $id]);
    json_ok();
  }

  if ($action === 'delete_room') {
    if (!$isAdmin) json_error('Accès refusé.', 403);
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) json_error('ID manquant.', 400);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ward_patients WHERE room_id=? AND discharged_at IS NULL");
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() > 0) json_error('Libérez d\'abord tous les patients de cette chambre.', 400);
    $pdo->prepare("DELETE FROM ward_rooms WHERE id=?")->execute([$id]);
    json_ok();
  }

  if ($action === 'admit_patient') {
    $roomId      = (int)($data['room_id']      ?? 0);
    $name        = trim((string)($data['name']        ?? ''));
    $reason      = trim((string)($data['reason']      ?? ''));
    $practitioner= trim((string)($data['practitioner']?? ''));
    $status      = (string)($data['status'] ?? 'stable');
    $notes       = trim((string)($data['notes'] ?? ''));

    if ($roomId <= 0) json_error('Chambre requise.', 400);
    if ($name === '')  json_error('Nom du patient requis.', 400);
    if (!in_array($status, ['stable','monitoring','critical'])) $status = 'stable';

    $stmt = $pdo->prepare("SELECT beds FROM ward_rooms WHERE id=? LIMIT 1");
    $stmt->execute([$roomId]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) json_error('Chambre introuvable.', 404);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ward_patients WHERE room_id=? AND discharged_at IS NULL");
    $stmt->execute([$roomId]);
    if ((int)$stmt->fetchColumn() >= (int)$room['beds']) {
      json_error('Chambre complète.', 400);
    }

    $pdo->prepare("
      INSERT INTO ward_patients (room_id, name, reason, practitioner, status, notes, created_by)
      VALUES (?,?,?,?,?,?,?)
    ")->execute([$roomId, $name, $reason, $practitioner, $status, $notes ?: null, $uid]);

    json_ok(['id' => (int)$pdo->lastInsertId()]);
  }

  if ($action === 'update_patient') {
    $id          = (int)($data['id']           ?? 0);
    $name        = trim((string)($data['name']        ?? ''));
    $reason      = trim((string)($data['reason']      ?? ''));
    $practitioner= trim((string)($data['practitioner']?? ''));
    $status      = (string)($data['status'] ?? 'stable');
    $notes       = trim((string)($data['notes'] ?? ''));

    if ($id <= 0 || $name === '') json_error('Données invalides.', 400);
    if (!in_array($status, ['stable','monitoring','critical'])) $status = 'stable';

    $pdo->prepare("
      UPDATE ward_patients SET name=?,reason=?,practitioner=?,status=?,notes=? WHERE id=?
    ")->execute([$name, $reason, $practitioner, $status, $notes ?: null, $id]);
    json_ok();
  }

  if ($action === 'transfer_patient') {
    $id        = (int)($data['id']      ?? 0);
    $newRoomId = (int)($data['room_id'] ?? 0);
    if ($id <= 0 || $newRoomId <= 0) json_error('Données invalides.', 400);

    $stmt = $pdo->prepare("SELECT beds FROM ward_rooms WHERE id=? LIMIT 1");
    $stmt->execute([$newRoomId]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) json_error('Chambre introuvable.', 404);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ward_patients WHERE room_id=? AND discharged_at IS NULL AND id!=?");
    $stmt->execute([$newRoomId, $id]);
    if ((int)$stmt->fetchColumn() >= (int)$room['beds']) json_error('Chambre cible complète.', 400);

    $pdo->prepare("UPDATE ward_patients SET room_id=? WHERE id=?")->execute([$newRoomId, $id]);
    json_ok();
  }

  if ($action === 'discharge_patient') {
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) json_error('ID manquant.', 400);
    $pdo->prepare("UPDATE ward_patients SET discharged_at=NOW(), status='discharged' WHERE id=?")
        ->execute([$id]);

    $stmt = $pdo->prepare("SELECT room_id FROM ward_patients WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $roomId = (int)($stmt->fetchColumn() ?: 0);
    if ($roomId > 0) {
      $pdo->prepare("UPDATE ward_rooms SET status='dirty' WHERE id=?")->execute([$roomId]);
    }

    json_ok();
  }

  json_error('Action inconnue.', 400);

} catch (Throwable $e) {
  json_error('Erreur serveur.', 500, ['detail' => $e->getMessage()]);
}
