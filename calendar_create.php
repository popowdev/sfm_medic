<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';

require_login();
$u = current_user();
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$tz = new DateTimeZone('Europe/Paris');

$title = '';
$date = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
$time = '09:00';
$notes = '';
$err = '';

function is_valid_time_30min(string $time): bool {
  if (!preg_match('/^\d{2}:\d{2}$/', $time)) return false;
  [$hh,$mm] = explode(':',$time);
  $h=(int)$hh; $m=(int)$mm;
  if ($h<0||$h>23) return false;
  return ($m===0||$m===30);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    verify_csrf();

    $title = trim((string)($_POST['title'] ?? ''));
    $date  = trim((string)($_POST['date'] ?? ''));
    $time  = trim((string)($_POST['time'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($title === '') throw new RuntimeException("Titre requis.");
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new RuntimeException("Date invalide.");
    if (!is_valid_time_30min($time)) throw new RuntimeException("Heure invalide (créneaux :00 ou :30).");

    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date.' '.$time, $tz);
    if (!$start) throw new RuntimeException("Date/heure invalide.");
    $end = $start->modify('+30 minutes');

    $stmt = db()->prepare("SELECT COUNT(*) AS c FROM appointments WHERE start_at < ? AND end_at > ?");
    $stmt->execute([$end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')]);
    $c = (int)($stmt->fetch()['c'] ?? 0);
    if ($c > 0) throw new RuntimeException("Conflit : un rendez-vous existe déjà sur ce créneau.");

    $ins = db()->prepare("INSERT INTO appointments (title, start_at, end_at, created_by, notes) VALUES (?,?,?,?,?)");
    $ins->execute([
      $title,
      $start->format('Y-m-d H:i:s'),
      $end->format('Y-m-d H:i:s'),
      (int)($u['id'] ?? 0),
      $notes !== '' ? $notes : null
    ]);

    header('Location: /calendar.php');
    exit;
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

$times = [];
$cur = new DateTimeImmutable('2000-01-01 06:00', $tz);
$stop = new DateTimeImmutable('2000-01-01 22:00', $tz);
while ($cur <= $stop) {
  $times[] = $cur->format('H:i');
  $cur = $cur->modify('+30 minutes');
}

samr_layout_start('SAMR - Nouveau RDV');
?>
<div class="card">
  <h1 style="margin:0 0 6px 0;">Créer un rendez-vous</h1>
  <div class="muted">Créneau fixe : <b>30 minutes</b> • Conflits bloqués automatiquement.</div>
</div>

<div class="card">
  <?php if ($err): ?>
    <div style="color:#ff9aa2;margin-bottom:10px;"><?= h($err) ?></div>
  <?php endif; ?>

  <form method="post">
    <?= csrf_input() ?>

    <label class="muted">Titre</label>
    <input name="title" value="<?= h($title) ?>" maxlength="150" required
      style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)">

    <div style="height:10px;"></div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div>
        <label class="muted">Date</label>
        <input type="date" name="date" value="<?= h($date) ?>" required
          style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)">
      </div>
      <div>
        <label class="muted">Heure</label>
        <select name="time" required
          style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)">
          <?php foreach ($times as $t): ?>
            <option value="<?= h($t) ?>" <?= $t === $time ? 'selected' : '' ?>><?= h($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div style="height:10px;"></div>

    <label class="muted">Notes (optionnel)</label>
    <textarea name="notes" style="width:100%;min-height:110px;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)"><?= h($notes) ?></textarea>

    <div style="height:14px;"></div>

    <button class="btn" type="submit">Créer le RDV</button>
    <a class="btn secondary" href="/calendar.php" style="margin-left:10px;">Annuler</a>
  </form>
</div>

<?php samr_layout_end(); ?>
