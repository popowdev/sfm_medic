<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../csrf.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $action = (string)($_POST['action'] ?? '');
  $id = (int)($_POST['id'] ?? 0);

  if ($action === 'delete' && $id > 0) {
    $stmt = db()->prepare("DELETE FROM announcements WHERE id=?");
    $stmt->execute([$id]);
  } elseif ($action === 'toggle' && $id > 0) {
    $stmt = db()->prepare("UPDATE announcements SET is_published = 1 - is_published WHERE id=?");
    $stmt->execute([$id]);
  }
  header('Location: /admin/announcements_manage.php');
  exit;
}

$stmt = db()->query("SELECT a.*, u.username FROM announcements a JOIN users u ON u.id=a.created_by ORDER BY a.created_at DESC LIMIT 100");
$rows = $stmt->fetchAll();

samr_layout_start('Admin - Annonces');
?>

<div class="card">
  <h2 style="margin:0 0 8px 0;">Annonces</h2>
  <div style="display:flex; gap:10px; flex-wrap:wrap;">
    <a class="btn" href="/admin/announcement_edit.php">+ Nouvelle annonce</a>
  </div>
</div>

<?php foreach ($rows as $r): ?>
  <div class="card">
    <div class="muted" style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
      <span>#<?= (int)$r['id'] ?> • <?= htmlspecialchars((string)$r['created_at'], ENT_QUOTES, 'UTF-8') ?> • par <?= htmlspecialchars((string)$r['username'], ENT_QUOTES, 'UTF-8') ?></span>
      <span class="pill"><?= (int)$r['is_published'] ? 'Publié' : 'Brouillon' ?></span>
    </div>

    <h3 style="margin:10px 0 8px 0;"><?= htmlspecialchars((string)$r['title'], ENT_QUOTES, 'UTF-8') ?></h3>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
      <a class="btn secondary" href="/admin/announcement_edit.php?id=<?= (int)$r['id'] ?>">✏️ Éditer</a>

      <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:0;">
        <?= csrf_input() ?>
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <button class="btn secondary" name="action" value="toggle" type="submit">
          <?= (int)$r['is_published'] ? 'Dépublier' : 'Publier' ?>
        </button>
        <button class="btn" name="action" value="delete" type="submit" onclick="return confirm('Supprimer cette annonce ?');">
          🗑️ Supprimer
        </button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php
samr_layout_end();
