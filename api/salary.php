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
function csrf_ok(string $token): bool {
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

function current_user_meta(PDO $pdo): array {
  $u   = current_user();
  $uid = (int)($u['id'] ?? 0);
  if ($uid <= 0) json_error('Session invalide.', 401);

  $stmt = $pdo->prepare("SELECT id, username, grade, iban FROM users WHERE id=? LIMIT 1");
  $stmt->execute([$uid]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) json_error('Utilisateur introuvable.', 404);

  return [
    'user_id'  => (int)$row['id'],
    'username' => (string)$row['username'],
    'iban'     => $row['iban'],
    'grade'    => (string)($row['grade'] ?? ''),
  ];
}

function parse_dt(string $s): string {
  $s = trim($s);
  if ($s === '') json_error('Date/heure manquante.', 400);
  foreach (['Y-m-d H:i:s','Y-m-d\\TH:i','Y-m-d\\TH:i:s','Y-m-d H:i'] as $fmt) {
    $dt = DateTime::createFromFormat($fmt, $s);
    if ($dt instanceof DateTime) return $dt->format('Y-m-d H:i:s');
  }
  try { return (new DateTime($s))->format('Y-m-d H:i:s'); }
  catch (Throwable $e) { json_error('Format de date invalide.', 400); }
}

function parse_day(string $s, string $fallback): string {
  $s  = trim($s);
  if ($s === '') return $fallback;
  $dt = DateTime::createFromFormat('Y-m-d', $s);
  if (!$dt) json_error('Date invalide.', 400);
  return $dt->format('Y-m-d');
}

function assert_own_shift(PDO $pdo, int $shiftId, int $userId): array {
  $stmt = $pdo->prepare("SELECT id, user_id, started_at, ended_at FROM salary_shifts WHERE id=? LIMIT 1");
  $stmt->execute([$shiftId]);
  $shift = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$shift) json_error('Shift introuvable.', 404);
  if ((int)$shift['user_id'] !== $userId) json_error('Accès refusé.', 403);
  return $shift;
}

function shift_overlaps(PDO $pdo, int $userId, string $start, ?string $end, int $excludeId = 0): bool {
  $sql = "
    SELECT 1 FROM salary_shifts s
    WHERE s.user_id = ?
      AND (? <= COALESCE(s.ended_at, '9999-12-31 23:59:59'))
      AND (COALESCE(?, '9999-12-31 23:59:59') >= s.started_at)
  ";
  $params = [$userId, $start, $end];
  if ($excludeId > 0) { $sql .= " AND s.id <> ?"; $params[] = $excludeId; }
  $sql .= " LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return (bool)$stmt->fetchColumn();
}

function build_global_payload(PDO $pdo, string $from, string $to, int $defaultRate): array {
  $fromDt = $from . ' 00:00:00';
  $toDt   = $to   . ' 23:59:59';

  $active = $pdo->query("
    SELECT s.id, s.user_id, s.person_name, s.started_at
    FROM salary_shifts s
    WHERE s.ended_at IS NULL
    ORDER BY s.started_at DESC
  ")->fetchAll(PDO::FETCH_ASSOC);

  $stmt = $pdo->prepare("
    SELECT s.id, s.user_id, s.person_name, s.started_at, s.ended_at,
           u.username, u.grade, u.iban
    FROM salary_shifts s
    LEFT JOIN users u ON u.id = s.user_id
    WHERE s.started_at BETWEEN ? AND ?
    ORDER BY s.started_at DESC
    LIMIT 2000
  ");
  $stmt->execute([$fromDt, $toDt]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $now           = new DateTime('now');
  $history       = [];
  $totalsByPerson= [];
  $grandSeconds  = 0;
  $grandAmount   = 0;

  foreach ($rows as $r) {
    $uid  = (int)($r['user_id'] ?? 0);
    $name = trim((string)($r['username'] ?? ''));
    if ($name === '') $name = (string)$r['person_name'];

    $grade  = trim((string)($r['grade'] ?? ''));
    $rate   = GRADE_RATES[$grade] ?? $defaultRate;

    $s   = new DateTime((string)$r['started_at']);
    $e   = $r['ended_at'] ? new DateTime((string)$r['ended_at']) : $now;
    $sec = (int)max(0, $e->getTimestamp() - $s->getTimestamp());
    $amt = (int)round(($sec / 3600) * $rate);
    $key = 'u:' . $uid;

    $history[] = [
      'id'          => (int)$r['id'],
      'user_id'     => $uid,
      'person_name' => $name,
      'grade'       => $grade,
      'started_at'  => (string)$r['started_at'],
      'ended_at'    => $r['ended_at'] ? (string)$r['ended_at'] : null,
      'seconds'     => $sec,
      'amount'      => $amt,
      'rate'        => $rate,
    ];

    if (!isset($totalsByPerson[$key])) {
      $totalsByPerson[$key] = [
        'user_id'     => $uid,
        'person_name' => $name,
        'grade'       => $grade,
        'rate'        => $rate,
        'seconds'     => 0,
        'amount'      => 0,
        'iban'        => $r['iban'] ?? null,
      ];
    }
    $totalsByPerson[$key]['seconds'] += $sec;
    $totalsByPerson[$key]['amount']  += $amt;
    $grandSeconds += $sec;
    $grandAmount  += $amt;
  }

  foreach ($active as $a) {
    $uid = (int)($a['user_id'] ?? 0);
    if ($uid <= 0) continue;
    $key = 'u:' . $uid;
    if (!isset($totalsByPerson[$key])) {
      $stmt = $pdo->prepare("SELECT username, grade, iban FROM users WHERE id=? LIMIT 1");
      $stmt->execute([$uid]);
      $meta = $stmt->fetch(PDO::FETCH_ASSOC);
      $name  = trim((string)($meta['username'] ?? ''));
      if ($name === '') $name = (string)$a['person_name'];
      $grade = trim((string)($meta['grade'] ?? ''));
      $rate  = GRADE_RATES[$grade] ?? $defaultRate;
      $totalsByPerson[$key] = [
        'user_id' => $uid, 'person_name' => $name, 'grade' => $grade,
        'rate' => $rate, 'seconds' => 0, 'amount' => 0, 'iban' => $meta['iban'] ?? null,
      ];
    }
  }

  $primesMap = [];
  try {
    $stmtP = $pdo->prepare("
      SELECT user_id, SUM(amount) AS total_prime
      FROM salary_primes
      WHERE created_at BETWEEN ? AND ?
      GROUP BY user_id
    ");
    $stmtP->execute([$fromDt, $toDt]);
    foreach ($stmtP->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $primesMap[(int)$row['user_id']] = (int)$row['total_prime'];
    }
  } catch (Throwable $e) {

  }

  foreach ($totalsByPerson as $key => &$person) {
    $uid   = (int)$person['user_id'];
    $prime = $primesMap[$uid] ?? 0;
    $person['prime']  = $prime;
    $person['amount'] += $prime;
    $grandAmount      += $prime;
  }
  unset($person);

  $people = array_values($totalsByPerson);
  usort($people, fn($a, $b) => ($b['amount'] <=> $a['amount']) ?: ($b['seconds'] <=> $a['seconds']));

  return [
    'default_rate' => $defaultRate,
    'from'         => $from,
    'to'           => $to,
    'active'       => $active,
    'people'       => $people,
    'history'      => $history,
    'grand'        => ['seconds' => $grandSeconds, 'amount' => $grandAmount],
  ];
}

function build_my_days(PDO $pdo, int $userId, string $from, string $to): array {
  $stmt = $pdo->prepare("
    SELECT id, user_id, person_name, started_at, ended_at
    FROM salary_shifts
    WHERE user_id = ?
      AND started_at BETWEEN ? AND ?
    ORDER BY started_at DESC, id DESC
    LIMIT 1000
  ");
  $stmt->execute([$userId, $from . ' 00:00:00', $to . ' 23:59:59']);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $days = [];
  foreach ($rows as $r) {
    $day = substr((string)$r['started_at'], 0, 10);
    if (!isset($days[$day])) $days[$day] = ['date' => $day, 'label' => $day, 'items' => []];
    $days[$day]['items'][] = [
      'id'         => (int)$r['id'],
      'started_at' => (string)$r['started_at'],
      'ended_at'   => $r['ended_at'] ? (string)$r['ended_at'] : null,
      'is_active'  => $r['ended_at'] === null,
    ];
  }

  return array_values($days);
}

start_session();
require_login();

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $method      = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $defaultRate = (int)$pdo->query("SELECT hourly_rate FROM salary_settings WHERE id=1")->fetchColumn();
  if ($defaultRate <= 0) $defaultRate = 1000;

  if ($method === 'GET') {

    if (isset($_GET['admin_user_id'])) {
      $u = current_user();
      if (($u['role'] ?? '') !== 'admin') json_error('Accès refusé.', 403);

      $targetUserId = (int)$_GET['admin_user_id'];
      if ($targetUserId <= 0) json_error('user_id invalide.', 400);

      $from = trim((string)($_GET['from'] ?? ''));
      $to   = trim((string)($_GET['to']   ?? ''));

      $sql    = "SELECT id, started_at, ended_at FROM salary_shifts WHERE user_id=?";
      $params = [$targetUserId];
      if ($from !== '' && $to !== '') {
        $sql .= " AND started_at BETWEEN ? AND ?";
        $params[] = $from . ' 00:00:00';
        $params[] = $to   . ' 23:59:59';
      }
      $sql .= " ORDER BY started_at DESC LIMIT 500";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      json_ok(['shifts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    $from = parse_day((string)($_GET['from'] ?? ''), (new DateTime('now'))->modify('-7 days')->format('Y-m-d'));
    $to   = parse_day((string)($_GET['to']   ?? ''), (new DateTime('now'))->format('Y-m-d'));

    $payload = build_global_payload($pdo, $from, $to, $defaultRate);

    $scope = (string)($_GET['scope'] ?? 'global');
    if ($scope === 'mine') {
      $meta = current_user_meta($pdo);
      $payload['mine'] = [
        'user' => [
          'id'       => $meta['user_id'],
          'username' => $meta['username'],
        ],
        'days' => build_my_days($pdo, $meta['user_id'], $from, $to),
      ];
    }

    json_ok($payload);
  }

  if ($method !== 'POST') json_error('Méthode non autorisée.', 405);

  $data = read_json();
  $csrf = isset($data['csrf']) ? (string)$data['csrf'] : '';
  if (!csrf_ok($csrf)) json_error('Requête invalide (CSRF).', 403);

  $action = (string)($data['action'] ?? '');

  $u    = current_user();
  $isAdmin = ($u['role'] ?? '') === 'admin';

  if ($action === 'admin_get_shifts') {
    if (!$isAdmin) json_error('Accès refusé.', 403);
    $targetUserId = (int)($data['user_id'] ?? 0);
    if ($targetUserId <= 0) json_error('user_id invalide.', 400);

    $from = trim((string)($data['from'] ?? ''));
    $to   = trim((string)($data['to'] ?? ''));

    $sql = "SELECT id, started_at, ended_at FROM salary_shifts WHERE user_id=?";
    $params = [$targetUserId];
    if ($from !== '' && $to !== '') {
      $sql .= " AND started_at BETWEEN ? AND ?";
      $params[] = $from . ' 00:00:00';
      $params[] = $to   . ' 23:59:59';
    }
    $sql .= " ORDER BY started_at DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_ok(['shifts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
  }

  if ($action === 'admin_update_shift') {
    if (!$isAdmin) json_error('Accès refusé.', 403);
    $shiftId   = (int)($data['shift_id'] ?? 0);
    $startedAt = parse_dt((string)($data['started_at'] ?? ''));
    $endedAtRaw= trim((string)($data['ended_at'] ?? ''));
    $endedAt   = $endedAtRaw !== '' ? parse_dt($endedAtRaw) : null;

    if ($shiftId <= 0) json_error('shift_id invalide.', 400);
    if ($endedAt !== null && strtotime($endedAt) <= strtotime($startedAt)) {
      json_error('La fin doit être après le début.', 400);
    }

    $stmt = $pdo->prepare("UPDATE salary_shifts SET started_at=?, ended_at=? WHERE id=? LIMIT 1");
    $stmt->execute([$startedAt, $endedAt, $shiftId]);
    json_ok(['message' => 'Shift mis à jour.']);
  }

  if ($action === 'admin_delete_shift') {
    if (!$isAdmin) json_error('Accès refusé.', 403);
    $shiftId = (int)($data['shift_id'] ?? 0);
    if ($shiftId <= 0) json_error('shift_id invalide.', 400);

    $pdo->prepare("DELETE FROM salary_shifts WHERE id=? LIMIT 1")->execute([$shiftId]);
    json_ok(['message' => 'Shift supprimé.']);
  }

  if ($action === 'admin_create_shift') {
    if (!$isAdmin) json_error('Accès refusé.', 403);
    $targetUserId = (int)($data['user_id'] ?? 0);
    if ($targetUserId <= 0) json_error('user_id invalide.', 400);

    $startedAt  = parse_dt((string)($data['started_at'] ?? ''));
    $endedAtRaw = trim((string)($data['ended_at'] ?? ''));
    $endedAt    = $endedAtRaw !== '' ? parse_dt($endedAtRaw) : null;

    if ($endedAt !== null && strtotime($endedAt) <= strtotime($startedAt)) {
      json_error('La fin doit être après le début.', 400);
    }

    $stmt = $pdo->prepare("SELECT username FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$targetUserId]);
    $name = (string)($stmt->fetchColumn() ?: '');

    $stmt = $pdo->prepare("INSERT INTO salary_shifts (user_id, person_name, started_at, ended_at) VALUES (?,?,?,?)");
    $stmt->execute([$targetUserId, $name, $startedAt, $endedAt]);
    json_ok(['id' => (int)$pdo->lastInsertId()]);
  }

  $meta   = current_user_meta($pdo);
  $userId = (int)$meta['user_id'];

  if ($action === 'create_shift') {
    $startedAt  = parse_dt((string)($data['started_at'] ?? ''));
    $endedAtRaw = trim((string)($data['ended_at'] ?? ''));
    $endedAt    = $endedAtRaw !== '' ? parse_dt($endedAtRaw) : null;

    if ($endedAt !== null && strtotime($endedAt) <= strtotime($startedAt)) {
      json_error('La fin doit être après le début.', 400);
    }
    if (shift_overlaps($pdo, $userId, $startedAt, $endedAt)) {
      json_error('Ce shift chevauche déjà un de tes horaires.', 409);
    }

    $stmt = $pdo->prepare("INSERT INTO salary_shifts (user_id, person_name, started_at, ended_at) VALUES (?,?,?,?)");
    $stmt->execute([$userId, (string)$meta['username'], $startedAt, $endedAt]);
    json_ok(['message' => 'Shift créé.', 'id' => (int)$pdo->lastInsertId()]);
  }

  if ($action === 'update_shift') {
    $shiftId = (int)($data['shift_id'] ?? 0);
    if ($shiftId <= 0) json_error('shift_id invalide.', 400);

    $shift       = assert_own_shift($pdo, $shiftId, $userId);
    $startedAt   = parse_dt((string)($data['started_at'] ?? $shift['started_at'] ?? ''));
    $endedAtInput= array_key_exists('ended_at', $data) ? (string)$data['ended_at'] : (string)($shift['ended_at'] ?? '');
    $endedAt     = trim($endedAtInput) !== '' ? parse_dt($endedAtInput) : null;

    if ($endedAt !== null && strtotime($endedAt) <= strtotime($startedAt)) {
      json_error('La fin doit être après le début.', 400);
    }
    if (shift_overlaps($pdo, $userId, $startedAt, $endedAt, $shiftId)) {
      json_error('Ce shift chevauche déjà un de tes horaires.', 409);
    }

    $stmt = $pdo->prepare("UPDATE salary_shifts SET person_name=?, started_at=?, ended_at=? WHERE id=? AND user_id=? LIMIT 1");
    $stmt->execute([(string)$meta['username'], $startedAt, $endedAt, $shiftId, $userId]);
    json_ok(['message' => 'Shift mis à jour.']);
  }

  if ($action === 'delete_shift') {
    $shiftId = (int)($data['shift_id'] ?? 0);
    if ($shiftId <= 0) json_error('shift_id invalide.', 400);
    assert_own_shift($pdo, $shiftId, $userId);

    $stmt = $pdo->prepare("DELETE FROM salary_shifts WHERE id=? AND user_id=? LIMIT 1");
    $stmt->execute([$shiftId, $userId]);
    json_ok(['message' => 'Shift supprimé.']);
  }

  if ($action === 'add_prime') {
    if (!$isAdmin) json_error('Accès refusé.', 403);
    $targetUserId = (int)($data['user_id'] ?? 0);
    $amount       = (int)($data['amount']  ?? 0);
    if ($targetUserId <= 0) json_error('user_id invalide.', 400);
    if ($amount <= 0)       json_error('Montant invalide.', 400);

    $stmt = $pdo->prepare("INSERT INTO salary_primes (user_id, amount, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$targetUserId, $amount]);
    json_ok(['id' => (int)$pdo->lastInsertId(), 'message' => 'Prime enregistrée.']);
  }

  json_error('Action inconnue.', 400);

} catch (Throwable $e) {
  json_error('Erreur serveur.', 500, ['detail' => $e->getMessage()]);
}
