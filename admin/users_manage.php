<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../layout.php';
require_once __DIR__ . '/../csrf.php';

require_login();

$u = current_user();
if (!is_array($u) || (($u['role'] ?? '') !== 'admin')) {
  http_response_code(403);
  exit('Accès refusé.');
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $rows = $pdo->query("
    SELECT id, username, role, discord_id, grade, can_supervise,
           dispatch_list_id, dispatch_role, is_active
    FROM users
    ORDER BY username ASC, id ASC
  ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Erreur SQL / PHP dans users_manage.php\n";
  echo $e->getMessage();
  exit;
}

samr_layout_start('Admin - Utilisateurs');
?>

<div class="card">
  <h1 style="margin:0 0 6px 0;">🗑️ Gestion des utilisateurs</h1>
  <div class="muted">Suppression complète du compte, formations et shifts liés.</div>
  <?php if (isset($_GET['deleted'])): ?>
    <div style="margin-top:10px;color:#9fe6b8;">Utilisateur supprimé avec succès.</div>
  <?php endif; ?>
</div>

<div class="card">
  <div style="overflow:auto;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line);">ID</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line);">Nom</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line);">Rôle</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line);">Grade</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line);">Supervision</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line);">Dispatch</th>
          <th style="text-align:left;padding:10px;border-bottom:1px solid var(--line);">Statut</th>
          <th style="text-align:right;padding:10px;border-bottom:1px solid var(--line);"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="padding:10px;border-bottom:1px solid var(--line);"><?= (int)$r['id'] ?></td>
            <td style="padding:10px;border-bottom:1px solid var(--line);"><strong><?= h((string)$r['username']) ?></strong></td>
            <td style="padding:10px;border-bottom:1px solid var(--line);"><?= h((string)($r['role'] ?? '')) ?></td>
            <td style="padding:10px;border-bottom:1px solid var(--line);"><?= h((string)($r['grade'] ?? '—')) ?></td>
            <td style="padding:10px;border-bottom:1px solid var(--line);"><?= ((int)($r['can_supervise'] ?? 0) === 1) ? 'Oui' : 'Non' ?></td>
            <td style="padding:10px;border-bottom:1px solid var(--line);">
              <?php $dispRole = (string)($r['dispatch_role'] ?? ''); ?>
              <?php if ($dispRole !== ''): ?>
                <span class="pill"><?= h($dispRole) ?></span>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td style="padding:10px;border-bottom:1px solid var(--line);">
              <?php if ((int)($r['is_active'] ?? 1) === 0): ?>
                <span style="color:#ffb3b3;">Inactif</span>
              <?php else: ?>
                <span style="color:#9fe6b8;">Actif</span>
              <?php endif; ?>
            </td>
            <td style="padding:10px;border-bottom:1px solid var(--line);text-align:right;">
              <?php if ((int)$r['id'] !== (int)$u['id']): ?>
                <form method="post" action="/admin/delete_user.php" onsubmit="return confirm('Supprimer cet utilisateur définitivement ?');" style="display:inline;">
                  <?= csrf_input() ?>
                  <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                  <button class="btn danger" type="submit">Supprimer</button>
                </form>
              <?php else: ?>
                <span class="muted">Compte actuel</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
.btn.danger{
  border-color: rgba(255,120,120,.35);
  background: rgba(255,80,80,.12);
  color: var(--text);
}
</style>

<?php samr_layout_end(); ?>
