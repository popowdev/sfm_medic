<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/rdv_common.php';

rdv_ensure_schema();
$pdo = db();

$id = 0;
if (isset($_GET['id'])) $id = (int)$_GET['id'];
if ($id <= 0 && !empty($_SERVER['PATH_INFO'])) {
  $id = (int)trim((string)$_SERVER['PATH_INFO'], '/');
}
if ($id <= 0) {
  $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
  if (preg_match('~/rdv/(\d+)~', $uri, $m)) $id = (int)$m[1];
}

$createdId = 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? 'create');

  if ($action === 'create') {
    $category = trim((string)($_POST['category'] ?? ''));
    $rpName = trim((string)($_POST['rp_name'] ?? ''));
    $discordName = trim((string)($_POST['discord_name'] ?? ''));
    $rpPhone = trim((string)($_POST['rp_phone'] ?? ''));
    $availability = trim((string)($_POST['availability'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $honeypot = trim((string)($_POST['website'] ?? ''));

    $categories = rdv_categories();
    if ($honeypot !== '') {
      $error = 'Demande invalide.';
    } elseif (!isset($categories[$category])) {
      $error = 'Merci de sélectionner une catégorie valide.';
    } elseif ($rpName === '' || $discordName === '' || $message === '') {
      $error = 'Merci de remplir au minimum le nom, le prénom et le motif.';
    } else {
      $stmt = $pdo->prepare("INSERT INTO rdv_requests (category, rp_name, discord_name, rp_phone, availability, message, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())");
      $stmt->execute([$category, $rpName, $discordName, $rpPhone, $availability, $message]);
      $createdId = (int)$pdo->lastInsertId();
      $stmt = $pdo->prepare("INSERT INTO rdv_messages (request_id, author_type, author_name, body, created_at) VALUES (?, 'player', ?, ?, NOW())");
      $stmt->execute([$createdId, trim($rpName . ' ' . $discordName), $message]);
      rdv_log_action($createdId, 'create', null, null, null, ['id' => null, 'name' => trim($rpName . ' ' . $discordName), 'color' => '#3b9eff'], 'player');
      header('Location: ' . rdv_url($createdId) . '?created=1');
      exit;
    }
  } elseif ($action === 'reply') {
    $id = (int)($_POST['id'] ?? 0);
    $body = trim((string)($_POST['body'] ?? ''));
    $request = $id > 0 ? rdv_find($id) : null;
    if (!$request) {
      $error = 'Demande introuvable.';
    } elseif ($body === '') {
      $error = 'Merci d\'écrire un message.';
    } else {
      $type = is_logged_in() ? 'staff' : 'player';
      if ($type === 'staff') {
        $staff = rdv_current_staff();
        $stmt = $pdo->prepare("INSERT INTO rdv_messages (request_id, author_type, author_user_id, author_name, author_color, body, created_at) VALUES (?, 'staff', ?, ?, ?, ?, NOW())");
        $stmt->execute([$id, $staff['id'], $staff['name'], $staff['color'], $body]);
        rdv_log_action($id, 'reply', null, null, null, $staff);
      } else {
        $author = trim((string)$request['rp_name'] . ' ' . (string)$request['discord_name']);
        $stmt = $pdo->prepare("INSERT INTO rdv_messages (request_id, author_type, author_name, body, created_at) VALUES (?, 'player', ?, ?, NOW())");
        $stmt->execute([$id, $author, $body]);
      }
      $pdo->prepare('UPDATE rdv_requests SET updated_at = NOW() WHERE id = ?')->execute([$id]);
      header('Location: ' . rdv_url($id));
      exit;
    }
  }
}

if ($id <= 0) {
  header('Location: /#demande-rdv');
  exit;
}

$request = rdv_find($id);
$messages = $request ? rdv_messages($id) : [];
$actions = $request ? rdv_actions($id) : [];

samr_layout_start('SAMR - Suivi RDV');
?>

<div class="page-header">
  <h1>Suivi de demande RDV</h1>
  <div class="sub">Lien de suivi : <strong><?= rdv_h(rdv_url($id)) ?></strong></div>
</div>

<?php if (!$request): ?>
  <div class="card"><strong>Demande introuvable.</strong><br><span class="muted">Vérifie le numéro de demande.</span></div>
<?php else: ?>
  <?php if (isset($_GET['created'])): ?>
    <div class="card card-accent">
      <h2 style="margin-bottom:8px;">✅ Demande envoyée</h2>
      <p>Numéro de demande : <strong>#<?= (int)$request['id'] ?></strong></p>
      <p class="muted">Garde ce lien pour suivre les réponses : <strong><?= rdv_h(rdv_url((int)$request['id'])) ?></strong></p>
    </div>
  <?php endif; ?>

  <?php if ($error !== ''): ?><div class="card" style="border-color:rgba(255,79,79,.35);color:#ffb3b3;"><?= rdv_h($error) ?></div><?php endif; ?>

  <div class="card card-accent">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start;">
      <div>
        <h2 style="margin-bottom:6px;">Demande #<?= (int)$request['id'] ?> — <?= rdv_h((string)$request['category']) ?></h2>
        <div class="muted">Créée le <?= rdv_h((string)$request['created_at']) ?> • Mise à jour le <?= rdv_h((string)$request['updated_at']) ?></div>
      </div>
      <span class="pill pill-blue"><?= rdv_h(rdv_status_label((string)$request['status'])) ?></span>
    </div>
    <?php if (!empty($request['assigned_name'])): ?>
      <div style="margin-top:12px;display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:<?= rdv_h(rdv_valid_color((string)$request['assigned_color'])) ?>22;border:1px solid <?= rdv_h(rdv_valid_color((string)$request['assigned_color'])) ?>66;color:#fff;">
        <span style="width:10px;height:10px;border-radius:999px;background:<?= rdv_h(rdv_valid_color((string)$request['assigned_color'])) ?>;"></span>
        Cette demande est prise en charge par <strong><?= rdv_h((string)$request['assigned_name']) ?></strong>
      </div>
    <?php elseif (!empty($request['status_updated_by_name'])): ?>
      <div class="muted" style="margin-top:12px;">Dernière action par <?= rdv_h((string)$request['status_updated_by_name']) ?></div>
    <?php endif; ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:16px;">
      <div><div class="muted">Nom</div><strong><?= rdv_h((string)$request['rp_name']) ?></strong></div>
      <div><div class="muted">Prénom</div><strong><?= rdv_h((string)$request['discord_name']) ?></strong></div>
      <div><div class="muted">Téléphone</div><strong><?= rdv_h((string)($request['rp_phone'] ?: '—')) ?></strong></div>
      <div><div class="muted">Disponibilités</div><strong><?= nl2br(rdv_h((string)($request['availability'] ?: '—'))) ?></strong></div>
    </div>
  </div>

  <div class="card">
    <h2 style="margin-bottom:12px;">Échanges</h2>
    <?php foreach ($messages as $m): ?>
      <?php $msgColor = rdv_valid_color((string)($m['author_color'] ?? '#3b9eff')); ?>
      <div style="border:1px solid <?= ($m['author_type'] === 'staff') ? rdv_h($msgColor) . '66' : 'var(--line)' ?>;border-left:4px solid <?= ($m['author_type'] === 'staff') ? rdv_h($msgColor) : 'var(--line)' ?>;border-radius:12px;padding:12px;margin-bottom:10px;background:<?= ($m['author_type'] === 'staff') ? rdv_h($msgColor) . '12' : 'rgba(255,255,255,.02)' ?>;">
        <div class="muted" style="font-size:12px;margin-bottom:6px;"><?= $m['author_type'] === 'staff' ? '🚑 SAMR' : '👤 Demandeur' ?> — <?= rdv_h((string)$m['author_name']) ?> • <?= rdv_h((string)$m['created_at']) ?></div>
        <div style="white-space:pre-wrap;"><?= rdv_h((string)$m['body']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2 style="margin-bottom:12px;">Historique</h2>
    <?php if (!$actions): ?>
      <div class="muted">Aucune action enregistrée.</div>
    <?php else: ?>
      <div style="display:grid;gap:8px;">
        <?php foreach ($actions as $a): $aColor = rdv_valid_color((string)($a['actor_color'] ?? '#3b9eff')); ?>
          <div style="display:flex;gap:8px;align-items:center;font-size:13px;color:var(--muted);">
            <span style="width:9px;height:9px;border-radius:999px;background:<?= rdv_h($aColor) ?>;"></span>
            <strong style="color:#fff;"><?= rdv_h((string)$a['actor_name']) ?></strong>
            <span><?= rdv_h(rdv_action_label((string)$a['action'], $a['old_value'] ?? null, $a['new_value'] ?? null)) ?></span>
            <span>• <?= rdv_h((string)$a['created_at']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!in_array((string)$request['status'], ['done','refused'], true)): ?>
    <div class="card">
      <h2 style="margin-bottom:12px;">Répondre</h2>
      <form method="post">
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
        <textarea class="inp" name="body" rows="5" required placeholder="Écrire un message..."></textarea>
        <div style="margin-top:12px;"><button class="btn success" type="submit">Envoyer la réponse</button></div>
      </form>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php samr_layout_end(); ?>
