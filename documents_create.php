<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';

require_login();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$templates = db()->query("SELECT id, name FROM doc_templates ORDER BY category, name")->fetchAll();

$err = '';
$template_id = (int)($_GET['template_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    verify_csrf();
    $template_id = (int)($_POST['template_id'] ?? 0);
    if ($template_id <= 0) throw new RuntimeException("Choisis un modèle.");
    header('Location: /documents_edit.php?template_id=' . $template_id);
    exit;
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

samr_layout_start('Nouveau document');
?>
<div class="card">
  <h1 style="margin:0 0 6px 0;">Nouveau document</h1>
  <div class="muted">Choisis un modèle, puis complète les champs.</div>
</div>

<div class="card">
  <?php if ($err): ?><div style="color:#ff9aa2;margin-bottom:10px;"><?= h($err) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_input() ?>
    <label class="muted">Modèle</label>
    <select name="template_id" required
      style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)">
      <option value="">— Choisir —</option>
      <?php foreach ($templates as $t): ?>
        <option value="<?= (int)$t['id'] ?>" <?= (int)$t['id'] === $template_id ? 'selected' : '' ?>>
          <?= h((string)$t['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <div style="height:14px;"></div>
    <button class="btn" type="submit">Continuer</button>
    <a class="btn secondary" href="/documents.php" style="margin-left:10px;">Annuler</a>
  </form>
</div>
<?php samr_layout_end(); ?>
