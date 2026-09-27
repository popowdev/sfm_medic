<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/layout.php';

require_admin();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

const SIGNATURE_BLOCK = <<<HTML
<p style="margin-top:30px;">Signature et cachet :</p>

{{#if signature_url}}
  <div style="margin:8px 0 14px 0;">
    <img src="{{signature_url}}" alt="Signature" style="height:70px;max-width:280px;">
  </div>
{{/if}}

<div style="height:70px;border:1px dashed #999;border-radius:10px;"></div>
HTML;

function ensure_signature_field(string $fields_json): string {
  $decoded = json_decode($fields_json, true);
  if (!is_array($decoded)) return $fields_json;

  foreach ($decoded as $f) {
    if (is_array($f) && (($f['key'] ?? '') === 'signature_url')) {
      return json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }
  }

  $decoded[] = [
    'key' => 'signature_url',
    'label' => 'Signature (auto)',
    'type' => 'text',
    'required' => false,
  ];

  return json_encode($decoded, JSON_UNESCAPED_UNICODE);
}

function body_has_signature(string $body_html): bool {

  return strpos($body_html, 'signature_url') !== false;
}

function replace_legacy_signature_box(string $body_html): string {
  if (body_has_signature($body_html)) return $body_html;

  $pattern = '~<p[^>]*>\s*Signature\s+et\s+cachet\s*:\s*</p>\s*'
           . '<div[^>]*height\s*:\s*70px[^>]*border\s*:\s*1px\s*dashed[^>]*>\s*</div>~i';

  $replaced = preg_replace($pattern, SIGNATURE_BLOCK, $body_html, 1);
  return is_string($replaced) ? $replaced : $body_html;
}

function dedupe_signature_blocks(string $body_html): string {
  if (!body_has_signature($body_html)) return $body_html;

  $count = substr_count(strtolower($body_html), 'signature et cachet');
  if ($count < 2) return $body_html;

  $pattern = '~\s*<p[^>]*>\s*Signature\s+et\s+cachet\s*:\s*</p>\s*'
           . '<div[^>]*height\s*:\s*70px[^>]*border\s*:\s*1px\s*dashed[^>]*>\s*</div>\s*~i';
  $out = preg_replace($pattern, "\n", $body_html, 1);
  return is_string($out) ? $out : $body_html;
}

function ensure_signature_block(string $body_html): string {
  if (body_has_signature($body_html)) return dedupe_signature_blocks($body_html);

  $body_html2 = replace_legacy_signature_box($body_html);
  if ($body_html2 !== $body_html) return $body_html2;

  return rtrim($body_html) . "\n\n" . SIGNATURE_BLOCK . "\n";
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$name = '';
$slug = '';
$category = 'general';
$fields_json = '[]';
$body_html = '';

if ($id > 0) {
  $st = db()->prepare("SELECT * FROM doc_templates WHERE id=?");
  $st->execute([$id]);
  $tpl = $st->fetch();
  if (!$tpl) { http_response_code(404); exit('Modèle introuvable'); }

  $name = (string)$tpl['name'];
  $slug = (string)$tpl['slug'];
  $category = (string)$tpl['category'];
  $fields_json = (string)$tpl['fields_json'];
  $body_html = (string)$tpl['body_html'];
}

$err = '';
$autoAddSignature = !$id ? 1 : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    verify_csrf();

    $name = trim((string)($_POST['name'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'general'));
    $fields_json = (string)($_POST['fields_json'] ?? '[]');
    $body_html = (string)($_POST['body_html'] ?? '');
    $autoAddSignature = isset($_POST['auto_add_signature']) ? 1 : 0;

    if ($name === '' || $slug === '') throw new RuntimeException("Nom et slug requis.");

    $decoded = json_decode($fields_json, true);
    if (!is_array($decoded)) throw new RuntimeException("fields_json doit être un JSON valide (tableau).");

    if ($body_html === '') throw new RuntimeException("body_html requis.");

    $fields_json = ensure_signature_field($fields_json);

    if ($autoAddSignature) {
      $body_html = ensure_signature_block($body_html);
    } else {

      $body_html = dedupe_signature_blocks($body_html);
    }

    if ($id > 0) {
      $up = db()->prepare("UPDATE doc_templates SET name=?, slug=?, category=?, fields_json=?, body_html=? WHERE id=?");
      $up->execute([$name, $slug, $category, $fields_json, $body_html, $id]);
    } else {
      $ins = db()->prepare("INSERT INTO doc_templates (name, slug, category, fields_json, body_html) VALUES (?,?,?,?,?)");
      $ins->execute([$name, $slug, $category, $fields_json, $body_html]);
      $id = (int)db()->lastInsertId();
    }

    header('Location: /admin/templates_manage.php');
    exit;
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

samr_layout_start($id > 0 ? 'Admin - Éditer modèle' : 'Admin - Nouveau modèle');
?>
<div class="card">
  <div class="muted"><a class="btn secondary" href="/admin/templates_manage.php">← Modèles</a></div>
  <h1 style="margin:12px 0 6px 0;"><?= $id > 0 ? 'Éditer un modèle' : 'Nouveau modèle' ?></h1>
  <div class="muted">
    Placeholders : <code>{{field}}</code> et <code>{{#if field}}...{{/if}}</code>. Pour l’ordonnance : <code>{{RX_BLOCK}}</code>.<br>
    Signature : <code>{{signature_url}}</code> (image).
  </div>
</div>

<div class="card">
  <?php if ($err): ?><div style="color:#ff9aa2;margin-bottom:10px;"><?= h($err) ?></div><?php endif; ?>

  <form method="post">
    <?= csrf_input() ?>

    <label class="muted">Nom</label>
    <input name="name" value="<?= h($name) ?>" required
      style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)">

    <div style="height:10px;"></div>

    <label class="muted">Slug (unique)</label>
    <input name="slug" value="<?= h($slug) ?>" required
      style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)">

    <div style="height:10px;"></div>

    <label class="muted">Catégorie</label>
    <input name="category" value="<?= h($category) ?>"
      style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)">

    <div style="height:10px;"></div>

    <label class="muted">Champs (JSON)</label>
    <textarea name="fields_json" style="width:100%;min-height:170px;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)"><?= h($fields_json) ?></textarea>
    <div class="muted" style="margin-top:6px;font-size:12px;">
      Note : le champ <code>signature_url</code> sera ajouté automatiquement si absent.
    </div>

    <div style="height:10px;"></div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:6px 0 10px 0;">
      <label style="display:flex;gap:10px;align-items:center;">
        <input type="checkbox" name="auto_add_signature" value="1" <?= $autoAddSignature ? 'checked' : '' ?> style="width:auto;">
        Auto-ajouter le bloc signature au HTML (si absent)
      </label>

      <button class="btn secondary" type="button" id="insertSigBtn">Insérer le bloc signature (au curseur)</button>
    </div>

    <label class="muted">HTML du modèle</label>
    <textarea name="body_html" id="body_html" style="width:100%;min-height:320px;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)"><?= h($body_html) ?></textarea>

    <div style="height:14px;"></div>

    <button class="btn" type="submit">Enregistrer</button>
    <a class="btn secondary" href="/admin/templates_manage.php" style="margin-left:10px;">Annuler</a>
  </form>
</div>

<script>
(function(){
  const sigBlock = <?= json_encode(SIGNATURE_BLOCK, JSON_UNESCAPED_UNICODE) ?>;
  const ta = document.getElementById('body_html');
  const btn = document.getElementById('insertSigBtn');

  btn?.addEventListener('click', () => {
    if (!ta) return;
    const start = ta.selectionStart || 0;
    const end = ta.selectionEnd || 0;
    const before = ta.value.substring(0, start);
    const after = ta.value.substring(end);
    ta.value = before + "\\n\\n" + sigBlock + "\\n\\n" + after;
    ta.focus();
    ta.selectionStart = ta.selectionEnd = start + sigBlock.length + 4;
  });
})();
</script>

<?php samr_layout_end(); ?>