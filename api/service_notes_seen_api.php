<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

start_session();
require_login();

$u   = current_user();
$uid = (int)($u['id'] ?? 0);

$data   = json_decode(file_get_contents('php://input'), true);
$postId = (int)($data['post_id'] ?? 0);

if ($uid <= 0 || $postId <= 0) {
    echo json_encode(['ok'=>false]);
    exit;
}

try {
    $pdo = db();
    $pdo->prepare("INSERT IGNORE INTO `service_notes_seen` (user_id, post_id, seen_at) VALUES (?, ?, NOW())")
        ->execute([$uid, $postId]);
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
