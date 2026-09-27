<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_login();

$u   = current_user();
$uid = (int)($u['id'] ?? 0);
$pdo = db();

echo "<pre style='font-family:monospace;padding:20px;background:#0d1219;color:#dce8f5;'>";

try {
    $r = $pdo->query("SELECT type FROM feed_posts LIMIT 1");
    echo "✅ Colonne 'type' existe dans feed_posts\n";
} catch (Throwable $e) {
    echo "❌ Colonne 'type' MANQUANTE dans feed_posts\n";
    echo "   → Exécuter migration_feed_type.sql dans phpMyAdmin\n\n";
}

try {
    $count = $pdo->query("SELECT COUNT(*) FROM feed_posts WHERE type='service_note'")->fetchColumn();
    echo "📋 Notes de service en base : {$count}\n";
} catch (Throwable $e) {
    echo "❌ Impossible de compter : " . $e->getMessage() . "\n";
}

try {
    $seen = $pdo->prepare("SELECT COUNT(*) FROM service_notes_seen WHERE user_id=?");
    $seen->execute([$uid]);
    echo "👁️  Notes vues par user #{$uid} : " . $seen->fetchColumn() . "\n";
} catch (Throwable $e) {
    echo "❌ Table service_notes_seen manquante : " . $e->getMessage() . "\n";
}

try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.body, p.created_at FROM feed_posts p
        WHERE p.type='service_note'
        AND p.id NOT IN (SELECT post_id FROM service_notes_seen WHERE user_id=?)
        LIMIT 5
    ");
    $stmt->execute([$uid]);
    $rows = $stmt->fetchAll();
    echo "🔔 Notes non vues : " . count($rows) . "\n";
    foreach ($rows as $r) {
        echo "   - #{$r['id']} : " . substr($r['body'], 0, 60) . "...\n";
    }
} catch (Throwable $e) {
    echo "❌ Erreur requête finale : " . $e->getMessage() . "\n";
}

echo "</pre>";
