<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';

require_login();

$u = current_user();
$isAdmin = $u && (($u['role'] ?? '') === 'admin');

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function extract_patient_name(?string $json): string {
  if (!$json) return '—';

  $data = json_decode($json, true);
  if (!is_array($data)) return '—';

  $candidates = [
    'patient_name',
    'patient',
    'nom_patient',
    'patient_fullname',
  ];

  foreach ($candidates as $key) {
    if (!empty($data[$key]) && is_string($data[$key])) {
      $v = trim($data[$key]);
      if ($v !== '') return $v;
    }
  }

  $pairs = [
    ['nom', 'prenom'],
    ['last_name', 'first_name'],
    ['patient_last_name', 'patient_first_name'],
    ['patient_lastname',  'patient_firstname'],
  ];

  foreach ($pairs as [$last, $first]) {
    $l = trim((string)($data[$last] ?? ''));
    $f = trim((string)($data[$first] ?? ''));
    $full = trim($l . ' ' . $f);
    if ($full !== '') return $full;
  }

  return '—';
}

function format_dt(?string $dt): string {
  if (!$dt) return '—';
  $ts = strtotime($dt);
  if ($ts === false) return $dt;
  return date('d/m/Y H:i', $ts);
}

$stmt = db()->prepare("
  SELECT
    d.id,
    d.title,
    d.data_json,
    d.created_at,
    t.name AS template_name,
    u.username
  FROM documents d
  JOIN doc_templates t ON t.id = d.template_id
  JOIN users u ON u.id = d.created_by
  ORDER BY t.name ASC, d.created_at DESC
  LIMIT 500
");
$stmt->execute();
$rows = $stmt->fetchAll();

$grouped = [];
$creators = [];

foreach ($rows as $r) {
  $templateName = (string)$r['template_name'];
  $username = (string)$r['username'];
  $patientName = extract_patient_name((string)($r['data_json'] ?? ''));

  if (!isset($grouped[$templateName])) {
    $grouped[$templateName] = [];
  }

  $grouped[$templateName][] = [
    'id' => (int)$r['id'],
    'template_name' => $templateName,
    'patient_name' => $patientName,
    'username' => $username,
    'created_at_raw' => (string)$r['created_at'],
    'created_at_fmt' => format_dt((string)$r['created_at']),
    'title' => (string)$r['title'],
  ];

  $creators[$username] = true;
}

ksort($creators, SORT_NATURAL | SORT_FLAG_CASE);

samr_layout_start('Documents');
?>
<div class="card">
  <h1 style="margin:0 0 6px 0;">Documents</h1>
  <div class="muted">Recherche par patient, filtre par créateur, affichage regroupé par modèle.</div>

  <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
    <a class="btn" href="/documents_create.php">+ Nouveau document</a>
    <?php if ($isAdmin): ?>
      <a class="btn secondary" href="/admin/templates_manage.php">🧩 Modèles</a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="docs-toolbar">
    <label class="docs-field docs-field--grow">
      <div class="muted" style="font-size:12px;margin-bottom:6px;">Recherche patient</div>
      <input type="text" id="searchPatient" class="inp" placeholder="Ex: John Doe">
    </label>

    <label class="docs-field">
      <div class="muted" style="font-size:12px;margin-bottom:6px;">Créé par</div>
      <select id="filterCreator" class="inp">
        <option value="">Tous</option>
        <?php foreach (array_keys($creators) as $creator): ?>
          <option value="<?= h($creator) ?>"><?= h($creator) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <div class="docs-actions">
      <button type="button" class="btn secondary" id="btnExpandAll">Tout ouvrir</button>
      <button type="button" class="btn secondary" id="btnCollapseAll">Tout fermer</button>
    </div>
  </div>
</div>

<?php if (!$rows): ?>
  <div class="card"><span class="muted">Aucun document pour le moment.</span></div>
<?php else: ?>
  <div id="docsAccordion">
    <?php $groupIndex = 0; ?>
    <?php foreach ($grouped as $templateName => $docs): ?>
      <?php $groupIndex++; ?>
      <div class="card doc-group" data-model="<?= h(mb_strtolower($templateName)) ?>">
        <button type="button" class="doc-acc-head" aria-expanded="<?= $groupIndex === 1 ? 'true' : 'false' ?>">
          <div>
            <div class="doc-acc-title"><?= h($templateName) ?></div>
            <div class="muted doc-acc-sub">
              <span class="doc-count" data-base-count="<?= count($docs) ?>"><?= count($docs) ?></span> document(s)
            </div>
          </div>
          <div class="doc-chevron">▾</div>
        </button>

        <div class="doc-acc-body" style="<?= $groupIndex === 1 ? '' : 'display:none;' ?>">
          <div style="overflow:auto;">
            <table class="docs-table">
              <thead>
                <tr>
                  <th>Modèle</th>
                  <th>Nom du patient</th>
                  <th>Créé par</th>
                  <th>Date</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($docs as $r): ?>
                  <tr
                    class="doc-row"
                    data-patient="<?= h(mb_strtolower($r['patient_name'])) ?>"
                    data-creator="<?= h($r['username']) ?>"
                  >
                    <td><?= h($r['template_name']) ?></td>
                    <td><strong><?= h($r['patient_name']) ?></strong></td>
                    <td><?= h($r['username']) ?></td>
                    <td data-sort="<?= h($r['created_at_raw']) ?>"><?= h($r['created_at_fmt']) ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                      <span class="doc-actions">
                        <a class="btn secondary" href="/documents_view.php?id=<?= (int)$r['id'] ?>">Ouvrir</a>

                        <?php if ($isAdmin): ?>
                          <form
                            method="post"
                            action="/documents_delete.php"
                            onsubmit="return confirm('Supprimer définitivement ce document ?');"
                            style="display:inline;margin:0;"
                          >
                            <?= csrf_input() ?>
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button
                              type="submit"
                              class="btn secondary btn-danger"
                            >
                              Supprimer
                            </button>
                          </form>
                        <?php endif; ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <tr class="doc-empty-row" style="display:none;">
                  <td colspan="5" class="muted">Aucun document ne correspond aux filtres.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<style>
.docs-toolbar{
  display:flex;
  gap:12px;
  flex-wrap:wrap;
  align-items:end;
}
.docs-field{min-width:220px}
.docs-field--grow{flex:1; min-width:280px}
.docs-actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}
.inp{
  width:100%;
  padding:10px 12px;
  border-radius:12px;
  border:1px solid var(--line);
  background:rgba(255,255,255,.03);
  color:var(--text);
}
.inp:focus{
  outline:none;
  border-color:rgba(102,178,255,.5);
}

.doc-acc-head{
  width:100%;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  text-align:left;
  background:rgba(255,255,255,.02);
  border:1px solid var(--line);
  color:var(--text);
  border-radius:14px;
  padding:12px 14px;
  cursor:pointer;
}
.doc-acc-head:hover{
  background:rgba(255,255,255,.04);
}
.doc-acc-title{
  font-size:18px;
  font-weight:900;
}
.doc-acc-sub{
  font-size:12px;
  margin-top:4px;
}
.doc-chevron{
  font-size:18px;
  transition:transform .18s ease;
}
.doc-acc-head[aria-expanded="true"] .doc-chevron{
  transform:rotate(180deg);
}
.doc-acc-body{
  margin-top:12px;
}

.docs-table{
  width:100%;
  border-collapse:collapse;
}
.docs-table th,
.docs-table td{
  padding:10px;
  border-bottom:1px solid var(--line);
  text-align:left;
  vertical-align:middle;
}
.docs-table th{
  font-size:12px;
  text-transform:uppercase;
  letter-spacing:.2px;
  color:#b9c6d5;
}
.docs-table tbody tr:last-child td{
  border-bottom:none;
}
.doc-row.is-hidden{
  display:none;
}

.doc-actions{
  display:inline-flex;
  gap:8px;
  align-items:center;
  justify-content:flex-end;
}

.btn-danger{
  border-color:#7a2e2e !important;
  color:#ffb3b3 !important;
}

@media (max-width: 700px){
  .docs-actions{width:100%}
}
</style>

<script>
(function(){
  const searchInput = document.getElementById('searchPatient');
  const creatorSelect = document.getElementById('filterCreator');
  const groups = Array.from(document.querySelectorAll('.doc-group'));
  const btnExpandAll = document.getElementById('btnExpandAll');
  const btnCollapseAll = document.getElementById('btnCollapseAll');

  function normalize(v){
    return String(v || '').toLowerCase().trim();
  }

  function toggleGroup(head, forceOpen = null){
    const body = head.parentElement.querySelector('.doc-acc-body');
    const expanded = head.getAttribute('aria-expanded') === 'true';
    const next = forceOpen === null ? !expanded : !!forceOpen;
    head.setAttribute('aria-expanded', next ? 'true' : 'false');
    body.style.display = next ? '' : 'none';
  }

  function applyFilters(){
    const patientNeedle = normalize(searchInput.value);
    const creatorNeedle = creatorSelect.value;

    groups.forEach(group => {
      const rows = Array.from(group.querySelectorAll('.doc-row'));
      const emptyRow = group.querySelector('.doc-empty-row');
      const countEl = group.querySelector('.doc-count');

      let visibleCount = 0;

      rows.forEach(row => {
        const patient = normalize(row.dataset.patient);
        const creator = row.dataset.creator || '';

        const okPatient = !patientNeedle || patient.includes(patientNeedle);
        const okCreator = !creatorNeedle || creator === creatorNeedle;

        const visible = okPatient && okCreator;
        row.classList.toggle('is-hidden', !visible);
        if (visible) visibleCount++;
      });

      if (countEl) countEl.textContent = visibleCount;
      if (emptyRow) emptyRow.style.display = visibleCount === 0 ? '' : 'none';
      group.style.display = visibleCount === 0 ? 'none' : '';
    });
  }

  document.querySelectorAll('.doc-acc-head').forEach(head => {
    head.addEventListener('click', () => toggleGroup(head));
  });

  searchInput?.addEventListener('input', applyFilters);
  creatorSelect?.addEventListener('change', applyFilters);

  btnExpandAll?.addEventListener('click', () => {
    document.querySelectorAll('.doc-acc-head').forEach(head => toggleGroup(head, true));
  });

  btnCollapseAll?.addEventListener('click', () => {
    document.querySelectorAll('.doc-acc-head').forEach(head => toggleGroup(head, false));
  });
})();
</script>

<?php
samr_layout_end();