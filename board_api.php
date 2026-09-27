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
function board_csrf_ok(string $token): bool {
  start_session();
  if ($token === '') return false;
  if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) return false;
  return hash_equals($_SESSION['csrf'], $token);
}

start_session();
require_login();

$CAP = ['lincoln'=>1,'adam'=>2,'tango'=>3,'queen'=>4];

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  if ($method === 'GET') {
    $settings = $pdo->query("SELECT hospital_status FROM board_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    $hospitalStatus = $settings ? (string)$settings['hospital_status'] : 'NORMAL';

    $groups = $pdo->query("
      SELECT id, group_type, label, sort_order
      FROM board_groups
      ORDER BY group_type ASC, sort_order ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $peopleRows = $pdo->query("
      SELECT id, username AS name,
             COALESCE(NULLIF(dispatch_role,''), grade, '') AS role,
             dispatch_hospital_site AS hospital_site,
             dispatch_status_text AS status_text, dispatch_status_106 AS status_106,
             dispatch_in_intervention AS in_intervention, dispatch_has_radio AS has_radio,
             dispatch_list_id AS list_id, dispatch_group_id AS group_id, dispatch_sort_order AS sort_order,
             matricule, phone, grade
      FROM users
      WHERE is_active = 1
      ORDER BY dispatch_sort_order ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $formationRows = $pdo->query("
      SELECT a.user_id, f.emoji, f.name
      FROM personnel_formations_assign a
      JOIN personnel_formations f ON f.id = a.formation_id
      ORDER BY a.user_id ASC, f.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $formationsByUser = [];
    foreach ($formationRows as $fr) {
      $uid = (int)($fr['user_id'] ?? 0);
      if ($uid <= 0) continue;
      if (!isset($formationsByUser[$uid])) $formationsByUser[$uid] = [];
      $formationsByUser[$uid][] = [
        'emoji' => (string)($fr['emoji'] ?? ''),
        'name'  => (string)($fr['name'] ?? ''),
      ];
    }

    $people = [];
    foreach ($peopleRows as $r) {
      $uid = (int)$r['id'];
      $people[] = [
        'id'              => $uid,
        'name'            => (string)$r['name'],
        'role'            => (string)$r['role'],
        'hospital_site'   => (string)($r['hospital_site'] ?? ''),
        'status_text'     => (string)$r['status_text'],
        'status_106'      => (int)($r['status_106'] ?? 0),
        'in_intervention' => (int)$r['in_intervention'],
        'has_radio'       => (int)($r['has_radio'] ?? 0),
        'formations'      => $formationsByUser[$uid] ?? [],
        'matricule'       => (string)($r['matricule'] ?? ''),
        'phone'           => (string)($r['phone']     ?? ''),
        'grade'           => (string)($r['grade']     ?? ''),
      ];
    }

    $lists = ['on'=>[], 'off'=>[]];
    $groupMembers = [];

    foreach ($peopleRows as $r) {
      $uid  = (int)$r['id'];
      $gid  = $r['group_id'] !== null ? (int)$r['group_id'] : null;
      $listId = (string)($r['list_id'] ?? 'off');

      if ($gid !== null) {
        $k = (string)$gid;
        if (!isset($groupMembers[$k])) $groupMembers[$k] = [];
        $groupMembers[$k][] = $uid;
      } else {
        if (!isset($lists[$listId])) $lists[$listId] = [];
        $lists[$listId][] = $uid;
      }
    }

    foreach ($groups as $g) {
      $gid = (string)$g['id'];
      if (!isset($groupMembers[$gid])) $groupMembers[$gid] = [];
    }

    json_ok([
      'hospital_status' => $hospitalStatus,
      'groups'          => $groups,
      'people'          => $people,
      'lists'           => $lists,
      'group_members'   => $groupMembers,
      'cap'             => $CAP,
    ]);
  }

  if ($method !== 'POST') json_error('Méthode non autorisée.', 405);

  $data = read_json();
  $csrf = isset($data['csrf']) ? (string)$data['csrf'] : '';
  if (!board_csrf_ok($csrf)) json_error('Requête invalide (CSRF). Rafraîchis la page.', 403);

  $action = (string)($data['action'] ?? '');

  if ($action === 'set_hospital_status') {
    $hs = trim((string)($data['hospital_status'] ?? ''));
    if ($hs === '' || mb_strlen($hs) > 80) json_error('Statut invalide.', 400);
    $stmt = $pdo->prepare("UPDATE board_settings SET hospital_status = ? WHERE id=1");
    $stmt->execute([$hs]);
    json_ok();
  }

  if ($action === 'create_person') {

    $name = trim((string)($data['name'] ?? ''));
    $role = trim((string)($data['role'] ?? ''));
    $list = trim((string)($data['list'] ?? 'off'));
    $hospitalSite = trim((string)($data['hospital_site'] ?? ''));

    if ($name === '') json_error('Nom requis.', 400);
    if (mb_strlen($name) > 80) json_error('Nom trop long.', 400);
    if (mb_strlen($role) > 40) json_error('Rôle trop long.', 400);
    if (!in_array($list, ['on','off'], true)) json_error('Liste invalide.', 400);
    if ($hospitalSite !== '' && !in_array($hospitalSite, ['north', 'south'], true)) {
      json_error('Site hôpital invalide.', 400);
    }

    $stmt = $pdo->prepare("SELECT COALESCE(MAX(dispatch_sort_order),0) FROM users WHERE is_active=1 AND dispatch_group_id IS NULL AND dispatch_list_id=?");
    $stmt->execute([$list]);
    $next = ((int)$stmt->fetchColumn()) + 1;

    $stmt = $pdo->prepare("
      INSERT INTO users (username, password_hash, role, is_active, dispatch_role, dispatch_hospital_site, dispatch_list_id, dispatch_sort_order)
      VALUES (?, '', 'member', 1, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $role, $hospitalSite !== '' ? $hospitalSite : null, $list, $next]);

    json_ok(['id' => (int)$pdo->lastInsertId()]);
  }

  if ($action === 'update_person') {
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) json_error('ID manquant.', 400);

    $status = trim((string)($data['status_text'] ?? ''));
    $hospitalSite = trim((string)($data['hospital_site'] ?? ''));
    if (mb_strlen($status) > 80) json_error('Statut trop long.', 400);
    if ($hospitalSite !== '' && !in_array($hospitalSite, ['north', 'south'], true)) {
      json_error('Site hôpital invalide.', 400);
    }

    $inInter  = !empty($data['in_intervention']) ? 1 : 0;
    $status106 = !empty($data['status_106']) ? 1 : 0;
    $hasRadio = !empty($data['has_radio']) ? 1 : 0;

    $stmt = $pdo->prepare("
      UPDATE users
      SET dispatch_status_text=?, dispatch_in_intervention=?, dispatch_status_106=?,
          dispatch_has_radio=?, dispatch_hospital_site=?
      WHERE id=? AND is_active=1
    ");
    $stmt->execute([
      $status, $inInter, $status106, $hasRadio,
      $hospitalSite !== '' ? $hospitalSite : null,
      $id
    ]);

    json_ok();
  }

  if ($action === 'delete_person') {
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) json_error('ID manquant.', 400);

    $stmt = $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?");
    $stmt->execute([$id]);
    json_ok();
  }

  if ($action === 'create_group') {
    $type  = trim((string)($data['group_type'] ?? ''));
    $label = trim((string)($data['label'] ?? ''));

    if (!isset($CAP[$type])) json_error('Type invalide.', 400);
    if ($label === '' || mb_strlen($label) > 40) json_error('Nom invalide.', 400);

    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) m FROM board_groups WHERE group_type=?");
    $stmt->execute([$type]);
    $next = ((int)$stmt->fetchColumn()) + 1;

    $stmt = $pdo->prepare("INSERT INTO board_groups (group_type, label, sort_order) VALUES (?,?,?)");
    $stmt->execute([$type, $label, $next]);
    json_ok(['id' => (int)$pdo->lastInsertId()]);
  }

  if ($action === 'rename_group') {
    $id    = (int)($data['id'] ?? 0);
    $label = trim((string)($data['label'] ?? ''));
    if ($id <= 0) json_error('ID manquant.', 400);
    if ($label === '' || mb_strlen($label) > 40) json_error('Nom invalide.', 400);
    $stmt = $pdo->prepare("UPDATE board_groups SET label=? WHERE id=?");
    $stmt->execute([$label, $id]);
    json_ok();
  }

  if ($action === 'delete_group') {
    $id    = (int)($data['id'] ?? 0);
    $force = !empty($data['force']);
    if ($id <= 0) json_error('ID manquant.', 400);

    if ($force) {
      $pdo->beginTransaction();
      try {

        $stmt = $pdo->prepare("SELECT COALESCE(MAX(dispatch_sort_order),0) FROM users WHERE is_active=1 AND dispatch_group_id IS NULL AND dispatch_list_id='on'");
        $stmt->execute();
        $order = ((int)$stmt->fetchColumn()) + 1;

        $stmt = $pdo->prepare("SELECT id FROM users WHERE is_active=1 AND dispatch_group_id=? ORDER BY dispatch_sort_order ASC, id ASC");
        $stmt->execute([$id]);
        $memberIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $upd = $pdo->prepare("UPDATE users SET dispatch_group_id=NULL, dispatch_list_id='on', dispatch_sort_order=? WHERE id=?");
        foreach ($memberIds as $uid) {
          $upd->execute([$order, (int)$uid]);
          $order++;
        }

        $pdo->prepare("DELETE FROM board_groups WHERE id=?")->execute([$id]);
        $pdo->commit();
      } catch (Throwable $e) {
        $pdo->rollBack();
        json_error('Erreur DB.', 500);
      }
      json_ok();
    } else {
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_active=1 AND dispatch_group_id=?");
      $stmt->execute([$id]);
      $cnt = (int)$stmt->fetchColumn();
      if ($cnt > 0) json_error("Unité non vide ($cnt).", 400);

      $pdo->prepare("DELETE FROM board_groups WHERE id=?")->execute([$id]);
      json_ok();
    }
  }

  if ($action === 'save_state') {
    $lists      = $data['lists'] ?? null;
    $groups     = $data['groups'] ?? null;
    $groupOrder = $data['group_order'] ?? null;

    if (!is_array($lists) || !is_array($groups) || !is_array($groupOrder)) json_error('Format invalide.', 400);

    $gtypes = [];
    $rows = $pdo->query("SELECT id, group_type FROM board_groups")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) $gtypes[(string)$r['id']] = (string)$r['group_type'];

    foreach ($groups as $gid => $memberIds) {
      $gidStr = (string)$gid;
      if (!isset($gtypes[$gidStr])) continue;
      $type = $gtypes[$gidStr];
      $cap  = $CAP[$type] ?? 0;
      if (is_array($memberIds) && $cap > 0 && count($memberIds) > $cap) {
        json_error(strtoupper($type) . " max $cap personnes.", 400);
      }
    }

    $curr = [];
    $rows = $pdo->query("SELECT id, username, dispatch_list_id AS list_id, dispatch_group_id AS group_id FROM users WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $uid  = (int)$r['id'];
      $gid  = $r['group_id'] !== null ? (int)$r['group_id'] : null;
      $list = (string)($r['list_id'] ?? 'off');
      $on   = ($gid !== null) || ($list === 'on');
      $curr[$uid] = ['on' => $on, 'name' => (string)$r['username']];
    }

    $next = [];
    foreach ($curr as $uid => $c) $next[$uid] = ['on' => false, 'name' => $c['name']];

    foreach (['on','off'] as $lid) {
      $ids = $lists[$lid] ?? [];
      if (!is_array($ids)) continue;
      foreach ($ids as $pid) {
        $pid = (int)$pid;
        if ($pid > 0 && isset($next[$pid])) $next[$pid]['on'] = ($lid === 'on');
      }
    }
    foreach ($groups as $gid => $memberIds) {
      if (!is_array($memberIds)) continue;
      foreach ($memberIds as $pid) {
        $pid = (int)$pid;
        if ($pid > 0 && isset($next[$pid])) $next[$pid]['on'] = true;
      }
    }

    $pdo->beginTransaction();
    try {

      foreach ($groupOrder as $type => $ids) {
        $type = (string)$type;
        if (!isset($CAP[$type]) || !is_array($ids)) continue;
        $order = 1;
        $stmt  = $pdo->prepare("UPDATE board_groups SET sort_order=? WHERE id=? AND group_type=?");
        foreach ($ids as $gid) {
          $gidInt = (int)$gid;
          if ($gidInt <= 0) continue;
          $stmt->execute([$order, $gidInt, $type]);
          $order++;
        }
      }

      $stmtGroup = $pdo->prepare("UPDATE users SET dispatch_group_id=?, dispatch_list_id=NULL, dispatch_sort_order=? WHERE id=? AND is_active=1");
      foreach ($groups as $gid => $memberIds) {
        $gidInt = (int)$gid;
        if ($gidInt <= 0 || !is_array($memberIds)) continue;
        $order = 1;
        foreach ($memberIds as $pid) {
          $pidInt = (int)$pid;
          if ($pidInt <= 0) continue;
          $stmtGroup->execute([$gidInt, $order, $pidInt]);
          $order++;
        }
      }

      $stmtList = $pdo->prepare("UPDATE users SET dispatch_group_id=NULL, dispatch_list_id=?, dispatch_sort_order=? WHERE id=? AND is_active=1");
      foreach (['on','off'] as $listId) {
        $ids = $lists[$listId] ?? [];
        if (!is_array($ids)) continue;
        $order = 1;
        foreach ($ids as $pid) {
          $pidInt = (int)$pid;
          if ($pidInt <= 0) continue;
          $stmtList->execute([$listId, $order, $pidInt]);
          $order++;
        }
      }

      $startStmt = $pdo->prepare("
        INSERT INTO salary_shifts (user_id, person_name, started_at)
        SELECT ?, ?, NOW()
        WHERE NOT EXISTS (
          SELECT 1 FROM salary_shifts WHERE user_id=? AND ended_at IS NULL
        )
      ");
      $stopStmt = $pdo->prepare("
        UPDATE salary_shifts
        SET ended_at = NOW()
        WHERE user_id = ? AND ended_at IS NULL
      ");

      foreach ($next as $uid => $n) {
        $wasOn = !empty($curr[$uid]['on']);
        $isOn  = !empty($n['on']);

        if (!$wasOn && $isOn) {
          $startStmt->execute([$uid, (string)$n['name'], $uid]);
        } elseif ($wasOn && !$isOn) {
          $stopStmt->execute([$uid]);
        }
      }

      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      json_error('Erreur DB.', 500, ['detail' => $e->getMessage()]);
    }

    json_ok();
  }

  json_error('Action inconnue.', 400);

} catch (PDOException $e) {
  json_error('Erreur base de données.', 500, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
  json_error('Erreur serveur.', 500, ['detail' => $e->getMessage()]);
}
