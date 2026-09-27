<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/csrf.php';

require_login();
$u   = current_user();
$uid = (int)($u['id'] ?? 0);

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function normalize_nullable_string(?string $v): ?string {
  $v = trim((string)$v);
  return $v !== '' ? $v : null;
}
function is_valid_hex_color(?string $color): bool {
  return $color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1;
}

function samr_grade_rates(): array {
  return [
    'Ambulancier'       => 600,
    'Chef Ambulancier'  => 700,
    'Infirmier'         => 800,
    'Infirmier en chef' => 900,
    'Médecin'           => 1000,
    'Médecin en chef'   => 1100,
    'Chef d\'équipe'    => 1200,
    'Co-Directeur'      => 1300,
    'Directeur'         => 1500,
  ];
}

function get_user_rate(PDO $pdo, string $grade): int {
  $rates       = samr_grade_rates();
  $defaultRate = (int)($pdo->query("SELECT hourly_rate FROM salary_settings WHERE id=1")->fetchColumn() ?: 1000);
  return $rates[$grade] ?? $defaultRate;
}

function load_profile(PDO $pdo, int $uid): array {
  $stmt = $pdo->prepare("
    SELECT id, username, email, role, grade, can_supervise,
           calendar_color, iban, matricule, phone,
           signature_url, profile_photo_url, discord_id
    FROM users
    WHERE id=? LIMIT 1
  ");
  $stmt->execute([$uid]);
  $profile = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$profile) {
    header('Location: /logout.php');
    exit;
  }
  return $profile;
}

function save_uploaded_image(array $file, int $uid, string $prefix): string {
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    throw new RuntimeException('Aucun fichier envoyé.');
  }
  if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
    throw new RuntimeException('Image trop lourde (max 5 Mo).');
  }
  if (!is_uploaded_file($file['tmp_name'])) {
    throw new RuntimeException('Upload invalide.');
  }

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = (string)$finfo->file($file['tmp_name']);
  $map = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
  ];
  if (!isset($map[$mime])) {
    throw new RuntimeException('Format image non supporté.');
  }

  $dir = __DIR__ . '/uploads/profile';
  if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    throw new RuntimeException('Impossible de créer le dossier d\'upload.');
  }

  $name = sprintf('%s_%d_%s.%s', $prefix, $uid, bin2hex(random_bytes(8)), $map[$mime]);
  $full = $dir . '/' . $name;
  if (!move_uploaded_file($file['tmp_name'], $full)) {
    throw new RuntimeException('Échec de l\'upload.');
  }

  return '/uploads/profile/' . $name;
}

function get_salary_data(PDO $pdo, int $userId, int $weekOffset = 0): array {
  $tz  = new DateTimeZone('Europe/Paris');
  $now = new DateTime('now', $tz);

  $stmt = $pdo->prepare("SELECT grade FROM users WHERE id=? LIMIT 1");
  $stmt->execute([$userId]);
  $grade = (string)($stmt->fetchColumn() ?: '');
  $rate  = get_user_rate($pdo, $grade);

  $activeStmt = $pdo->prepare("SELECT id, started_at FROM salary_shifts WHERE user_id=? AND ended_at IS NULL LIMIT 1");
  $activeStmt->execute([$userId]);
  $active = $activeStmt->fetch(PDO::FETCH_ASSOC) ?: null;

  $anchor = clone $now;
  if ($weekOffset > 0) {
    $anchor->modify('-' . ($weekOffset * 7) . ' days');
  }

  $dow = (int)$anchor->format('w');
  $from = clone $anchor;
  $from->modify('-' . $dow . ' days');
  $from->setTime(0, 0, 0);

  $to = clone $from;
  $to->modify('+6 days');
  $to->setTime(23, 59, 59);

  $stmt = $pdo->prepare("
    SELECT id, started_at, ended_at
    FROM salary_shifts
    WHERE user_id=? AND (
      (started_at BETWEEN ? AND ?)
      OR (ended_at BETWEEN ? AND ?)
      OR (started_at <= ? AND (ended_at >= ? OR ended_at IS NULL))
    )
    ORDER BY started_at ASC
  ");
  $fromStr = $from->format('Y-m-d H:i:s');
  $toStr   = $to->format('Y-m-d H:i:s');
  $stmt->execute([$userId, $fromStr, $toStr, $fromStr, $toStr, $fromStr, $fromStr]);
  $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $byDay = [];
  for ($d = 0; $d < 7; $d++) {
    $day = clone $from;
    $day->modify('+' . $d . ' days');
    $key = $day->format('Y-m-d');
    $byDay[$key] = [
      'date'    => clone $day,
      'shifts'  => [],
      'seconds' => 0,
      'amount'  => 0,
    ];
  }

  $now2 = new DateTime('now', $tz);
  foreach ($shifts as $sh) {
    $start = new DateTime($sh['started_at'], $tz);
    $end   = $sh['ended_at'] ? new DateTime($sh['ended_at'], $tz) : clone $now2;

    $cursor = clone $start;
    while ($cursor < $end) {
      $dayStart = (clone $cursor)->setTime(0, 0, 0);
      $dayEnd   = (clone $dayStart)->setTime(23, 59, 59);

      $segmentStart = $cursor > $dayStart ? clone $cursor : clone $dayStart;
      $segmentEnd   = $end < $dayEnd ? clone $end : clone $dayEnd;
      $seconds = max(0, $segmentEnd->getTimestamp() - $segmentStart->getTimestamp());
      $key = $dayStart->format('Y-m-d');

      if (isset($byDay[$key])) {
        $byDay[$key]['seconds'] += $seconds;
        $byDay[$key]['amount']  += (int)round(($seconds / 3600) * $rate);
      }

      $nextDay = (clone $dayStart)->modify('+1 day')->setTime(0, 0, 0);
      if ($nextDay <= $cursor) break;
      $cursor = $nextDay;
    }

    $dayKey = $start->format('Y-m-d');
    if (isset($byDay[$dayKey])) {
      $byDay[$dayKey]['shifts'][] = $sh;
    }
  }

  $totalSec = array_sum(array_column($byDay, 'seconds'));

  return [
    'grade'      => $grade,
    'rate'       => $rate,
    'active'     => $active,
    'byDay'      => array_values($byDay),
    'totalSec'   => $totalSec,
    'hours'      => floor($totalSec / 3600),
    'mins'       => floor(($totalSec % 3600) / 60),
    'amount'     => (int)round(($totalSec / 3600) * $rate),
    'from'       => $from->format('d/m/Y'),
    'to'         => $to->format('d/m/Y'),
    'weekOffset' => $weekOffset,
    'now'        => $now2,
  ];
}

$pdo = db();
$profile = load_profile($pdo, $uid);
$weekOffset = max(0, (int)($_GET['week'] ?? 0));
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_shift'])) {
  try {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
      throw new RuntimeException('CSRF invalide.');
    }

    $action  = (string)$_POST['action_shift'];
    $shiftId = (int)($_POST['shift_id'] ?? 0);

    $own = $pdo->prepare("SELECT id FROM salary_shifts WHERE id=? AND user_id=? LIMIT 1");
    $own->execute([$shiftId, $uid]);
    if (!$own->fetchColumn()) {
      throw new RuntimeException('Shift introuvable.');
    }

    if ($action === 'delete') {
      $pdo->prepare("DELETE FROM salary_shifts WHERE id=?")->execute([$shiftId]);
      $msg = 'Service supprimé.';
    } elseif ($action === 'edit') {
      $newStart = trim((string)($_POST['new_start'] ?? ''));
      $newEnd   = trim((string)($_POST['new_end'] ?? ''));
      if ($newStart === '') {
        throw new RuntimeException('Heure de début requise.');
      }
      $startDt = new DateTime($newStart, new DateTimeZone('Europe/Paris'));
      $endDt   = $newEnd !== '' ? new DateTime($newEnd, new DateTimeZone('Europe/Paris')) : null;
      if ($endDt && $endDt <= $startDt) {
        throw new RuntimeException('La fin doit être après le début.');
      }
      $pdo->prepare("UPDATE salary_shifts SET started_at=?, ended_at=? WHERE id=?")
        ->execute([
          $startDt->format('Y-m-d H:i:s'),
          $endDt ? $endDt->format('Y-m-d H:i:s') : null,
          $shiftId,
        ]);
      $msg = 'Service modifié.';
    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
  try {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
      throw new RuntimeException('CSRF invalide.');
    }

    $newUsername  = trim((string)($_POST['username'] ?? ''));
    $newColor     = trim((string)($_POST['calendar_color'] ?? ''));
    $newIban      = normalize_nullable_string($_POST['iban'] ?? null);
    $newMatricule = normalize_nullable_string($_POST['matricule'] ?? null);
    $newPhone     = normalize_nullable_string($_POST['phone'] ?? null);

    if ($newUsername === '') {
      throw new RuntimeException('Le nom ne peut pas être vide.');
    }
    if ($newColor !== '' && !is_valid_hex_color($newColor)) {
      throw new RuntimeException('Couleur invalide.');
    }

    $stmt = $pdo->prepare("UPDATE users SET username=?, calendar_color=?, iban=?, matricule=?, phone=? WHERE id=?");
    $stmt->execute([
      $newUsername,
      $newColor !== '' ? $newColor : null,
      $newIban,
      $newMatricule,
      $newPhone,
      $uid,
    ]);
    $msg = 'Informations personnelles mises à jour.';
    $profile = load_profile($pdo, $uid);
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_photo'])) {
  try {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
      throw new RuntimeException('CSRF invalide.');
    }
    if (!isset($_FILES['profile_photo'])) {
      throw new RuntimeException('Aucune photo envoyée.');
    }
    $path = save_uploaded_image($_FILES['profile_photo'], $uid, 'profile');
    $pdo->prepare("UPDATE users SET profile_photo_url=? WHERE id=?")->execute([$path, $uid]);
    $msg = 'Photo de profil enregistrée.';
    $profile = load_profile($pdo, $uid);
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_signature'])) {
  try {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
      throw new RuntimeException('CSRF invalide.');
    }
    if (!isset($_FILES['signature_file'])) {
      throw new RuntimeException('Aucune signature envoyée.');
    }
    $path = save_uploaded_image($_FILES['signature_file'], $uid, 'signature');
    $pdo->prepare("UPDATE users SET signature_url=? WHERE id=?")->execute([$path, $uid]);
    $msg = 'Signature enregistrée.';
    $profile = load_profile($pdo, $uid);
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

$salary = get_salary_data($pdo, $uid, $weekOffset);

samr_layout_start('SAMR - Mon Profil');
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
  <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
    <div style="width:68px;height:68px;border-radius:50%;overflow:hidden;border:1px solid var(--line2);background:rgba(255,255,255,.04);display:flex;align-items:center;justify-content:center;flex:0 0 auto;">
      <?php if (!empty($profile['profile_photo_url'])): ?>
        <img src="<?= h((string)$profile['profile_photo_url']) ?>" alt="Photo de profil" style="width:100%;height:100%;object-fit:cover;">
      <?php else: ?>
        <span style="font-size:24px;">👤</span>
      <?php endif; ?>
    </div>
    <div>
      <h1 style="margin:0;">🧑‍⚕️ Mon profil</h1>
      <div class="sub"><?= h((string)$profile['username']) ?> — <?= h((string)($profile['grade'] ?: 'Grade non défini')) ?></div>
    </div>
  </div>
  <?php if ($salary['active']): ?>
    <span class="pill pill-green">● En service</span>
  <?php else: ?>
    <span class="pill">● Hors service</span>
  <?php endif; ?>
</div>

<?php if ($msg): ?>
  <div style="padding:10px 14px;border-radius:9px;background:rgba(0,212,170,.08);border:1px solid rgba(0,212,170,.25);color:var(--accent2);font-size:13px;margin-bottom:14px;">✓ <?= h($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div style="padding:10px 14px;border-radius:9px;background:rgba(255,79,79,.08);border:1px solid rgba(255,79,79,.25);color:#ff7a7a;font-size:13px;margin-bottom:14px;">✗ <?= h($err) ?></div>
<?php endif; ?>

<div class="card">
  <div style="font-family:var(--font-head);font-size:14px;font-weight:700;margin-bottom:14px;">📋 Informations SAMR</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
    <div style="padding:12px;border-radius:9px;background:rgba(255,255,255,.02);border:1px solid var(--line);">
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:4px;">Grade</div>
      <div style="font-weight:600;"><?= h((string)($profile['grade'] ?: '—')) ?></div>
    </div>
    <div style="padding:12px;border-radius:9px;background:rgba(255,255,255,.02);border:1px solid var(--line);">
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:4px;">Taux horaire</div>
      <div style="font-weight:600;color:var(--accent2);"><?= $salary['rate'] ?> $/h</div>
    </div>
    <div style="padding:12px;border-radius:9px;background:rgba(255,255,255,.02);border:1px solid var(--line);">
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:4px;">Rôle</div>
      <div style="font-weight:600;"><?= h((string)($profile['role'] === 'admin' ? 'Administrateur' : 'Membre')) ?></div>
    </div>
    <div style="padding:12px;border-radius:9px;background:rgba(255,255,255,.02);border:1px solid var(--line);">
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:4px;">Discord</div>
      <div style="font-weight:600;"><?= !empty($profile['discord_id']) ? '✓ Lié' : '— Non lié' ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div style="font-family:var(--font-head);font-size:14px;font-weight:700;margin-bottom:16px;">⚙️ Informations personnelles</div>
  <form method="post" class="profile-form-grid">
    <?= csrf_input() ?>
    <input type="hidden" name="save_profile" value="1">

    <label style="grid-column:1/-1;">
      <div class="field-label">Nom affiché</div>
      <input class="inp" name="username" value="<?= h((string)$profile['username']) ?>" required>
    </label>

    <label>
      <div class="field-label">Matricule</div>
      <input class="inp" name="matricule" value="<?= h((string)($profile['matricule'] ?? '')) ?>" placeholder="Ex: 01, 09, 87…" maxlength="20">
    </label>

    <label>
      <div class="field-label">Téléphone</div>
      <input class="inp" name="phone" value="<?= h((string)($profile['phone'] ?? '')) ?>" placeholder="555 - xxxxx" maxlength="30">
    </label>

    <label>
      <div class="field-label">Couleur calendrier</div>
      <div style="display:flex;gap:8px;align-items:center;">
        <input type="color" name="calendar_color" id="calendarColor" value="<?= h((string)($profile['calendar_color'] ?? '#3b9eff')) ?>" style="width:40px;height:38px;padding:2px;border-radius:8px;border:1px solid var(--line2);background:transparent;cursor:pointer;">
        <input class="inp" id="colorHex" value="<?= h((string)($profile['calendar_color'] ?? '#3b9eff')) ?>" style="font-family:monospace;" readonly>
      </div>
    </label>

    <label>
      <div class="field-label">IBAN (numéro de badge)</div>
      <input class="inp" name="iban" value="<?= h((string)($profile['iban'] ?? '')) ?>" placeholder="Ex: 123456">
    </label>

    <div style="grid-column:1/-1;display:flex;gap:10px;justify-content:flex-end;padding-top:4px;">
      <button type="submit" class="btn success">✓ Enregistrer</button>
    </div>
  </form>
</div>

<div class="card">
  <div style="font-family:var(--font-head);font-size:14px;font-weight:700;margin-bottom:16px;">🖋️ Signature et photo de profil</div>

  <div class="profile-upload-grid">
    <form method="post" enctype="multipart/form-data" class="upload-card">
      <?= csrf_input() ?>
      <input type="hidden" name="save_photo" value="1">
      <div class="field-label">Photo de profil</div>
      <div class="upload-preview upload-preview-avatar">
        <?php if (!empty($profile['profile_photo_url'])): ?>
          <img src="<?= h((string)$profile['profile_photo_url']) ?>" alt="Aperçu photo de profil" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <span style="font-size:36px;">👤</span>
        <?php endif; ?>
      </div>
      <input id="profilePhotoInput" type="file" name="profile_photo" accept="image/*" style="display:none;">
      <div class="upload-file-row">
        <label class="btn secondary file-label" for="profilePhotoInput">Upload photo de profil</label>
        <span id="profilePhotoName" class="upload-file-name">Aucun fichier sélectionné</span>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button type="submit" class="btn success">Enregistrer photo de profil</button>
      </div>
    </form>

    <form method="post" enctype="multipart/form-data" class="upload-card">
      <?= csrf_input() ?>
      <input type="hidden" name="save_signature" value="1">
      <div class="field-label">Signature</div>
      <div class="upload-preview upload-preview-signature">
        <?php if (!empty($profile['signature_url'])): ?>
          <img src="<?= h((string)$profile['signature_url']) ?>" alt="Aperçu signature" style="max-width:100%;max-height:160px;object-fit:contain;">
        <?php else: ?>
          <span style="font-size:12px;color:var(--muted);">Aucune signature renseignée.</span>
        <?php endif; ?>
      </div>
      <input id="signatureInput" type="file" name="signature_file" accept="image/*" style="display:none;">
      <div class="upload-file-row">
        <label class="btn secondary file-label" for="signatureInput">Upload signature</label>
        <span id="signatureName" class="upload-file-name">Aucun fichier sélectionné</span>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button type="submit" class="btn success">Enregistrer signature</button>
      </div>
    </form>
  </div>
</div>

<div class="card card-accent">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
    <div>
      <div style="font-family:var(--font-head);font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;">💲 Historique d’heures</div>
      <div style="font-size:12px;color:var(--muted);margin-top:3px;">Semaine affichée : du <?= h($salary['from']) ?> au <?= h($salary['to']) ?> (dimanche → samedi)</div>
    </div>
    <div style="text-align:right;">
      <div style="font-family:var(--font-head);font-size:28px;font-weight:800;color:var(--accent2);">$<?= number_format($salary['amount'], 0, ',', ' ') ?></div>
      <div style="font-size:12px;color:var(--muted);"><?= $salary['hours'] ?>h<?= str_pad((string)$salary['mins'], 2, '0', STR_PAD_LEFT) ?> travaillées • <?= $salary['rate'] ?>$/h</div>
    </div>
  </div>

  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;padding-top:10px;border-top:1px solid var(--line);">
    <div style="font-size:12px;color:var(--muted);">Navigation des semaines</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <?php if ($salary['weekOffset'] > 0): ?>
        <a class="btn" href="?week=0">Semaine actuelle</a>
      <?php endif; ?>
      <a class="btn" href="?week=<?= (int)$salary['weekOffset'] + 1 ?>">Semaine précédente</a>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:10px;">
    <?php
    $JOURS_FR = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
    $todayKey = (new DateTime('now', new DateTimeZone('Europe/Paris')))->format('Y-m-d');
    foreach ($salary['byDay'] as $dayData):
      $date    = $dayData['date'];
      $dayName = $JOURS_FR[(int)$date->format('w')];
      $keyYmd  = $date->format('Ymd');
      $dayKey  = $date->format('Y-m-d');
      $isToday = $dayKey === $todayKey;
      $hDay    = floor($dayData['seconds'] / 3600);
      $mDay    = floor(($dayData['seconds'] % 3600) / 60);
      $defaultOpen = $isToday || !empty($dayData['shifts']);
    ?>
      <div class="day-block" style="border-radius:10px;border:1px solid var(--line);overflow:hidden;">
        <div onclick="toggleDay('day-<?= $keyYmd ?>')" style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;cursor:pointer;background:<?= $isToday ? 'rgba(0,212,170,.06)' : 'rgba(255,255,255,.02)' ?>;user-select:none;">
          <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:12px;font-weight:700;color:<?= $isToday ? 'var(--accent2)' : 'var(--muted)' ?>;width:28px;"><?= $dayName ?></span>
            <span style="font-size:13px;font-weight:600;"><?= $date->format('d/m') ?></span>
            <?php if ($isToday): ?>
              <span style="font-size:10px;background:rgba(0,212,170,.15);color:var(--accent2);padding:2px 7px;border-radius:20px;">Aujourd'hui</span>
            <?php endif; ?>
          </div>
          <div style="display:flex;align-items:center;gap:12px;">
            <?php if ($dayData['seconds'] > 0): ?>
              <span style="font-size:12px;color:var(--muted);"><?= $hDay ?>h<?= str_pad((string)$mDay,2,'0',STR_PAD_LEFT) ?></span>
              <span style="font-size:13px;font-weight:700;color:var(--accent2);">$<?= number_format($dayData['amount'],0,',',' ') ?></span>
            <?php else: ?>
              <span style="font-size:12px;color:var(--muted);opacity:.5;">—</span>
            <?php endif; ?>
            <span class="chevron-<?= $keyYmd ?>" style="font-size:11px;color:var(--muted);transition:transform .2s;<?= $defaultOpen ? 'transform:rotate(180deg);' : '' ?>">▼</span>
          </div>
        </div>

        <div id="day-<?= $keyYmd ?>" style="<?= $defaultOpen ? '' : 'display:none;' ?>">
          <?php if ($dayData['shifts']): ?>
            <div style="border-top:1px solid var(--line);padding:10px 14px;display:flex;flex-direction:column;gap:8px;">
              <?php foreach ($dayData['shifts'] as $sh):
                $shStart  = new DateTime($sh['started_at'], new DateTimeZone('Europe/Paris'));
                $shEnd    = $sh['ended_at'] ? new DateTime($sh['ended_at'], new DateTimeZone('Europe/Paris')) : $salary['now'];
                $shDur    = max(0, $shEnd->getTimestamp() - $shStart->getTimestamp());
                $shH      = floor($shDur / 3600);
                $shM      = floor(($shDur % 3600) / 60);
                $shAmt    = (int)round(($shDur / 3600) * $salary['rate']);
                $isActive = $sh['ended_at'] === null;
              ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;border-radius:8px;background:rgba(255,255,255,.03);border:1px solid var(--line);gap:10px;flex-wrap:wrap;">
                  <div>
                    <span style="font-size:12px;font-weight:600;"><?= $shStart->format('H:i') ?> → <?= $isActive ? '<span style="color:var(--accent2)">En cours</span>' : $shEnd->format('H:i') ?></span>
                    <span style="font-size:11px;color:var(--muted);margin-left:8px;"><?= $shH ?>h<?= str_pad((string)$shM,2,'0',STR_PAD_LEFT) ?></span>
                  </div>
                  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span style="font-size:12px;font-weight:700;color:var(--accent2);">$<?= number_format($shAmt,0,',',' ') ?></span>
                    <button type="button" onclick="openEdit(<?= (int)$sh['id'] ?>,'<?= $shStart->format('Y-m-d\TH:i') ?>','<?= $isActive ? '' : $shEnd->format('Y-m-d\TH:i') ?>')" class="btn" style="font-size:11px;padding:3px 10px;border-color:rgba(59,158,255,.4);color:#60c0ff;">✎</button>
                    <button type="button" onclick="confirmDelete(<?= (int)$sh['id'] ?>)" class="btn" style="font-size:11px;padding:3px 10px;border-color:rgba(255,79,79,.3);color:#ff7a7a;">✕</button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div style="border-top:1px solid var(--line);padding:12px 14px;font-size:12px;color:var(--muted);text-align:center;">Aucun service sur cette journée.</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:var(--card);border-radius:14px;border:1px solid var(--line2);padding:24px;width:340px;max-width:95vw;">
    <div style="font-family:var(--font-head);font-size:15px;font-weight:700;margin-bottom:16px;">✎ Modifier le service</div>
    <form method="post" id="editForm">
      <?= csrf_input() ?>
      <input type="hidden" name="action_shift" value="edit">
      <input type="hidden" name="shift_id" id="editShiftId">
      <label style="display:block;margin-bottom:12px;">
        <div class="field-label">Début</div>
        <input class="inp" type="datetime-local" name="new_start" id="editStart" required>
      </label>
      <label style="display:block;margin-bottom:16px;">
        <div class="field-label">Fin <span style="font-weight:400;text-transform:none;">(vide = en cours)</span></div>
        <input class="inp" type="datetime-local" name="new_end" id="editEnd">
      </label>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" onclick="closeEdit()" class="btn secondary">Annuler</button>
        <button type="submit" class="btn success">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<form method="post" id="deleteForm" style="display:none;">
  <?= csrf_input() ?>
  <input type="hidden" name="action_shift" value="delete">
  <input type="hidden" name="shift_id" id="deleteShiftId">
</form>

<script>
const colorPicker = document.getElementById('calendarColor');
if (colorPicker) {
  colorPicker.addEventListener('input', function() {
    const hex = document.getElementById('colorHex');
    if (hex) hex.value = this.value;
  });
}

function toggleDay(id) {
  const el = document.getElementById(id);
  const key = id.replace('day-', '');
  const chev = document.querySelector('.chevron-' + key);
  if (!el) return;
  if (el.style.display === 'none') {
    el.style.display = '';
    if (chev) chev.style.transform = 'rotate(180deg)';
  } else {
    el.style.display = 'none';
    if (chev) chev.style.transform = '';
  }
}

function openEdit(id, start, end) {
  document.getElementById('editShiftId').value = id;
  document.getElementById('editStart').value = start;
  document.getElementById('editEnd').value = end;
  document.getElementById('editModal').style.display = 'flex';
}

function closeEdit() {
  document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeEdit();
});

function confirmDelete(id) {
  if (!confirm('Supprimer ce service ?')) return;
  document.getElementById('deleteShiftId').value = id;
  document.getElementById('deleteForm').submit();
}

[['profilePhotoInput','profilePhotoName'], ['signatureInput','signatureName']].forEach(function(pair){
  const input = document.getElementById(pair[0]);
  const label = document.getElementById(pair[1]);
  if (!input || !label) return;
  input.addEventListener('change', function(){
    label.textContent = this.files && this.files[0] ? this.files[0].name : 'Aucun fichier sélectionné';
  });
});
</script>

<style>
.profile-form-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:14px;
}
.profile-upload-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:16px;
}
.upload-card{
  border:1px solid var(--line);
  border-radius:12px;
  padding:14px;
  background:rgba(255,255,255,.02);
}
.upload-preview{
  border:1px dashed var(--line2);
  border-radius:12px;
  padding:14px;
  background:rgba(255,255,255,.02);
  display:flex;
  align-items:center;
  justify-content:center;
  margin-bottom:12px;
}
.upload-file-row{
  display:flex;
  align-items:center;
  gap:10px;
  flex-wrap:wrap;
  margin-bottom:10px;
}
.upload-file-name{
  font-size:12px;
  color:var(--muted);
}
.upload-preview-avatar{
  width:140px;
  height:140px;
  border-radius:50%;
  overflow:hidden;
  padding:0;
}
.upload-preview-signature{
  min-height:180px;
}
.field-label{
  font-size:11px;
  color:var(--muted);
  margin-bottom:6px;
  text-transform:uppercase;
  letter-spacing:.5px;
}
@media (max-width: 700px) {
  .profile-form-grid,
  .profile-upload-grid {
    grid-template-columns: 1fr !important;
  }
}
</style>

<?php samr_layout_end(); ?>
