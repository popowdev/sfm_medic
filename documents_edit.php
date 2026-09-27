<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';

require_login();

$u = current_user();

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function format_date_fr(?string $date): string {
  if (!$date) return '';
  $dt = DateTime::createFromFormat('Y-m-d', $date);
  if (!$dt) return $date;
  return $dt->format('d/m/Y');
}

function render_value_for_template(string $key, mixed $value): string {
  if ($key === 'signature_url') {
    $url = trim((string)$value);

    if ($url === '') {
      return '';
    }

    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    return '<img src="' . $safeUrl . '" alt="Signature" style="max-height:86px;max-width:100%;">';
  }

  $escaped = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

  if (preg_match('/^(body|prescription|notes|comment|commentaire|remarques|remarks|observations)$/i', $key)) {
    return nl2br($escaped);
  }

  return $escaped;
}

function render_template(string $html, array $data): string {
  $html = preg_replace_callback(
    '/\{\{\#if\s+([a-zA-Z0-9_]+)\s*\}\}(.*?)\{\{\/if\}\}/s',
    function ($m) use ($data) {
      $k = $m[1];
      $block = $m[2];
      $val = $data[$k] ?? '';
      return trim((string)$val) !== '' ? $block : '';
    },
    $html
  );

  $html = preg_replace_callback(
    '/\{\{([a-zA-Z0-9_]+)\}\}/',
    function ($m) use ($data) {
      $k = $m[1];
      return render_value_for_template($k, $data[$k] ?? '');
    },
    $html
  );

  return $html;
}

function dedupe_signature_in_rendered(string $html): string {
  if (substr_count(strtolower($html), 'signature et cachet') < 2) return $html;

  $pattern = '~<p[^>]*>\s*Signature\s+et\s+cachet\s*:\s*</p>\s*'
           . '<div[^>]*border\s*:\s*1px\s*dashed[^>]*>\s*</div>\s*'
           . '(?=.*<img[^>]+src="[^"]+"[^>]*>)~is';

  $out = preg_replace($pattern, '', $html, 1);
  return is_string($out) ? $out : $html;
}

$doc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$template_id = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;

$title = '';
$data = [];

$userSignatureUrl = '';
$userId = (int)($u['id'] ?? 0);
if ($userId > 0) {
  $sigStmt = db()->prepare("SELECT signature_url FROM users WHERE id = ? LIMIT 1");
  $sigStmt->execute([$userId]);
  $userSignatureUrl = trim((string)($sigStmt->fetchColumn() ?: ''));
}

if ($doc_id > 0) {
  $stmt = db()->prepare("SELECT * FROM documents WHERE id=?");
  $stmt->execute([$doc_id]);
  $doc = $stmt->fetch();
  if (!$doc) {
    http_response_code(404);
    exit('Document introuvable.');
  }

  $template_id = (int)$doc['template_id'];
  $title = (string)$doc['title'];
  $data = json_decode((string)$doc['data_json'], true) ?: [];
}

if ($template_id <= 0) {
  http_response_code(400);
  exit("Template manquant.");
}

$stmt = db()->prepare("SELECT * FROM doc_templates WHERE id=?");
$stmt->execute([$template_id]);
$tpl = $stmt->fetch();
if (!$tpl) {
  http_response_code(404);
  exit('Modèle introuvable.');
}

$fields = json_decode((string)$tpl['fields_json'], true) ?: [];
$body_html = (string)$tpl['body_html'];

$hasSig = false;
foreach ($fields as $f) {
  if (($f['key'] ?? '') === 'signature_url') {
    $hasSig = true;
    break;
  }
}
if (!$hasSig) {
  $fields[] = [
    "key" => "signature_url",
    "label" => "Signature (auto)",
    "type" => "text",
    "required" => false
  ];
}

if (empty($data['signature_url']) && $userSignatureUrl !== '') {
  $data['signature_url'] = $userSignatureUrl;
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    verify_csrf();

    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
      $title = (string)$tpl['name'];
    }

    $data = [];
    foreach ($fields as $f) {
      $k = (string)($f['key'] ?? '');
      if ($k === '') continue;

      $posted = $_POST[$k] ?? '';
      if (is_array($posted)) $posted = '';

      $data[$k] = (string)$posted;

      if (!empty($f['required']) && trim($data[$k]) === '') {
        throw new RuntimeException("Champ requis : " . ($f['label'] ?? $k));
      }
    }

    if (trim((string)($data['signature_url'] ?? '')) === '' && $userSignatureUrl !== '') {
      $data['signature_url'] = $userSignatureUrl;
    }

    if (empty($data['doc_ref'])) {
      $random = strtoupper(bin2hex(random_bytes(3)));
      $data['doc_ref'] = 'SAMR-' . date('Ymd') . '-' . $random;
    }

    if ((string)$tpl['slug'] === 'ordonnance') {
      $sel = $_POST['rx_items'] ?? [];
      if (!is_array($sel)) $sel = [];
      $data['rx_items'] = array_values(
        array_filter(array_map('intval', $sel), fn($x) => $x > 0)
      );
    }

    $renderBody = $body_html;
    $renderData = $data;

    if ((string)$tpl['slug'] === 'ordonnance') {
      $selected = $data['rx_items'] ?? [];
      if (!is_array($selected)) {
        $selected = [];
      }

      $rxHtml = '';

      if (count($selected) > 0) {
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        $st = db()->prepare("
          SELECT label_fictif, form, instructions, duration, notes
          FROM rx_items
          WHERE is_active=1 AND id IN ($placeholders)
          ORDER BY label_fictif ASC
        ");
        $st->execute($selected);
        $items = $st->fetchAll();

        if ($items) {
          $rxHtml = '<ul style="margin:8px 0 0 18px;padding:0;">';

          foreach ($items as $it) {
            $line = '<b>' . h((string)$it['label_fictif']) . '</b>';

            if (!empty($it['form'])) {
              $line .= ' — ' . h((string)$it['form']);
            }
            if (!empty($it['instructions'])) {
              $line .= ' • ' . h((string)$it['instructions']);
            }
            if (!empty($it['duration'])) {
              $line .= ' • ' . h((string)$it['duration']);
            }
            if (!empty($it['notes'])) {
              $line .= ' • ' . h((string)$it['notes']);
            }

            $rxHtml .= '<li>' . $line . '</li>';
          }

          $rxHtml .= '</ul>';
        }
      }

      $renderBody = str_replace('{{RX_BLOCK}}', $rxHtml, $renderBody);
      $renderBody = str_replace('{{rx_block}}', $rxHtml, $renderBody);

      $renderData['prescription'] = (string)($data['prescription'] ?? '');
    } else {
      $renderBody = str_replace('{{RX_BLOCK}}', '', $renderBody);
      $renderBody = str_replace('{{rx_block}}', '', $renderBody);
    }

    foreach ($fields as $f) {
      $k = (string)($f['key'] ?? '');
      if ($k === '') continue;

      if (($f['type'] ?? '') === 'date' && !empty($renderData[$k])) {
        $renderData[$k] = format_date_fr((string)$renderData[$k]);
      }
    }

    $rendered = render_template($renderBody, $renderData);
    $rendered = dedupe_signature_in_rendered($rendered);

    if ($doc_id > 0) {
      $up = db()->prepare("
        UPDATE documents
        SET title=?, data_json=?, rendered_html=?, updated_at=NOW()
        WHERE id=?
      ");
      $up->execute([
        $title,
        json_encode($data, JSON_UNESCAPED_UNICODE),
        $rendered,
        $doc_id
      ]);
    } else {
      $ins = db()->prepare("
        INSERT INTO documents (template_id, title, data_json, rendered_html, created_by)
        VALUES (?,?,?,?,?)
      ");
      $ins->execute([
        $template_id,
        $title,
        json_encode($data, JSON_UNESCAPED_UNICODE),
        $rendered,
        (int)($u['id'] ?? 0)
      ]);
    }

    $newId = $doc_id > 0 ? $doc_id : (int)db()->lastInsertId();
    header('Location: /documents_view.php?id=' . $newId);
    exit;

  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

samr_layout_start($doc_id > 0 ? 'Éditer document' : 'Nouveau document');
?>
<div class="card">
  <div class="muted"><a class="btn secondary" href="/documents.php">← Documents</a></div>
  <h1 style="margin:12px 0 6px 0;"><?= h((string)$tpl['name']) ?></h1>
  <div class="muted">Complète les champs, puis génère le document.</div>
</div>

<div class="card">
  <?php if ($err): ?>
    <div style="color:#ff9aa2;margin-bottom:10px;"><?= h($err) ?></div>
  <?php endif; ?>

  <form method="post">
    <?= csrf_input() ?>

    <label class="muted">Titre (liste)</label>
    <input
      name="title"
      value="<?= h($title ?: (string)$tpl['name']) ?>"
      maxlength="180"
      style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)"
    >

    <div style="height:12px;"></div>

    <?php foreach ($fields as $f):
      $k = (string)($f['key'] ?? '');
      if ($k === '' || $k === 'signature_url') continue;
      $label = (string)($f['label'] ?? $k);
      $type = (string)($f['type'] ?? 'text');
      $required = !empty($f['required']);
      $val = (string)($data[$k] ?? '');
      $opts = $f['options'] ?? [];
    ?>
      <div style="margin-bottom:12px;">
        <label class="muted"><?= h($label) ?><?= $required ? ' *' : '' ?></label>

        <?php if ($type === 'textarea'): ?>
          <textarea
            name="<?= h($k) ?>"
            <?= $required ? 'required' : '' ?>
            style="width:100%;min-height:120px;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)"
          ><?= h($val) ?></textarea>

        <?php elseif ($type === 'date'): ?>
          <input
            type="date"
            name="<?= h($k) ?>"
            value="<?= h($val) ?>"
            <?= $required ? 'required' : '' ?>
            style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)"
          >

        <?php elseif ($type === 'select'): ?>
          <select
            name="<?= h($k) ?>"
            <?= $required ? 'required' : '' ?>
            style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)"
          >
            <option value="">— Choisir —</option>
            <?php foreach ($opts as $o): $o = (string)$o; ?>
              <option value="<?= h($o) ?>" <?= $o === $val ? 'selected' : '' ?>><?= h($o) ?></option>
            <?php endforeach; ?>
          </select>

        <?php else: ?>
          <input
            name="<?= h($k) ?>"
            value="<?= h($val) ?>"
            <?= $required ? 'required' : '' ?>
            style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)"
          >
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php if ((string)$tpl['slug'] === 'ordonnance'):
      $selected = $data['rx_items'] ?? [];
      if (!is_array($selected)) $selected = [];
      $rxList = db()->query("SELECT id, label_fictif, form FROM rx_items WHERE is_active=1 ORDER BY label_fictif ASC")->fetchAll();
    ?>
      <div class="card" style="margin-top:12px;">
        <h3 style="margin:0 0 8px 0;">Traitements (à cocher)</h3>
        <div class="muted" style="margin-bottom:10px;">Sélectionne les traitements à faire apparaître dans l’ordonnance.</div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px;">
          <?php foreach ($rxList as $it):
            $id = (int)$it['id'];
            $lbl = (string)$it['label_fictif'];
            $form = (string)($it['form'] ?? '');
            $isChecked = in_array($id, $selected, true);
          ?>
            <label style="display:flex;gap:10px;align-items:center;padding:10px;border:1px solid var(--line);border-radius:12px;background:#0b0f14;">
              <input type="checkbox" name="rx_items[]" value="<?= $id ?>" <?= $isChecked ? 'checked' : '' ?> style="width:auto;">
              <div>
                <div style="font-weight:700;"><?= h($lbl) ?></div>
                <?php if ($form !== ''): ?>
                  <div class="muted" style="font-size:12px;"><?= h($form) ?></div>
                <?php endif; ?>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card" style="margin-top:12px;">
      <h3 style="margin:0 0 8px 0;">Signature du praticien</h3>
      <div class="muted" style="margin-bottom:10px;">
        Signature du profil chargée automatiquement. Tu peux la remplacer ici uniquement pour ce document si besoin.
      </div>

      <input type="hidden" name="signature_url" id="signature_url" value="<?= h((string)($data['signature_url'] ?? '')) ?>">

      <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start;">
        <div>
          <div class="muted" style="font-size:12px;margin-bottom:6px;">Dessin</div>
          <canvas id="sigCanvas" width="360" height="140" style="background:#fff;border-radius:12px;border:1px solid #cfd6e0;touch-action:none;"></canvas>
          <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;">
            <button class="btn secondary" type="button" id="sigClear">Effacer</button>
            <button class="btn" type="button" id="sigSave">Remplacer pour ce document</button>
          </div>
        </div>

        <div>
          <div class="muted" style="font-size:12px;margin-bottom:6px;">Upload (png/jpg)</div>
          <input type="file" id="sigFile" accept="image/png,image/jpeg" style="width:360px;max-width:100%;">
          <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;">
            <button class="btn" type="button" id="sigUploadBtn">Uploader</button>
            <button class="btn secondary" type="button" id="sigResetBtn">Remettre celle du profil</button>
          </div>

          <div style="margin-top:12px;">
            <div class="muted" style="font-size:12px;margin-bottom:6px;">Aperçu actuel</div>
            <img
              id="sigPreview"
              src="<?= h((string)($data['signature_url'] ?? '')) ?>"
              alt="Signature"
              style="max-width:360px;max-height:120px;display:<?= !empty($data['signature_url']) ? 'block' : 'none' ?>;background:#fff;border:1px solid #cfd6e0;border-radius:12px;padding:8px;"
            >
            <div
              id="sigEmpty"
              class="muted"
              style="display:<?= !empty($data['signature_url']) ? 'none' : 'block' ?>;"
            >
              Aucune signature sélectionnée.
            </div>
          </div>
        </div>
      </div>
    </div>

    <div style="height:14px;"></div>
    <button class="btn" type="submit">Générer / Enregistrer</button>
    <a class="btn secondary" href="/documents.php" style="margin-left:10px;">Annuler</a>
  </form>
</div>

<script>
(function(){
  const csrf = document.querySelector('input[name="csrf"]')?.value || '';
  const sigUrlInput = document.getElementById('signature_url');
  const preview = document.getElementById('sigPreview');
  const emptyText = document.getElementById('sigEmpty');
  const defaultProfileSignature = <?= json_encode((string)$userSignatureUrl, JSON_UNESCAPED_UNICODE) ?>;

  const canvas = document.getElementById('sigCanvas');
  const ctx = canvas.getContext('2d');
  ctx.lineWidth = 2;
  ctx.lineCap = 'round';
  ctx.strokeStyle = '#111';

  let drawing = false;
  let last = null;

  function updatePreview(url){
    sigUrlInput.value = url || '';

    if (url) {
      preview.src = url;
      preview.style.display = 'block';
      emptyText.style.display = 'none';
    } else {
      preview.src = '';
      preview.style.display = 'none';
      emptyText.style.display = 'block';
    }
  }

  function pos(e){
    const r = canvas.getBoundingClientRect();
    const x = (e.touches ? e.touches[0].clientX : e.clientX) - r.left;
    const y = (e.touches ? e.touches[0].clientY : e.clientY) - r.top;
    return {x, y};
  }

  function start(e){
    drawing = true;
    last = pos(e);
    e.preventDefault();
  }

  function move(e){
    if (!drawing) return;
    const p = pos(e);
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
    e.preventDefault();
  }

  function end(){
    drawing = false;
    last = null;
  }

  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);

  canvas.addEventListener('touchstart', start, {passive:false});
  canvas.addEventListener('touchmove', move, {passive:false});
  window.addEventListener('touchend', end);

  document.getElementById('sigClear').addEventListener('click', () => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
  });

  async function postSignature(fd){
    const res = await fetch('/signature_upload.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    });
    const js = await res.json();
    if (!js.ok) throw new Error(js.error || 'Erreur signature');
    updatePreview(js.url || '');
  }

  document.getElementById('sigSave').addEventListener('click', async () => {
    try {
      const dataUrl = canvas.toDataURL('image/png');
      const fd = new FormData();
      fd.append('csrf', csrf);
      fd.append('sig_data', dataUrl);
      await postSignature(fd);
      alert('Signature remplacée pour ce document.');
    } catch(e) {
      alert('Signature impossible : ' + (e.message || e));
    }
  });

  document.getElementById('sigUploadBtn').addEventListener('click', async () => {
    try {
      const file = document.getElementById('sigFile').files[0];
      if (!file) {
        alert('Choisis un fichier');
        return;
      }
      const fd = new FormData();
      fd.append('csrf', csrf);
      fd.append('sig_file', file);
      await postSignature(fd);
      alert('Signature uploadée pour ce document.');
    } catch(e) {
      alert('Upload impossible : ' + (e.message || e));
    }
  });

  document.getElementById('sigResetBtn').addEventListener('click', () => {
    updatePreview(defaultProfileSignature || '');
  });
})();
</script>

<?php samr_layout_end(); ?>