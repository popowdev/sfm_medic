<?php

declare(strict_types=1);
?>
<style>
/* ── Grille patients ── */
.sp-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;}
.sp-title{display:flex;align-items:center;gap:12px;}
.sp-icon-box{width:46px;height:46px;border-radius:12px;background:rgba(59,158,255,.1);border:1px solid rgba(59,158,255,.2);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;}
.sp-title h1{font-family:var(--font-head);font-size:20px;font-weight:800;margin:0;}
.sp-title .sub{font-size:12px;color:var(--muted);margin-top:2px;}
.sp-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 12px;border-radius:999px;font-size:11px;font-weight:700;background:rgba(0,212,170,.08);border:1px solid rgba(0,212,170,.2);color:var(--accent2);}

.patient-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px;}
.patient-card{background:linear-gradient(160deg,rgba(13,18,25,.95),rgba(10,15,21,.98));border:1px solid var(--line);border-radius:14px;padding:16px;cursor:pointer;transition:border-color .15s,transform .12s;}
.patient-card:hover{border-color:rgba(59,158,255,.35);transform:translateY(-2px);}
.pc-top{display:flex;align-items:center;gap:11px;margin-bottom:12px;}
.pc-avatar{width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff;flex-shrink:0;}
.pc-name{font-family:var(--font-head);font-size:14px;font-weight:700;margin-bottom:2px;}
.pc-dob{font-size:11px;color:var(--muted);}
.pc-meta{display:flex;align-items:center;justify-content:space-between;font-size:11px;color:var(--muted);}
.pc-reports-count{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:999px;background:rgba(59,158,255,.07);border:1px solid rgba(59,158,255,.15);color:var(--accent);font-weight:700;}

/* ── Formulaire nouveau patient ── */
.new-patient-form{display:none;background:linear-gradient(160deg,rgba(13,18,25,.98),rgba(8,12,16,.98));border:1px solid var(--line2);border-radius:14px;padding:18px;margin-bottom:16px;}
.new-patient-form.open{display:block;}
.nf-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;}
@media(max-width:600px){.nf-grid{grid-template-columns:1fr;}}

/* ── Modal dossier ── */
.sp-modal-backdrop{position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.72);backdrop-filter:blur(6px);display:none;align-items:center;justify-content:center;padding:16px;}
.sp-modal-backdrop.open{display:flex;}
.sp-modal{
  width:min(860px,100%);
  height:min(90vh,900px);
  background:linear-gradient(160deg,#0d1219 0%,#080c10 100%);
  border:1px solid var(--line2);
  border-radius:20px;
  box-shadow:0 40px 100px rgba(0,0,0,.7);
  display:flex;flex-direction:column;
  overflow:hidden;
  position:relative;
}

/* En-tête modal */
.sm-head{
  padding:20px 24px 16px;
  border-bottom:1px solid var(--line);
  flex-shrink:0;
  display:flex;align-items:flex-start;justify-content:space-between;gap:12px;
  background:linear-gradient(180deg,rgba(59,158,255,.05) 0%,transparent 100%);
}
.sm-head-left{display:flex;align-items:center;gap:14px;}
.sm-avatar{width:54px;height:54px;border-radius:14px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;color:#fff;flex-shrink:0;}
.sm-patient-name{font-family:var(--font-head);font-size:18px;font-weight:800;margin-bottom:4px;}
.sm-patient-meta{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.sm-patient-meta span{display:flex;align-items:center;gap:4px;}
.sm-actions{display:flex;gap:7px;align-items:flex-start;flex-shrink:0;}
.sm-close{width:32px;height:32px;border-radius:8px;border:1px solid var(--line2);background:transparent;color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;transition:all .12s;}
.sm-close:hover{background:rgba(255,79,79,.1);border-color:rgba(255,79,79,.3);color:#ff7a7a;}

/* Tabs */
.sm-tabs{display:flex;gap:0;border-bottom:1px solid var(--line);flex-shrink:0;background:rgba(255,255,255,.01);}
.sm-tab{padding:12px 20px;font-size:13px;font-weight:600;color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;transition:all .12s;border-top:none;border-left:none;border-right:none;background:transparent;font-family:var(--font-body);}
.sm-tab:hover{color:var(--text);}
.sm-tab.active{color:var(--accent);border-bottom-color:var(--accent);}

/* Corps scrollable */
.sm-body{flex:1;overflow-y:auto;padding:20px 24px;}

/* Infos patient éditable */
.sm-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;}
@media(max-width:600px){.sm-info-grid{grid-template-columns:1fr;}}

/* Historique rapports */
.report-item{background:rgba(255,255,255,.02);border:1px solid var(--line);border-radius:12px;margin-bottom:10px;overflow:hidden;}
.report-item-head{padding:12px 16px;display:flex;align-items:flex-start;justify-content:space-between;gap:10px;cursor:pointer;transition:background .1s;}
.report-item-head:hover{background:rgba(255,255,255,.03);}
.report-item-title{font-size:13px;font-weight:700;color:var(--text);margin-bottom:2px;}
.report-item-meta{font-size:11px;color:var(--muted);}
.report-item-body{display:none;padding:0 16px 14px;font-size:13px;color:#8aa8c0;line-height:1.7;border-top:1px solid var(--line);}
.report-item-body.open{display:block;}
.report-chevron{color:var(--muted);font-size:12px;transition:transform .2s;flex-shrink:0;margin-top:2px;}
.report-chevron.open{transform:rotate(180deg);}

/* Nouveau rapport */
.new-report-form{background:rgba(255,255,255,.02);border:1px solid rgba(59,158,255,.2);border-radius:12px;padding:16px;margin-bottom:14px;}
.nr-title{font-family:var(--font-head);font-size:13px;font-weight:700;margin-bottom:12px;color:var(--accent);}

/* Bouton flottant */
.fab-new-report{
  position:fixed;
  bottom:28px;right:28px;
  z-index:9500;
  display:none;
  align-items:center;gap:8px;
  padding:13px 22px;
  border-radius:50px;
  border:1px solid rgba(0,212,170,.35);
  background:linear-gradient(135deg,rgba(0,212,170,.15),rgba(59,158,255,.1));
  color:var(--accent2);
  font-weight:700;font-size:13px;
  cursor:pointer;
  box-shadow:0 8px 32px rgba(0,0,0,.5);
  backdrop-filter:blur(10px);
  font-family:var(--font-body);
  transition:all .15s;
}
.fab-new-report:hover{background:linear-gradient(135deg,rgba(0,212,170,.25),rgba(59,158,255,.18));transform:translateY(-2px);}
.fab-new-report.visible{display:flex;}

/* Micro dans modal */
.mic-btn-sm{
  width:34px;height:34px;border-radius:8px;border:1px solid var(--line2);
  background:rgba(59,158,255,.07);cursor:pointer;
  display:flex;align-items:center;justify-content:center;font-size:15px;
  transition:all .15s;flex-shrink:0;
}
.mic-btn-sm:hover{background:rgba(59,158,255,.15);}
.mic-btn-sm.listening{background:rgba(255,79,79,.1);border-color:rgba(255,79,79,.4);animation:micpulse 1.2s infinite;}
@keyframes micpulse{0%{box-shadow:0 0 0 0 rgba(255,79,79,.35);}70%{box-shadow:0 0 0 8px rgba(255,79,79,0);}100%{box-shadow:0 0 0 0 rgba(255,79,79,0);}}

.sm-section-title{font-size:10px;text-transform:uppercase;letter-spacing:.9px;color:var(--muted);font-weight:700;margin:20px 0 10px;display:flex;align-items:center;gap:8px;}
.sm-section-title::after{content:'';flex:1;height:1px;background:var(--line);}

.empty-state{text-align:center;padding:40px 20px;color:var(--muted);}
.empty-state .es-icon{font-size:36px;margin-bottom:10px;}
.empty-state .es-text{font-size:13px;}

/* Editeur Quill */
.rich-editor-wrap{background:#fff;border:1px solid rgba(59,158,255,.28);border-radius:12px;overflow:hidden;margin-bottom:12px;}
.rich-editor-wrap .ql-toolbar.ql-snow{border:0;border-bottom:1px solid #d9e2ec;background:#f8fafc;}
.rich-editor-wrap .ql-container.ql-snow{border:0;min-height:260px;font-family:Arial,Helvetica,sans-serif;font-size:14px;}
.rich-editor-wrap .ql-editor{min-height:260px;color:#111827;line-height:1.55;}
.report-rich-body{padding:14px 0 0;color:#d7e7f5;line-height:1.65;white-space:normal;}
.report-rich-body p{margin:0 0 10px;}
.report-rich-body h1,.report-rich-body h2,.report-rich-body h3{font-family:var(--font-head);color:#fff;margin:12px 0 8px;}
.report-rich-body h1{font-size:20px;}.report-rich-body h2{font-size:17px;}.report-rich-body h3{font-size:15px;}
.report-rich-body ul,.report-rich-body ol{margin:8px 0 10px 22px;padding:0;}
.report-rich-body blockquote{border-left:3px solid var(--accent);margin:10px 0;padding:8px 12px;background:rgba(59,158,255,.07);border-radius:8px;}
.report-rich-body .ql-align-center{text-align:center;}.report-rich-body .ql-align-right{text-align:right;}.report-rich-body .ql-align-justify{text-align:justify;}

</style>

<!-- En-tête -->
<div class="sp-header">
  <div class="sp-title">
    <div class="sp-icon-box"><?= htmlspecialchars($SPEC_ICON, ENT_QUOTES, 'UTF-8') ?></div>
    <div>
      <h1><?= htmlspecialchars($SPEC_LABEL, ENT_QUOTES, 'UTF-8') ?></h1>
      <div class="sub">Dossiers patients · <?= htmlspecialchars($SPEC_LABEL, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <span class="sp-badge">✓ Spécialisation autorisée</span>
    <input class="inp" id="searchInput" placeholder="🔍 Rechercher un patient…" style="width:200px;">
    <button class="btn success" id="btnToggleNew">➕ Nouveau patient</button>
  </div>
</div>

<!-- Formulaire nouveau patient -->
<div class="new-patient-form" id="newPatientForm">
  <div style="font-family:var(--font-head);font-size:14px;font-weight:700;margin-bottom:12px;">Nouveau patient</div>
  <div class="nf-grid">
    <div>
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Nom *</div>
      <input class="inp" id="nfLastName" placeholder="Nom de famille">
    </div>
    <div>
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Prénom *</div>
      <input class="inp" id="nfFirstName" placeholder="Prénom">
    </div>
    <div>
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Date de naissance</div>
      <input class="inp" type="date" id="nfBirthDate">
    </div>
    <div>
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Téléphone</div>
      <input class="inp" id="nfPhone" placeholder="Optionnel">
    </div>
  </div>
  <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Notes initiales</div>
  <textarea class="inp" id="nfNotes" rows="2" style="resize:none;margin-bottom:10px;" placeholder="Antécédents, remarques…"></textarea>
  <div style="display:flex;gap:8px;justify-content:flex-end;">
    <button class="btn secondary" id="btnCancelNew">Annuler</button>
    <button class="btn success" id="btnCreatePatient">✅ Créer le dossier</button>
  </div>
</div>

<!-- Grille patients -->
<div class="patient-grid" id="patientGrid">
  <div class="muted" style="padding:20px;">Chargement…</div>
</div>

<!-- ── Modal dossier ── -->
<div class="sp-modal-backdrop" id="modalBackdrop">
  <div class="sp-modal" id="spModal">

    <!-- En-tête patient -->
    <div class="sm-head">
      <div class="sm-head-left">
        <div class="sm-avatar" id="smAvatar">?</div>
        <div>
          <div class="sm-patient-name" id="smName">—</div>
          <div class="sm-patient-meta" id="smMeta"></div>
        </div>
      </div>
      <div class="sm-actions">
        <?php if($isAdmin): ?>
        <button class="btn danger" style="font-size:12px;padding:6px 12px;" id="btnDeletePatient">🗑 Supprimer</button>
        <?php endif; ?>
        <button class="sm-close" id="btnCloseModal" title="Fermer">✕</button>
      </div>
    </div>

    <!-- Tabs -->
    <div class="sm-tabs">
      <button class="sm-tab active" data-tab="reports">📋 Historique des rapports</button>
      <button class="sm-tab" data-tab="info">👤 Informations patient</button>
    </div>

    <!-- Corps -->
    <div class="sm-body" id="smBody">
      <div class="muted" style="padding:20px;">Chargement…</div>
    </div>

  </div>
</div>

<!-- Bouton flottant -->
<button class="fab-new-report" id="fabNewReport">✏️ Nouveau rapport</button>

<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.2.6/dist/purify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>

<script>
(function(){
  const SPEC    = <?= json_encode($SPEC_KEY) ?>;
  const CSRF    = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
  const IS_ADMIN= <?= json_encode($isAdmin) ?>;
  const API     = '/api/spec_patients.php';

  let currentPatientId = null;
  let currentPatient   = null;
  let currentReports   = [];
  let activeTab        = 'reports';
  let recognition      = null;
  let isListening      = false;
  let finalTranscript  = '';
  let editingReportId  = null;
  let reportQuill      = null;

  function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  function looksLikeHtml(s){ return /<\/?[a-z][\s\S]*>/i.test(String(s||'')); }
  function plainToHtml(s){ return esc(s).replace(/\n/g,'<br>'); }
  function cleanReportHtml(html){
    return DOMPurify.sanitize(String(html||''), {
      ALLOWED_TAGS:['p','br','strong','b','em','i','u','s','h1','h2','h3','ol','ul','li','blockquote','span','a'],
      ALLOWED_ATTR:['href','target','rel','class','style'],
      ALLOWED_CSS_PROPERTIES:['color','background-color','text-align']
    });
  }
  function reportToHtml(body){ return looksLikeHtml(body) ? cleanReportHtml(body) : plainToHtml(body); }
  function fmtDate(s){ if(!s)return '—'; return new Date(s).toLocaleDateString('fr-FR',{day:'2-digit',month:'2-digit',year:'numeric'}); }
  function fmtDT(s){ if(!s)return '—'; return new Date(s).toLocaleString('fr-FR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}); }

  async function apiGet(params){
    const url = API+'?spec='+SPEC+'&'+new URLSearchParams(params);
    const r = await fetch(url,{credentials:'same-origin'});
    return r.json();
  }
  async function apiPost(data){
    const r = await fetch(API,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({...data,spec:SPEC,csrf:CSRF})});
    return r.json();
  }

  async function loadList(q=''){
    const d = await apiGet({action:'list',q});
    const grid = document.getElementById('patientGrid');
    if(!d.ok||!d.patients?.length){
      grid.innerHTML='<div class="muted" style="padding:20px;grid-column:1/-1;">Aucun patient enregistré.</div>';
      return;
    }
    grid.innerHTML = d.patients.map(p=>{
      const initials = (p.last_name[0]||'?')+(p.first_name[0]||'');
      return `<div class="patient-card" data-id="${p.id}">
        <div class="pc-top">
          <div class="pc-avatar">${esc(initials.toUpperCase())}</div>
          <div>
            <div class="pc-name">${esc(p.last_name)} ${esc(p.first_name)}</div>
            <div class="pc-dob">${p.birth_date?fmtDate(p.birth_date):'DDN non renseignée'}</div>
          </div>
        </div>
        <div class="pc-meta">
          <span>${p.phone?'📞 '+esc(p.phone):'<span style="font-style:italic;">Pas de téléphone</span>'}</span>
        </div>
      </div>`;
    }).join('');
    grid.querySelectorAll('.patient-card').forEach(card=>{
      card.addEventListener('click',()=>openDossier(parseInt(card.dataset.id)));
    });
  }

  const newForm = document.getElementById('newPatientForm');
  document.getElementById('btnToggleNew').addEventListener('click',()=>{
    newForm.classList.toggle('open');
    if(newForm.classList.contains('open')) document.getElementById('nfLastName').focus();
  });
  document.getElementById('btnCancelNew').addEventListener('click',()=>newForm.classList.remove('open'));
  document.getElementById('btnCreatePatient').addEventListener('click',async()=>{
    const ln=document.getElementById('nfLastName').value.trim();
    const fn=document.getElementById('nfFirstName').value.trim();
    if(!ln||!fn){alert('Nom et prénom requis.');return;}
    const d=await apiPost({action:'create_patient',last_name:ln,first_name:fn,
      birth_date:document.getElementById('nfBirthDate').value||null,
      phone:document.getElementById('nfPhone').value.trim()||null,
      notes:document.getElementById('nfNotes').value.trim()||null});
    if(!d.ok){alert(d.error);return;}
    newForm.classList.remove('open');
    ['nfLastName','nfFirstName','nfBirthDate','nfPhone','nfNotes'].forEach(id=>document.getElementById(id).value='');
    loadList();
    openDossier(d.id);
  });

  async function openDossier(id){
    currentPatientId = id;
    document.getElementById('modalBackdrop').classList.add('open');
    document.getElementById('fabNewReport').classList.add('visible');
    document.body.style.overflow='hidden';
    const d = await apiGet({action:'get',id});
    if(!d.ok){alert(d.error);closeModal();return;}
    currentPatient  = d.patient;
    currentReports  = d.reports||[];
    renderModalHead();
    renderTab(activeTab);
  }

  function renderModalHead(){
    const p = currentPatient;
    const initials = ((p.last_name[0]||'')+(p.first_name[0]||'')).toUpperCase();
    document.getElementById('smAvatar').textContent = initials||'?';
    document.getElementById('smName').textContent   = p.last_name+' '+p.first_name;
    let meta = '';
    if(p.birth_date) meta+=`<span>📅 ${fmtDate(p.birth_date)}</span>`;
    if(p.phone)      meta+=`<span>📞 ${esc(p.phone)}</span>`;
    meta+=`<span>📋 ${currentReports.length} rapport(s)</span>`;
    document.getElementById('smMeta').innerHTML = meta;
  }

  document.querySelectorAll('.sm-tab').forEach(tab=>{
    tab.addEventListener('click',()=>{
      activeTab = tab.dataset.tab;
      document.querySelectorAll('.sm-tab').forEach(t=>t.classList.toggle('active',t===tab));
      renderTab(activeTab);
    });
  });

  function renderTab(tab){
    if(tab==='reports') renderReports();
    else renderInfo();
  }

  function renderReports(){
    const body = document.getElementById('smBody');
    if(!currentReports.length){
      body.innerHTML=`<div class="empty-state"><div class="es-icon">📋</div><div class="es-text">Aucun rapport pour ce patient.<br>Utilisez le bouton <strong>Nouveau rapport</strong> ci-dessous.</div></div>`;
      return;
    }
    body.innerHTML = currentReports.map((r,i)=>`
      <div class="report-item">
        <div class="report-item-head" onclick="toggleReport(${i})">
          <div>
            <div class="report-item-title">${esc(r.title)}</div>
            <div class="report-item-meta">📅 ${fmtDT(r.report_date)} · ✍️ ${esc(r.author||'—')}</div>
          </div>
          <div style="display:flex;align-items:center;gap:6px;">
            <button class="btn secondary" style="font-size:11px;padding:4px 10px;" onclick="editReport(event,${i})">✏️</button>
            ${IS_ADMIN||true?`<button class="btn danger" style="font-size:11px;padding:4px 10px;" onclick="deleteReport(event,${r.id})">🗑</button>`:''}
            <span class="report-chevron" id="chev-${i}">▼</span>
          </div>
        </div>
        <div class="report-item-body" id="rbody-${i}"><div class="report-rich-body">${reportToHtml(r.body)}</div></div>
      </div>
    `).join('');
  }

  window.toggleReport = function(i){
    const body = document.getElementById('rbody-'+i);
    const chev = document.getElementById('chev-'+i);
    body.classList.toggle('open');
    chev.classList.toggle('open');
  };

  window.editReport = function(e,i){
    e.stopPropagation();
    const r = currentReports[i];
    editingReportId = r.id;
    showNewReportForm(r.title, r.body);
  };

  window.deleteReport = async function(e,id){
    e.stopPropagation();
    if(!confirm('Supprimer ce rapport ?')) return;
    const d = await apiPost({action:'delete_report',id});
    if(!d.ok){alert(d.error);return;}
    await refreshDossier();
  };

  function renderInfo(){
    const p = currentPatient;
    document.getElementById('smBody').innerHTML=`
      <div class="sm-section-title">Informations du patient</div>
      <div class="sm-info-grid">
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Nom</div>
          <input class="inp" id="eiLastName" value="${esc(p.last_name)}">
        </div>
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Prénom</div>
          <input class="inp" id="eiFirstName" value="${esc(p.first_name)}">
        </div>
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Date de naissance</div>
          <input class="inp" type="date" id="eiBirthDate" value="${esc(p.birth_date||'')}">
        </div>
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Téléphone</div>
          <input class="inp" id="eiPhone" value="${esc(p.phone||'')}">
        </div>
      </div>
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Notes médicales</div>
      <textarea class="inp" id="eiNotes" rows="4" style="resize:vertical;margin-bottom:12px;">${esc(p.notes||'')}</textarea>
      <div style="display:flex;justify-content:flex-end;">
        <button class="btn success" id="btnSavePatient">💾 Enregistrer</button>
      </div>
    `;
    document.getElementById('btnSavePatient').addEventListener('click',async()=>{
      const d=await apiPost({action:'update_patient',id:currentPatientId,
        last_name:document.getElementById('eiLastName').value.trim(),
        first_name:document.getElementById('eiFirstName').value.trim(),
        birth_date:document.getElementById('eiBirthDate').value||null,
        phone:document.getElementById('eiPhone').value.trim()||null,
        notes:document.getElementById('eiNotes').value.trim()||null});
      if(!d.ok){alert(d.error);return;}
      await refreshDossier();
      activeTab='reports';
      document.querySelectorAll('.sm-tab').forEach(t=>t.classList.toggle('active',t.dataset.tab==='reports'));
      renderTab('reports');
    });
  }

  function showNewReportForm(titleVal='', bodyVal=''){
    const body = document.getElementById('smBody');
    const isEdit = editingReportId !== null;
    body.innerHTML=`
      <div class="new-report-form">
        <div class="nr-title">${isEdit?'✏️ Modifier le rapport':'📝 Nouveau rapport'}</div>
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Titre du rapport *</div>
        <input class="inp" id="nrTitle" placeholder="Ex: Consultation du 3 avril 2026" style="margin-bottom:10px;" value="${esc(titleVal)}">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Date</div>
        <input class="inp" type="datetime-local" id="nrDate" style="margin-bottom:10px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);">Compte-rendu *</div>
          <button class="mic-btn-sm" id="micBtn" title="Dicter le rapport">🎙️</button>
        </div>
        <div id="micLive" style="display:none;font-size:12px;color:var(--accent);font-style:italic;padding:6px 10px;border:1px dashed rgba(59,158,255,.2);border-radius:8px;margin-bottom:6px;min-height:28px;"></div>
        <div class="rich-editor-wrap">
          <div id="nrToolbar">
            <span class="ql-formats"><select class="ql-header"><option selected></option><option value="1">Titre 1</option><option value="2">Titre 2</option><option value="3">Titre 3</option></select></span>
            <span class="ql-formats"><button class="ql-bold"></button><button class="ql-italic"></button><button class="ql-underline"></button><button class="ql-strike"></button></span>
            <span class="ql-formats"><select class="ql-color"></select><select class="ql-background"></select></span>
            <span class="ql-formats"><button class="ql-list" value="ordered"></button><button class="ql-list" value="bullet"></button><button class="ql-blockquote"></button></span>
            <span class="ql-formats"><select class="ql-align"></select></span>
            <span class="ql-formats"><button class="ql-link"></button><button class="ql-clean"></button></span>
          </div>
          <div id="nrEditor"></div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
          <button class="btn secondary" id="btnCancelReport">Annuler</button>
          <button class="btn success" id="btnSaveReport">${isEdit?'💾 Mettre à jour':'💾 Enregistrer le rapport'}</button>
        </div>
      </div>
    `;

    reportQuill = new Quill('#nrEditor', { theme: 'snow', placeholder: 'Compte-rendu de la consultation…', modules: { toolbar: '#nrToolbar' } });
    reportQuill.clipboard.dangerouslyPasteHTML(reportToHtml(bodyVal));

    const now = new Date();
    const pad = n=>String(n).padStart(2,'0');
    document.getElementById('nrDate').value = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;

    document.getElementById('btnCancelReport').addEventListener('click',()=>{
      editingReportId=null; finalTranscript=''; stopMic();
      renderTab('reports');
    });

    document.getElementById('btnSaveReport').addEventListener('click',async()=>{
      stopMic();
      const title = document.getElementById('nrTitle').value.trim();
      const rawHtml = reportQuill ? reportQuill.root.innerHTML : '';
      const plain   = reportQuill ? reportQuill.getText().trim() : '';
      const body2   = cleanReportHtml(rawHtml);
      if(!title||!plain){alert('Titre et compte-rendu requis.');return;}
      let d;
      if(editingReportId){
        d = await apiPost({action:'update_report',id:editingReportId,title,body:body2});
      } else {
        d = await apiPost({action:'add_report',patient_id:currentPatientId,title,body:body2,
          report_date:document.getElementById('nrDate').value});
      }
      if(!d.ok){alert(d.error);return;}
      editingReportId=null; finalTranscript='';
      await refreshDossier();
      activeTab='reports';
      document.querySelectorAll('.sm-tab').forEach(t=>t.classList.toggle('active',t.dataset.tab==='reports'));
      renderTab('reports');
    });

    initMic();
  }

  function initMic(){
    const SR = window.SpeechRecognition||window.webkitSpeechRecognition;
    const micBtn = document.getElementById('micBtn');
    if(!SR||!micBtn) return;

    micBtn.addEventListener('click',()=>{
      if(isListening){ stopMic(); return; }
      finalTranscript = reportQuill ? reportQuill.getText() : '';
      recognition = new SR();
      recognition.continuous=true; recognition.interimResults=true; recognition.lang='fr-FR';
      recognition.onstart=()=>{ isListening=true; micBtn.classList.add('listening'); micBtn.textContent='⏹️'; document.getElementById('micLive').style.display=''; };
      recognition.onresult=(e)=>{
        let interim='';
        for(let i=e.resultIndex;i<e.results.length;i++){
          const t=e.results[i][0].transcript;
          if(e.results[i].isFinal) finalTranscript+=t+' '; else interim+=t;
        }
        if(reportQuill){ reportQuill.setText(finalTranscript); reportQuill.setSelection(reportQuill.getLength(),0); }
        const ml=document.getElementById('micLive'); if(ml) ml.textContent=interim||'…';
      };
      recognition.onerror=(e)=>{ if(e.error==='not-allowed'){alert('Microphone refusé.');stopMic();} };
      recognition.onend=()=>{ if(isListening) recognition.start(); };
      try{ recognition.start(); } catch(e){ console.warn(e); }
    });
  }

  function stopMic(){
    isListening=false;
    if(recognition) try{recognition.stop();}catch(e){}
    const micBtn=document.getElementById('micBtn');
    if(micBtn){micBtn.classList.remove('listening');micBtn.textContent='🎙️';}
    const ml=document.getElementById('micLive'); if(ml) ml.style.display='none';
  }

  document.getElementById('fabNewReport').addEventListener('click',()=>{
    if(!currentPatientId) return;
    editingReportId=null; finalTranscript='';
    document.querySelectorAll('.sm-tab').forEach(t=>t.classList.remove('active'));
    showNewReportForm();
  });

  function closeModal(){
    stopMic();
    document.getElementById('modalBackdrop').classList.remove('open');
    document.getElementById('fabNewReport').classList.remove('visible');
    document.body.style.overflow='';
    currentPatientId=null; currentPatient=null; editingReportId=null; finalTranscript='';
    activeTab='reports';
    loadList(document.getElementById('searchInput').value);
  }
  document.getElementById('btnCloseModal').addEventListener('click',closeModal);
  document.getElementById('modalBackdrop').addEventListener('click',e=>{ if(e.target===document.getElementById('modalBackdrop')) closeModal(); });
  document.addEventListener('keydown',e=>{ if(e.key==='Escape'&&document.getElementById('modalBackdrop').classList.contains('open')) closeModal(); });

  document.getElementById('btnDeletePatient')?.addEventListener('click',async()=>{
    if(!confirm('Supprimer ce patient et tous ses rapports ?')) return;
    const d=await apiPost({action:'delete_patient',id:currentPatientId});
    if(!d.ok){alert(d.error);return;}
    closeModal();
  });

  async function refreshDossier(){
    const d=await apiGet({action:'get',id:currentPatientId});
    if(d.ok){ currentPatient=d.patient; currentReports=d.reports||[]; renderModalHead(); }
  }

  let st;
  document.getElementById('searchInput').addEventListener('input',function(){ clearTimeout(st); st=setTimeout(()=>loadList(this.value),300); });

  loadList();
})();
</script>
<?php
