<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';

require_login();
$u       = current_user();
$isAdmin = ($u['role'] ?? '') === 'admin';
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

samr_layout_start('SAMR — Suivi traumatologique');
?>
<style>
.trauma-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;}
.trauma-header h1{font-family:var(--font-head);font-size:20px;font-weight:800;margin:0;}
.patient-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;}
.letter-filter{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px;}
.letter-filter button{min-width:34px;border-radius:10px;border:1px solid var(--line2);background:rgba(255,255,255,.03);color:var(--muted);font-family:var(--font-head);font-size:12px;font-weight:800;padding:7px 9px;cursor:pointer;transition:all .12s;}
.letter-filter button:hover{border-color:rgba(59,158,255,.35);color:var(--accent);}
.letter-filter button.active{background:rgba(59,158,255,.14);border-color:rgba(59,158,255,.5);color:var(--accent);}
.patient-card{background:linear-gradient(160deg,rgba(13,18,25,.95),rgba(10,15,21,.98));border:1px solid var(--line);border-radius:14px;overflow:hidden;cursor:pointer;transition:border-color .15s,transform .12s;}
.patient-card:hover{border-color:rgba(59,158,255,.3);transform:translateY(-2px);}
.pcard-top{display:flex;align-items:center;gap:12px;padding:14px;}
.pcard-photo{width:46px;height:46px;border-radius:10px;flex-shrink:0;background:rgba(59,158,255,.1);border:1px solid rgba(59,158,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px;overflow:hidden;}
.pcard-photo img{width:100%;height:100%;object-fit:cover;}
.pcard-name{font-family:var(--font-head);font-size:14px;font-weight:700;margin-bottom:2px;}
.pcard-dob{font-size:11px;color:var(--muted);}
.survival-bar-wrap{padding:0 14px 14px;}
.survival-label{display:flex;justify-content:space-between;align-items:center;font-size:11px;margin-bottom:5px;}
.survival-pct{font-family:var(--font-head);font-size:15px;font-weight:800;color:#fff;}
.survival-bar{height:8px;border-radius:4px;background:var(--line);overflow:hidden;}
.survival-fill{height:100%;border-radius:4px;transition:width .5s;}
.surv-high .survival-fill{background:var(--accent2);}
.surv-med  .survival-fill{background:#ffb060;}
.surv-low  .survival-fill{background:#ff7a7a;}
.surv-crit .survival-fill{background:#ff4f4f;}

/* Patient décédé */
.patient-card.deceased{background:linear-gradient(160deg,rgba(10,10,10,.98),rgba(5,5,5,.99)) !important;border-color:rgba(80,80,80,.35) !important;opacity:.72;filter:grayscale(0.55);}
.patient-card.deceased .pcard-name{color:#777;}
.patient-card.deceased .survival-pct{color:#555;}
.patient-card.deceased .survival-fill{background:#444 !important;}
.deceased-badge{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#777;background:rgba(0,0,0,.5);border:1px solid rgba(80,80,80,.3);border-radius:999px;padding:2px 9px;margin:0 14px 10px;}

/* Formulaire nouveau patient */
.new-patient-form{display:none;background:linear-gradient(160deg,rgba(13,18,25,.98),rgba(8,12,16,.98));border:1px solid var(--line2);border-radius:14px;padding:18px;margin-bottom:16px;}
.new-patient-form.open{display:block;}
.nf-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;}
@media(max-width:600px){.nf-grid{grid-template-columns:1fr;}}

/* Dossier */
.dossier-grid{display:grid;grid-template-columns:300px 1fr;gap:16px;align-items:start;}
@media(max-width:900px){.dossier-grid{grid-template-columns:1fr;}}
.dossier-sidebar{position:sticky;top:16px;}

/* Corps */
.body-diagram{background:rgba(255,255,255,.02);border:1px solid var(--line);border-radius:12px;padding:12px;margin-bottom:12px;text-align:center;}
.body-diagram svg{max-width:180px;}
.body-zone{cursor:pointer;fill:rgba(59,158,255,.1);stroke:rgba(59,158,255,.3);stroke-width:1.5;transition:fill .12s,stroke .12s;}
.body-zone:hover{fill:rgba(59,158,255,.3);stroke:rgba(59,158,255,.7);}
.body-zone.vital{fill:rgba(255,79,79,.08);stroke:rgba(255,79,79,.25);}
.body-zone.vital:hover{fill:rgba(255,79,79,.28);stroke:rgba(255,79,79,.65);}

/* Zone pills avec compteur */
.zone-counter-row{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;}
.zone-chip{display:inline-flex;align-items:center;gap:0;border-radius:999px;border:1px solid var(--line2);overflow:hidden;font-size:12px;}
.zone-chip-label{padding:4px 10px;color:var(--text);background:rgba(255,79,79,.08);border-right:1px solid rgba(255,79,79,.2);}
.zone-chip-minus,.zone-chip-plus{width:24px;height:100%;display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,.04);cursor:pointer;font-size:13px;font-weight:700;border:none;color:var(--muted);font-family:var(--font-body);transition:background .1s;}
.zone-chip-minus:hover{background:rgba(255,79,79,.15);color:#ff7a7a;}
.zone-chip-plus:hover{background:rgba(59,158,255,.15);color:var(--accent);}
.zone-chip-count{padding:0 8px;font-weight:800;color:#ff7a7a;min-width:20px;text-align:center;}

/* Historique */
.injury-row{display:flex;gap:10px;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--line);}
.injury-row:last-child{border:none;}
.injury-date{font-size:11px;color:var(--muted);white-space:nowrap;width:90px;flex-shrink:0;padding-top:2px;}
.inj-zone{font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;background:rgba(255,79,79,.1);border:1px solid rgba(255,79,79,.3);color:#ff7a7a;}
.inj-type{font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;background:rgba(59,158,255,.1);border:1px solid rgba(59,158,255,.3);color:#3b9eff;}

@media print{
  .trauma-header .btn,.dossier-sidebar .btn,.dossier-sidebar .body-diagram,.no-print{display:none!important;}
  .dossier-grid{grid-template-columns:1fr;}
}
</style>

<div class="trauma-header">
  <div>
    <h1>🩸 Suivi traumatologique</h1>
    <div style="font-size:13px;color:var(--muted);">Points de vie cumulés — +1.5 HP / jour de récupération</div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <input class="inp" id="searchInput" placeholder="🔍 Rechercher…" style="width:200px;">
    <label class="btn secondary" style="font-size:12px;display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
      <input type="checkbox" id="showDeceasedToggle" style="accent-color:var(--accent);">
      Afficher les décédés
    </label>
    <button class="btn success" id="btnToggleNew">➕ Nouveau patient</button>
  </div>
</div>

<!-- ── Formulaire création inline ── -->
<div class="new-patient-form" id="newPatientForm">
  <div style="font-family:var(--font-head);font-size:14px;font-weight:700;margin-bottom:12px;">Nouveau patient</div>
  <div class="nf-grid">
    <div>
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Nom</div>
      <input class="inp" id="nfLastName" placeholder="Nom de famille">
    </div>
    <div>
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Prénom</div>
      <input class="inp" id="nfFirstName" placeholder="Prénom">
    </div>
    <div>
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Date de naissance</div>
      <input class="inp" type="date" id="nfBirthDate">
    </div>
    <div>
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px;">Notes initiales</div>
      <input class="inp" id="nfNotes" placeholder="Optionnel">
    </div>
  </div>
  <div style="display:flex;gap:8px;justify-content:flex-end;">
    <button class="btn secondary" id="btnCancelNew">Annuler</button>
    <button class="btn success" id="btnCreatePatient">✅ Créer le patient</button>
  </div>
</div>

<!-- ── Vue liste ── -->
<div id="viewList">
  <div class="letter-filter" id="letterFilter" aria-label="Filtrer par première lettre du nom"></div>
  <div class="patient-grid" id="patientGrid">
    <div class="muted" style="padding:20px;">Chargement…</div>
  </div>
</div>

<!-- ── Vue dossier ── -->
<div id="viewDossier" style="display:none;">
  <div class="trauma-header no-print" style="margin-bottom:16px;">
    <button class="btn secondary" id="btnBack">← Retour</button>
    <div style="display:flex;gap:8px;">
      <button class="btn secondary" onclick="window.print()" style="font-size:12px;">🖨️ Imprimer</button>
      <button class="btn" id="btnDeclareDeceased" style="font-size:12px;border-color:rgba(80,80,80,.4);background:rgba(20,20,20,.6);color:#888;">💀 Déclarer décédé</button>
      <?php if($isAdmin): ?><button class="btn danger" id="btnDeletePatient" style="font-size:12px;">🗑 Supprimer</button><?php endif; ?>
    </div>
  </div>

  <div class="dossier-grid">
    <!-- Sidebar -->
    <div class="dossier-sidebar">

      <!-- Fiche résumé -->
      <div class="card" style="padding:16px;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
          <div class="pcard-photo" id="dossierPhoto" style="width:64px;height:64px;font-size:26px;">👤</div>
          <div>
            <div style="font-family:var(--font-head);font-size:16px;font-weight:800;" id="dossierName">—</div>
            <div style="font-size:12px;color:var(--muted);" id="dossierDob">—</div>
          </div>
        </div>
        <div style="margin-bottom:14px;" id="dossierSurvWrap">
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:6px;">Points de vie</div>
          <div class="survival-label"><span style="font-size:12px;color:var(--muted);">HP actuels</span><span class="survival-pct" id="dossierSurvPct">100 HP</span></div>
          <div class="survival-bar"><div class="survival-fill" id="dossierSurvFill" style="width:100%"></div></div>
        </div>
        <div class="no-print" style="margin-bottom:10px;">
          <input type="file" id="photoInput" accept="image/*" style="display:none">
          <button class="btn secondary" style="width:100%;font-size:12px;" onclick="document.getElementById('photoInput').click()">📷 Changer la photo</button>
        </div>
        <div class="no-print">
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:8px;">Informations</div>
          <input class="inp" id="editLastName"  placeholder="Nom"    style="margin-bottom:6px;">
          <input class="inp" id="editFirstName" placeholder="Prénom" style="margin-bottom:6px;">
          <input class="inp" type="date" id="editBirthDate"          style="margin-bottom:6px;">
          <textarea class="inp" id="editNotes" rows="2" style="resize:none;margin-bottom:8px;" placeholder="Notes médicales…"></textarea>
          <button class="btn success" style="width:100%;font-size:12px;" id="btnSavePatient">💾 Enregistrer</button>
        </div>
      </div>

      <!-- Enregistrer blessure -->
      <div class="card no-print" style="padding:16px;">
        <div style="font-family:var(--font-head);font-size:13px;font-weight:700;margin-bottom:12px;">🩸 Enregistrer une blessure</div>

        <!-- Type de trauma -->
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:6px;">Type de trauma</div>
        <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px;" id="traumaTypePills">
          <?php foreach(['Blessure par balle','Coup de couteau','Amputation','Autre'] as $tt): ?>
          <button class="zone-pill" style="border-radius:999px;border:1px solid var(--line2);background:transparent;color:var(--muted);font-size:11px;font-weight:700;padding:5px 11px;cursor:pointer;font-family:var(--font-body);transition:all .12s;"
            data-trauma="<?= h($tt) ?>"><?= h($tt) ?></button>
          <?php endforeach; ?>
        </div>
        <div id="traumaAutreWrap" style="display:none;margin-bottom:10px;">
          <input class="inp" id="traumaAutreInput" placeholder="Préciser le type…" style="font-size:12px;">
        </div>

        <!-- Gravité -->
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:6px;">Gravité</div>
        <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px;" id="traumaSeverityPills">
          <?php foreach(['Légère','Moyenne','Grave'] as $sv): ?>
          <button class="zone-pill" style="border-radius:999px;border:1px solid var(--line2);background:transparent;color:var(--muted);font-size:11px;font-weight:700;padding:5px 11px;cursor:pointer;font-family:var(--font-body);transition:all .12s;"
            data-severity="<?= h($sv) ?>"><?= h($sv) ?></button>
          <?php endforeach; ?>
        </div>

        <!-- Corps cliquable -->
        <div class="body-diagram">
          <svg viewBox="0 0 120 280" xmlns="http://www.w3.org/2000/svg">
            <ellipse class="body-zone vital" data-zone="tete"          cx="60" cy="22" rx="18" ry="20"/>
            <rect    class="body-zone vital" data-zone="tete"          x="53" y="40"  width="14" height="10" rx="3"/>
            <rect    class="body-zone vital" data-zone="thorax"        x="35" y="52"  width="50" height="55" rx="5"/>
            <ellipse class="body-zone"       data-zone="epaule_gauche" cx="24" cy="65" rx="12" ry="14"/>
            <ellipse class="body-zone"       data-zone="epaule_droite" cx="96" cy="65" rx="12" ry="14"/>
            <rect    class="body-zone"       data-zone="bras_gauche"   x="12" y="79"  width="14" height="45" rx="5"/>
            <rect    class="body-zone"       data-zone="bras_droit"    x="94" y="79"  width="14" height="45" rx="5"/>
            <rect    class="body-zone"       data-zone="bras_gauche"   x="10" y="128" width="12" height="35" rx="4"/>
            <rect    class="body-zone"       data-zone="bras_droit"    x="98" y="128" width="12" height="35" rx="4"/>
            <rect    class="body-zone"       data-zone="thorax"        x="36" y="110" width="48" height="30" rx="4"/>
            <rect    class="body-zone"       data-zone="jambe_gauche"  x="37" y="143" width="20" height="60" rx="5"/>
            <rect    class="body-zone"       data-zone="jambe_droite"  x="63" y="143" width="20" height="60" rx="5"/>
            <rect    class="body-zone"       data-zone="jambe_gauche"  x="38" y="207" width="18" height="45" rx="4"/>
            <rect    class="body-zone"       data-zone="jambe_droite"  x="64" y="207" width="18" height="45" rx="4"/>
            <text x="60" y="24" text-anchor="middle" font-size="6"   fill="#dce8f5" pointer-events="none">Tête</text>
            <text x="60" y="80" text-anchor="middle" font-size="6.5" fill="#dce8f5" pointer-events="none">Thorax</text>
            <text x="24" y="66" text-anchor="middle" font-size="5.5" fill="#dce8f5" pointer-events="none">Ép.G</text>
            <text x="96" y="66" text-anchor="middle" font-size="5.5" fill="#dce8f5" pointer-events="none">Ép.D</text>
            <text x="19" y="105" text-anchor="middle" font-size="5.5" fill="#dce8f5" pointer-events="none">Bras G</text>
            <text x="101" y="105" text-anchor="middle" font-size="5.5" fill="#dce8f5" pointer-events="none">Bras D</text>
            <text x="47" y="175" text-anchor="middle" font-size="5.5" fill="#dce8f5" pointer-events="none">Jambe G</text>
            <text x="73" y="175" text-anchor="middle" font-size="5.5" fill="#dce8f5" pointer-events="none">Jambe D</text>
          </svg>
        </div>

        <!-- Compteurs de zones -->
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:6px;">Zones touchées</div>
        <div class="zone-counter-row" id="zoneCounters">
          <span style="font-size:11px;color:var(--muted);font-style:italic;">Cliquez sur le schéma</span>
        </div>

        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin:10px 0 6px;">Cas particuliers</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">
          <button type="button" class="btn secondary" style="font-size:11px;padding:5px 10px;" data-special-zone="balles_gpb">🔫 Balles GPB</button>
          <button type="button" class="btn secondary" style="font-size:11px;padding:5px 10px;" data-special-zone="avp">🚗 AVP</button>
        </div>

        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:6px;">Date de la blessure</div>
        <input class="inp" type="datetime-local" id="injuryDate" style="margin-bottom:8px;">

        <textarea class="inp" id="injuryNote" rows="2" style="resize:none;margin-bottom:8px;" placeholder="Note (optionnel)…"></textarea>
        <div id="injuryPreview" style="display:none;padding:8px 10px;border-radius:8px;background:rgba(255,79,79,.08);border:1px solid rgba(255,79,79,.2);font-size:12px;color:#ff7a7a;margin-bottom:8px;"></div>
        <button class="btn danger" style="width:100%;" id="btnAddInjury">🩸 Enregistrer la blessure</button>
      </div>

    </div>

    <!-- Historique -->
    <div>
      <div class="card">
        <div style="font-family:var(--font-head);font-size:14px;font-weight:700;margin-bottom:14px;">📋 Historique des blessures</div>
        <div id="injuryList"><div class="muted" style="padding:10px 0;">Aucune blessure enregistrée.</div></div>
      </div>
      <div class="card" id="notesCard" style="display:none;">
        <div style="font-family:var(--font-head);font-size:14px;font-weight:700;margin-bottom:8px;">📝 Notes médicales</div>
        <div id="notesDisplay" style="font-size:13px;line-height:1.7;white-space:pre-wrap;color:var(--muted);"></div>
      </div>

      <!-- Générateur ticket staff -->
      <div class="card" id="ticketGenCard" style="display:none;border-color:rgba(255,79,79,.3);background:linear-gradient(160deg,rgba(20,10,10,.98),rgba(13,8,8,.98));">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
          <div>
            <div style="font-family:var(--font-head);font-size:14px;font-weight:800;color:#ff7a7a;">⚠️ Ticket staff requis</div>
            <div style="font-size:11px;color:var(--muted);margin-top:2px;">HP critique — demande d'ATA recommandée</div>
          </div>
          <button class="btn" id="btnGenTicket" style="border-color:rgba(255,79,79,.4);background:rgba(255,79,79,.1);color:#ff7a7a;font-weight:700;">
            📋 Générer le ticket
          </button>
        </div>
        <div id="ticketOutput" style="display:none;">
          <textarea id="ticketText" rows="16" style="width:100%;background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:12px;color:#dce8f5;font-family:'DM Sans',sans-serif;font-size:12px;line-height:1.7;resize:vertical;" readonly></textarea>
          <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">
            <button class="btn success" id="btnCopyTicket" style="flex:1;">📋 Copier</button>
            <button class="btn secondary" id="btnRegenTicket" style="font-size:12px;">🔄 Regénérer</button>
          </div>
          <div id="copyConfirm" style="display:none;text-align:center;font-size:12px;color:var(--accent2);margin-top:6px;">✅ Copié dans le presse-papier !</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const CSRF    = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
  const IS_ADMIN= <?= json_encode($isAdmin) ?>;
  const ZONE_LABELS = {tete:'Tête',thorax:'Torse',epaule_gauche:'Épaule G.',epaule_droite:'Épaule D.',bras_gauche:'Bras G.',bras_droit:'Bras D.',jambe_gauche:'Jambe G.',jambe_droite:'Jambe D.',balles_gpb:'Balles GPB',avp:'AVP'};
  const ZONE_PENALTIES = {tete:5,thorax:5,epaule_gauche:2,epaule_droite:2,bras_gauche:2,bras_droit:2,jambe_gauche:2,jambe_droite:2,balles_gpb:2,avp:2};
  const SEVERITY_PENALTIES = {'Légère':0,'Moyenne':5,'Grave':10};
  let selectedSeverity = 'Moyenne';
  const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
  let selectedLetter = 'A';

  let currentPatientId = null;
  let zoneCounts = {};
  let selectedTraumaType = 'Blessure par balle';

  function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
  function survClass(p){if(p>=80)return 'surv-high';if(p>=60)return 'surv-med';if(p>=40)return 'surv-low';return 'surv-crit';}
  function fmtDate(s){if(!s)return '—';return new Date(s).toLocaleDateString('fr-FR',{day:'2-digit',month:'2-digit',year:'numeric'});}
  function fmtDT(s){if(!s)return '—';return new Date(s).toLocaleString('fr-FR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});}
  function toDateTimeLocalValue(date){
    const pad = n => String(n).padStart(2,'0');
    return date.getFullYear() + '-'
      + pad(date.getMonth()+1) + '-'
      + pad(date.getDate()) + 'T'
      + pad(date.getHours()) + ':'
      + pad(date.getMinutes());
  }

  async function api(payload){
    const r=await fetch('/api/trauma.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({...payload,csrf:CSRF})});
    const d=await r.json().catch(()=>null);
    if(!r.ok||!d?.ok) throw new Error(d?.error||'Erreur API');
    return d;
  }

  const newForm = document.getElementById('newPatientForm');
  document.getElementById('btnToggleNew').addEventListener('click',()=>{
    newForm.classList.toggle('open');
    if(newForm.classList.contains('open')) document.getElementById('nfLastName').focus();
  });
  document.getElementById('btnCancelNew').addEventListener('click',()=>newForm.classList.remove('open'));
  document.getElementById('btnCreatePatient').addEventListener('click', async()=>{
    const ln=document.getElementById('nfLastName').value.trim();
    const fn=document.getElementById('nfFirstName').value.trim();
    if(!ln||!fn){alert('Nom et prénom requis.');return;}
    const d=await api({action:'create_patient',last_name:ln,first_name:fn,birth_date:document.getElementById('nfBirthDate').value,notes:document.getElementById('nfNotes').value.trim()});
    newForm.classList.remove('open');
    document.getElementById('nfLastName').value='';document.getElementById('nfFirstName').value='';document.getElementById('nfBirthDate').value='';document.getElementById('nfNotes').value='';
    openDossier(d.id);
  });

  function renderLetterFilter(){
    const wrap = document.getElementById('letterFilter');
    if(!wrap) return;
    wrap.innerHTML = ALPHABET.map(l => `<button type="button" data-letter="${l}" class="${l===selectedLetter?'active':''}">${l}</button>`).join('');
    wrap.querySelectorAll('[data-letter]').forEach(btn=>{
      btn.addEventListener('click',()=>{
        selectedLetter = btn.dataset.letter;
        renderLetterFilter();
        loadList(document.getElementById('searchInput').value);
      });
    });
  }

  async function loadList(q=''){
    const showDeceased = document.getElementById('showDeceasedToggle')?.checked ? '1' : '0';
    const params = new URLSearchParams({
      action: 'list',
      q: q || '',
      letter: selectedLetter,
      include_deceased: showDeceased
    });
    const r=await fetch('/api/trauma.php?'+params.toString(),{credentials:'same-origin'});
    const d=await r.json().catch(()=>null);
    const grid=document.getElementById('patientGrid');
    if(!d||!d.patients?.length){grid.innerHTML='<div class="muted" style="padding:20px;grid-column:1/-1;">Aucun patient pour la lettre '+esc(selectedLetter)+'.</div>';return;}
    grid.innerHTML='';
    for(const p of d.patients){
      const pct=parseFloat(p.survival);
      const cls=survClass(pct);
      const photo=p.photo_path?'<img src="'+esc(p.photo_path)+'" alt="">':'👤';
      const card=document.createElement('div');
      const isDeceased = parseInt(p.is_deceased||0) === 1;
      card.className='patient-card'+(isDeceased?' deceased':'');
      const deceasedBadge = isDeceased ? '<div class="deceased-badge">💀 Décédé</div>' : '';
      card.innerHTML='<div class="pcard-top"><div class="pcard-photo">'+photo+'</div><div><div class="pcard-name">'+esc(p.last_name)+' '+esc(p.first_name)+'</div><div class="pcard-dob">'+(p.birth_date?fmtDate(p.birth_date):'DDN non renseignée')+'</div></div></div>'
        +'<div class="survival-bar-wrap '+cls+'"><div class="survival-label"><span style="font-size:11px;color:var(--muted);">HP</span><span class="survival-pct">'+pct.toFixed(1)+' HP</span></div><div class="survival-bar"><div class="survival-fill" style="width:'+pct+'%"></div></div></div>'
        +deceasedBadge;
      card.addEventListener('click',()=>openDossier(p.id));
      grid.appendChild(card);
    }
  }

  let currentPatient  = null;
  let currentInjuries = [];

  async function openDossier(id){
    currentPatientId=id;
    const r=await fetch(`/api/trauma.php?action=get&id=${id}`,{credentials:'same-origin'});
    const d=await r.json();
    if(!d.ok){alert(d.error);return;}
    const p=d.patient;
    currentPatient  = p;
    currentInjuries = d.injuries||[];
    const pct=parseFloat(p.survival);
    const cls=survClass(pct);
    document.getElementById('dossierName').textContent=p.last_name+' '+p.first_name;
    document.getElementById('dossierDob').textContent=p.birth_date?fmtDate(p.birth_date):'DDN non renseignée';
    document.getElementById('dossierSurvPct').textContent=pct.toFixed(1)+' HP';
    document.getElementById('dossierSurvFill').style.width=pct+'%';
    document.getElementById('dossierSurvWrap').className=cls;
    document.getElementById('dossierPhoto').innerHTML=p.photo_path?'<img src="'+esc(p.photo_path)+'" alt="">':'👤';
    document.getElementById('editLastName').value=p.last_name;
    document.getElementById('editFirstName').value=p.first_name;
    document.getElementById('editBirthDate').value=p.birth_date||'';
    document.getElementById('editNotes').value=p.notes||'';
    const nc=document.getElementById('notesCard');
    if(p.notes){document.getElementById('notesDisplay').textContent=p.notes;nc.style.display='';}
    else nc.style.display='none';

    const ticketCard = document.getElementById('ticketGenCard');
    const ticketOutput = document.getElementById('ticketOutput');
    if(pct <= 25){
      ticketCard.style.display='';
      ticketOutput.style.display='none';
    } else {
      ticketCard.style.display='none';
    }

    renderInjuries(d.injuries||[]);
    zoneCounts={};updateZoneUI();
    const injuryDateInput = document.getElementById('injuryDate');
    if (injuryDateInput) {
      injuryDateInput.value = toDateTimeLocalValue(new Date());
    }
    document.getElementById('viewList').style.display='none';
    document.getElementById('viewDossier').style.display='';
    window.scrollTo(0,0);
  }

  function renderInjuries(injuries){
    const el=document.getElementById('injuryList');
    if(!injuries.length){el.innerHTML='<div class="muted" style="padding:10px 0;">Aucune blessure enregistrée.</div>';return;}
    el.innerHTML=injuries.map(inj=>{
      const zones=inj.zones.split(',').filter(Boolean);
      const counts={};zones.forEach(z=>{counts[z]=(counts[z]||0)+1;});
      const zoneTags=Object.entries(counts).map(([z,c])=>`<span class="inj-zone">${esc(ZONE_LABELS[z.trim()]||z)}${c>1?' ×'+c:''}</span>`).join('');
      return `<div class="injury-row">
        <div class="injury-date">${fmtDT(inj.created_at)}</div>
        <div style="flex:1;">
          <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:4px;">
            <span class="inj-type">${esc(inj.trauma_type||'—')}</span>
            ${inj.severity?`<span class="inj-type">${esc(inj.severity)}</span>`:''}
            ${zoneTags}
          </div>
          ${inj.note?`<div style="font-size:11px;color:var(--muted);font-style:italic;">${esc(inj.note)}</div>`:''}
          <div style="font-size:10px;color:var(--muted);margin-top:2px;">${parseFloat(inj.survival_before).toFixed(1)} HP → ${parseFloat(inj.survival_after).toFixed(1)} HP${inj.created_by_name?' · '+esc(inj.created_by_name):''}</div>
        </div>
        <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
          <span style="font-size:11px;font-weight:700;color:#ff7a7a;">−${parseFloat(inj.penalty).toFixed(0)} HP</span>
          ${IS_ADMIN?`<button class="btn danger" style="padding:4px 8px;font-size:11px;" data-del-inj="${inj.id}">🗑</button>`:''}
        </div>
      </div>`;
    }).join('');
    el.querySelectorAll('[data-del-inj]').forEach(btn=>{
      btn.addEventListener('click',async()=>{if(!confirm('Supprimer cette entrée ?'))return;await api({action:'delete_injury',id:parseInt(btn.dataset.delInj)});openDossier(currentPatientId);});
    });
  }

  document.querySelectorAll('.body-zone').forEach(zone=>{
    zone.addEventListener('click',()=>{
      const z=zone.dataset.zone;
      zoneCounts[z]=(zoneCounts[z]||0)+1;
      updateZoneUI();
    });
  });

  document.querySelectorAll('[data-special-zone]').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const z=btn.dataset.specialZone;
      zoneCounts[z]=(zoneCounts[z]||0)+1;
      updateZoneUI();
    });
  });

  function updateZoneUI(){
    const row=document.getElementById('zoneCounters');
    const preview=document.getElementById('injuryPreview');
    const total=Object.values(zoneCounts).reduce((s,c)=>s+c,0);
    if(!total){row.innerHTML='<span style="font-size:11px;color:var(--muted);font-style:italic;">Cliquez sur le schéma</span>';preview.style.display='none';return;}

    let penalty=0;
    row.innerHTML='';
    for(const [z,count] of Object.entries(zoneCounts)){
      if(!count)continue;
      penalty+=((ZONE_PENALTIES[z]||0)+(SEVERITY_PENALTIES[selectedSeverity]||0))*count;
      const chip=document.createElement('div');
      chip.className='zone-chip';
      chip.innerHTML=`<span class="zone-chip-label">${esc(ZONE_LABELS[z]||z)}</span><button class="zone-chip-minus" data-z="${z}">−</button><span class="zone-chip-count">${count}</span><button class="zone-chip-plus" data-z="${z}">+</button>`;
      chip.querySelector('[data-z].zone-chip-minus').addEventListener('click',()=>{if(zoneCounts[z]>1)zoneCounts[z]--;else delete zoneCounts[z];updateZoneUI();});
      chip.querySelector('[data-z].zone-chip-plus').addEventListener('click',()=>{zoneCounts[z]=(zoneCounts[z]||0)+1;updateZoneUI();});
      row.appendChild(chip);
    }
    preview.style.display='';
    preview.textContent='Dégâts totaux : −'+penalty+' HP ('+selectedSeverity+')';
  }

  document.querySelectorAll('[data-severity]').forEach(btn=>{
    btn.addEventListener('click',()=>{
      selectedSeverity=btn.dataset.severity;
      document.querySelectorAll('[data-severity]').forEach(b=>{
        b.style.borderColor=b===btn?'rgba(59,158,255,.5)':'';
        b.style.color=b===btn?'var(--accent)':'';
        b.style.background=b===btn?'rgba(59,158,255,.1)':'';
      });
      updateZoneUI();
    });
  });
  document.querySelector('[data-severity="Moyenne"]')?.click();

  document.querySelectorAll('[data-trauma]').forEach(btn=>{
    btn.addEventListener('click',()=>{
      selectedTraumaType=btn.dataset.trauma;
      document.querySelectorAll('[data-trauma]').forEach(b=>{
        b.style.borderColor=b===btn?'rgba(59,158,255,.5)':'';
        b.style.color=b===btn?'var(--accent)':'';
        b.style.background=b===btn?'rgba(59,158,255,.1)':'';
      });
      document.getElementById('traumaAutreWrap').style.display=btn.dataset.trauma==='Autre'?'':'none';
    });
  });
  document.querySelector('[data-trauma]').click();

  document.getElementById('btnAddInjury').addEventListener('click',async()=>{
    const zones=[];
    for(const [z,c] of Object.entries(zoneCounts)) for(let i=0;i<c;i++) zones.push(z);
    if(!zones.length){alert('Sélectionne au moins une zone.');return;}
    let traumaType=selectedTraumaType;
    if(traumaType==='Autre'){traumaType=document.getElementById('traumaAutreInput').value.trim()||'Autre';}
    const injuryDate = document.getElementById('injuryDate').value;
    try{
      await api({
        action:'add_injury',
        patient_id:currentPatientId,
        zones,
        trauma_type:traumaType,
        severity:selectedSeverity,
        note:document.getElementById('injuryNote').value.trim(),
        injury_date: injuryDate || null
      });
      document.getElementById('injuryNote').value='';
      document.getElementById('injuryDate').value = toDateTimeLocalValue(new Date());
      zoneCounts={};updateZoneUI();
      openDossier(currentPatientId);
    }catch(e){alert(e.message);}
  });

  document.getElementById('btnSavePatient').addEventListener('click',async()=>{
    try{await api({action:'update_patient',id:currentPatientId,last_name:document.getElementById('editLastName').value.trim(),first_name:document.getElementById('editFirstName').value.trim(),birth_date:document.getElementById('editBirthDate').value,notes:document.getElementById('editNotes').value.trim()});openDossier(currentPatientId);}catch(e){alert(e.message);}
  });

  document.getElementById('photoInput').addEventListener('change',async function(){
    if(!this.files[0])return;
    const fd=new FormData();fd.append('photo',this.files[0]);fd.append('patient_id',currentPatientId);
    const r=await fetch('/api/trauma_upload.php',{method:'POST',credentials:'same-origin',body:fd});
    const d=await r.json();
    if(d.ok)openDossier(currentPatientId);else alert(d.error);
  });

  document.getElementById('btnDeclareDeceased')?.addEventListener('click', async () => {
    if (!currentPatientId) return;
    const name = currentPatient ? currentPatient.last_name + ' ' + currentPatient.first_name : 'ce patient';
    if (!confirm('Déclarer ' + name + ' comme décédé(e) ? Cette action est irréversible.')) return;
    try {
      await api({ action: 'declare_deceased', id: currentPatientId });
      showList();
    } catch(e) { alert(e.message); }
  });

  document.getElementById('btnDeletePatient')?.addEventListener('click',async()=>{
    if(!confirm('Supprimer ce patient et tout son historique ?'))return;
    await api({action:'delete_patient',id:currentPatientId});
    showList();
  });

  function generateTicket(){
    const p = currentPatient;
    const injuries = currentInjuries;
    if(!p) return '';

    const fullName = p.last_name + ' ' + p.first_name;
    const survival = parseFloat(p.survival).toFixed(1);

    const byDay = {};
    for(const inj of injuries){
      const day = new Date(inj.created_at).toLocaleDateString('fr-FR',{weekday:'long',day:'2-digit',month:'long',year:'numeric'});
      if(!byDay[day]) byDay[day]=[];
      const zones = inj.zones.split(',').filter(Boolean);
      const counts={};zones.forEach(z=>{counts[z]=(counts[z]||0)+1;});
      const zonesStr = Object.entries(counts).map(([z,c])=>(ZONE_LABELS[z.trim()]||z)+(c>1?' (×'+c+')':'')).join(', ');
      byDay[day].push((inj.trauma_type||'Blessure')+' — '+zonesStr);
    }

    let lignesBlessures = '';
    for(const [day, events] of Object.entries(byDay)){
      const dayCap = day.charAt(0).toUpperCase()+day.slice(1);
      lignesBlessures += '  • '+dayCap+' :\n';
      events.forEach(e=>{ lignesBlessures += '      – '+e+'\n'; });
    }

    const date = new Date().toLocaleDateString('fr-FR',{day:'2-digit',month:'long',year:'numeric'});

    return `[DEMANDE D'ATA — RAPPORT MÉDICAL SAMR]
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Date du rapport : ${date}
Rédigé par      : Service Médical — SAMR
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

M./Mme ${fullName} s'est présenté(e) aux urgences du SAMR à plusieurs reprises pour des blessures graves par arme.

RÉCAPITULATIF DES INTERVENTIONS :
${lignesBlessures}
ÉTAT ACTUEL :
  HP estimés : ${survival} HP
  Seuil critique atteint (≤ 25 HP). L'état de santé du patient est jugé incompatible avec une nouvelle agression.

RECOMMANDATION MÉDICALE :
  Le Service Médical du SAMR fait formellement la demande d'un ATA (Avis de Traitement Administratif) de 24h minimum à l'encontre de ${fullName}.

  La prochaine blessure grave pourrait entraîner une mort RP définitive.
  Cette demande est motivée par le cumul des interventions médicales et l'état critique du patient.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Service Médical — San Andreas Medical Response
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`;
  }

  document.getElementById('btnGenTicket').addEventListener('click',()=>{
    const text = generateTicket();
    document.getElementById('ticketText').value = text;
    document.getElementById('ticketOutput').style.display='';
    document.getElementById('copyConfirm').style.display='none';
  });

  document.getElementById('btnRegenTicket').addEventListener('click',()=>{
    document.getElementById('ticketText').value = generateTicket();
    document.getElementById('copyConfirm').style.display='none';
  });

  document.getElementById('btnCopyTicket').addEventListener('click',async()=>{
    const text = document.getElementById('ticketText').value;
    try{
      await navigator.clipboard.writeText(text);
    } catch(e){
      const ta = document.getElementById('ticketText');
      ta.select();
      document.execCommand('copy');
    }
    const confirm = document.getElementById('copyConfirm');
    confirm.style.display='';
    setTimeout(()=>{ confirm.style.display='none'; }, 2500);
  });

  function showList(){
    document.getElementById('viewList').style.display='';
    document.getElementById('viewDossier').style.display='none';
    currentPatientId=null;
    loadList(document.getElementById('searchInput').value);
  }
  document.getElementById('btnBack').addEventListener('click',showList);
  let st;
  document.getElementById('searchInput').addEventListener('input',function(){clearTimeout(st);st=setTimeout(()=>loadList(this.value),300);});
  document.getElementById('showDeceasedToggle')?.addEventListener('change',()=>loadList(document.getElementById('searchInput').value));

  renderLetterFilter();
  loadList();
})();
</script>
<?php samr_layout_end(); ?>
