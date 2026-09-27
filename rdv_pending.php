<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/rdv_common.php';

require_login();
rdv_ensure_schema();
rdv_cleanup_expired();

$pdo = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  $id = (int)($_POST['id'] ?? 0);
  $request = $id > 0 ? rdv_find($id) : null;
  $staff = rdv_current_staff();

  if (!$request) {
    $error = 'Demande introuvable.';
  } elseif ($action === 'delete') {
    $pdo->prepare('DELETE FROM rdv_requests WHERE id = ?')->execute([$id]);
    header('Location: /rdv_pending.php');
    exit;
  } elseif ($action === 'schedule') {
    try {
      $startMysql = rdv_datetime_local_to_mysql((string)($_POST['scheduled_start_at'] ?? ''));
      $duration = (int)($_POST['duration_minutes'] ?? 30);
      if ($duration <= 0) $duration = 30;

      $appointmentId = rdv_create_calendar_appointment($request, $startMysql, $duration, $staff);
      $endMysql = (new DateTimeImmutable($startMysql, new DateTimeZone('Europe/Paris')))->modify('+' . max(15, min(240, $duration)) . ' minutes')->format('Y-m-d H:i:s');

      $oldStatus = (string)$request['status'];
      $stmt = $pdo->prepare("UPDATE rdv_requests
        SET status = 'accepted',
            assigned_user_id = ?, assigned_name = ?, assigned_color = ?,
            status_updated_by_id = ?, status_updated_by_name = ?, status_updated_by_color = ?, status_updated_at = NOW(),
            appointment_id = ?, scheduled_start_at = ?, scheduled_end_at = ?,
            scheduled_by_id = ?, scheduled_by_name = ?, scheduled_by_color = ?,
            updated_at = NOW()
        WHERE id = ?");
      $stmt->execute([
        $staff['id'], $staff['name'], $staff['color'],
        $staff['id'], $staff['name'], $staff['color'],
        $appointmentId, $startMysql, $endMysql,
        $staff['id'], $staff['name'], $staff['color'],
        $id
      ]);

      if ($oldStatus !== 'accepted') {
        rdv_log_action($id, 'status', $oldStatus, 'accepted', null, $staff);
      }
      rdv_log_action($id, 'schedule', null, $startMysql, 'Rendez-vous créé dans le calendrier #' . $appointmentId, $staff);
      header('Location: /rdv_pending.php?status=accepted#rdv-' . $id);
      exit;
    } catch (Throwable $e) {
      $error = $e->getMessage();
    }
  } elseif ($action === 'status') {
    $status = (string)($_POST['status'] ?? '');
    if (!array_key_exists($status, rdv_statuses())) {
      $error = 'Statut invalide.';
    } else {
      $oldStatus = (string)$request['status'];

      if ($status === 'accepted') {
        $stmt = $pdo->prepare('UPDATE rdv_requests SET status = ?, assigned_user_id = ?, assigned_name = ?, assigned_color = ?, status_updated_by_id = ?, status_updated_by_name = ?, status_updated_by_color = ?, status_updated_at = NOW(), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $staff['id'], $staff['name'], $staff['color'], $staff['id'], $staff['name'], $staff['color'], $id]);
      } else {
        $stmt = $pdo->prepare('UPDATE rdv_requests SET status = ?, status_updated_by_id = ?, status_updated_by_name = ?, status_updated_by_color = ?, status_updated_at = NOW(), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $staff['id'], $staff['name'], $staff['color'], $id]);
      }

      if ($oldStatus !== $status) {
        rdv_log_action($id, 'status', $oldStatus, $status, null, $staff);
      }
      header('Location: /rdv_pending.php#rdv-' . $id);
      exit;
    }
  } elseif ($action === 'reply') {
    $body = trim((string)($_POST['body'] ?? ''));
    if ($body === '') {
      $error = 'Message vide.';
    } else {
      $stmt = $pdo->prepare("INSERT INTO rdv_messages (request_id, author_type, author_user_id, author_name, author_color, body, created_at) VALUES (?, 'staff', ?, ?, ?, ?, NOW())");
      $stmt->execute([$id, $staff['id'], $staff['name'], $staff['color'], $body]);
      $pdo->prepare('UPDATE rdv_requests SET updated_at = NOW() WHERE id = ?')->execute([$id]);
      rdv_log_action($id, 'reply', null, null, null, $staff);
      header('Location: /rdv_pending.php#rdv-' . $id);
      exit;
    }
  }
}

$statusFilter = (string)($_GET['status'] ?? 'pending');
if ($statusFilter !== 'all' && !array_key_exists($statusFilter, rdv_statuses())) $statusFilter = 'pending';

if ($statusFilter === 'all') {
  $stmt = $pdo->query('SELECT * FROM rdv_requests ORDER BY FIELD(status, "pending", "need_info", "accepted", "refused", "done"), updated_at DESC LIMIT 300');
  $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
  $stmt = $pdo->prepare('SELECT * FROM rdv_requests WHERE status = ? ORDER BY updated_at DESC LIMIT 300');
  $stmt->execute([$statusFilter]);
  $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

samr_layout_start('SAMR - RDV en attente');
?>

<div class="page-header">
  <h1>RDV en attente</h1>
  <div class="sub">Gestion des demandes de rendez-vous. Les cartes sont en accordéon pour garder la page lisible.</div>
</div>

<?php if ($error !== ''): ?><div class="card" style="border-color:rgba(255,79,79,.35);color:#ffb3b3;"><?= rdv_h($error) ?></div><?php endif; ?>

<div class="card" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
  <?php foreach (['pending' => 'En attente', 'need_info' => 'Besoin d\'infos', 'accepted' => 'Acceptés', 'refused' => 'Refusés', 'done' => 'Terminés', 'all' => 'Tous'] as $key => $label): ?>
    <a class="btn <?= $statusFilter === $key ? '' : 'secondary' ?>" href="/rdv_pending.php?status=<?= rdv_h($key) ?>"><?= rdv_h($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$requests): ?>
  <div class="card"><span class="muted">Aucune demande dans ce filtre.</span></div>
<?php endif; ?>

<div class="rdv-accordion-list">
<?php foreach ($requests as $r):
  $msgs = rdv_messages((int)$r['id']);
  $actions = rdv_actions((int)$r['id']);
  $cardColor = rdv_request_color($r);
  $summaryName = trim((string)$r['rp_name'] . ' ' . (string)$r['discord_name']);
?>
  <details class="card rdv-accordion" id="rdv-<?= (int)$r['id'] ?>" style="border-left:5px solid <?= rdv_h($cardColor) ?>;box-shadow:0 0 0 1px rgba(255,255,255,.02), 0 0 18px <?= rdv_h($cardColor) ?>22;">
    <summary class="rdv-summary">
      <div class="rdv-summary-main">
        <strong>#<?= (int)$r['id'] ?> — <?= rdv_h((string)$r['category']) ?></strong>
        <span class="muted"><?= rdv_h($summaryName) ?> • <?= rdv_h(rdv_status_label((string)$r['status'])) ?></span>
      </div>
      <div class="rdv-summary-right">
        <?php if (!empty($r['scheduled_start_at'])): ?>
          <span class="pill">📅 <?= rdv_h((string)$r['scheduled_start_at']) ?></span>
        <?php endif; ?>
        <?php if (!empty($r['assigned_name'])): ?>
          <span class="pill" style="border-color:<?= rdv_h(rdv_valid_color((string)$r['assigned_color'])) ?>66;color:#fff;">🚑 <?= rdv_h((string)$r['assigned_name']) ?></span>
        <?php endif; ?>
      </div>
    </summary>

    <div class="rdv-details-body">
      <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start;">
        <div>
          <h2 style="margin-bottom:4px;">#<?= (int)$r['id'] ?> — <?= rdv_h((string)$r['category']) ?></h2>
          <div class="muted"><?= rdv_h((string)$r['rp_name']) ?> <?= rdv_h((string)$r['discord_name']) ?> • Tél : <?= rdv_h((string)($r['rp_phone'] ?: '—')) ?></div>
          <div class="muted">Créée le <?= rdv_h((string)$r['created_at']) ?> • Lien : <a href="<?= rdv_h(rdv_url((int)$r['id'])) ?>"><?= rdv_h(rdv_url((int)$r['id'])) ?></a></div>
          <?php if (!empty($r['scheduled_start_at'])): ?>
            <div class="muted" style="margin-top:8px;">Calendrier : RDV prévu le <strong><?= rdv_h((string)$r['scheduled_start_at']) ?></strong></div>
          <?php endif; ?>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
          <form method="post" onsubmit="return confirm('Supprimer cette demande ?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn danger" type="submit">Supprimer</button>
          </form>
          <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <select class="inp" name="status" style="min-width:170px;">
              <?php foreach (rdv_statuses() as $key => $label): ?>
                <option value="<?= rdv_h($key) ?>" <?= ((string)$r['status'] === $key) ? 'selected' : '' ?>><?= rdv_h($label) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">OK</button>
          </form>
        </div>
      </div>

      <div class="rdv-schedule-box">
        <h3 style="margin:0 0 8px 0;">Planifier dans le calendrier</h3>
        <form method="post" class="rdv-schedule-form">
          <input type="hidden" name="action" value="schedule">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <div>
            <label class="muted">Date et heure du RDV</label>
            <input class="inp" type="datetime-local" name="scheduled_start_at" value="<?= rdv_h(rdv_mysql_to_datetime_local($r['scheduled_start_at'] ?? null)) ?>" required>
          </div>
          <div>
            <label class="muted">Durée</label>
            <select class="inp" name="duration_minutes">
              <option value="30">30 min</option>
              <option value="45">45 min</option>
              <option value="60">1 h</option>
              <option value="90">1 h 30</option>
              <option value="120">2 h</option>
            </select>
          </div>
          <div style="display:flex;align-items:flex-end;">
            <button class="btn success" type="submit">Créer le RDV calendrier</button>
          </div>
        </form>
        <div class="muted" style="margin-top:8px;font-size:13px;">Cette action crée le rendez-vous dans le calendrier et passe automatiquement la demande en <strong>Accepté</strong>.</div>
      </div>

      <div style="margin-top:14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
        <div><div class="muted">Disponibilités</div><div style="white-space:pre-wrap;"><?= rdv_h((string)($r['availability'] ?: '—')) ?></div></div>
        <div><div class="muted">Demande initiale</div><div style="white-space:pre-wrap;"><?= rdv_h((string)$r['message']) ?></div></div>
      </div>

      <details style="margin-top:14px;" open>
        <summary style="cursor:pointer;font-weight:700;">Échanges (<?= count($msgs) ?>)</summary>
        <div style="margin-top:10px;">
          <?php foreach ($msgs as $m): $msgColor = rdv_valid_color((string)($m['author_color'] ?? '#3b9eff')); ?>
            <div style="border:1px solid <?= ($m['author_type'] === 'staff') ? rdv_h($msgColor) . '66' : 'var(--line)' ?>;border-left:4px solid <?= ($m['author_type'] === 'staff') ? rdv_h($msgColor) : 'var(--line)' ?>;border-radius:12px;padding:10px;margin-bottom:8px;background:<?= ($m['author_type'] === 'staff') ? rdv_h($msgColor) . '12' : 'rgba(255,255,255,.02)' ?>;">
              <div class="muted" style="font-size:12px;margin-bottom:4px;"><?= $m['author_type'] === 'staff' ? '🚑 SAMR' : '👤 Demandeur' ?> — <?= rdv_h((string)$m['author_name']) ?> • <?= rdv_h((string)$m['created_at']) ?></div>
              <div style="white-space:pre-wrap;"><?= rdv_h((string)$m['body']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </details>

      <details style="margin-top:12px;">
        <summary style="cursor:pointer;font-weight:700;">Historique des actions (<?= count($actions) ?>)</summary>
        <div style="margin-top:10px;display:grid;gap:8px;">
          <?php foreach ($actions as $a): $aColor = rdv_valid_color((string)($a['actor_color'] ?? '#3b9eff')); ?>
            <div style="display:flex;gap:8px;align-items:center;font-size:13px;color:var(--muted);">
              <span style="width:9px;height:9px;border-radius:999px;background:<?= rdv_h($aColor) ?>;"></span>
              <strong style="color:#fff;"><?= rdv_h((string)$a['actor_name']) ?></strong>
              <span><?= rdv_h(rdv_action_label((string)$a['action'], $a['old_value'] ?? null, $a['new_value'] ?? null)) ?></span>
              <span>• <?= rdv_h((string)$a['created_at']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </details>

      <form method="post" style="margin-top:12px;">
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <textarea class="inp" name="body" rows="3" placeholder="Répondre à cette demande..."></textarea>
        <div style="margin-top:8px;"><button class="btn success" type="submit">Répondre</button></div>
      </form>
    </div>
  </details>
<?php endforeach; ?>
</div>

<style>
.rdv-accordion-list{display:grid;gap:10px;}
.rdv-accordion{padding:0 !important;overflow:hidden;}
.rdv-summary{list-style:none;cursor:pointer;padding:14px 16px;display:flex;justify-content:space-between;gap:12px;align-items:center;}
.rdv-summary::-webkit-details-marker{display:none;}
.rdv-summary:before{content:'▶';color:var(--muted);margin-right:8px;}
.rdv-accordion[open] .rdv-summary:before{content:'▼';}
.rdv-summary-main{display:flex;flex-direction:column;gap:4px;min-width:0;}
.rdv-summary-right{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;}
.rdv-details-body{padding:0 16px 16px 16px;border-top:1px solid var(--line);}
.rdv-schedule-box{margin-top:14px;padding:12px;border:1px solid var(--line);border-radius:14px;background:rgba(59,158,255,.04);}
.rdv-schedule-form{display:grid;grid-template-columns:1fr 150px auto;gap:10px;align-items:end;}
.btn.danger{background:rgba(255,79,79,.12);border-color:rgba(255,79,79,.35);color:#ffb3b3;}
.btn.danger:hover{background:rgba(255,79,79,.2);}
@media (max-width:800px){.rdv-summary{align-items:flex-start;flex-direction:column}.rdv-summary-right{justify-content:flex-start}.rdv-schedule-form{grid-template-columns:1fr}.rdv-details-body{padding:0 12px 12px 12px}}
</style>

<?php samr_layout_end(); ?>
