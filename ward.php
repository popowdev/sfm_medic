<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';

require_login();
$u       = current_user();
$uid     = (int)($u['id'] ?? 0);
$isAdmin = ($u['role'] ?? '') === 'admin';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

samr_layout_start('SAMR — Chambres');
?>

<style>
/* ── Layout ── */
.ward-header{
  display:flex; align-items:center; justify-content:space-between;
  flex-wrap:wrap; gap:12px; margin-bottom:20px;
}
.ward-header h1{ font-family:var(--font-head); font-size:20px; font-weight:800; margin:0; }

/* ── Services ── */
.service-section{ margin-bottom:28px; }
.service-head{
  display:flex; align-items:center; gap:10px; margin-bottom:12px;
  padding-bottom:10px; border-bottom:2px solid var(--service-color, var(--line));
}
.service-icon{ font-size:22px; }
.service-name{ font-family:var(--font-head); font-size:16px; font-weight:800; }
.service-stats{ font-size:12px; color:var(--muted); margin-left:auto; }

/* ── Grille de chambres ── */
.rooms-grid{
  display:grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap:12px;
}

/* ── Carte chambre ── */
.room-card{
  border-radius:14px;
  border:2px solid var(--line);
  background: rgba(13,18,25,.9);
  display:flex; flex-direction:column;
  overflow:hidden;
  transition: border-color .15s, box-shadow .15s;
  cursor:pointer;
}
.room-card:hover{ border-color: rgba(59,158,255,.4); box-shadow: 0 4px 20px rgba(0,0,0,.3); }
.room-card.full{ border-color: rgba(255,79,79,.4); background: rgba(255,79,79,.04); }
.room-card.available{ border-color: rgba(0,212,170,.35); }
.room-card.dirty{ border-color: rgba(255,180,50,.35); background: rgba(255,180,50,.03); }
.room-card.maintenance{ border-color: rgba(150,150,150,.35); background: rgba(150,150,150,.04); }

.room-top{
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 12px 8px;
  gap:8px;
}
.room-number{
  font-family:var(--font-head); font-size:17px; font-weight:800;
  display:flex; align-items:center; gap:8px;
}
.room-label{ font-size:11px; color:var(--muted); font-weight:400; }
.room-badge{
  font-size:10px; padding:3px 8px; border-radius:999px; font-weight:700;
  white-space:nowrap;
}
.badge-free     { background:rgba(0,212,170,.15); color:var(--accent2); border:1px solid rgba(0,212,170,.3); }
.badge-occupied { background:rgba(255,79,79,.15);  color:#ff7a7a;        border:1px solid rgba(255,79,79,.3); }
.badge-dirty    { background:rgba(255,180,50,.15); color:#ffb060;        border:1px solid rgba(255,180,50,.3);}
.badge-maint    { background:rgba(150,150,150,.15);color:var(--muted);   border:1px solid rgba(150,150,150,.3);}
.badge-partial  { background:rgba(59,158,255,.15); color:var(--accent);  border:1px solid rgba(59,158,255,.3); }

.room-bed-dots{
  display:flex; gap:4px; padding:0 12px 8px;
}
.bed-dot{
  width:8px; height:8px; border-radius:50%;
  background: rgba(0,212,170,.35);
  border: 1px solid rgba(0,212,170,.5);
}
.bed-dot.used{
  background: rgba(255,79,79,.5);
  border-color: rgba(255,79,79,.7);
}

/* ── Patients dans la chambre ── */
.room-patients{ padding:0 10px 10px; display:flex; flex-direction:column; gap:6px; }

.patient-chip{
  border-radius:9px;
  padding:8px 10px;
  border:1px solid var(--line);
  background:rgba(255,255,255,.02);
  cursor:pointer;
  transition:background .12s;
}
.patient-chip:hover{ background:rgba(255,255,255,.05); }
.patient-chip.status-stable     { border-left:3px solid var(--accent2); }
.patient-chip.status-monitoring { border-left:3px solid #ffb060; }
.patient-chip.status-critical   { border-left:3px solid #ff4f4f; }

.patient-name{ font-size:12px; font-weight:700; margin-bottom:2px; }
.patient-info{ font-size:11px; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.patient-status-dot{
  display:inline-block; width:7px; height:7px; border-radius:50%;
  margin-right:5px; flex-shrink:0;
}
.dot-stable     { background:var(--accent2); }
.dot-monitoring { background:#ffb060; }
.dot-critical   { background:#ff4f4f; animation:pulse-dot 1s infinite; }

@keyframes pulse-dot{
  0%,100%{ opacity:1; } 50%{ opacity:.4; }
}

.room-add-btn{
  margin:0 10px 10px; padding:7px; border-radius:8px;
  border:1px dashed var(--line2); background:transparent;
  color:var(--muted); font-size:12px; cursor:pointer;
  font-family:var(--font-body); transition:all .12s; width:calc(100% - 20px);
}
.room-add-btn:hover{ border-color:var(--accent); color:var(--accent); background:rgba(59,158,255,.05); }

/* ── Modals ── */
.ward-backdrop{
  position:fixed; inset:0; z-index:9000;
  background:rgba(0,0,0,.65); backdrop-filter:blur(4px);
  display:none; align-items:flex-start; justify-content:center;
  padding-top:6vh;
}
.ward-backdrop.open{ display:flex; }
.ward-modal{
  width:min(500px, calc(100% - 32px));
  background:linear-gradient(160deg,#0d1219,#080c10);
  border:1px solid var(--line2); border-radius:18px;
  padding:22px; box-shadow:0 32px 80px rgba(0,0,0,.6);
  max-height:88vh; overflow-y:auto;
}
.modal-head{
  display:flex; justify-content:space-between; align-items:flex-start;
  margin-bottom:18px; gap:10px;
}
.modal-title{ font-family:var(--font-head); font-size:15px; font-weight:700; }
.modal-sub{ font-size:12px; color:var(--muted); margin-top:3px; }
.field{ margin-bottom:13px; }
.field label{
  display:block; font-size:10px; font-weight:700;
  text-transform:uppercase; letter-spacing:.6px; color:var(--muted); margin-bottom:5px;
}
.field-row{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.modal-actions{ display:flex; justify-content:flex-end; gap:8px; margin-top:18px; flex-wrap:wrap; }
.modal-err{
  padding:9px 12px; border-radius:9px; margin-top:10px; font-size:13px;
  background:rgba(255,79,79,.08); border:1px solid rgba(255,79,79,.25);
  color:#ff7a7a; display:none;
}

/* Status selector */
.status-pills{ display:flex; gap:6px; flex-wrap:wrap; }
.status-pill{
  padding:6px 12px; border-radius:999px; border:1px solid var(--line2);
  background:transparent; color:var(--muted); font-size:12px; font-weight:600;
  cursor:pointer; font-family:var(--font-body); transition:all .12s;
}
.status-pill.active-stable     { border-color:rgba(0,212,170,.5); color:var(--accent2); background:rgba(0,212,170,.1); }
.status-pill.active-monitoring { border-color:rgba(255,180,50,.5); color:#ffb060; background:rgba(255,180,50,.1); }
.status-pill.active-critical   { border-color:rgba(255,79,79,.5);  color:#ff7a7a; background:rgba(255,79,79,.1); }

/* Room status pills */
.rstat-pill{
  padding:5px 10px; border-radius:999px; border:1px solid var(--line2);
  background:transparent; color:var(--muted); font-size:11px; font-weight:600;
  cursor:pointer; font-family:var(--font-body); transition:all .12s;
}
.rstat-pill.active-clean{ border-color:rgba(0,212,170,.5); color:var(--accent2); background:rgba(0,212,170,.1); }
.rstat-pill.active-dirty{ border-color:rgba(255,180,50,.5); color:#ffb060; background:rgba(255,180,50,.1); }
.rstat-pill.active-maintenance{ border-color:rgba(150,150,150,.5); color:var(--muted); background:rgba(150,150,150,.1); }

/* Notification toast */
.ward-toast{
  position:fixed; bottom:24px; right:24px; z-index:99999;
  background:#0d1219; border:1px solid var(--accent2);
  border-radius:12px; padding:12px 18px;
  box-shadow:0 8px 32px rgba(0,0,0,.4);
  font-size:13px; color:var(--text);
  display:flex; align-items:center; gap:10px;
  transform:translateY(80px); opacity:0;
  transition:transform .3s, opacity .3s;
  max-width:320px;
}
.ward-toast.show{ transform:translateY(0); opacity:1; }

/* Admin toolbar */
.admin-bar{
  display:flex; gap:8px; align-items:center; flex-wrap:wrap;
  padding:10px 14px;
  background:rgba(59,158,255,.05);
  border:1px solid rgba(59,158,255,.15);
  border-radius:10px; margin-bottom:16px;
  font-size:12px;
}
.admin-bar span{ color:var(--muted); }

@media(max-width:600px){
  .field-row{ grid-template-columns:1fr; }
  .rooms-grid{ grid-template-columns:1fr 1fr; }
}
</style>

<div class="ward-header">
  <div>
    <h1>🛏️ Gestion des chambres</h1>
    <div style="font-size:13px;color:var(--muted);">Vue en temps réel — cliquez sur une chambre pour gérer les patients</div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <button class="btn secondary" id="btnRefresh" style="font-size:12px;">🔄 Actualiser</button>
    <?php if ($isAdmin): ?>
      <button class="btn secondary" id="btnManageServices" style="font-size:12px;">⚙️ Services</button>
      <button class="btn" id="btnAddRoom" style="font-size:12px;">🚪 Ajouter chambre</button>
    <?php endif; ?>
  </div>
</div>

<?php if ($isAdmin): ?>
<div class="admin-bar">
  <span>Mode admin :</span>
  <button class="btn secondary" id="btnAddService" style="font-size:11px;padding:5px 10px;">➕ Service</button>
  <button class="btn secondary" id="btnAddRoom2" style="font-size:11px;padding:5px 10px;">➕ Chambre</button>
</div>
<?php endif; ?>

<div id="wardRoot">
  <div class="muted" style="padding:30px;text-align:center;">Chargement…</div>
</div>

<!-- Toast notification -->
<div class="ward-toast" id="wardToast">
  <span id="toastIcon">🔔</span>
  <span id="toastMsg"></span>
</div>

<!-- ── Modal : Chambre (admin) ── -->
<div class="ward-backdrop" id="roomBackdrop">
  <div class="ward-modal">
    <div class="modal-head">
      <div><div class="modal-title" id="roomModalTitle">Nouvelle chambre</div></div>
      <button class="btn secondary" id="btnCloseRoom" style="padding:5px 9px;">✕</button>
    </div>
    <div class="field">
      <label>Service</label>
      <select class="inp" id="roomServiceId"></select>
    </div>
    <div class="field-row">
      <div class="field" style="margin:0">
        <label>Numéro</label>
        <input class="inp" id="roomNumber" placeholder="Ex: 12, 3B…" maxlength="20">
      </div>
      <div class="field" style="margin:0">
        <label>Lits</label>
        <input class="inp" id="roomBeds" type="number" min="1" max="10" value="1">
      </div>
    </div>
    <div class="field">
      <label>Libellé (optionnel)</label>
      <input class="inp" id="roomLabel" placeholder="Ex: Salle de réveil">
    </div>
    <div class="field" id="roomStatusField" style="display:none;">
      <label>Statut chambre</label>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px;">
        <button class="rstat-pill active-clean" data-rstat="clean">✅ Propre</button>
        <button class="rstat-pill" data-rstat="dirty">🧹 À désinfecter</button>
        <button class="rstat-pill" data-rstat="maintenance">🔧 Maintenance</button>
      </div>
    </div>
    <div class="modal-err" id="roomErr"></div>
    <div class="modal-actions">
      <button class="btn secondary" id="btnCancelRoom">Annuler</button>
      <button class="btn success" id="btnSaveRoom">Enregistrer</button>
    </div>
  </div>
</div>

<!-- ── Modal : Patient ── -->
<div class="ward-backdrop" id="patientBackdrop">
  <div class="ward-modal">
    <div class="modal-head">
      <div>
        <div class="modal-title" id="patientModalTitle">Admettre un patient</div>
        <div class="modal-sub" id="patientModalSub"></div>
      </div>
      <button class="btn secondary" id="btnClosePatient" style="padding:5px 9px;">✕</button>
    </div>
    <div class="field">
      <label>Nom du patient</label>
      <input class="inp" id="patientName" placeholder="Prénom Nom" maxlength="80">
    </div>
    <div class="field">
      <label>Motif d'admission</label>
      <input class="inp" id="patientReason" placeholder="Ex: Fracture, Traumatisme crânien…" maxlength="150">
    </div>
    <div class="field">
      <label>Praticien en charge</label>
      <input class="inp" id="patientPractitioner" placeholder="Dr. …" maxlength="80">
    </div>
    <div class="field">
      <label>État du patient</label>
      <div class="status-pills" id="statusPills">
        <button class="status-pill active-stable" data-pstatus="stable">🟢 Stable</button>
        <button class="status-pill" data-pstatus="monitoring">🟡 Surveillance</button>
        <button class="status-pill" data-pstatus="critical">🔴 Critique</button>
      </div>
    </div>
    <div class="field">
      <label>Notes (optionnel)</label>
      <textarea class="inp" id="patientNotes" rows="2" style="resize:none;" placeholder="Informations supplémentaires…"></textarea>
    </div>
    <div class="modal-err" id="patientErr"></div>
    <div class="modal-actions" id="patientActions">
      <button class="btn secondary" id="btnCancelPatient">Annuler</button>
      <button class="btn danger" id="btnDischarge" style="display:none;">Sortie patient</button>
      <button class="btn success" id="btnSavePatient">Admettre</button>
    </div>
  </div>
</div>

<!-- ── Modal : Service (admin) ── -->
<div class="ward-backdrop" id="serviceBackdrop">
  <div class="ward-modal" style="max-width:380px;">
    <div class="modal-head">
      <div><div class="modal-title" id="serviceModalTitle">Nouveau service</div></div>
      <button class="btn secondary" id="btnCloseService" style="padding:5px 9px;">✕</button>
    </div>
    <div class="field">
      <label>Nom du service</label>
      <input class="inp" id="serviceName" placeholder="Ex: Urgences, Réanimation…" maxlength="60">
    </div>
    <div class="field-row">
      <div class="field" style="margin:0">
        <label>Icône (emoji)</label>
        <input class="inp" id="serviceIcon" placeholder="🏥" maxlength="4" style="font-size:20px;text-align:center;">
      </div>
      <div class="field" style="margin:0">
        <label>Couleur</label>
        <input type="color" id="serviceColor" value="#3b9eff"
          style="width:100%;height:42px;padding:3px;border-radius:9px;border:1px solid var(--line2);background:transparent;cursor:pointer;">
      </div>
    </div>
    <div class="modal-err" id="serviceErr"></div>
    <div class="modal-actions">
      <button class="btn secondary" id="btnCancelService">Annuler</button>
      <button class="btn success" id="btnSaveService">Enregistrer</button>
    </div>
  </div>
</div>

<script>
(function(){
  const CSRF    = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
  const IS_ADMIN= <?= json_encode($isAdmin) ?>;
  const ME_NAME = <?= json_encode($u['username'] ?? '', JSON_UNESCAPED_UNICODE) ?>;

  let state = { services: [], rooms: [] };
  let prevFreeRooms = new Set();
  let initialized   = false;
  let refreshTimer  = null;

  async function api(payload) {
    const r = await fetch('/api/ward.php', {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({...payload, csrf: CSRF})
    });
    const d = await r.json().catch(()=>null);
    if (!r.ok) throw new Error(d?.error || 'Erreur API');
    return d;
  }

  async function loadData() {
    try {
      const r = await fetch('/api/ward.php', { credentials:'same-origin' });
      const d = await r.json();
      if (!d.ok) throw new Error(d.error);

      if (initialized) {
        const newFreeRooms = new Set();
        for (const room of d.rooms) {
          if (room.occupied === 0 && room.status === 'clean') {
            newFreeRooms.add(room.id);
            if (!prevFreeRooms.has(room.id)) {
              const svc = d.services.find(s => s.id == room.service_id);
              showToast(`🛏️ Chambre ${room.number}${svc ? ' — '+svc.name : ''} disponible`);
            }
          }
        }
        prevFreeRooms = newFreeRooms;
      } else {
        for (const room of d.rooms) {
          if (room.occupied === 0 && room.status === 'clean') prevFreeRooms.add(room.id);
        }
        initialized = true;
      }

      state = d;
      render();
      updateRoomSelect();
    } catch(e) {
      document.getElementById('wardRoot').innerHTML =
        `<div class="muted" style="padding:20px;text-align:center;">Erreur : ${esc(e.message)}</div>`;
    }
  }

  function startAutoRefresh() {
    if (refreshTimer) clearInterval(refreshTimer);
    refreshTimer = setInterval(loadData, 15000);
  }

  let toastTimer = null;
  function showToast(msg, icon='🔔') {
    const t  = document.getElementById('wardToast');
    document.getElementById('toastMsg').textContent  = msg;
    document.getElementById('toastIcon').textContent = icon;
    t.classList.add('show');
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 5000);
  }

  function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function render() {
    const root = document.getElementById('wardRoot');
    root.innerHTML = '';

    if (!state.services.length) {
      root.innerHTML = `<div class="muted" style="text-align:center;padding:40px;">
        Aucun service configuré.
        ${IS_ADMIN ? '<br><br><button class="btn" onclick="document.getElementById(\'btnAddService\').click()">➕ Créer un service</button>' : ''}
      </div>`;
      return;
    }

    const roomsByService = {};
    for (const r of state.rooms) {
      const sid = r.service_id;
      if (!roomsByService[sid]) roomsByService[sid] = [];
      roomsByService[sid].push(r);
    }

    for (const svc of state.services) {
      const rooms   = roomsByService[svc.id] || [];
      const total   = rooms.reduce((s,r)=>(s+(+r.beds||1)),0);
      const occupied= rooms.reduce((s,r)=>(s+(+r.occupied||0)),0);
      const free    = total - occupied;

      const section = document.createElement('div');
      section.className = 'service-section';
      section.style.setProperty('--service-color', svc.color);

      section.innerHTML = `
        <div class="service-head">
          <span class="service-icon">${esc(svc.icon)}</span>
          <span class="service-name" style="color:${esc(svc.color)}">${esc(svc.name)}</span>
          ${IS_ADMIN ? `<button class="btn secondary" style="font-size:11px;padding:4px 9px;" data-edit-service="${svc.id}">✏️</button>` : ''}
          <span class="service-stats">${free} lit${free>1?'s':''} libre${free>1?'s':''} / ${total}</span>
        </div>
        <div class="rooms-grid" id="service-rooms-${svc.id}"></div>
      `;

      section.querySelector(`[data-edit-service]`)?.addEventListener('click', () => openEditService(svc));

      const grid = section.querySelector(`#service-rooms-${svc.id}`);

      for (const room of rooms) {
        grid.appendChild(renderRoomCard(room, svc));
      }

      root.appendChild(section);
    }
  }

  function renderRoomCard(room, svc) {
    const isFull   = room.occupied >= room.beds;
    const isEmpty  = room.occupied === 0;
    const isDirty  = room.status === 'dirty';
    const isMaint  = room.status === 'maintenance';

    let cardClass = 'room-card';
    if      (isMaint)  cardClass += ' maintenance';
    else if (isDirty)  cardClass += ' dirty';
    else if (isFull)   cardClass += ' full';
    else if (isEmpty)  cardClass += ' available';

    let badgeClass, badgeText;
    if      (isMaint)  { badgeClass='badge-maint';   badgeText='🔧 Maintenance'; }
    else if (isDirty)  { badgeClass='badge-dirty';   badgeText='🧹 À désinfecter'; }
    else if (isFull)   { badgeClass='badge-occupied';badgeText='Complet'; }
    else if (room.occupied > 0){ badgeClass='badge-partial'; badgeText=`${room.occupied}/${room.beds} lit${room.beds>1?'s':''}`; }
    else               { badgeClass='badge-free';    badgeText='Libre'; }

    const card = document.createElement('div');
    card.className  = cardClass;
    card.dataset.roomId = room.id;

    const top = document.createElement('div');
    top.className = 'room-top';
    top.innerHTML = `
      <div class="room-number">
        ${esc(room.number)}
        ${room.label ? `<span class="room-label">${esc(room.label)}</span>` : ''}
      </div>
      <span class="room-badge ${badgeClass}">${badgeText}</span>
    `;

    const dots = document.createElement('div');
    dots.className = 'room-bed-dots';
    for (let i=0; i<room.beds; i++) {
      const d = document.createElement('div');
      d.className = 'bed-dot' + (i < room.occupied ? ' used' : '');
      dots.appendChild(d);
    }

    const pList = document.createElement('div');
    pList.className = 'room-patients';
    for (const p of (room.patients || [])) {
      const chip = document.createElement('div');
      chip.className = `patient-chip status-${p.status}`;
      chip.innerHTML = `
        <div class="patient-name">
          <span class="patient-status-dot dot-${p.status}"></span>${esc(p.name)}
        </div>
        <div class="patient-info">${esc(p.reason||'—')}${p.practitioner?' · '+esc(p.practitioner):''}</div>
      `;
      chip.addEventListener('click', e => { e.stopPropagation(); openEditPatient(p, room); });
      pList.appendChild(chip);
    }

    if (!isFull && !isMaint) {
      const addBtn = document.createElement('button');
      addBtn.className = 'room-add-btn';
      addBtn.textContent = '+ Admettre un patient';
      addBtn.addEventListener('click', e => { e.stopPropagation(); openAdmitPatient(room, svc); });
      card.appendChild(top);
      card.appendChild(dots);
      card.appendChild(pList);
      card.appendChild(addBtn);
    } else {
      card.appendChild(top);
      card.appendChild(dots);
      card.appendChild(pList);
    }

    if (IS_ADMIN) {
      top.addEventListener('click', () => openRoomMenu(room));
    }

    return card;
  }

  let editingPatientId = null;
  let currentRoomId    = null;
  let patientStatus    = 'stable';

  function openAdmitPatient(room, svc) {
    editingPatientId = null;
    currentRoomId    = room.id;
    patientStatus    = 'stable';

    document.getElementById('patientModalTitle').textContent = `Admettre — Chambre ${room.number}`;
    document.getElementById('patientModalSub').textContent   = svc?.name || '';
    document.getElementById('patientName').value        = '';
    document.getElementById('patientReason').value      = '';
    document.getElementById('patientPractitioner').value= ME_NAME;
    document.getElementById('patientNotes').value       = '';
    document.getElementById('btnDischarge').style.display = 'none';
    document.getElementById('btnSavePatient').textContent  = 'Admettre';
    setPatientStatus('stable');
    clearModalErr('patientErr');
    document.getElementById('patientBackdrop').classList.add('open');
    setTimeout(() => document.getElementById('patientName').focus(), 0);
  }

  function openEditPatient(p, room) {
    editingPatientId = p.id;
    currentRoomId    = room.id;
    patientStatus    = p.status;

    document.getElementById('patientModalTitle').textContent = 'Modifier le patient';
    document.getElementById('patientModalSub').textContent   = `Chambre ${room.number}`;
    document.getElementById('patientName').value         = p.name;
    document.getElementById('patientReason').value       = p.reason || '';
    document.getElementById('patientPractitioner').value = p.practitioner || '';
    document.getElementById('patientNotes').value        = p.notes || '';
    document.getElementById('btnDischarge').style.display = '';
    document.getElementById('btnSavePatient').textContent  = 'Enregistrer';
    setPatientStatus(p.status);
    clearModalErr('patientErr');
    document.getElementById('patientBackdrop').classList.add('open');
  }

  function setPatientStatus(s) {
    patientStatus = s;
    document.querySelectorAll('[data-pstatus]').forEach(btn => {
      btn.className = `status-pill${btn.dataset.pstatus===s?' active-'+s:''}`;
    });
  }

  document.querySelectorAll('[data-pstatus]').forEach(btn => {
    btn.addEventListener('click', () => setPatientStatus(btn.dataset.pstatus));
  });

  document.getElementById('btnSavePatient').addEventListener('click', async () => {
    const name        = document.getElementById('patientName').value.trim();
    const reason      = document.getElementById('patientReason').value.trim();
    const practitioner= document.getElementById('patientPractitioner').value.trim();
    const notes       = document.getElementById('patientNotes').value.trim();
    if (!name) return showModalErr('patientErr', 'Nom du patient requis.');

    const btn = document.getElementById('btnSavePatient');
    btn.disabled = true;
    try {
      if (editingPatientId) {
        await api({ action:'update_patient', id:editingPatientId, name, reason, practitioner, status:patientStatus, notes });
      } else {
        await api({ action:'admit_patient', room_id:currentRoomId, name, reason, practitioner, status:patientStatus, notes });
      }
      document.getElementById('patientBackdrop').classList.remove('open');
      await loadData();
    } catch(e) {
      showModalErr('patientErr', e.message);
    } finally { btn.disabled=false; }
  });

  document.getElementById('btnDischarge').addEventListener('click', async () => {
    if (!editingPatientId || !confirm('Confirmer la sortie du patient ?')) return;
    try {
      await api({ action:'discharge_patient', id:editingPatientId });
      document.getElementById('patientBackdrop').classList.remove('open');
      showToast('✅ Patient sorti — chambre à désinfecter', '🧹');
      await loadData();
    } catch(e) { showModalErr('patientErr', e.message); }
  });

  document.getElementById('btnClosePatient').addEventListener('click',  () => document.getElementById('patientBackdrop').classList.remove('open'));
  document.getElementById('btnCancelPatient').addEventListener('click', () => document.getElementById('patientBackdrop').classList.remove('open'));
  document.getElementById('patientBackdrop').addEventListener('click', e => { if(e.target===document.getElementById('patientBackdrop')) document.getElementById('patientBackdrop').classList.remove('open'); });

  let editingRoomId = null;
  let roomStatus    = 'clean';

  function openAddRoom() {
    editingRoomId = null;
    roomStatus    = 'clean';
    document.getElementById('roomModalTitle').textContent = 'Nouvelle chambre';
    document.getElementById('roomNumber').value = '';
    document.getElementById('roomLabel').value  = '';
    document.getElementById('roomBeds').value   = '1';
    document.getElementById('roomStatusField').style.display = 'none';
    setRoomStatus('clean');
    clearModalErr('roomErr');
    document.getElementById('roomBackdrop').classList.add('open');
    setTimeout(() => document.getElementById('roomNumber').focus(), 0);
  }

  function openRoomMenu(room) {
    editingRoomId = room.id;
    roomStatus    = room.status;
    document.getElementById('roomModalTitle').textContent = `Chambre ${room.number}`;
    document.getElementById('roomNumber').value = room.number;
    document.getElementById('roomLabel').value  = room.label || '';
    document.getElementById('roomBeds').value   = room.beds;
    document.getElementById('roomStatusField').style.display = '';
    setRoomStatus(room.status);
    clearModalErr('roomErr');
    document.getElementById('roomBackdrop').classList.add('open');

    const sel = document.getElementById('roomServiceId');
    if (sel) sel.value = room.service_id;
  }

  function setRoomStatus(s) {
    roomStatus = s;
    document.querySelectorAll('[data-rstat]').forEach(btn => {
      btn.className = `rstat-pill${btn.dataset.rstat===s?' active-'+s:''}`;
    });
  }
  document.querySelectorAll('[data-rstat]').forEach(btn => {
    btn.addEventListener('click', () => setRoomStatus(btn.dataset.rstat));
  });

  document.getElementById('btnSaveRoom').addEventListener('click', async () => {
    const serviceId = parseInt(document.getElementById('roomServiceId').value || '0');
    const number    = document.getElementById('roomNumber').value.trim();
    const label     = document.getElementById('roomLabel').value.trim();
    const beds      = parseInt(document.getElementById('roomBeds').value || '1');
    if (!number) return showModalErr('roomErr', 'Numéro requis.');

    const btn = document.getElementById('btnSaveRoom');
    btn.disabled = true;
    try {
      if (editingRoomId) {
        await api({ action:'edit_room', id:editingRoomId, number, label, beds, status:roomStatus });
      } else {
        if (!serviceId) throw new Error('Sélectionnez un service.');
        await api({ action:'create_room', service_id:serviceId, number, label, beds });
      }
      document.getElementById('roomBackdrop').classList.remove('open');
      await loadData();
    } catch(e) {
      showModalErr('roomErr', e.message);
    } finally { btn.disabled=false; }
  });

  document.getElementById('btnCloseRoom').addEventListener('click',  () => document.getElementById('roomBackdrop').classList.remove('open'));
  document.getElementById('btnCancelRoom').addEventListener('click', () => document.getElementById('roomBackdrop').classList.remove('open'));
  document.getElementById('roomBackdrop').addEventListener('click', e => { if(e.target===document.getElementById('roomBackdrop')) document.getElementById('roomBackdrop').classList.remove('open'); });

  let editingServiceId = null;

  function openAddService() {
    editingServiceId = null;
    document.getElementById('serviceModalTitle').textContent = 'Nouveau service';
    document.getElementById('serviceName').value  = '';
    document.getElementById('serviceIcon').value  = '🏥';
    document.getElementById('serviceColor').value = '#3b9eff';
    clearModalErr('serviceErr');
    document.getElementById('serviceBackdrop').classList.add('open');
    setTimeout(() => document.getElementById('serviceName').focus(), 0);
  }

  function openEditService(svc) {
    editingServiceId = svc.id;
    document.getElementById('serviceModalTitle').textContent = 'Modifier le service';
    document.getElementById('serviceName').value  = svc.name;
    document.getElementById('serviceIcon').value  = svc.icon;
    document.getElementById('serviceColor').value = svc.color;
    clearModalErr('serviceErr');
    document.getElementById('serviceBackdrop').classList.add('open');
  }

  document.getElementById('btnSaveService').addEventListener('click', async () => {
    const name  = document.getElementById('serviceName').value.trim();
    const icon  = document.getElementById('serviceIcon').value.trim() || '🏥';
    const color = document.getElementById('serviceColor').value;
    if (!name) return showModalErr('serviceErr', 'Nom requis.');

    const btn = document.getElementById('btnSaveService');
    btn.disabled = true;
    try {
      if (editingServiceId) {
        await api({ action:'edit_service', id:editingServiceId, name, icon, color });
      } else {
        await api({ action:'create_service', name, icon, color });
      }
      document.getElementById('serviceBackdrop').classList.remove('open');
      await loadData();
    } catch(e) {
      showModalErr('serviceErr', e.message);
    } finally { btn.disabled=false; }
  });

  document.getElementById('btnCloseService').addEventListener('click',  () => document.getElementById('serviceBackdrop').classList.remove('open'));
  document.getElementById('btnCancelService').addEventListener('click', () => document.getElementById('serviceBackdrop').classList.remove('open'));
  document.getElementById('serviceBackdrop').addEventListener('click', e => { if(e.target===document.getElementById('serviceBackdrop')) document.getElementById('serviceBackdrop').classList.remove('open'); });

  function showModalErr(id, msg) {
    const el = document.getElementById(id);
    el.textContent = msg; el.style.display='block';
  }
  function clearModalErr(id) {
    const el = document.getElementById(id);
    el.textContent=''; el.style.display='none';
  }

  function updateRoomSelect() {
    const sel = document.getElementById('roomServiceId');
    sel.innerHTML = '';
    for (const s of state.services) {
      const opt = document.createElement('option');
      opt.value = s.id;
      opt.textContent = s.icon + ' ' + s.name;
      sel.appendChild(opt);
    }
  }

  document.getElementById('btnRefresh').addEventListener('click', loadData);
  if (IS_ADMIN) {
    document.getElementById('btnAddRoom').addEventListener('click',   openAddRoom);
    document.getElementById('btnAddRoom2')?.addEventListener('click', openAddRoom);
    document.getElementById('btnAddService').addEventListener('click', openAddService);
  }

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    ['roomBackdrop','patientBackdrop','serviceBackdrop'].forEach(id => {
      document.getElementById(id)?.classList.remove('open');
    });
  });

  loadData();
  startAutoRefresh();
})();
</script>

<?php samr_layout_end(); ?>
