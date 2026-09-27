<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/layout.php';

require_admin();
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$rows = db()->query("SELECT id, name, slug, category, created_at FROM doc_templates ORDER BY category, name")->fetchAll();

samr_layout_start('Admin - Modèles');
?>
<div class="card">
  <h1 style="margin:0 0 6px 0;">Modèles de documents</h1>
  <div class="muted">Gère les modèles et les champs affichés dans le générateur.</div>
  <div style="margin-top:12px;">
    <a class="btn" href="/admin/template_edit.php">+ Nouveau modèle</a>
  </div>
</div>

<div class="card">
  <div style="overflow:auto;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr>
          <th style="text-align:left;border-bottom:1px solid var(--line);padding:10px;">Nom</th>
          <th style="text-align:left;border-bottom:1px solid var(--line);padding:10px;">Slug</th>
          <th style="text-align:left;border-bottom:1px solid var(--line);padding:10px;">Catégorie</th>
          <th style="text-align:left;border-bottom:1px solid var(--line);padding:10px;">Créé</th>
          <th style="border-bottom:1px solid var(--line);padding:10px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="border-bottom:1px solid var(--line);padding:10px;"><?= h((string)$r['name']) ?></td>
            <td style="border-bottom:1px solid var(--line);padding:10px;"><?= h((string)$r['slug']) ?></td>
            <td style="border-bottom:1px solid var(--line);padding:10px;"><?= h((string)$r['category']) ?></td>
            <td style="border-bottom:1px solid var(--line);padding:10px;"><?= h((string)$r['created_at']) ?></td>
            <td style="border-bottom:1px solid var(--line);padding:10px;text-align:right;">
              <a class="btn secondary" href="/admin/template_edit.php?id=<?= (int)$r['id'] ?>">Éditer</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php samr_layout_end(); ?>
