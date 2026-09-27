<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';

require_login();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('ID manquant'); }

$stmt = db()->prepare("
  SELECT d.*, t.name AS template_name, u.username
  FROM documents d
  JOIN doc_templates t ON t.id=d.template_id
  JOIN users u ON u.id=d.created_by
  WHERE d.id=?
");
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) { http_response_code(404); exit('Document introuvable'); }

$rendered = (string)$doc['rendered_html'];

samr_layout_start('Document');
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;">
    <div>
      <div class="muted"><a class="btn secondary" href="/documents.php">← Documents</a></div>
      <h1 style="margin:12px 0 6px 0;"><?= h((string)$doc['title']) ?></h1>
      <div class="muted">Modèle : <?= h((string)$doc['template_name']) ?> • Par <?= h((string)$doc['username']) ?> • <?= h((string)$doc['created_at']) ?></div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <a class="btn secondary" href="/documents_edit.php?id=<?= (int)$doc['id'] ?>">Éditer</a>
      <button class="btn secondary" id="exportPngBtn" type="button">🖼️ Export PNG</button>
    </div>
  </div>
</div>

<style>
.doc-wrap{
  background:#fff;
  color:#111;
  border-radius:16px;
  border:1px solid #cfd6e0;
  padding:26px;
  max-width:850px;
}
@media print{
  body{background:#fff !important}
  .sidebar, .btn, .card { display:none !important; }
  .main{margin-left:0 !important; padding:0 !important;}
  .doc-wrap{border:none !important; border-radius:0 !important; padding:0 !important; max-width:none !important;}
}
</style>

<div class="card" style="display:flex;justify-content:center;">
  <div class="doc-wrap" id="docWrap">
    <?= $rendered ?>
  </div>
</div>

<?= csrf_input() ?>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
document.getElementById('exportPngBtn')?.addEventListener('click', async () => {
  const wrap = document.getElementById('docWrap');
  if (!wrap) return;

  const btn = document.getElementById('exportPngBtn');
  btn.disabled = true;
  const old = btn.textContent;
  btn.textContent = 'Export...';

  try {
    const canvas = await html2canvas(wrap, {scale: 2, backgroundColor: '#ffffff'});
    const dataUrl = canvas.toDataURL('image/png');

    const form = new FormData();
    form.append('doc_id', '<?= (int)$doc['id'] ?>');
    form.append('png_data', dataUrl);
    form.append('csrf', document.querySelector('input[name="csrf"]')?.value || '');

    const res = await fetch('/docs_upload_png.php', {method: 'POST', body: form, credentials: 'same-origin'});
    const js = await res.json();
    if (!js.ok) throw new Error(js.error || 'Erreur export');

    window.prompt('Lien PNG (copie-colle):', js.full_url || js.url);
  } catch (e) {
    alert('Export PNG impossible : ' + (e.message || e));
  } finally {
    btn.disabled = false;
    btn.textContent = old;
  }
});
</script>

<?php samr_layout_end(); ?>
