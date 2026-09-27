<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../auth.php';

require_admin();

samr_layout_start('Admin - SAMR');
?>

<div class="card">
  <h2 style="margin:0 0 8px 0;">Administration</h2>
  <div class="muted">Gestion des comptes et des annonces.</div>

  <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
    <a class="btn" href="/admin/users_create.php">👤 Créer un compte</a>
    <a class="btn secondary" href="/admin/announcements_manage.php">📢 Gérer les annonces</a>
  </div>
</div>

<?php
samr_layout_end();
