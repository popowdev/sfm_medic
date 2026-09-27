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
function feed_csrf_ok(string $token): bool {
  start_session();
  if ($token === '') return false;
  if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) return false;
  return hash_equals($_SESSION['csrf'], $token);
}

start_session();
require_login();

$u       = current_user();
$uid     = (int)($u['id'] ?? 0);
$isAdmin = ($u['role'] ?? '') === 'admin';

$feedType = in_array($_GET['type'] ?? '', ['internal','service_note'])
  ? ($_GET['type'])
  : 'internal';

$canPost = ($feedType === 'internal') || $isAdmin;

try {
  $pdo    = db();
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  if ($method === 'GET') {
    $before = isset($_GET['before']) ? (int)$_GET['before'] : 0;
    $limit  = 20;

    $params = [$uid, $feedType];
    $sql = "
      SELECT p.id, p.body, p.created_at, p.updated_at, p.pinned, p.type,
             u.id AS user_id, u.username, u.grade,
             (SELECT COUNT(*) FROM feed_reactions r WHERE r.post_id = p.id AND r.emoji = '❤️') AS hearts,
             (SELECT COUNT(*) FROM feed_reactions r WHERE r.post_id = p.id AND r.user_id = ?) AS my_reaction,
             (SELECT COUNT(*) FROM feed_comments c WHERE c.post_id = p.id) AS comment_count
      FROM feed_posts p
      JOIN users u ON u.id = p.user_id
      WHERE p.type = ?
    ";

    if ($before > 0) {
      $sql .= " AND p.id < ?";
      $params[] = $before;
    }

    $sql .= " ORDER BY p.pinned DESC, p.created_at DESC LIMIT " . ($limit + 1);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasMore = count($rows) > $limit;
    if ($hasMore) array_pop($rows);

    $postIds  = array_column($rows, 'id');
    $comments = [];
    if ($postIds && $feedType === 'internal') {
      $ph   = implode(',', array_fill(0, count($postIds), '?'));
      $stmt = $pdo->prepare("
        SELECT c.id, c.post_id, c.body, c.created_at, u.username, u.id AS user_id
        FROM feed_comments c
        JOIN users u ON u.id = c.user_id
        WHERE c.post_id IN ($ph)
        ORDER BY c.created_at ASC
      ");
      $stmt->execute($postIds);
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $comments[(int)$c['post_id']][] = $c;
      }
    }

    $posts = [];
    foreach ($rows as $r) {
      $pid    = (int)$r['id'];
      $posts[] = [
        'id'            => $pid,
        'body'          => (string)$r['body'],
        'type'          => (string)$r['type'],
        'created_at'    => (string)$r['created_at'],
        'updated_at'    => $r['updated_at'],
        'pinned'        => (int)$r['pinned'],
        'user_id'       => (int)$r['user_id'],
        'username'      => (string)$r['username'],
        'grade'         => (string)($r['grade'] ?? ''),
        'hearts'        => (int)$r['hearts'],
        'my_reaction'   => (int)$r['my_reaction'] > 0,
        'comment_count' => (int)$r['comment_count'],
        'comments'      => $comments[$pid] ?? [],
        'can_edit'      => ($uid === (int)$r['user_id']) || $isAdmin,
      ];
    }

    json_ok(['posts' => $posts, 'has_more' => $hasMore, 'can_post' => $canPost]);
  }

  if ($method !== 'POST') json_error('Méthode non autorisée.', 405);

  $data = read_json();
  $csrf = (string)($data['csrf'] ?? '');
  if (!feed_csrf_ok($csrf)) json_error('Requête invalide (CSRF).', 403);

  $action   = (string)($data['action'] ?? '');
  $postType = in_array($data['type'] ?? '', ['internal','service_note'])
    ? (string)$data['type']
    : 'internal';

  if ($action === 'create_post') {

    if ($postType === 'service_note' && !$isAdmin) json_error('Accès refusé.', 403);

    $body = trim((string)($data['body'] ?? ''));
    if ($body === '')              json_error('Message vide.', 400);
    if (mb_strlen($body) > 2000)  json_error('Message trop long (max 2000 caractères).', 400);

    $stmt = $pdo->prepare("INSERT INTO feed_posts (type, user_id, body) VALUES (?, ?, ?)");
    $stmt->execute([$postType, $uid, $body]);
    $id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
      SELECT p.id, p.body, p.type, p.created_at, p.updated_at, p.pinned,
             u.id AS user_id, u.username, u.grade
      FROM feed_posts p JOIN users u ON u.id = p.user_id
      WHERE p.id = ? LIMIT 1
    ");
    $stmt->execute([$id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);

    json_ok(['post' => [
      'id' => (int)$r['id'], 'body' => $r['body'], 'type' => $r['type'],
      'created_at' => $r['created_at'], 'updated_at' => null, 'pinned' => 0,
      'user_id' => (int)$r['user_id'], 'username' => $r['username'], 'grade' => $r['grade'],
      'hearts' => 0, 'my_reaction' => false, 'comment_count' => 0, 'comments' => [],
      'can_edit' => true,
    ]]);
  }

  if ($action === 'edit_post') {
    $postId = (int)($data['post_id'] ?? 0);
    $body   = trim((string)($data['body'] ?? ''));
    if ($postId <= 0) json_error('ID manquant.', 400);
    if ($body === '')  json_error('Message vide.', 400);
    if (mb_strlen($body) > 2000) json_error('Message trop long.', 400);

    $stmt = $pdo->prepare("SELECT user_id, type FROM feed_posts WHERE id=? LIMIT 1");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post) json_error('Post introuvable.', 404);
    if ((int)$post['user_id'] !== $uid && !$isAdmin) json_error('Accès refusé.', 403);

    $pdo->prepare("UPDATE feed_posts SET body=?, updated_at=NOW() WHERE id=?")->execute([$body, $postId]);
    json_ok();
  }

  if ($action === 'delete_post') {
    $postId = (int)($data['post_id'] ?? 0);
    if ($postId <= 0) json_error('ID manquant.', 400);

    $stmt = $pdo->prepare("SELECT user_id FROM feed_posts WHERE id=? LIMIT 1");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post) json_error('Post introuvable.', 404);
    if ((int)$post['user_id'] !== $uid && !$isAdmin) json_error('Accès refusé.', 403);

    $pdo->prepare("DELETE FROM feed_comments WHERE post_id=?")->execute([$postId]);
    $pdo->prepare("DELETE FROM feed_reactions WHERE post_id=?")->execute([$postId]);
    $pdo->prepare("DELETE FROM feed_posts WHERE id=?")->execute([$postId]);
    json_ok();
  }

  if ($action === 'toggle_pin') {
    if (!$isAdmin) json_error('Accès refusé.', 403);
    $postId = (int)($data['post_id'] ?? 0);
    if ($postId <= 0) json_error('ID manquant.', 400);
    $pdo->prepare("UPDATE feed_posts SET pinned = NOT pinned WHERE id=?")->execute([$postId]);
    json_ok();
  }

  if ($action === 'toggle_reaction') {
    $postId = (int)($data['post_id'] ?? 0);
    $emoji  = '❤️';
    if ($postId <= 0) json_error('ID manquant.', 400);

    $stmt = $pdo->prepare("SELECT id FROM feed_reactions WHERE post_id=? AND user_id=? AND emoji=? LIMIT 1");
    $stmt->execute([$postId, $uid, $emoji]);
    $existing = $stmt->fetch();

    if ($existing) {
      $pdo->prepare("DELETE FROM feed_reactions WHERE id=?")->execute([$existing['id']]);
      $active = false;
    } else {
      $pdo->prepare("INSERT INTO feed_reactions (post_id, user_id, emoji) VALUES (?,?,?)")->execute([$postId, $uid, $emoji]);
      $active = true;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feed_reactions WHERE post_id=? AND emoji=?");
    $stmt->execute([$postId, $emoji]);
    json_ok(['active' => $active, 'count' => (int)$stmt->fetchColumn()]);
  }

  if ($action === 'add_comment') {
    $postId = (int)($data['post_id'] ?? 0);
    $body   = trim((string)($data['body'] ?? ''));
    if ($postId <= 0) json_error('ID manquant.', 400);
    if ($body === '') json_error('Commentaire vide.', 400);
    if (mb_strlen($body) > 500) json_error('Commentaire trop long (max 500).', 400);

    $stmt = $pdo->prepare("INSERT INTO feed_comments (post_id, user_id, body) VALUES (?,?,?)");
    $stmt->execute([$postId, $uid, $body]);
    $id = (int)$pdo->lastInsertId();

    json_ok(['comment' => [
      'id'         => $id,
      'post_id'    => $postId,
      'body'       => $body,
      'created_at' => date('Y-m-d H:i:s'),
      'username'   => $u['username'],
      'user_id'    => $uid,
    ]]);
  }

  if ($action === 'delete_comment') {
    $commentId = (int)($data['comment_id'] ?? 0);
    if ($commentId <= 0) json_error('ID manquant.', 400);

    $stmt = $pdo->prepare("SELECT user_id FROM feed_comments WHERE id=? LIMIT 1");
    $stmt->execute([$commentId]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$c) json_error('Commentaire introuvable.', 404);
    if ((int)$c['user_id'] !== $uid && !$isAdmin) json_error('Accès refusé.', 403);

    $pdo->prepare("DELETE FROM feed_comments WHERE id=?")->execute([$commentId]);
    json_ok();
  }

  json_error('Action inconnue.', 400);

} catch (Throwable $e) {
  json_error('Erreur serveur.', 500, ['detail' => $e->getMessage()]);
}
