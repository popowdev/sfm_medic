<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/db.php';
if (!function_exists('current_user')) {
  require_once __DIR__ . '/auth.php';
}

if (!is_logged_in()) {
  header('Location: /login.php');
  exit;
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS blacklist_entries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(190) NOT NULL,
  blacklist_date DATE NOT NULL,
  reason TEXT NOT NULL,
  duration_value INT UNSIGNED DEFAULT NULL,
  duration_unit ENUM('jours','semaines','mois','a_vie') NOT NULL DEFAULT 'jours',
  end_date DATE DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_by_name VARCHAR(190) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_blacklist_name (full_name),
  INDEX idx_blacklist_date (blacklist_date),
  INDEX idx_blacklist_end (end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

function h(?string $value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function duration_label(?int $value, string $unit): string {
  if ($unit === 'a_vie') {
    return 'À vie';
  }
  if ($value === null || $value <= 0) {
    return 'Non renseignée';
  }
  $label = match ($unit) {
    'jours' => $value > 1 ? 'jours' : 'jour',
    'semaines' => $value > 1 ? 'semaines' : 'semaine',
    'mois' => $value > 1 ? 'mois' : 'mois',
    default => $unit,
  };
  return $value . ' ' . $label;
}

function compute_end_date(string $date, ?int $value, string $unit): ?string {
  if ($unit === 'a_vie') {
    return null;
  }
  if ($value === null || $value <= 0) {
    return null;
  }
  try {
    $dt = new DateTimeImmutable($date . ' 00:00:00');
    return match ($unit) {
      'jours' => $dt->modify('+' . $value . ' days')->format('Y-m-d'),
      'semaines' => $dt->modify('+' . $value . ' weeks')->format('Y-m-d'),
      'mois' => $dt->modify('+' . $value . ' months')->format('Y-m-d'),
      default => null,
    };
  } catch (Throwable $e) {
    return null;
  }
}

$errors = [];
$success = null;
$form = [
  'full_name' => '',
  'blacklist_date' => date('Y-m-d'),
  'reason' => '',
  'duration_value' => '',
  'duration_unit' => 'jours',
  'search' => trim((string)($_GET['search'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'create') {
    $form['full_name'] = trim((string)($_POST['full_name'] ?? ''));
    $form['blacklist_date'] = trim((string)($_POST['blacklist_date'] ?? date('Y-m-d')));
    $form['reason'] = trim((string)($_POST['reason'] ?? ''));
    $form['duration_value'] = trim((string)($_POST['duration_value'] ?? ''));
    $form['duration_unit'] = (string)($_POST['duration_unit'] ?? 'jours');

    if ($form['full_name'] === '') {
      $errors[] = 'Le nom et prénom sont obligatoires.';
    }

    if ($form['reason'] === '') {
      $errors[] = 'La raison est obligatoire.';
    }

    $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $form['blacklist_date']);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $form['blacklist_date']) {
      $errors[] = 'La date est invalide.';
    }

    $allowedUnits = ['jours', 'semaines', 'mois', 'a_vie'];
    if (!in_array($form['duration_unit'], $allowedUnits, true)) {
      $errors[] = 'La durée choisie est invalide.';
    }

    $durationValue = null;
    if ($form['duration_unit'] !== 'a_vie') {
      if ($form['duration_value'] === '' || !ctype_digit($form['duration_value']) || (int)$form['duration_value'] <= 0) {
        $errors[] = 'La durée doit être un chiffre supérieur à 0.';
      } else {
        $durationValue = (int)$form['duration_value'];
      }
    }

    if (!$errors) {
      $endDate = compute_end_date($form['blacklist_date'], $durationValue, $form['duration_unit']);
      $user = current_user();
      $creatorName = is_array($user) ? (string)($user['username'] ?? 'Inconnu') : 'Inconnu';
      $creatorId = is_array($user) ? (int)($user['id'] ?? 0) : null;
      if ($creatorId === 0) {
        $creatorId = null;
      }

      $stmt = $pdo->prepare('INSERT INTO blacklist_entries (full_name, blacklist_date, reason, duration_value, duration_unit, end_date, created_by, created_by_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
      $stmt->execute([
        $form['full_name'],
        $form['blacklist_date'],
        $form['reason'],
        $durationValue,
        $form['duration_unit'],
        $endDate,
        $creatorId,
        $creatorName,
      ]);

      $success = 'La personne a été ajoutée à la blacklist.';
      $form['full_name'] = '';
      $form['blacklist_date'] = date('Y-m-d');
      $form['reason'] = '';
      $form['duration_value'] = '';
      $form['duration_unit'] = 'jours';
    }
  }

  if ($action === 'delete') {
    $deleteId = (int)($_POST['id'] ?? 0);
    if ($deleteId > 0) {
      $stmt = $pdo->prepare('DELETE FROM blacklist_entries WHERE id = ? LIMIT 1');
      $stmt->execute([$deleteId]);
      $success = 'L’entrée blacklist a été supprimée.';
    } else {
      $errors[] = 'Entrée introuvable pour la suppression.';
    }
  }
}

$where = '';
$params = [];
if ($form['search'] !== '') {
  $where = 'WHERE full_name LIKE ? OR reason LIKE ?';
  $like = '%' . $form['search'] . '%';
  $params = [$like, $like];
}

$stmt = $pdo->prepare("SELECT * FROM blacklist_entries {$where} ORDER BY created_at DESC, id DESC");
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = new DateTimeImmutable('today');
$total = count($entries);
$active = 0;
$lifetime = 0;
$expired = 0;
foreach ($entries as $entry) {
  if (($entry['duration_unit'] ?? '') === 'a_vie') {
    $active++;
    $lifetime++;
    continue;
  }
  $endDate = $entry['end_date'] ?? null;
  if ($endDate) {
    try {
      $end = new DateTimeImmutable($endDate);
      if ($end >= $today) {
        $active++;
      } else {
        $expired++;
      }
    } catch (Throwable $e) {
      $active++;
    }
  } else {
    $active++;
  }
}

samr_layout_start('Blacklist');
?>

<div class="page-header">
  <h1>Blacklist</h1>
  <div class="sub">Ajout, consultation et suppression des personnes blacklistées. La suppression est disponible pour tout utilisateur connecté.</div>
</div>

<div class="stats-grid">
  <div class="stat-card blue">
    <div class="s-label">Total</div>
    <div class="s-val"><?= $total ?></div>
    <div class="s-sub">Entrées enregistrées</div>
  </div>
  <div class="stat-card green">
    <div class="s-label">Actives</div>
    <div class="s-val"><?= $active ?></div>
    <div class="s-sub">Blacklist en cours</div>
  </div>
  <div class="stat-card">
    <div class="s-label">À vie</div>
    <div class="s-val"><?= $lifetime ?></div>
    <div class="s-sub">Sans expiration</div>
  </div>
  <div class="stat-card">
    <div class="s-label">Expirées</div>
    <div class="s-val"><?= $expired ?></div>
    <div class="s-sub">Toujours conservées en base</div>
  </div>
</div>

<?php if ($success): ?>
  <div class="card card-accent" style="border-color:rgba(0,212,170,.28);background:linear-gradient(160deg,rgba(0,212,170,.06),rgba(10,15,21,.95));">
    <div class="pill pill-green">✅ Action effectuée</div>
    <div style="margin-top:10px;"><?= h($success) ?></div>
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="card" style="border-color:rgba(255,79,79,.25);">
    <div class="pill pill-red">⚠️ Vérification</div>
    <div style="margin-top:10px; display:grid; gap:6px;">
      <?php foreach ($errors as $error): ?>
        <div><?= h($error) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:minmax(320px,420px) minmax(0,1fr); gap:16px; align-items:start;">
  <section class="card card-accent">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;">
      <div>
        <h2 style="font-family:var(--font-head);font-size:18px;font-weight:800;">Nouvelle entrée</h2>
        <div class="muted" style="font-size:13px;">Renseigne les informations puis ajoute la personne à la blacklist.</div>
      </div>
      <span class="pill pill-blue">BDD auto</span>
    </div>

    <form method="post" style="display:grid;gap:14px;">
      <input type="hidden" name="action" value="create">

      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Nom / Prénom</label>
        <input class="inp" type="text" name="full_name" value="<?= h($form['full_name']) ?>" placeholder="Ex. John Doe" required>
      </div>

      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Date</label>
        <input class="inp" type="date" name="blacklist_date" value="<?= h($form['blacklist_date']) ?>" required>
      </div>

      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Raison</label>
        <textarea class="inp" name="reason" rows="4" placeholder="Motif de la blacklist" required style="resize:vertical;"><?= h($form['reason']) ?></textarea>
      </div>

      <div style="display:grid;grid-template-columns:120px minmax(0,1fr);gap:10px;">
        <div>
          <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Durée</label>
          <input class="inp" id="duration_value" type="number" min="1" step="1" name="duration_value" value="<?= h($form['duration_value']) ?>" placeholder="Ex. 7">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Unité</label>
          <select class="inp" id="duration_unit" name="duration_unit">
            <option value="jours" <?= $form['duration_unit'] === 'jours' ? 'selected' : '' ?>>Jours</option>
            <option value="semaines" <?= $form['duration_unit'] === 'semaines' ? 'selected' : '' ?>>Semaines</option>
            <option value="mois" <?= $form['duration_unit'] === 'mois' ? 'selected' : '' ?>>Mois</option>
            <option value="a_vie" <?= $form['duration_unit'] === 'a_vie' ? 'selected' : '' ?>>À vie</option>
          </select>
        </div>
      </div>

      <div class="muted" style="font-size:12px;">Pour “À vie”, le champ chiffre sera automatiquement désactivé.</div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn success" type="submit">➕ Ajouter à la blacklist</button>
        <button class="btn secondary" type="reset" id="resetBtn">Réinitialiser</button>
      </div>
    </form>
  </section>

  <section class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
      <div>
        <h2 style="font-family:var(--font-head);font-size:18px;font-weight:800;">Liste des personnes</h2>
        <div class="muted" style="font-size:13px;">Historique complet conservé en base de données.</div>
      </div>

      <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <input class="inp" type="search" name="search" value="<?= h($form['search']) ?>" placeholder="Rechercher un nom ou une raison" style="width:min(320px, 100%);">
        <button class="btn secondary" type="submit">Rechercher</button>
        <?php if ($form['search'] !== ''): ?>
          <a class="btn secondary" href="/blacklist.php">Effacer</a>
        <?php endif; ?>
      </form>
    </div>

    <div style="overflow:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Nom / Prénom</th>
            <th>Date</th>
            <th>Raison</th>
            <th>Durée</th>
            <th>Fin</th>
            <th>Ajoutée par</th>
            <th>Statut</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$entries): ?>
          <tr>
            <td colspan="8" class="muted" style="text-align:center;padding:24px 12px;">Aucune entrée blacklist trouvée.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($entries as $entry): ?>
            <?php
              $statusHtml = '<span class="pill">Inconnue</span>';
              if (($entry['duration_unit'] ?? '') === 'a_vie') {
                $statusHtml = '<span class="pill pill-red">À vie</span>';
              } elseif (!empty($entry['end_date'])) {
                try {
                  $end = new DateTimeImmutable((string)$entry['end_date']);
                  if ($end >= $today) {
                    $statusHtml = '<span class="pill pill-blue">Active</span>';
                  } else {
                    $statusHtml = '<span class="pill">Expirée</span>';
                  }
                } catch (Throwable $e) {
                  $statusHtml = '<span class="pill pill-blue">Active</span>';
                }
              } else {
                $statusHtml = '<span class="pill pill-blue">Active</span>';
              }
            ?>
            <tr>
              <td style="font-weight:700;"><?= h((string)$entry['full_name']) ?></td>
              <td><?= h((string)$entry['blacklist_date']) ?></td>
              <td style="min-width:220px;white-space:pre-wrap;"><?= nl2br(h((string)$entry['reason'])) ?></td>
              <td><?= h(duration_label(isset($entry['duration_value']) ? (int)$entry['duration_value'] : null, (string)$entry['duration_unit'])) ?></td>
              <td><?= $entry['duration_unit'] === 'a_vie' ? '—' : h((string)($entry['end_date'] ?? '—')) ?></td>
              <td><?= h((string)($entry['created_by_name'] ?? 'Inconnu')) ?></td>
              <td><?= $statusHtml ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Supprimer cette entrée de la blacklist ?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$entry['id'] ?>">
                  <button class="btn danger" type="submit">Supprimer</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<script>
(function () {
  const unit = document.getElementById('duration_unit');
  const value = document.getElementById('duration_value');
  const resetBtn = document.getElementById('resetBtn');

  function syncDurationField() {
    const isLifetime = unit.value === 'a_vie';
    value.disabled = isLifetime;
    value.required = !isLifetime;
    if (isLifetime) {
      value.value = '';
      value.placeholder = 'Non requis';
    } else if (!value.placeholder || value.placeholder === 'Non requis') {
      value.placeholder = 'Ex. 7';
    }
  }

  unit.addEventListener('change', syncDurationField);
  resetBtn.addEventListener('click', function () {
    setTimeout(syncDurationField, 0);
  });
  syncDurationField();
})();
</script>

<?php samr_layout_end();
