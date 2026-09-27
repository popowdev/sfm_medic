<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';

require_login();
$u        = current_user();
$username = (string)($u['username'] ?? 'Utilisateur');
$uid      = (int)($u['id'] ?? 0);

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

samr_layout_start('SAMR — Calendrier');
?>

<!-- FullCalendar -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales-all.global.min.js"></script>

<style>
/* ── Calendar overrides ── */
:root{
  --fc-bg: transparent;
  --fc-border-color: var(--line);
  --fc-today-bg-color: rgba(59,158,255,.06);
  --fc-now-indicator-color: var(--accent2);
  --fc-event-border-color: transparent;
  --fc-page-bg-color: transparent;
  --fc-neutral-bg-color: rgba(255,255,255,.03);
  --fc-list-event-hover-bg-color: rgba(255,255,255,.04);
  --fc-highlight-color: rgba(59,158,255,.1);
  --fc-non-business-color: rgba(255,255,255,.01);
}
.fc{ font-family: var(--font-body) !important; font-size: 13px; }
.fc-theme-standard td, .fc-theme-standard th { border-color: var(--line); }
.fc-col-header-cell { padding: 10px 0 !important; }
.fc-col-header-cell-cushion {
  color: var(--muted);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .6px;
  text-decoration: none !important;
}
.fc-col-header-cell.fc-day-today .fc-col-header-cell-cushion {
  color: var(--accent);
}
.fc-timegrid-slot-label-cushion {
  color: var(--muted);
  font-size: 11px;
}
.fc-button-primary {
  background: rgba(59,158,255,.1) !important;
  border: 1px solid var(--line2) !important;
  border-radius: 8px !important;
  color: var(--text) !important;
  font-family: var(--font-body) !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  padding: 6px 12px !important;
  transition: all .15s !important;
  box-shadow: none !important;
}
.fc-button-primary:hover {
  background: rgba(59,158,255,.2) !important;
  border-color: rgba(59,158,255,.35) !important;
}
.fc-button-primary:not(:disabled).fc-button-active,
.fc-button-primary:not(:disabled):active {
  background: rgba(59,158,255,.22) !important;
  border-color: rgba(59,158,255,.5) !important;
}
.fc-toolbar-title {
  font-family: var(--font-head) !important;
  font-size: 17px !important;
  font-weight: 800 !important;
  color: var(--text) !important;
}
.fc-event {
  border-radius: 8px !important;
  border: none !important;
  padding: 3px 6px !important;
  font-size: 12px !important;
  font-weight: 600 !important;
}
.fc-event-title { font-weight: 600 !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fc-event-time { font-size: 10px !important; opacity: .8; }
.fc-timegrid-now-indicator-line { border-color: var(--accent2) !important; border-width: 2px !important; }
.fc-timegrid-now-indicator-arrow { border-top-color: var(--accent2) !important; border-bottom-color: var(--accent2) !important; }

/* ── Page layout ── */
.cal-shell { display: flex; flex-direction: column; gap: 14px; }
.cal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}
.cal-header h1 { font-family: var(--font-head); font-size: 20px; font-weight: 800; margin: 0; }
.cal-header .sub { font-size: 12px; color: var(--muted); margin-top: 2px; }
.cal-header-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

/* ── Modal styles ── */
.rdv-modal-backdrop {
  position: fixed; inset: 0; z-index: 9000;
  background: rgba(0,0,0,.6);
  backdrop-filter: blur(4px);
  display: none;
  align-items: flex-start;
  justify-content: center;
  padding-top: 7vh;
}
.rdv-modal-backdrop.open { display: flex; }
.rdv-modal {
  width: min(540px, calc(100% - 32px));
  background: linear-gradient(160deg, #0d1219 0%, #080c10 100%);
  border: 1px solid var(--line2);
  border-radius: 18px;
  padding: 22px;
  box-shadow: 0 32px 80px rgba(0,0,0,.6);
  position: relative;
  max-height: 90vh;
  overflow-y: auto;
}
.rdv-modal-head {
  display: flex; justify-content: space-between; align-items: flex-start;
  margin-bottom: 18px;
  gap: 10px;
}
.rdv-modal-title { font-family: var(--font-head); font-size: 16px; font-weight: 700; }
.rdv-modal-sub { font-size: 12px; color: var(--muted); margin-top: 3px; }
.rdv-field { margin-bottom: 14px; }
.rdv-field label {
  display: block;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .6px;
  color: var(--muted);
  margin-bottom: 6px;
}
.rdv-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.rdv-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }

/* Duration chips */
.dur-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.dur-chip {
  padding: 7px 12px;
  border-radius: 8px;
  border: 1px solid var(--line2);
  background: rgba(255,255,255,.03);
  color: var(--muted);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all .12s;
  white-space: nowrap;
}
.dur-chip:hover { border-color: var(--accent); color: var(--accent); background: rgba(59,158,255,.06); }
.dur-chip.active { border-color: var(--accent); color: var(--accent); background: rgba(59,158,255,.1); }
.dur-chip.custom-chip { border-style: dashed; }

/* Custom duration inputs */
.custom-dur-row {
  display: none;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
}
.custom-dur-row.visible { display: flex; }
.custom-dur-row input {
  width: 70px;
  text-align: center;
  padding: 7px 10px;
}
.custom-dur-row span { font-size: 12px; color: var(--muted); }

/* View modal details */
.rdv-detail-row {
  display: grid;
  grid-template-columns: 100px 1fr;
  gap: 6px 12px;
  align-items: start;
  padding: 10px 0;
  border-bottom: 1px solid var(--line);
}
.rdv-detail-row:last-of-type { border-bottom: none; }
.rdv-detail-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); padding-top: 1px; }
.rdv-detail-val { font-size: 13px; font-weight: 600; }

.rdv-error {
  padding: 10px 12px; border-radius: 9px;
  background: rgba(255,79,79,.08); border: 1px solid rgba(255,79,79,.25);
  color: #ff7a7a; font-size: 13px; margin-top: 12px; display: none;
}
.rdv-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; flex-wrap: wrap; }

@media (max-width: 600px) {
  .rdv-row, .rdv-row-3 { grid-template-columns: 1fr; }
}
</style>

<div class="cal-shell">

  <div class="cal-header">
    <div>
      <h1>📅 Calendrier</h1>
      <div class="sub">Cliquez sur le calendrier pour créer un RDV • Glissez pour déplacer ou redimensionner</div>
    </div>
    <div class="cal-header-actions">
      <button class="btn" id="btnNewRdv">➕ Nouveau rendez-vous</button>
      <span class="pill pill-blue"><?= h($username) ?></span>
    </div>
  </div>

  <div class="card" style="padding:16px;">
    <div id="calendar"></div>
  </div>

</div>

<!-- ── Modal Création ── -->
<div class="rdv-modal-backdrop" id="createBackdrop">
  <div class="rdv-modal" role="dialog" aria-modal="true" aria-labelledby="createTitle">
    <div class="rdv-modal-head">
      <div>
        <div class="rdv-modal-title" id="createTitle">Nouveau rendez-vous</div>
        <div class="rdv-modal-sub">Par <strong><?= h($username) ?></strong></div>
      </div>
      <button class="btn secondary" id="btnCloseCreate" style="padding:6px 10px;">✕</button>
    </div>

    <div class="rdv-field">
      <label>Nom du patient</label>
      <input class="inp" id="cPatient" type="text" maxlength="120" placeholder="Ex : John Doe" required>
    </div>

    <div class="rdv-field">
      <label>Motif</label>
      <input class="inp" id="cReason" type="text" maxlength="180" placeholder="Ex : Consultation, VM, urgence…" required>
    </div>

    <div class="rdv-row">
      <div class="rdv-field" style="margin:0;">
        <label>Date & heure (début)</label>
        <input class="inp" id="cStart" type="datetime-local" required>
      </div>
      <div class="rdv-field" style="margin:0;">
        <label>Date & heure (fin)</label>
        <input class="inp" id="cEnd" type="datetime-local" required>
      </div>
    </div>

    <div class="rdv-field" style="margin-top:14px;">
      <label>Durée rapide</label>
      <div class="dur-chips">
        <div class="dur-chip" data-min="15">15 min</div>
        <div class="dur-chip active" data-min="30">30 min</div>
        <div class="dur-chip" data-min="45">45 min</div>
        <div class="dur-chip" data-min="60">1 h</div>
        <div class="dur-chip" data-min="90">1 h 30</div>
        <div class="dur-chip" data-min="120">2 h</div>
        <div class="dur-chip custom-chip" data-min="custom">⚙ Sur mesure</div>
      </div>
      <div class="custom-dur-row" id="customDurRow">
        <input class="inp" id="customH" type="number" min="0" max="23" value="0" placeholder="h">
        <span>h</span>
        <input class="inp" id="customM" type="number" min="0" max="59" step="1" value="30" placeholder="min">
        <span>min</span>
        <button class="btn" id="btnApplyCustom" style="padding:7px 12px;font-size:12px;">Appliquer</button>
      </div>
    </div>

    <div class="rdv-error" id="createErr"></div>

    <div class="rdv-actions">
      <button class="btn secondary" id="btnCancelCreate">Annuler</button>
      <button class="btn success" id="btnSubmitCreate">Créer le RDV</button>
    </div>
  </div>
</div>

<!-- ── Modal Détail / Édition ── -->
<div class="rdv-modal-backdrop" id="viewBackdrop">
  <div class="rdv-modal" role="dialog" aria-modal="true" aria-labelledby="viewTitle">
    <div class="rdv-modal-head">
      <div>
        <div class="rdv-modal-title" id="viewTitle">Détails</div>
        <div class="rdv-modal-sub" id="viewSub">—</div>
      </div>
      <button class="btn secondary" id="btnCloseView" style="padding:6px 10px;">✕</button>
    </div>

    <div id="viewDetails">
      <div class="rdv-detail-row">
        <div class="rdv-detail-label">Patient</div>
        <div class="rdv-detail-val" id="vPatient">—</div>
      </div>
      <div class="rdv-detail-row">
        <div class="rdv-detail-label">Motif</div>
        <div class="rdv-detail-val" id="vReason">—</div>
      </div>
      <div class="rdv-detail-row">
        <div class="rdv-detail-label">Praticien</div>
        <div class="rdv-detail-val" id="vPractitioner">—</div>
      </div>
    </div>

    <!-- Édition (visible seulement pour le créateur) -->
    <div id="editSection" style="display:none;">
      <div style="margin:16px 0 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Modifier les horaires</div>
      <div class="rdv-row">
        <div class="rdv-field" style="margin:0;">
          <label>Début</label>
          <input class="inp" id="eStart" type="datetime-local">
        </div>
        <div class="rdv-field" style="margin:0;">
          <label>Fin</label>
          <input class="inp" id="eEnd" type="datetime-local">
        </div>
      </div>
      <div class="rdv-field" style="margin-top:12px;">
        <label>Durée rapide</label>
        <div class="dur-chips" id="editDurChips">
          <div class="dur-chip" data-min="15">15 min</div>
          <div class="dur-chip" data-min="30">30 min</div>
          <div class="dur-chip" data-min="45">45 min</div>
          <div class="dur-chip" data-min="60">1 h</div>
          <div class="dur-chip" data-min="90">1 h 30</div>
          <div class="dur-chip" data-min="120">2 h</div>
          <div class="dur-chip custom-chip" data-min="custom">⚙ Sur mesure</div>
        </div>
        <div class="custom-dur-row" id="editCustomDurRow">
          <input class="inp" id="editCustomH" type="number" min="0" max="23" value="0">
          <span>h</span>
          <input class="inp" id="editCustomM" type="number" min="0" max="59" step="1" value="30">
          <span>min</span>
          <button class="btn" id="btnApplyEditCustom" style="padding:7px 12px;font-size:12px;">Appliquer</button>
        </div>
      </div>
    </div>

    <div class="rdv-error" id="viewErr"></div>

    <div class="rdv-actions">
      <button class="btn secondary" id="btnCloseView2">Fermer</button>
      <button class="btn danger" id="btnDelete" style="display:none;">Supprimer</button>
      <button class="btn success" id="btnSave" style="display:none;">Enregistrer</button>
    </div>
  </div>
</div>

<script>
(function(){
  const ME        = <?= json_encode($uid) ?>;
  const CSRF      = <?= json_encode(csrf_token()) ?>;

  const pad = n => String(n).padStart(2,'0');

  function toLocal(d){
    return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())
          +'T'+pad(d.getHours())+':'+pad(d.getMinutes())+':00';
  }
  function toInputVal(d){
    return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())
          +'T'+pad(d.getHours())+':'+pad(d.getMinutes());
  }
  function parseInput(v){ const d=new Date(v); return isNaN(d)?null:d; }
  function addMin(d,m){ return new Date(d.getTime()+m*60000); }
  function snapTo15(d){
    const m=d.getMinutes(); d.setMinutes(m-m%15,0,0); return d;
  }
  function getDetails(ext){
    return {
      patient: ext?.patient_name || '',
      reason:  ext?.reason       || '',
    };
  }

  async function api(payload){
    const r = await fetch('/appointments.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body:JSON.stringify({...payload,csrf:CSRF})
    });
    const d = await r.json().catch(()=>null);
    if(!r.ok) throw new Error(d?.error||'Erreur API');
    return d;
  }

  function openBackdrop(id){ document.getElementById(id).classList.add('open'); }
  function closeBackdrop(id){ document.getElementById(id).classList.remove('open'); }

  function showErr(id,msg){ const el=document.getElementById(id); el.textContent=msg; el.style.display='block'; }
  function clearErr(id){ const el=document.getElementById(id); el.textContent=''; el.style.display='none'; }

  function makeDurChips(containerSel, customRowId, applyBtnId, onApply){
    const chips   = document.querySelectorAll(containerSel+' .dur-chip');
    const customRow = document.getElementById(customRowId);
    const applyBtn  = document.getElementById(applyBtnId);
    let active = 30;

    function setActive(min){
      active = min;
      chips.forEach(c=>{
        c.classList.toggle('active', parseInt(c.dataset.min)===min);
      });
    }

    chips.forEach(chip=>{
      chip.addEventListener('click',()=>{
        const min = chip.dataset.min;
        if(min==='custom'){
          chips.forEach(c=>c.classList.remove('active'));
          chip.classList.add('active');
          customRow.classList.add('visible');
        } else {
          customRow.classList.remove('visible');
          setActive(parseInt(min));
          onApply(parseInt(min));
        }
      });
    });

    applyBtn.addEventListener('click',()=>{
      const h = parseInt(document.getElementById(customRowId==='customDurRow'?'customH':'editCustomH').value||'0');
      const m = parseInt(document.getElementById(customRowId==='customDurRow'?'customM':'editCustomM').value||'0');
      const total = h*60+m;
      if(total<5){ alert('Durée minimum : 5 minutes'); return; }
      onApply(total);
    });

    return { getActive: ()=>active, setActive };
  }

  const createBackdrop = document.getElementById('createBackdrop');
  const cStart  = document.getElementById('cStart');
  const cEnd    = document.getElementById('cEnd');
  const cPatient= document.getElementById('cPatient');
  const cReason = document.getElementById('cReason');

  const createChips = makeDurChips('#createBackdrop .dur-chips','customDurRow','btnApplyCustom',(mins)=>{
    const s = parseInput(cStart.value);
    if(s){ cEnd.value = toInputVal(addMin(s,mins)); }
  });

  function openCreate(startDate){
    clearErr('createErr');
    cPatient.value=''; cReason.value='';
    const s = snapTo15(startDate || new Date());
    cStart.value = toInputVal(s);
    cEnd.value   = toInputVal(addMin(s, createChips.getActive()));
    document.getElementById('customDurRow').classList.remove('visible');
    openBackdrop('createBackdrop');
    setTimeout(()=>cPatient.focus(),0);
  }

  cStart.addEventListener('change',()=>{
    const s=parseInput(cStart.value);
    if(s) cEnd.value = toInputVal(addMin(s, createChips.getActive()));
  });

  document.getElementById('btnCloseCreate').addEventListener('click',()=>closeBackdrop('createBackdrop'));
  document.getElementById('btnCancelCreate').addEventListener('click',()=>closeBackdrop('createBackdrop'));
  createBackdrop.addEventListener('click',e=>{ if(e.target===createBackdrop) closeBackdrop('createBackdrop'); });

  document.getElementById('btnSubmitCreate').addEventListener('click', async ()=>{
    clearErr('createErr');
    const patient = cPatient.value.trim();
    const reason  = cReason.value.trim();
    const s       = parseInput(cStart.value);
    const e       = parseInput(cEnd.value);

    if(!patient||!reason)      return showErr('createErr','Patient et motif requis.');
    if(!s||!e)                 return showErr('createErr','Dates requises.');
    if(e<=s)                   return showErr('createErr','La fin doit être après le début.');

    const btn = document.getElementById('btnSubmitCreate');
    btn.disabled = true;
    try{
      await api({ action:'create', patient_name:patient, reason, start:toLocal(s), end:toLocal(e) });
      closeBackdrop('createBackdrop');
      calendar.refetchEvents();
    }catch(err){
      showErr('createErr', err.message);
    }finally{ btn.disabled=false; }
  });

  document.getElementById('btnNewRdv').addEventListener('click',()=>openCreate());

  let currentEvent = null;

  const editChips = makeDurChips('#editDurChips','editCustomDurRow','btnApplyEditCustom',(mins)=>{
    const s=parseInput(document.getElementById('eStart').value);
    if(s) document.getElementById('eEnd').value = toInputVal(addMin(s,mins));
  });

  function openView(fcEvent){
    currentEvent = fcEvent;
    const ext     = fcEvent.extendedProps || {};
    const details = getDetails(ext);
    const canEdit = Number(ext.created_by) === ME;

    document.getElementById('viewTitle').textContent   = fcEvent.title || 'RDV';
    document.getElementById('viewSub').textContent     = formatEventSpan(fcEvent);
    document.getElementById('vPatient').textContent    = details.patient || '—';
    document.getElementById('vReason').textContent     = details.reason  || '—';
    document.getElementById('vPractitioner').textContent = ext.creator || '—';

    const editSection = document.getElementById('editSection');
    editSection.style.display = canEdit ? '' : 'none';

    if(canEdit){
      document.getElementById('eStart').value = toInputVal(fcEvent.start);
      document.getElementById('eEnd').value   = toInputVal(fcEvent.end || addMin(fcEvent.start,30));
      document.getElementById('editCustomDurRow').classList.remove('visible');
    }

    document.getElementById('btnSave').style.display   = canEdit ? '' : 'none';
    document.getElementById('btnDelete').style.display = canEdit ? '' : 'none';

    clearErr('viewErr');
    openBackdrop('viewBackdrop');
  }

  function formatEventSpan(ev){
    const opts = {weekday:'short',day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'};
    const s    = ev.start?.toLocaleString('fr-FR',opts) || '';
    const e    = ev.end?.toLocaleString('fr-FR',{hour:'2-digit',minute:'2-digit'}) || '';
    return s + (e ? ' → '+e : '');
  }

  document.getElementById('btnCloseView').addEventListener('click',()=>closeBackdrop('viewBackdrop'));
  document.getElementById('btnCloseView2').addEventListener('click',()=>closeBackdrop('viewBackdrop'));
  document.getElementById('viewBackdrop').addEventListener('click',e=>{ if(e.target===document.getElementById('viewBackdrop')) closeBackdrop('viewBackdrop'); });

  document.getElementById('btnSave').addEventListener('click', async ()=>{
    if(!currentEvent) return;
    clearErr('viewErr');
    const s = parseInput(document.getElementById('eStart').value);
    const e = parseInput(document.getElementById('eEnd').value);
    if(!s||!e||e<=s) return showErr('viewErr','Dates invalides (fin > début).');

    const btn = document.getElementById('btnSave');
    btn.disabled=true;
    try{
      await api({ action:'update', id:currentEvent.id, start:toLocal(s), end:toLocal(e) });
      currentEvent.setStart(s); currentEvent.setEnd(e);
      closeBackdrop('viewBackdrop');
    }catch(err){
      showErr('viewErr',err.message);
    }finally{ btn.disabled=false; }
  });

  document.getElementById('btnDelete').addEventListener('click', async ()=>{
    if(!currentEvent||!confirm('Supprimer ce rendez-vous ?')) return;
    clearErr('viewErr');
    const btn=document.getElementById('btnDelete'); btn.disabled=true;
    try{
      await api({ action:'delete', id:currentEvent.id });
      currentEvent.remove();
      closeBackdrop('viewBackdrop');
    }catch(err){
      showErr('viewErr',err.message);
    }finally{ btn.disabled=false; }
  });

  document.getElementById('eStart').addEventListener('change',()=>{
    const s=parseInput(document.getElementById('eStart').value);
    if(s) document.getElementById('eEnd').value=toInputVal(addMin(s,editChips.getActive()));
  });

  document.addEventListener('keydown',e=>{
    if(e.key==='Escape'){
      closeBackdrop('createBackdrop');
      closeBackdrop('viewBackdrop');
    }
  });

  const calendar = new FullCalendar.Calendar(document.getElementById('calendar'),{
    locale:         'fr',
    timeZone:       'local',
    firstDay:       1,
    initialView:    'timeGridWeek',
    nowIndicator:   true,
    allDaySlot:     false,
    slotMinTime:    '16:00:00',
    slotMaxTime:    '27:00:00',
    scrollTime:     '20:00:00',
    slotDuration:   '00:15:00',
    snapDuration:   '00:15:00',
    expandRows:     true,
    height:         'auto',
    editable:       true,
    selectable:     true,

    headerToolbar:{
      left:   'prev,next today',
      center: 'title',
      right:  'timeGridWeek,timeGridDay,listWeek'
    },
    buttonText:{
      today:    "Aujourd'hui",
      week:     'Semaine',
      day:      'Jour',
      list:     'Liste'
    },

    events:{
      url:     '/appointments.php',
      method:  'GET',
      failure: ()=>alert("Impossible de charger les RDV.")
    },

    select(info){
      openCreate(info.start);
      cEnd.value = toInputVal(info.end);
    },

    dateClick(info){
      openCreate(info.date);
    },

    eventClick(info){
      openView(info.event);
    },

    eventAllow(drop, event){
      return Number(event.extendedProps?.created_by)===ME;
    },
    async eventDrop(info){
      try{
        await api({
          action:'update', id:info.event.id,
          start:toLocal(info.event.start),
          end:toLocal(info.event.end||addMin(info.event.start,30))
        });
      }catch(e){ alert(e.message); info.revert(); }
    },

    async eventResize(info){
      try{
        await api({
          action:'update', id:info.event.id,
          start:toLocal(info.event.start),
          end:toLocal(info.event.end)
        });
      }catch(e){ alert(e.message); info.revert(); }
    },

    eventDidMount(arg){
      const ext = arg.event.extendedProps||{};
      const d   = getDetails(ext);
      arg.el.title = [
        d.patient  ? 'Patient: '+d.patient  : '',
        d.reason   ? 'Motif: '+d.reason     : '',
        ext.creator? 'Par: '+ext.creator    : '',
      ].filter(Boolean).join('\n');

      if(Number(ext.created_by)!==ME){
        arg.el.style.opacity='.75';
        arg.el.style.cursor='default';
      }
    }
  });

  calendar.render();

})();
</script>

<?php samr_layout_end(); ?>
