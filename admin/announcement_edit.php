<?php
declare(strict_types=1);

ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../php_errors.log');
error_reporting(E_ALL);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../csrf.php';

require_admin();

function sanitize_announcement_html(string $html): string {
  $allowedTags = '<p><br><b><strong><i><em><u><span><div><h1><h2><h3><h4><ul><ol><li><a><img><table><thead><tbody><tr><th><td>';
  $html = strip_tags($html, $allowedTags);

  $html = preg_replace('/\son\w+="[^"]*"/i', '', $html);
  $html = preg_replace("/\son\w+='[^']*'/i", '', $html);
  $html = preg_replace('/javascript\s*:/i', '', $html);

  $html = preg_replace_callback('/<(a|img|span)\b([^>]*)>/i', function($m) {
    $tag = strtolower((string)($m[1] ?? ''));
    $attrs = (string)($m[2] ?? '');
    $keep = [];

    if (preg_match_all('/\s([a-zA-Z0-9_-]+)\s*=\s*("([^"]*)"|\'([^\']*)\')/m', $attrs, $mm, PREG_SET_ORDER)) {
      foreach ($mm as $a) {
        $name = strtolower((string)($a[1] ?? ''));
        $v3 = (string)($a[3] ?? '');
        $v4 = (string)($a[4] ?? '');
        $val = (string)($v3 !== '' ? $v3 : $v4);

        if ($tag === 'a') {
          if (in_array($name, ['href','target','rel','title'], true)) {
            if ($name === 'href') $val = preg_replace('/javascript\s*:/i', '', $val);
            $keep[] = $name . '="' . htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') . '"';
          }
        } elseif ($tag === 'img') {
          if (in_array($name, ['src','alt','title','width','height'], true)) {
            if ($name === 'src') $val = preg_replace('/javascript\s*:/i', '', $val);
            $keep[] = $name . '="' . htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') . '"';
          }
        } elseif ($tag === 'span') {
          if ($name === 'style') {
            $valLower = strtolower((string)$val);
            $safe = [];
            foreach (explode(';', $valLower) as $decl) {
              $decl = trim($decl);
              if ($decl === '') continue;
              if (preg_match('/^(color|background-color)\s*:\s*[^;]+$/', $decl)) {
                $safe[] = $decl;
              }
            }
            if ($safe) {
              $keep[] = 'style="' . htmlspecialchars(implode('; ', $safe), ENT_QUOTES, 'UTF-8') . '"';
            }
          }
        }
      }
    }

    return '<' . $tag . (count($keep) ? ' ' . implode(' ', $keep) : '') . '>';
  }, $html);

  return $html;
}

function has_meaningful_content(string $html): bool {
  if (preg_match('/<img\b/i', $html)) return true;

  $text = strip_tags($html);
  $flags = ENT_QUOTES;
  if (defined('ENT_HTML5')) $flags |= ENT_HTML5;

  $text = html_entity_decode($text, $flags, 'UTF-8');
  $text = str_replace("\xc2\xa0", ' ', $text);
  $text = trim($text);

  return $text !== '';
}

$u = current_user();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$title = '';
$body = '';
$is_published = 0;

if ($id > 0) {
  $stmt = db()->prepare("SELECT * FROM announcements WHERE id=?");
  $stmt->execute([$id]);
  $a = $stmt->fetch();
  if (!$a) {
    http_response_code(404);
    echo "Annonce introuvable.";
    exit;
  }
  $title = (string)($a['title'] ?? '');
  $body  = (string)($a['body'] ?? '');
  $is_published = (int)($a['is_published'] ?? 0);
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    verify_csrf();

    $title = trim((string)($_POST['title'] ?? ''));
    $body  = (string)($_POST['body'] ?? '');
    $is_published = isset($_POST['is_published']) ? 1 : 0;

    if ($title === '' || !has_meaningful_content($body)) {
      $err = "Titre et contenu requis.";
    } else {
      $body = sanitize_announcement_html($body);

      if ($id > 0) {
        $stmt = db()->prepare("UPDATE announcements SET title=?, body=?, is_published=? WHERE id=?");
        $stmt->execute([$title, $body, $is_published, $id]);
      } else {
        $stmt = db()->prepare("INSERT INTO announcements (title, body, is_published, created_by) VALUES (?,?,?,?)");
        $stmt->execute([$title, $body, $is_published, (int)($u['id'] ?? 0)]);
      }

      header('Location: /admin/announcements_manage.php');
      exit;
    }
  } catch (Throwable $e) {
    $err = "Erreur lors de l'enregistrement : " . $e->getMessage();
    @error_log("ANNOUNCEMENT_SAVE_ERROR: " . $e->getMessage());
  }
}

samr_layout_start($id > 0 ? "Admin - Éditer l'annonce" : "Admin - Nouvelle annonce");
?>

<style>
  input, textarea{
    width:100%;
    padding:10px;
    border-radius:12px;
    border:1px solid var(--line);
    background:#0b0f14;
    color:var(--text);
  }
</style>

<div class="card">
  <div class="muted"><a href="/admin/announcements_manage.php">← Retour</a></div>
  <h2 style="margin:10px 0 0 0;"><?= $id > 0 ? "Éditer l'annonce" : "Nouvelle annonce" ?></h2>
  <div class="muted">Éditeur riche : gras/italique/souligné, couleurs, liens, images (upload).</div>
</div>

<div class="card">
  <?php if ($err): ?>
    <p style="color:#ff9aa2;"><?= htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

  <form method="post">
    <?= csrf_input() ?>

    <label class="muted">Titre</label>
    <input name="title" value="<?= htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') ?>" maxlength="140" required>

    <div style="height:10px;"></div>

    <label class="muted">Contenu</label>
    <textarea name="body" rows="12"><?= htmlspecialchars((string)$body, ENT_QUOTES, 'UTF-8') ?></textarea>

    <div style="height:10px;"></div>

    <label style="display:flex; gap:10px; align-items:center;">
      <input type="checkbox" name="is_published" <?= $is_published ? 'checked' : '' ?> style="width:auto;">
      Publier
    </label>

    <div style="height:14px;"></div>

    <button class="btn" type="submit">Enregistrer</button>
    <a class="btn secondary" href="/admin/announcements_manage.php" style="margin-left:10px;">Annuler</a>
  </form>
</div>

<div class="card">
  <div class="muted">
    Note: le contenu est enregistré en HTML et filtré (anti XSS basique).<br>
    Astuce: les images uploadées sont stockées dans <code>/uploads</code> via <code>/admin/upload_image.php</code>.
  </div>
</div>

<script src="https://cdn.tiny.cloud/1/bvlx587cgqi3i0tkb38ypfiea2itrizmrxnkza9x7701yii1/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  tinymce.init({
    selector: 'textarea[name="body"]',
    height: 420,
    menubar: false,
    plugins: 'link image lists code table',
    toolbar: 'undo redo | blocks | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link image table | code',
    images_upload_url: '/admin/upload_image.php',
    automatic_uploads: true,
    paste_data_images: false,
    images_replace_blob_uris: true,
    link_default_target: '_blank',
    link_rel_list: [{title: 'noopener', value: 'noopener'}],
    link_assume_external_targets: true
  });

  document.addEventListener('submit', function () {
    if (typeof tinymce !== 'undefined') tinymce.triggerSave();
  }, true);
</script>

<?php
samr_layout_end();
