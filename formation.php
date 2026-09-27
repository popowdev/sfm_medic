<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';

require_login();

$pdo = db();
$me = current_user();
$uid = (int)($me['id'] ?? 0);

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function column_exists(PDO $pdo, string $table, string $column): bool {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
  $stmt->execute([$table, $column]);
  return (int)$stmt->fetchColumn() > 0;
}

function ensure_formation_tables(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS samr_formation_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    label VARCHAR(120) NOT NULL,
    description TEXT NULL,
    category VARCHAR(80) NOT NULL DEFAULT 'Base SAMR',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_active_sort (is_active, sort_order, id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS samr_formation_validations (
    user_id INT NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    validated_by INT NULL,
    validated_at DATETIME NULL,
    note VARCHAR(255) NULL,
    PRIMARY KEY (user_id, item_id),
    KEY idx_item (item_id),
    KEY idx_validated_by (validated_by),
    CONSTRAINT fk_samr_form_item FOREIGN KEY (item_id) REFERENCES samr_formation_items(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $removed = [
  'Règlement SAMR',
  'Respect secret médical RP',
  'Brancardage',
  'Sécurisation de scène',
  'Menu RP',
  'Constantes',
  'Transport patient',
  'Conduite prio',
  'Communication patient',
  'Transmission hôpital'
];
  $stmtRemove = $pdo->prepare("UPDATE samr_formation_items SET is_active = 0 WHERE label = ?");
  foreach ($removed as $label) { $stmtRemove->execute([$label]); }

  $items = [
    ['Base', 'Utilisation radio', 'Canaux, annonces propres, priorités et messages courts.'],
    ['Base', 'Utilisation tablette', 'Consultation des appels, informations terrain et outils utiles.'],
    ['Base', 'Utilisation site', 'Création, consultation et mise à jour des fiches et rapports.'],

    ['Soins', 'Soins légers', 'Prise en charge des blessures simples, bandages et conseils patient.'],

    ['Intervention', 'Gestion patient', 'Questions utiles, explication des gestes et comportement adapté.'],

    ['Documents', 'Rapport intervention', 'Compte rendu court, lisible et exploitable.'],
    ['Documents', 'Facturation', 'Création d’une facture ou d’un devis selon les procédures internes.']
  ];

  $exists = $pdo->prepare("SELECT id FROM samr_formation_items WHERE label = ? LIMIT 1");
  $insert = $pdo->prepare("INSERT INTO samr_formation_items (category, label, description, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
  $reactivate = $pdo->prepare("UPDATE samr_formation_items SET category = ?, description = ?, sort_order = ?, is_active = 1 WHERE label = ?");
  $i = 10;
  foreach ($items as $it) {
    $exists->execute([$it[1]]);
    if ($exists->fetchColumn()) $reactivate->execute([$it[0], $it[2], $i, $it[1]]);
    else $insert->execute([$it[0], $it[1], $it[2], $i]);
    $i += 10;
  }
}

ensure_formation_tables($pdo);

$isAdmin = (($me['role'] ?? '') === 'admin');
$canSupervise = false;
try {
  $stmt = $pdo->prepare("SELECT can_supervise FROM users WHERE id=? LIMIT 1");
  $stmt->execute([$uid]);
  $canSupervise = ((int)$stmt->fetchColumn() === 1);
} catch (Throwable $e) {}
$canEdit = $isAdmin || $canSupervise;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$canEdit) { http_response_code(403); exit('Accès refusé'); }
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'toggle') {
    $targetUser = (int)($_POST['user_id'] ?? 0);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $checked = (int)($_POST['checked'] ?? 0);
    $ok = false;
    if ($targetUser > 0 && $itemId > 0) {
      if ($checked === 1) {
        $stmt = $pdo->prepare("INSERT INTO samr_formation_validations (user_id, item_id, validated_by, validated_at)
          VALUES (?, ?, ?, NOW())
          ON DUPLICATE KEY UPDATE validated_by=VALUES(validated_by), validated_at=NOW()");
        $stmt->execute([$targetUser, $itemId, $uid]);
      } else {
        $stmt = $pdo->prepare("DELETE FROM samr_formation_validations WHERE user_id=? AND item_id=?");
        $stmt->execute([$targetUser, $itemId]);
      }
      $ok = true;
    }
    if ((string)($_POST['ajax'] ?? '') === '1') {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['ok'=>$ok, 'checked'=>$checked === 1, 'validated_by'=>(string)($me['username'] ?? 'Superviseur')]);
      exit;
    }
    header('Location: /formation.php#u' . $targetUser); exit;
  }

  if ($action === 'add_item') {
    $label = trim((string)($_POST['label'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'Base')) ?: 'Base';
    $description = trim((string)($_POST['description'] ?? ''));
    if ($label !== '') {
      $max = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM samr_formation_items")->fetchColumn();
      $stmt = $pdo->prepare("INSERT INTO samr_formation_items (label, category, description, sort_order) VALUES (?, ?, ?, ?)");
      $stmt->execute([$label, $category, $description !== '' ? $description : null, $max + 10]);
    }
    header('Location: /formation.php'); exit;
  }
}

$items = $pdo->query("SELECT * FROM samr_formation_items WHERE is_active=1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

$hasGrade = column_exists($pdo, 'users', 'grade');
$hasRole = column_exists($pdo, 'users', 'role');
$hasJob = column_exists($pdo, 'users', 'job');
$hasService = column_exists($pdo, 'users', 'service');
$hasDepartment = column_exists($pdo, 'users', 'department');
$hasActive = column_exists($pdo, 'users', 'active');
$hasArchived = column_exists($pdo, 'users', 'archived');
$hasPhoto = column_exists($pdo, 'users', 'profile_photo_url');

$where = [];
$params = [];
if ($hasActive) $where[] = 'active = 1';
if ($hasArchived) $where[] = 'archived = 0';
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$gradeExpr = $hasGrade ? "COALESCE(NULLIF(TRIM(grade),''),'Sans grade')" : "'Sans grade'";
$sql = "SELECT id, username"
  . ($hasGrade ? ", grade" : ", 'Sans grade' AS grade")
  . ($hasPhoto ? ", profile_photo_url" : ", '' AS profile_photo_url")
  . " FROM users {$whereSql} ORDER BY {$gradeExpr} ASC, username ASC";
$stmtUsers = $pdo->prepare($sql);
$stmtUsers->execute($params);
$users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

$usersByGrade = [];
foreach ($users as $u) {
  $gradeName = trim((string)($u['grade'] ?? ''));
  if ($gradeName === '') $gradeName = 'Sans grade';
  $usersByGrade[$gradeName][] = $u;
}

$validations = [];
$validatorIds = [];
$stmt = $pdo->query("SELECT user_id, item_id, validated_by, validated_at FROM samr_formation_validations");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
  $validations[(int)$v['user_id']][(int)$v['item_id']] = $v;
  if (!empty($v['validated_by'])) $validatorIds[] = (int)$v['validated_by'];
}
$validators = [];
if ($validatorIds) {
  $validatorIds = array_values(array_unique($validatorIds));
  $in = implode(',', array_fill(0, count($validatorIds), '?'));
  $s = $pdo->prepare("SELECT id, username FROM users WHERE id IN ($in)");
  $s->execute($validatorIds);
  foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $validators[(int)$r['id']] = (string)$r['username'];
}

$totalItems = max(1, count($items));
$totalUsers = count($users);
$full = 0; $avg = 0;
foreach ($users as $usr) {
  $done = isset($validations[(int)$usr['id']]) ? count($validations[(int)$usr['id']]) : 0;
  if ($done >= $totalItems) $full++;
  $avg += (int)round(($done / $totalItems) * 100);
}
$avg = $totalUsers > 0 ? (int)round($avg / $totalUsers) : 0;

samr_layout_start('Formation SAMR');
?>
<style>
:root{--ok:#10b981;--ko:#ef4444;--okbg:rgba(16,185,129,.18);--kobg:rgba(239,68,68,.16)}
.page-header{margin-bottom:10px}.page-header h1{font-size:24px}.sub{font-size:12px}.stats-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-bottom:10px}.stat-card{padding:10px 12px}.stat-card .s-label{font-size:10px}.stat-card .s-val{font-size:24px}.stat-card .s-sub{font-size:10px}.card{padding:10px!important;margin-bottom:10px}.formation-toolbar{display:flex;gap:8px;align-items:center;justify-content:space-between;margin-bottom:8px}.formation-search{width:230px;height:34px}.grade-tools{display:flex;gap:6px;flex-wrap:wrap;align-items:center}.grade-group{border:1px solid var(--line);border-radius:12px;margin-bottom:8px;overflow:hidden;background:rgba(255,255,255,.015)}.grade-group summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 12px;background:#0d1219;font-weight:900}.grade-group summary::-webkit-details-marker{display:none}.grade-title{display:flex;align-items:center;gap:8px}.grade-chevron{display:inline-flex;width:20px;height:20px;border-radius:7px;align-items:center;justify-content:center;background:rgba(255,255,255,.06);transition:.15s}.grade-group[open] .grade-chevron{transform:rotate(90deg)}.grade-count{font-size:11px;color:var(--muted);font-weight:800}.grade-progress{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--muted);min-width:160px}.grade-progress .progress-line{width:90px;flex:0 0 90px}.mini-btn{height:30px;padding:0 10px;font-size:11px}.formation-wrap{overflow:hidden;border:1px solid var(--line);border-radius:12px}.formation-table{width:100%;border-collapse:separate;border-spacing:0;table-layout:fixed}.formation-table th,.formation-table td{border-bottom:1px solid rgba(24,32,48,.85);padding:5px 4px;font-size:11px;vertical-align:middle;text-align:center}.formation-table th{background:#0d1219;color:var(--muted);text-transform:uppercase;letter-spacing:.35px;font-size:9px;height:42px}.formation-table th:first-child,.formation-table td:first-child{text-align:left;width:210px;background:#0d1219;border-right:1px solid var(--line)}.formation-table tbody tr:last-child td{border-bottom:0}.person{display:flex;align-items:center;gap:7px}.avatar-mini{width:27px;height:27px;border-radius:8px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-weight:800;font-size:10px;color:#fff;overflow:hidden;flex:0 0 auto}.avatar-mini img{width:100%;height:100%;object-fit:cover}.person-name{font-weight:800;color:var(--text);font-size:12px;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.person-grade{font-size:10px;color:var(--muted);line-height:1.1}.progress-row{display:flex;align-items:center;gap:6px;margin-top:4px}.progress-line{height:6px;background:rgba(255,255,255,.07);border-radius:99px;overflow:hidden;flex:1}.progress-line span{display:block;height:100%;background:linear-gradient(90deg,var(--ko),#f59e0b,var(--ok));border-radius:99px}.pct-badge{min-width:34px;text-align:center;font-size:10px;font-weight:900;border-radius:999px;padding:2px 5px;background:rgba(255,255,255,.07);color:var(--text)}.item-head{display:flex;flex-direction:column;gap:2px;align-items:center}.item-cat{font-size:8px;color:var(--accent2);font-weight:900}.item-label{font-size:9px;color:var(--text);line-height:1.05;max-width:74px}.check-cell{height:38px}.cell-ok{background:var(--okbg)}.cell-ko{background:var(--kobg)}.toggle-form{margin:0}.formation-state{width:26px;height:26px;border:0;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-weight:900;cursor:pointer;color:#fff;box-shadow:inset 0 0 0 1px rgba(255,255,255,.08)}.formation-state.ok{background:var(--ok)}.formation-state.ko{background:var(--ko)}.formation-state:disabled{opacity:.65;cursor:not-allowed}.legend{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.mini-pill{font-size:10px;font-weight:800;border-radius:999px;padding:5px 8px;background:rgba(255,255,255,.06)}.mini-pill.ok{color:#bbf7d0}.mini-pill.ko{color:#fecaca}.category-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px}.category-card{border:1px solid var(--line);border-radius:10px;padding:9px;background:rgba(255,255,255,.02)}.category-card h3{font-family:var(--font-head);font-size:12px;margin-bottom:5px}.category-card ul{margin-left:15px;color:var(--muted);font-size:10px;line-height:1.35}.add-form{display:grid;grid-template-columns:150px 180px 1fr auto;gap:8px;align-items:start}.add-form .inp{height:34px}.add-form .btn{height:34px;padding:0 12px}@media(max-height:900px){.optional{display:none}.formation-table th{height:36px}.formation-table th,.formation-table td{padding:4px 3px}.check-cell{height:32px}.formation-state{width:23px;height:23px}.avatar-mini{width:24px;height:24px}.person-name{font-size:11px}.person-grade,.pct-badge{font-size:9px}.stats-grid{margin-bottom:7px}.stat-card .s-val{font-size:20px}.page-header h1{font-size:20px}}@media(max-width:1100px){.formation-table th:first-child,.formation-table td:first-child{width:185px}.item-label{font-size:8px;max-width:62px}.formation-table th,.formation-table td{padding:4px 2px}.formation-state{width:22px;height:22px}.formation-search{width:210px}}
</style>

<div class="page-header">
  <h1>Formation SAMR</h1>
  <div class="sub">Suivi compact des acquis, trié par grade avec affichage repliable.</div>
</div>

<div class="stats-grid">
  <div class="stat-card blue"><div class="s-label">Membres</div><div class="s-val"><?= (int)$totalUsers ?></div><div class="s-sub">dans le suivi</div></div>
  <div class="stat-card green"><div class="s-label">Progression moyenne</div><div class="s-val"><?= (int)$avg ?>%</div><div class="s-sub">tous modules</div></div>
  <div class="stat-card"><div class="s-label">Acquis</div><div class="s-val"><?= count($items) ?></div><div class="s-sub">à valider</div></div>
  <div class="stat-card"><div class="s-label">Complets</div><div class="s-val"><?= (int)$full ?></div><div class="s-sub">à 100%</div></div>
</div>

<div class="card card-accent">
  <div class="formation-toolbar">
    <div class="legend">
      <span class="mini-pill ok">✓ Validé</span>
      <span class="mini-pill ko">× À former</span>
      <?php if ($canEdit): ?><span class="mini-pill">Mode superviseur</span><?php else: ?><span class="mini-pill">Lecture seule</span><?php endif; ?>
    </div>
    <div class="grade-tools">
      <button class="btn mini-btn" type="button" id="openAllGrades">Tout afficher</button>
      <button class="btn mini-btn" type="button" id="closeAllGrades">Tout masquer</button>
      <input class="inp formation-search" id="formationSearch" placeholder="Rechercher un membre...">
    </div>
  </div>

  <?php if (!$usersByGrade): ?>
    <div class="sub" style="padding:14px">Aucun membre actif trouvé.</div>
  <?php endif; ?>

  <?php $gradeIndex = 0; foreach ($usersByGrade as $gradeName => $gradeUsers):
    $gradeDoneTotal = 0;
    foreach ($gradeUsers as $gu) $gradeDoneTotal += isset($validations[(int)$gu['id']]) ? count($validations[(int)$gu['id']]) : 0;
    $gradePct = count($gradeUsers) > 0 ? (int)round(($gradeDoneTotal / (count($gradeUsers) * $totalItems)) * 100) : 0;
    $openDefault = $gradeIndex === 0 || preg_match('/ambul|samr|ems|secour|paramedic/i', $gradeName);
  ?>
  <details class="grade-group" data-grade-group <?= $openDefault ? 'open' : '' ?>>
    <summary>
      <div class="grade-title"><span class="grade-chevron">›</span><span><?= h($gradeName) ?></span><span class="grade-count"><?= count($gradeUsers) ?> membre<?= count($gradeUsers) > 1 ? 's' : '' ?></span></div>
      <div class="grade-progress"><div class="progress-line"><span style="width:<?= $gradePct ?>%"></span></div><strong><?= $gradePct ?>%</strong></div>
    </summary>
    <div class="formation-wrap">
      <table class="formation-table formationTable">
        <thead><tr><th>Membre</th><?php foreach ($items as $it): ?><th title="<?= h($it['description'] ?? '') ?>"><div class="item-head"><span class="item-cat"><?= h($it['category']) ?></span><span class="item-label"><?= h($it['label']) ?></span></div></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($gradeUsers as $usr):
          $userId = (int)$usr['id'];
          $done = isset($validations[$userId]) ? count($validations[$userId]) : 0;
          $pct = (int)round(($done / $totalItems) * 100);
          $name = (string)($usr['username'] ?? '');
          $initials = '';
          foreach (array_slice(preg_split('/[\s|]+/', $name) ?: [], 0, 2) as $p) if (trim($p) !== '') $initials .= mb_strtoupper(mb_substr(trim($p), 0, 1));
        ?>
          <tr id="u<?= $userId ?>" data-name="<?= h(mb_strtolower($name . ' ' . $gradeName)) ?>" data-total-items="<?= $totalItems ?>">
            <td>
              <div class="person">
                <div class="avatar-mini"><?php if (!empty($usr['profile_photo_url'])): ?><img src="<?= h($usr['profile_photo_url']) ?>" alt=""><?php else: ?><?= h($initials ?: '?') ?><?php endif; ?></div>
                <div style="min-width:0;flex:1"><div class="person-name"><?= h($name) ?></div><div class="person-grade"><?= h($gradeName) ?></div><div class="progress-row"><div class="progress-line"><span style="width:<?= $pct ?>%"></span></div><span class="pct-badge"><?= $pct ?>%</span></div></div>
              </div>
            </td>
            <?php foreach ($items as $it):
              $itemId = (int)$it['id'];
              $v = $validations[$userId][$itemId] ?? null;
              $checked = $v !== null;
              $validator = $checked ? ($validators[(int)($v['validated_by'] ?? 0)] ?? 'Superviseur') : '';
            ?>
            <td class="check-cell <?= $checked ? 'cell-ok' : 'cell-ko' ?>" title="<?= $checked ? ('Validé par ' . h($validator) . ' le ' . h($v['validated_at'] ?? '')) : 'Non validé' ?>" data-check-cell>
              <form method="post" class="toggle-form">
                <input type="hidden" name="action" value="toggle"><input type="hidden" name="ajax" value="1"><input type="hidden" name="user_id" value="<?= $userId ?>"><input type="hidden" name="item_id" value="<?= $itemId ?>"><input type="hidden" name="checked" value="<?= $checked ? 0 : 1 ?>">
                <button class="formation-state <?= $checked ? 'ok' : 'ko' ?>" type="submit" <?= $canEdit ? '' : 'disabled' ?>><?= $checked ? '✓' : '×' ?></button>
              </form>
            </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>
  <?php $gradeIndex++; endforeach; ?>
</div>

<div class="card optional">
  <div class="page-header" style="margin-bottom:8px"><h1 style="font-size:15px">Référentiel des acquis</h1><div class="sub">Masqué automatiquement sur petit écran pour garder la page en 1080p.</div></div>
  <div class="category-grid">
    <?php $byCat=[]; foreach($items as $it){$byCat[(string)$it['category']][]=$it;} foreach($byCat as $cat=>$list): ?>
      <div class="category-card"><h3><?= h($cat) ?></h3><ul><?php foreach($list as $it): ?><li title="<?= h($it['description'] ?? '') ?>"><?= h($it['label']) ?></li><?php endforeach; ?></ul></div>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($canEdit): ?>
<div class="card optional">
  <div class="page-header" style="margin-bottom:8px"><h1 style="font-size:15px">Ajouter un acquis</h1><div class="sub">Permet d’ajouter rapidement une compétence sans toucher au code.</div></div>
  <form method="post" class="add-form">
    <input type="hidden" name="action" value="add_item">
    <input class="inp" name="category" placeholder="Catégorie" value="Base">
    <input class="inp" name="label" placeholder="Nom de l’acquis" required>
    <input class="inp" name="description" placeholder="Description courte">
    <button class="btn success" type="submit">Ajouter</button>
  </form>
</div>
<?php endif; ?>

<script>
(function(){
  const input=document.getElementById('formationSearch');
  const groups=[...document.querySelectorAll('[data-grade-group]')];

  document.getElementById('openAllGrades')?.addEventListener('click',()=>groups.forEach(g=>g.open=true));
  document.getElementById('closeAllGrades')?.addEventListener('click',()=>groups.forEach(g=>g.open=false));

  input?.addEventListener('input',()=>{
    const q=input.value.trim().toLowerCase();
    groups.forEach(g=>{
      let visible=0;
      g.querySelectorAll('tbody tr').forEach(r=>{
        const show=!q||r.dataset.name.includes(q);
        r.style.display=show?'':'none';
        if(show) visible++;
      });
      g.style.display=visible?'':'none';
      if(q && visible) g.open=true;
    });
  });

  function updateRowProgress(row){
    const total=parseInt(row.dataset.totalItems||'1',10);
    const done=row.querySelectorAll('[data-check-cell].cell-ok').length;
    const pct=Math.round((done/Math.max(1,total))*100);
    const bar=row.querySelector('.progress-row .progress-line span');
    const badge=row.querySelector('.pct-badge');
    if(bar) bar.style.width=pct+'%';
    if(badge) badge.textContent=pct+'%';
  }

  function updateGradeProgress(group){
    const rows=[...group.querySelectorAll('tbody tr')];
    let total=0, done=0;
    rows.forEach(r=>{
      total += parseInt(r.dataset.totalItems||'1',10);
      done += r.querySelectorAll('[data-check-cell].cell-ok').length;
    });
    const pct=total ? Math.round((done/total)*100) : 0;
    const bar=group.querySelector('summary .progress-line span');
    const txt=group.querySelector('summary .grade-progress strong');
    if(bar) bar.style.width=pct+'%';
    if(txt) txt.textContent=pct+'%';
  }

  document.querySelectorAll('.toggle-form').forEach(form=>{
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const btn=form.querySelector('button');
      if(!btn || btn.disabled) return;
      btn.disabled=true;
      try{
        const data=new FormData(form);
        data.set('ajax','1');
        const res=await fetch(window.location.pathname,{method:'POST',body:data,headers:{'X-Requested-With':'fetch'}});
        const json=await res.json();
        if(!json.ok) throw new Error('bad response');
        const checked=!!json.checked;
        const cell=form.closest('[data-check-cell]');
        const hidden=form.querySelector('input[name="checked"]');
        cell.classList.toggle('cell-ok',checked);
        cell.classList.toggle('cell-ko',!checked);
        btn.classList.toggle('ok',checked);
        btn.classList.toggle('ko',!checked);
        btn.textContent=checked?'✓':'×';
        if(hidden) hidden.value=checked?'0':'1';
        cell.title=checked ? ('Validé par '+(json.validated_by||'Superviseur')) : 'Non validé';
        const row=form.closest('tr');
        const group=form.closest('[data-grade-group]');
        if(row) updateRowProgress(row);
        if(group) updateGradeProgress(group);
      }catch(err){
        form.querySelector('input[name="ajax"]')?.remove();
        form.submit();
      }finally{
        btn.disabled=false;
      }
    });
  });
})();
</script>
<?php samr_layout_end(); ?>
