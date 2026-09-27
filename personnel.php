<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/csrf.php';

require_login();

$u = current_user();
if (!is_array($u)) { http_response_code(403); exit('Accès refusé.'); }

$uid     = (int)($u['id'] ?? 0);
$isAdmin = ($u['role'] ?? '') === 'admin';

$pdo = db();
$stmt = $pdo->prepare("SELECT can_supervise FROM users WHERE id=? LIMIT 1");
$stmt->execute([$uid]);
$canSupervise = ((int)$stmt->fetchColumn() === 1);

if (!$isAdmin && !$canSupervise) {
  http_response_code(403);
  exit('Accès refusé.');
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

samr_layout_start('SAMR - Personnel');
?>

<div class="card" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
  <div>
    <h1 style="margin:0 0 4px 0;">👥 Gestion du personnel</h1>
    <div class="muted">Grades, formations et droits de supervision — tout dans un seul endroit.</div>
  </div>
  <div class="pill" id="statusMsg"></div>
</div>

<div class="card" id="personnelRoot">
  <div class="muted">Chargement…</div>
</div>

<!-- Modal édition -->
<div id="editModal" class="samr-modal" aria-hidden="true">
  <div class="samr-modal__backdrop" data-close="1"></div>
  <div class="samr-modal__panel" role="dialog" aria-modal="true">
    <div class="samr-modal__head">
      <div style="font-weight:800;" id="modalTitle">Modifier</div>
      <button class="btn secondary" type="button" data-close="1">✕</button>
    </div>

    <div style="margin-top:14px;">
      <div class="muted" style="font-size:12px;margin-bottom:6px;">Grade</div>
      <select class="inp" id="modalGrade">
        <option value="">— Aucun —</option>
        <option>Ambulancier</option>
        <option>Chef Ambulancier</option>
        <option>Infirmier</option>
        <option>Infirmier en chef</option>
        <option>Médecin</option>
        <option>Médecin en chef</option>
        <option>Chef d'équipe</option>
        <option>Co-Directeur</option>
        <option>Directeur</option>
      </select>

      <label style="display:flex;align-items:center;gap:10px;margin-top:14px;">
        <input type="checkbox" id="modalSupervise">
        <span>Peut superviser (accès Gestion personnel)</span>
      </label>

      <div style="margin-top:16px;">
        <div class="muted" style="font-size:12px;margin-bottom:8px;">Formations</div>
        <div id="modalFormations" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
        <button class="btn secondary" type="button" data-close="1">Annuler</button>
        <button class="btn" type="button" id="modalSave">Enregistrer</button>
      </div>

      <div id="modalErr" class="muted" style="margin-top:8px;color:#ffb3b3;display:none;"></div>
    </div>
  </div>
</div>

<style>
.inp{width:100%;padding:10px 12px;border-radius:12px;border:1px solid var(--line);background:rgba(255,255,255,.03);color:var(--text);}
.samr-modal{position:fixed;inset:0;display:none;z-index:9999;}
.samr-modal[aria-hidden="false"]{display:block;}
.samr-modal__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.55);}
.samr-modal__panel{position:relative;width:min(520px,calc(100% - 24px));margin:6vh auto 0 auto;background:linear-gradient(180deg,rgba(15,23,34,.98),rgba(12,19,29,.96));border:1px solid var(--line);border-radius:18px;padding:18px;box-shadow:0 25px 80px rgba(0,0,0,.45);}
.samr-modal__head{display:flex;justify-content:space-between;align-items:center;gap:10px}
.person-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--line);flex-wrap:wrap;}
.person-row:last-child{border-bottom:none;}
.person-info{min-width:0;}
.person-name{font-weight:800;}
.person-meta{font-size:12px;color:var(--muted);margin-top:2px;}
.formation-chip{font-size:18px;padding:4px 6px;border-radius:10px;border:1px solid var(--line);background:rgba(255,255,255,.03);cursor:pointer;user-select:none;}
.formation-chip.selected{border-color:rgba(60,220,130,.5);background:rgba(60,220,130,.15);}
</style>

<script>
(function(){
  const csrf = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
  const root = document.getElementById('personnelRoot');
  const modal = document.getElementById('editModal');
  const modalTitle = document.getElementById('modalTitle');
  const modalGrade = document.getElementById('modalGrade');
  const modalSupervise = document.getElementById('modalSupervise');
  const modalFormations = document.getElementById('modalFormations');
  const modalSave = document.getElementById('modalSave');
  const modalErr = document.getElementById('modalErr');
  const statusMsg = document.getElementById('statusMsg');

  let allFormations = [];
  let selectedFormationIds = new Set();
  let editingUserId = null;

  function openModal(el){ el.setAttribute('aria-hidden','false'); }
  function closeModal(el){ el.setAttribute('aria-hidden','true'); }
  function showErr(msg){ modalErr.textContent=msg; modalErr.style.display='block'; }
  function clearErr(){ modalErr.textContent=''; modalErr.style.display='none'; }

  modal.addEventListener('click', e => { if (e.target?.dataset?.close) closeModal(modal); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') closeModal(modal); });

  async function load() {
    root.innerHTML = '<div class="muted">Chargement…</div>';
    try {
      const resp = await fetch('/api/personnel.php', { credentials: 'same-origin' });
      const data = await resp.json();
      if (!data.ok) throw new Error(data.error || 'API error');

      allFormations = data.formations || [];
      render(data.people || []);
    } catch(e) {
      root.innerHTML = '<div class="muted" style="color:#ffb3b3;">Erreur : ' + e.message + '</div>';
    }
  }

  function render(people) {
    if (!people.length) {
      root.innerHTML = '<div class="muted">Aucun utilisateur.</div>';
      return;
    }

    root.innerHTML = '';
    for (const p of people) {
      const row = document.createElement('div');
      row.className = 'person-row';

      const info = document.createElement('div');
      info.className = 'person-info';

      const name = document.createElement('div');
      name.className = 'person-name';
      name.textContent = p.username;

      const meta = document.createElement('div');
      meta.className = 'person-meta';
      const grade = p.grade || '—';
      const rate = p.hourly_rate ? ' • ' + p.hourly_rate + '$/h' : '';
      const sup = p.can_supervise ? ' • 👁 Supervision' : '';
      meta.textContent = grade + rate + sup;

      info.appendChild(name);
      info.appendChild(meta);

      const btn = document.createElement('button');
      btn.className = 'btn secondary';
      btn.textContent = '✏️ Modifier';
      btn.addEventListener('click', () => openEdit(p));

      row.appendChild(info);
      row.appendChild(btn);
      root.appendChild(row);
    }
  }

  async function openEdit(person) {
    editingUserId = person.id;
    clearErr();
    modalTitle.textContent = '✏️ ' + person.username;
    modalGrade.value = person.grade || '';
    modalSupervise.checked = person.can_supervise === 1;

    try {
      const resp = await fetch('/api/personnel.php?user_id=' + person.id, { credentials: 'same-origin' });
      const data = await resp.json();
      if (!data.ok) throw new Error(data.error || 'API');

      selectedFormationIds = new Set(data.assigned_ids || []);
      renderFormations();
      openModal(modal);
    } catch(e) {
      alert('Impossible de charger les données : ' + e.message);
    }
  }

  function renderFormations() {
    modalFormations.innerHTML = '';
    for (const f of allFormations) {
      const chip = document.createElement('span');
      chip.className = 'formation-chip' + (selectedFormationIds.has(f.id) ? ' selected' : '');
      chip.textContent = f.emoji;
      chip.title = f.name;
      chip.addEventListener('click', () => {
        if (selectedFormationIds.has(f.id)) selectedFormationIds.delete(f.id);
        else selectedFormationIds.add(f.id);
        renderFormations();
      });
      modalFormations.appendChild(chip);
    }
    if (!allFormations.length) {
      modalFormations.innerHTML = '<span class="muted">Aucune formation définie.</span>';
    }
  }

  modalSave.addEventListener('click', async () => {
    if (!editingUserId) return;
    clearErr();
    modalSave.disabled = true;

    try {
      const r1 = await fetch('/api/personnel.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          action: 'update_user',
          user_id: editingUserId,
          grade: modalGrade.value,
          can_supervise: modalSupervise.checked ? 1 : 0,
          csrf
        })
      });
      const d1 = await r1.json();
      if (!d1.ok) throw new Error(d1.error || 'Erreur sauvegarde grade');

      const r2 = await fetch('/api/personnel.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          action: 'save_formations',
          user_id: editingUserId,
          formation_ids: Array.from(selectedFormationIds),
          csrf
        })
      });
      const d2 = await r2.json();
      if (!d2.ok) throw new Error(d2.error || 'Erreur sauvegarde formations');

      closeModal(modal);
      statusMsg.textContent = '✅ Sauvegardé';
      setTimeout(() => { statusMsg.textContent = ''; }, 3000);
      await load();
    } catch(e) {
      showErr(e.message || 'Erreur inconnue');
    } finally {
      modalSave.disabled = false;
    }
  });

  load();
})();
</script>

<?php samr_layout_end(); ?>
