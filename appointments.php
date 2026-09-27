<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

function json_ok(array $data = []): void {
  echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
  exit;
}

function json_error(string $message, int $code = 400, array $extra = []): void {
  http_response_code($code);
  echo json_encode(['error' => $message] + $extra, JSON_UNESCAPED_UNICODE);
  exit;
}

function read_body(): array {
  $ct = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
  }
  return $_POST ?? [];
}

function to_mysql_datetime(string $iso): string {
  try {
    $dt = new DateTime($iso);
  } catch (Throwable $e) {
    json_error('Date/heure invalide.', 400);
  }
  return $dt->format('Y-m-d H:i:s');
}

function color_from_user_id(int $uid): string {
  $h = crc32((string)$uid);
  $hue = $h % 360;
  return "hsl($hue 70% 45%)";
}

start_session();

if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
  json_error('Non connecté.', 401);
}

$user = $_SESSION['user'];
$uid = (int)($user['id'] ?? 0);
if ($uid <= 0) json_error('Session invalide.', 500);

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  if ($method === 'GET') {
    $stmt = $pdo->query("
      SELECT
        a.id,
        a.title,
        a.start_at,
        a.end_at,
        a.notes,
        a.created_by,
        a.created_name,
        a.created_color,
        u.calendar_color
      FROM appointments a
      LEFT JOIN users u ON u.id = a.created_by
      ORDER BY a.start_at ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($rows as $r) {
      $notesRaw = (string)($r['notes'] ?? '');
      $notes = [];
      if ($notesRaw !== '') {
        $tmp = json_decode($notesRaw, true);
        if (is_array($tmp)) $notes = $tmp;
      }

      $creatorId = (int)($r['created_by'] ?? 0);
      $creatorName = trim((string)($r['created_name'] ?? ''));
      if ($creatorName === '') $creatorName = '—';

      $color = trim((string)($r['calendar_color'] ?? ''));
      if ($color === '') {
        $color = trim((string)($r['created_color'] ?? ''));
      }
      if ($color === '') {
        $color = color_from_user_id($creatorId > 0 ? $creatorId : 1);
      }

      $events[] = [
        'id'    => (string)$r['id'],
        'title' => (string)$r['title'],
        'start' => (string)$r['start_at'],
        'end'   => (string)$r['end_at'],
        'color' => $color,
        'extendedProps' => [
          'patient_name'      => (string)($notes['patient_name'] ?? ''),
          'reason'            => (string)($notes['reason'] ?? ''),
          'created_by'        => $creatorId,
          'creator'           => $creatorName,
          'practitioner_name' => $creatorName,
          'can_edit'          => ($creatorId === $uid),
        ],
      ];
    }

    echo json_encode($events, JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($method !== 'POST') {
    json_error('Méthode non autorisée.', 405);
  }

  $data = read_body();
  $action = (string)($data['action'] ?? 'create');

  if ($action === 'delete') {
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) json_error('ID manquant.', 400);

    $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ? AND created_by = ?");
    $stmt->execute([$id, $uid]);

    if ($stmt->rowCount() <= 0) {
      json_error("Vous ne pouvez supprimer que vos propres rendez-vous.", 403);
    }

    json_ok();
  }

  if ($action === 'update') {
    $id = (int)($data['id'] ?? 0);
    $startIso = (string)($data['start'] ?? '');
    $endIso   = (string)($data['end'] ?? '');

    if ($id <= 0 || $startIso === '' || $endIso === '') {
      json_error('Champs requis manquants (id, start, end).', 400);
    }

    $start = to_mysql_datetime($startIso);
    $end   = to_mysql_datetime($endIso);

    $stmt = $pdo->prepare("
      UPDATE appointments
      SET start_at = ?, end_at = ?
      WHERE id = ? AND created_by = ?
    ");
    $stmt->execute([$start, $end, $id, $uid]);

    if ($stmt->rowCount() <= 0) {
      json_error("Vous ne pouvez modifier que vos propres rendez-vous.", 403);
    }

    json_ok();
  }

  $patient  = trim((string)($data['patient_name'] ?? ''));
  $reason   = trim((string)($data['reason'] ?? ''));
  $startIso = (string)($data['start'] ?? '');
  $endIso   = (string)($data['end'] ?? '');

  if ($patient === '' || $reason === '' || $startIso === '' || $endIso === '') {
    json_error('Champs requis manquants (patient, motif, start, end).', 400);
  }

  $start = to_mysql_datetime($startIso);
  $end   = to_mysql_datetime($endIso);

  $creatorName = trim((string)($user['username'] ?? ''));
  if ($creatorName === '') $creatorName = 'Utilisateur';

  $title = $creatorName;

  $notes = json_encode([
    'patient_name' => $patient,
    'reason'       => $reason,
  ], JSON_UNESCAPED_UNICODE);

  $creatorColor = trim((string)($user['calendar_color'] ?? ''));
  if ($creatorColor === '') {
    $creatorColor = color_from_user_id($uid);
  }

  $stmt = $pdo->prepare("
    INSERT INTO appointments (user_id, title, start_at, end_at, created_by, created_name, created_color, notes, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
  ");
  $stmt->execute([$uid, $title, $start, $end, $uid, $creatorName, $creatorColor, $notes]);

  $id = (int)$pdo->lastInsertId();

  json_ok([
    'event' => [
      'id'    => (string)$id,
      'title' => $title,
      'start' => $start,
      'end'   => $end,
      'color' => $creatorColor,
      'extendedProps' => [
        'patient_name'      => $patient,
        'reason'            => $reason,
        'created_by'        => $uid,
        'creator'           => $creatorName,
        'practitioner_name' => $creatorName,
        'can_edit'          => true,
      ],
    ]
  ]);
} catch (PDOException $e) {
  json_error('Erreur base de données.', 500, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
  json_error('Erreur serveur.', 500, ['detail' => $e->getMessage()]);
}