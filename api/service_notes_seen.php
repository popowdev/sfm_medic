<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');
function je(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

start_session();
require_login();

$u   = current_user();
$uid = (int)($u['id'] ?? 0);
$pdo = db();

$data   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = (string)($data['action'] ?? 'mark_seen');

try {

    $pdo->exec("CREATE TABLE IF NOT EXISTS `service_notes_seen` (
        `user_id` INT(11) NOT NULL,
        `post_id` INT(11) NOT NULL,
        `seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`user_id`, `post_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($action === 'mark_seen') {
        $postId = (int)($data['post_id'] ?? 0);
        if ($uid <= 0 || $postId <= 0) je(['ok'=>false]);
        $pdo->prepare("INSERT IGNORE INTO `service_notes_seen` (user_id, post_id, seen_at) VALUES (?,?,NOW())")
            ->execute([$uid, $postId]);
        je(['ok'=>true]);
    }

    if ($action === 'dismiss_all') {

        $pdo->prepare("
            INSERT IGNORE INTO `service_notes_seen` (user_id, post_id, seen_at)
            SELECT ?, id, NOW() FROM feed_posts WHERE type = 'service_note'
        ")->execute([$uid]);
        je(['ok'=>true]);
    }

    je(['ok'=>false,'error'=>'Action inconnue.']);
} catch (Throwable $e) {
    je(['ok'=>false,'error'=>$e->getMessage()]);
}
