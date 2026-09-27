<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';

require_login();
$u = current_user();
$username = (string)($u['username'] ?? 'Utilisateur');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

samr_layout_start('SAMR - Dispatch');
?>
<div class="dispatch-shell">
  <div class="dispatch-toolbar card">
    <div class="dispatch-toolbar__main">
      <div class="dispatch-title-row">
        <div>
          <h1 class="dispatch-title">🚑 Dispatch SAMR</h1>
          <div class="muted">Drag &amp; drop • Unités (Lincoln/Adam/Tango/Queen) illimitées • Double-clic sur une unité pour renommer</div>
        </div>

        <div class="dispatch-title-actions">
          <button class="btn secondary" id="btnFullscreen">🖥 Mode focus</button>
          <button class="btn secondary" id="btnLayoutMode" title="Réorganiser / redimensionner les colonnes">⚙️ Mise en page</button>
          <button class="btn" id="btnSelfDuty">Se mettre en service</button>
        </div>
      </div>

      <div class="dispatch-toolbar__controls">
        <div class="pill">Connecté : <?= h($username) ?></div>

        <label style="display:flex; gap:8px; align-items:center;">
          <span class="muted" style="font-size:12px;">Statut hôpital</span>
          <select class="inp" id="hospitalStatus">
            <option value="NORMAL">NORMAL</option>
            <option value="DEFCON 5">DEFCON 5</option>
            <option value="DEFCON 4">DEFCON 4</option>
            <option value="DEFCON 3">DEFCON 3</option>
            <option value="DEFCON 2">DEFCON 2</option>
            <option value="DEFCON 1">DEFCON 1</option>
            <option value="CODE ROSE">CODE ROSE</option>
            <option value="PLAN BLANC">PLAN BLANC</option>
          </select>
        </label>

        <div class="pill" id="refreshInfo">Dernière mise à jour : —</div>

        <button class="btn" id="btnNewPerson">➕ Nouvelle carte</button>
        <button class="btn secondary" id="btnNewUnit">➕ Nouvelle unité</button>
      </div>
    </div>

    <div class="dispatch-toolbar__stats" id="serviceSummaryCompact">
      <div class="summary-chip"><div class="summary-chip__label">Actif</div><div class="summary-chip__value" id="countActive">0</div></div>
      <div class="summary-chip"><div class="summary-chip__label">Disponibles</div><div class="summary-chip__value" id="countAvailable">0</div></div>
      <div class="summary-chip"><div class="summary-chip__label">En unité</div><div class="summary-chip__value" id="countGrouped">0</div></div>
      <div class="summary-chip"><div class="summary-chip__label">Inter</div><div class="summary-chip__value" id="countIntervention">0</div></div>
      <div class="summary-chip"><div class="summary-chip__label">10-6</div><div class="summary-chip__value" id="count106">0</div></div>
      <div class="summary-chip"><div class="summary-chip__label">Hors service</div><div class="summary-chip__value" id="countOff">0</div></div>
    </div>
  </div>

  <div class="layout-mode-banner" id="layoutBanner">
    <span>⚙️</span>
    <span><strong>Mode mise en page</strong> — Glisse chaque zone indépendamment • Tire le bord droit pour redimensionner • Les hauteurs restent automatiques</span>
    <button class="btn secondary" id="btnCompactLayout" type="button" style="font-size:12px;">⇤ Compact</button>
    <button class="btn secondary" id="btnDefaultLayout" type="button" style="font-size:12px;">◼ Standard</button>
    <button class="btn secondary" id="btnLargeLayout" type="button" style="font-size:12px;">⇥ Large</button>
    <button class="btn secondary" id="btnToggleSizePanel" type="button" style="font-size:12px;">↔ Tailles</button>
    <button class="btn secondary" id="btnResetLayout" style="margin-left:auto;font-size:12px;">↺ Réinitialiser</button>
    <button class="btn" id="btnExitLayout" style="font-size:12px;">✓ Terminé</button>
  </div>

  <div class="layout-size-panel" id="layoutSizePanel" hidden>
    <div class="layout-size-panel__head">
      <strong>Largeur des colonnes</strong>
      <span class="muted" style="font-size:12px;">Les cartes suivent automatiquement la largeur de leur colonne.</span>
    </div>
    <div class="layout-size-grid" id="layoutSizeGrid"></div>
  </div>

  <div class="board-wrap">
    <div class="board" id="board">
      <div class="col" data-col-id="service_on">
        <div class="col-head">En service</div>
        <div class="col-body people-list compact-list" data-list="on"></div>
      </div>

      <div class="col" data-col-id="service_off">
        <div class="col-head">Hors service</div>
        <div class="col-body people-list compact-list" data-list="off"></div>
      </div>

      <div class="col" data-col-id="lincoln">
        <div class="col-head">Lincoln <span class="muted" style="font-size:12px;">(1 pers)</span></div>
        <div class="col-body group-col compact-group-col" data-type="lincoln"></div>
      </div>

      <div class="col" data-col-id="adam">
        <div class="col-head">Adam <span class="muted" style="font-size:12px;">(2 pers)</span></div>
        <div class="col-body group-col compact-group-col" data-type="adam"></div>
      </div>

      <div class="col" data-col-id="tango">
        <div class="col-head">Tango <span class="muted" style="font-size:12px;">(3 pers)</span></div>
        <div class="col-body group-col compact-group-col" data-type="tango"></div>
      </div>

      <div class="col" data-col-id="queen">
        <div class="col-head">Queen <span class="muted" style="font-size:12px;">(4 pers)</span></div>
        <div class="col-body group-col compact-group-col" data-type="queen"></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal création carte personne -->
<div id="personModal" class="samr-modal" aria-hidden="true">
  <div class="samr-modal__backdrop" data-close="1"></div>
  <div class="samr-modal__panel" role="dialog" aria-modal="true" aria-labelledby="personTitle">
    <div class="samr-modal__head">
      <div>
        <div id="personTitle" style="font-weight:800;">Nouvelle carte</div>
        <div class="muted" style="font-size:13px;">Créée par <b><?= h($username) ?></b></div>
      </div>
      <button class="btn secondary" type="button" data-close="1">✕</button>
    </div>

    <form id="personForm" style="margin-top:12px;">
      <input type="hidden" id="csrf" value="<?= h(csrf_token()) ?>">
      <div class="grid">
        <label class="grid-span-2">
          <div class="muted" style="font-size:12px;margin-bottom:6px;">Nom</div>
          <input class="inp" type="text" id="personName" maxlength="80" placeholder="Ex: Chris Miller" required>
        </label>

        <label>
          <div class="muted" style="font-size:12px;margin-bottom:6px;">Rôle</div>
          <input class="inp" type="text" id="personRole" maxlength="40" placeholder="Médecin / Infirmier / ...">
        </label>

        <label>
          <div class="muted" style="font-size:12px;margin-bottom:6px;">Liste</div>
          <select class="inp" id="personList">
            <option value="on">En service</option>
            <option value="off">Hors service</option>
          </select>
        </label>

        <label class="grid-span-2">
          <div class="muted" style="font-size:12px;margin-bottom:6px;">Hôpital</div>
          <select class="inp" id="personHospitalSite">
            <option value="">Aucun</option>
            <option value="north">🏥 Hôpital Nord</option>
            <option value="south">🏥 Hôpital Sud</option>
          </select>
        </label>

        <div class="grid-span-2" style="display:flex;justify-content:flex-end;gap:10px;margin-top:4px;">
          <button class="btn secondary" type="button" data-close="1">Annuler</button>
          <button class="btn" type="submit">Créer</button>
        </div>
      </div>
    </form>

    <div id="personErr" class="muted" style="margin-top:10px; color:#ffb3b3; display:none;"></div>
  </div>
</div>

<!-- Modal création unité -->
<div id="unitModal" class="samr-modal" aria-hidden="true">
  <div class="samr-modal__backdrop" data-close-unit="1"></div>
  <div class="samr-modal__panel" role="dialog" aria-modal="true" aria-labelledby="unitTitle">
    <div class="samr-modal__head">
      <div>
        <div id="unitTitle" style="font-weight:800;">Nouvelle unité</div>
        <div class="muted" style="font-size:13px;">Ex: Adam 35 / Tango 12 / Lincoln 7 / Queen 01</div>
      </div>
      <button class="btn secondary" type="button" data-close-unit="1">✕</button>
    </div>

    <form id="unitForm" style="margin-top:12px;">
      <div class="grid">
        <label>
          <div class="muted" style="font-size:12px;margin-bottom:6px;">Type</div>
          <select class="inp" id="unitType">
            <option value="lincoln">Lincoln (1)</option>
            <option value="adam" selected>Adam (2)</option>
            <option value="tango">Tango (3)</option>
            <option value="queen">Queen (4)</option>
          </select>
        </label>

        <label>
          <div class="muted" style="font-size:12px;margin-bottom:6px;">Nom</div>
          <input class="inp" type="text" id="unitLabel" maxlength="40" placeholder="Ex: Adam 35" required>
        </label>

        <div class="grid-span-2" style="display:flex;justify-content:flex-end;gap:10px;margin-top:4px;">
          <button class="btn secondary" type="button" data-close-unit="1">Annuler</button>
          <button class="btn" type="submit">Créer</button>
        </div>
      </div>
    </form>

    <div id="unitErr" class="muted" style="margin-top:10px; color:#ffb3b3; display:none;"></div>
  </div>
</div>

<!-- Modal édition personne -->
<div id="editPersonModal" class="samr-modal" aria-hidden="true">
  <div class="samr-modal__backdrop" data-close-edit="1"></div>
  <div class="samr-modal__panel" role="dialog" aria-modal="true" aria-labelledby="editPersonTitle">
    <div class="samr-modal__head">
      <div>
        <div id="editPersonTitle" style="font-weight:800;">Carte</div>
        <div class="muted" style="font-size:13px;">Statut / Intervention / Site</div>
      </div>
      <button class="btn secondary" type="button" data-close-edit="1">✕</button>
    </div>

    <div style="margin-top:12px;">
      <div class="muted" style="font-size:12px;margin-bottom:6px;">Statut</div>
      <input class="inp" type="text" id="editStatus" maxlength="80" placeholder="Ex: RDV / PATROUILLE / PAUSE">

      <div class="muted" style="font-size:12px;margin-top:12px;margin-bottom:6px;">Hôpital</div>
      <select class="inp" id="editHospitalSite">
        <option value="">Aucun</option>
        <option value="north">🏥 Hôpital Nord</option>
        <option value="south">🏥 Hôpital Sud</option>
      </select>

      <div style="margin-top:12px;">
        <div class="muted" style="font-size:12px;margin-bottom:6px;">Formations</div>
        <div id="editFormations" class="modal-formations muted">Aucune formation</div>
      </div>

      <label style="display:flex; align-items:center; gap:10px; margin-top:12px;">
        <input type="checkbox" id="editInter">
        <span>En intervention</span>
      </label>

      <label style="display:flex; align-items:center; gap:10px; margin-top:8px;">
        <input type="checkbox" id="edit106">
        <span>10-6</span>
      </label>

      <label style="display:flex; align-items:center; gap:10px; margin-top:8px;">
        <input type="checkbox" id="editHasRadio">
        <span>Dispatch</span>
      </label>

      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:14px;">
        <button class="btn secondary" type="button" data-close-edit="1">Fermer</button>
        <button class="btn" type="button" id="btnSavePerson">Enregistrer</button>
      </div>

      <div id="editPersonErr" class="muted" style="margin-top:10px; color:#ffb3b3; display:none;"></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<style>
.dispatch-shell{width:100%;}
.dispatch-toolbar{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:16px;
  flex-wrap:wrap;
}
.dispatch-toolbar__main{flex:1 1 760px; min-width:320px;}
.dispatch-title-row{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:16px;
  flex-wrap:wrap;
}
.dispatch-title{margin:0 0 6px 0;}
.dispatch-title-actions,
.dispatch-toolbar__controls{
  display:flex;
  gap:10px;
  align-items:center;
  flex-wrap:wrap;
}
.dispatch-toolbar__controls{margin-top:14px;}
.dispatch-toolbar__stats{
  display:grid;
  grid-template-columns:repeat(6, minmax(96px, 1fr));
  gap:10px;
  flex:0 1 640px;
  min-width:320px;
}
.summary-chip{
  border:1px solid var(--line);
  border-radius:14px;
  padding:10px 12px;
  background:rgba(255,255,255,.03);
}
.summary-chip__label{font-size:12px; opacity:.82; margin-bottom:4px;}
.summary-chip__value{font-size:18px; font-weight:800; line-height:1;}
.board-wrap{ padding:12px 4px 14px 4px; overflow-x:auto; overflow-y:visible; }
.board{
  display: flex;
  flex-direction: row;
  gap: 12px;
  align-items: flex-start;
}
[data-col-id]{
  flex: 0 0 auto;
  min-width: 180px;
  display: flex;
  flex-direction: column;
  position: relative;
}
.layout-mode-banner{
  display:none; align-items:center; gap:10px;
  padding:9px 14px; margin-bottom:10px;
  background:rgba(255,180,50,.07); border:1px solid rgba(255,180,50,.28);
  border-radius:12px; font-size:13px; color:#ffd080;
}
.layout-mode-banner.visible{ display:flex; }
.layout-mode-banner strong{ font-weight:700; }
.layout-size-panel{
  display:none;
  margin:0 0 12px 0;
  padding:12px 14px;
  border:1px solid rgba(255,180,50,.2);
  border-radius:14px;
  background:rgba(255,255,255,.03);
}
.layout-size-panel.visible{display:block;}
.layout-size-panel__head{display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:10px;}
.layout-size-grid{display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px 14px;}
.layout-size-item{display:flex; flex-direction:column; gap:6px;}
.layout-size-item label{display:flex; justify-content:space-between; gap:10px; align-items:center; font-size:12px; opacity:.95;}
.layout-size-item input[type="range"]{width:100%;}
.layout-size-item output{font-weight:700; min-width:52px; text-align:right;}
.board.layout-mode [data-col-id]{
  outline:2px dashed rgba(255,180,50,.38); outline-offset:3px; border-radius:16px;
}
.board.layout-mode [data-col-id].sortable-chosen{
  outline-color:rgba(59,158,255,.7); box-shadow:0 0 0 3px rgba(59,158,255,.12); z-index:20;
}
.board.layout-mode [data-col-id].sortable-ghost{ opacity:.4; }
.col-drag-indicator{ display:none; font-size:15px; opacity:.45; margin-right:6px; cursor:grab; flex-shrink:0; }
.board.layout-mode .col-drag-indicator{ display:inline-block; }
.col-resize-w{
  display:none; position:absolute; top:0; right:-6px; width:12px; height:100%;
  cursor:col-resize; z-index:30;
}
.col-resize-w::after{
  content:''; position:absolute; top:15%; bottom:15%; left:5px;
  width:2px; border-radius:2px; background:rgba(255,180,50,.55); transition:background .15s;
}
.col-resize-w:hover::after{ background:rgba(255,180,50,.95); }
.col-resize-h{
  display:none; position:absolute; bottom:-6px; left:0; width:100%; height:12px;
  cursor:row-resize; z-index:30;
}
.col-resize-h::after{
  content:''; position:absolute; left:15%; right:15%; top:5px;
  height:2px; border-radius:2px; background:rgba(255,180,50,.55); transition:background .15s;
}
.col-resize-h:hover::after{ background:rgba(255,180,50,.95); }
.board.layout-mode .col-resize-w,
.board.layout-mode .col-resize-h{ display:block; }
[data-col-id].col-drag-over{ outline-color:rgba(59,158,255,.8) !important; background:rgba(59,158,255,.04); }
.col{
  background:rgba(255,255,255,.03);
  border:1px solid var(--line);
  border-radius:16px;
  padding:10px;
  display:flex;
  flex-direction:column;
  flex:0 0 auto;
  min-height:unset;
}
.col-stack{
  display:flex;
  flex-direction:column;
  gap:12px;
}
.compact-list{
  min-height: calc(var(--base-visible-cards, 8) * 76px);
}
.compact-group-col{
  min-height: 210px;
}
.col-head{
  font-weight:800;
  margin-bottom:10px;
  display:flex;
  justify-content:space-between;
  align-items:center;
}
.col-body{
  flex:0 0 auto;
  min-height:0;
  overflow:visible;
  display:flex;
  flex-direction:column;
  gap:10px;
}
.group{border:1px solid var(--line); border-radius:16px; background:rgba(255,255,255,.02); padding:10px;}
.group-head{display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:8px;}
.group-title{font-weight:800; cursor:pointer;}
.group-title::after{content:"✎"; font-size:12px; opacity:.6; margin-left:8px;}
.group-meta{display:flex; gap:8px; align-items:center;}
.group-cap{font-size:11px; padding:3px 8px; border-radius:999px; border:1px solid var(--line); opacity:.9;}
.group-actions{display:flex; gap:6px;}
.iconbtn{border:1px solid var(--line); background:rgba(255,255,255,.03); color:var(--text); border-radius:10px; padding:6px 8px; cursor:pointer;}
.iconbtn:hover{background:rgba(255,255,255,.06);}
.member-list{display:flex; flex-direction:column; gap:8px; min-height:12px;}
.person{border-radius:14px; border:1px solid var(--line); padding:10px 10px; cursor:grab; user-select:none;}
.person-head{display:flex; align-items:flex-start; justify-content:space-between; gap:8px;}
.person-main-wrap{display:flex; align-items:flex-start; gap:10px; min-width:0; flex:1;}
.person-avatar{
  width:42px;
  height:42px;
  border-radius:12px;
  overflow:hidden;
  flex:0 0 42px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.08);
  font-size:13px;
  font-weight:800;
}
.person-avatar img{
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
}
.person-main{min-width:0; flex:1;}
.person-actions{display:flex; align-items:center; gap:6px; flex-shrink:0;}
.person-name{font-weight:800; line-height:1.1;}
.person-toggle-btn{border:1px solid var(--line); background:rgba(255,255,255,.04); color:var(--text); border-radius:999px; padding:4px 8px; font-size:11px; cursor:pointer;}
.person-toggle-btn:hover{background:rgba(255,255,255,.08);}
.person-radio{display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:10px; border:1px solid rgba(90,160,255,.35); background:rgba(90,160,255,.12); font-size:15px;}
.person-role{font-size:12px; opacity:.85; margin-top:4px;}
.badges{display:flex; gap:6px; flex-wrap:wrap; margin-top:8px;}
.badge{font-size:11px; padding:3px 8px; border-radius:999px; border:1px solid var(--line); opacity:.95;}
.badge106{background:rgba(255,80,80,.22); border-color:rgba(255,80,80,.55); font-weight:800;}
.badge-site-north{background:rgba(80,160,255,.18); border-color:rgba(80,160,255,.45);}
.badge-site-south{background:rgba(255,180,80,.18); border-color:rgba(255,180,80,.45);}
.formations{display:flex; gap:6px; flex-wrap:wrap; margin-top:8px;}
.formation{font-size:16px; line-height:1; display:inline-flex; align-items:center; justify-content:center; opacity:.95;}
.modal-formations{display:flex; gap:8px; flex-wrap:wrap; min-height:24px; align-items:center;}
.modal-formation{font-size:22px; line-height:1; display:inline-flex; align-items:center; justify-content:center; padding:4px 6px; border-radius:10px; border:1px solid var(--line); background:rgba(255,255,255,.03);}
.person.is-106{background:rgba(255,80,80,.18); border-color:rgba(255,80,80,.55);}
.people-list[data-list="off"] .person{background:rgba(160,160,160,.08);}
.people-list[data-list="on"] .person{background:rgba(60,220,130,.10); border-color:rgba(60,220,130,.25);}
.people-list .person.is-106,
.people-list[data-list="off"] .person.is-106,
.people-list[data-list="on"] .person.is-106{background:rgba(255,80,80,.18); border-color:rgba(255,80,80,.55);}
.group-col[data-type] .group{background:rgba(90,160,255,.06); border-color:rgba(90,160,255,.20);}
.person.drag-chosen{cursor:grabbing;}
.person.drag-ghost{opacity:.4; background:rgba(90,160,255,.14) !important; border-style:dashed !important;}
.person.drag-active{box-shadow:0 12px 26px rgba(0,0,0,.5); opacity:.98;}
.inp{width:100%; padding:10px 12px; border-radius:12px; border:1px solid var(--line); background:rgba(255,255,255,.03); color:var(--text);}
.grid{display:grid; grid-template-columns:1fr 180px; gap:12px;}
.grid-span-2{grid-column:1 / -1}
@media (max-width: 1200px){
  .dispatch-toolbar__stats{grid-template-columns:repeat(3, minmax(96px, 1fr)); flex-basis:100%;}
}
@media (max-width: 700px){
  .grid{grid-template-columns:1fr}
  .grid-span-2{grid-column:auto}
  .dispatch-toolbar__stats{grid-template-columns:repeat(2, minmax(96px, 1fr));}
}
body.dispatch-focus{overflow-x:hidden;}
body.dispatch-focus .board{ min-width: max-content; }
body.dispatch-focus [data-col-id]{ min-width:max(220px, var(--col-min-width, 280px)); }
body.dispatch-focus .dispatch-shell{width:100%; max-width:none !important; position:relative; z-index:5;}
body.dispatch-focus .sidebar,
body.dispatch-focus .side-nav,
body.dispatch-focus .sidenav,
body.dispatch-focus .app-sidebar,
body.dispatch-focus .layout-sidebar,
body.dispatch-focus .sidebar-shell,
body.dispatch-focus .left-sidebar,
body.dispatch-focus aside{display:none !important;}
body.dispatch-focus main,
body.dispatch-focus .main,
body.dispatch-focus .main-content,
body.dispatch-focus .content,
body.dispatch-focus .content-wrap,
body.dispatch-focus .page,
body.dispatch-focus .layout-main,
body.dispatch-focus .app-main{margin-left:0 !important; width:100% !important; max-width:none !important;}
.samr-modal{position:fixed; inset:0; display:none; z-index:9999;}
.samr-modal[aria-hidden="false"]{display:block;}
.samr-modal__backdrop{position:absolute; inset:0; background:rgba(0,0,0,.55);}
.samr-modal__panel{position:relative; width:min(560px, calc(100% - 24px)); margin:6vh auto 0 auto; background:linear-gradient(180deg, rgba(15,23,34,.98), rgba(12,19,29,.96)); border:1px solid var(--line); border-radius:18px; padding:14px; box-shadow:0 25px 80px rgba(0,0,0,.45);}
.samr-modal__head{display:flex; justify-content:space-between; align-items:flex-start; gap:10px}
.btn.danger{border-color:rgba(255,120,120,.35); background:rgba(255,80,80,.12);}
</style>

<script>
(function(){
  const csrf = document.getElementById('csrf')?.value || '';
  const hospitalSel = document.getElementById('hospitalStatus');
  const refreshInfo = document.getElementById('refreshInfo');
  const btnFullscreen = document.getElementById('btnFullscreen');
  const countActive = document.getElementById('countActive');
  const countAvailable = document.getElementById('countAvailable');
  const countGrouped = document.getElementById('countGrouped');
  const countIntervention = document.getElementById('countIntervention');
  const count106 = document.getElementById('count106');
  const countOff = document.getElementById('countOff');
  const btnSelfDuty = document.getElementById('btnSelfDuty');
  const selfDutyHint = document.getElementById('selfDutyHint');
  const currentUsername = <?= json_encode($username, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  const personModal = document.getElementById('personModal');
  const personForm = document.getElementById('personForm');
  const personErr = document.getElementById('personErr');
  const personName = document.getElementById('personName');
  const personRole = document.getElementById('personRole');
  const personList = document.getElementById('personList');
  const personHospitalSite = document.getElementById('personHospitalSite');

  const unitModal = document.getElementById('unitModal');
  const unitForm = document.getElementById('unitForm');
  const unitErr = document.getElementById('unitErr');
  const unitType = document.getElementById('unitType');
  const unitLabel = document.getElementById('unitLabel');

  const editPersonModal = document.getElementById('editPersonModal');
  const editStatus = document.getElementById('editStatus');
  const editInter = document.getElementById('editInter');
  const edit106 = document.getElementById('edit106');
  const editHasRadio = document.getElementById('editHasRadio');
  const editHospitalSite = document.getElementById('editHospitalSite');
  const editFormations = document.getElementById('editFormations');
  const editPersonErr = document.getElementById('editPersonErr');
  const btnSavePerson = document.getElementById('btnSavePerson');

  let selectedPersonId = null;
  let selectedPersonData = null;
  let isSaving = false;
  let isDragging = false;
  let autoRefreshTimer = null;
  let dispatchFocus = false;
  let latestBoardData = null;
  let hiddenFocusNodes = new Map();
  let focusExpandedNodes = new Map();

  function normalizeMatchValue(value){
    return String(value || '')
      .trim()
      .toLowerCase()
      .replace(/^discord[_-]?/, '')
      .replace(/[^a-z0-9]/g, '');
  }

  function findOwnPerson(){
    const people = Array.isArray(latestBoardData?.people) ? latestBoardData.people : [];
    const unameRaw = String(currentUsername || '').trim();
    const uname = normalizeMatchValue(unameRaw);
    if (!unameRaw) return null;

    const candidateFields = ['name', 'username', 'discord_username', 'owner_username', 'created_by', 'display_name'];
    for (const p of people){
      for (const field of candidateFields){
        if (normalizeMatchValue(p?.[field]) === uname) return p;
      }
    }
    return null;
  }

  function updateSelfDutyButton(){
    if (!btnSelfDuty) return;
    const me = findOwnPerson();
    if (!me){
      btnSelfDuty.textContent = 'Se mettre en service';
      btnSelfDuty.disabled = false;
      btnSelfDuty.title = 'Carte session introuvable : utilisez la colonne Hors service en attendant';
      return;
    }
    btnSelfDuty.disabled = false;
    btnSelfDuty.title = '';
    const offIds = new Set((latestBoardData?.lists?.off || []).map((v)=> Number(v)));
    btnSelfDuty.textContent = offIds.has(Number(me.id)) ? 'Se mettre en service' : 'Se mettre hors service';
  }

  async function toggleSelfDuty(){
    const me = findOwnPerson();
    if (!me){
      alert('Aucune carte liée automatiquement à cette session. Utilise la colonne Hors service pour déplacer la carte manuellement, ou ajoute un rattachement utilisateur ↔ carte côté API.');
      return;
    }
    const onIds = Array.from(document.querySelectorAll('.people-list[data-list="on"] .person')).map((el)=> Number(el.dataset.personId));
    const offIds = Array.from(document.querySelectorAll('.people-list[data-list="off"] .person')).map((el)=> Number(el.dataset.personId));
    const memberLists = Array.from(document.querySelectorAll('.member-list'));
    let isOff = offIds.includes(Number(me.id));

    const nextOn = onIds.filter((id)=> id !== Number(me.id));
    const nextOff = offIds.filter((id)=> id !== Number(me.id));
    for (const listEl of memberLists){
      const found = listEl.querySelector(`.person[data-person-id="${Number(me.id)}"]`);
      if (found) found.remove();
    }

    if (isOff){
      nextOn.unshift(Number(me.id));
    } else {
      nextOff.unshift(Number(me.id));
    }

    const groups = {};
    document.querySelectorAll('.member-list').forEach((el)=>{
      groups[el.dataset.groupId] = Array.from(el.querySelectorAll('.person')).map((p)=> Number(p.dataset.personId));
    });
    const groupOrder = {};
    document.querySelectorAll('.group-col').forEach((el)=>{
      groupOrder[el.dataset.type] = Array.from(el.querySelectorAll('.group')).map((g)=> Number(g.dataset.groupId));
    });

    await apiPost({ action:'save_state', csrf, lists:{ on: nextOn, off: nextOff }, groups, group_order: groupOrder });
    await loadBoard();
  }

  function openModal(el){ el.setAttribute('aria-hidden','false'); }
  function closeModal(el){ el.setAttribute('aria-hidden','true'); }
  function showErr(el, msg){ el.textContent = msg; el.style.display='block'; }
  function clearErr(el){ el.textContent=''; el.style.display='none'; }

  function rememberOriginalStyle(store, el){
    if (!(el instanceof HTMLElement)) return;
    if (!store.has(el)) store.set(el, el.getAttribute('style') || '');
  }

  function restoreOriginalStyle(store){
    store.forEach((prev, el)=>{
      if (!(el instanceof HTMLElement)) return;
      if (prev) el.setAttribute('style', prev);
      else el.removeAttribute('style');
    });
    store.clear();
  }

  function applyFocusLayout(){
    clearFocusLayout();

    const dispatchShell = document.querySelector('.dispatch-shell');
    const selectors = ['.sidebar', '.side-nav', '.sidenav', '.app-sidebar', '.layout-sidebar', '.sidebar-shell', '.left-sidebar', 'aside'];
    selectors.forEach((sel)=>{
      document.querySelectorAll(sel).forEach((el)=>{
        if (!(el instanceof HTMLElement)) return;
        if (dispatchShell && (el.contains(dispatchShell) || dispatchShell.contains(el))) return;
        rememberOriginalStyle(hiddenFocusNodes, el);
        el.style.setProperty('display', 'none', 'important');
      });
    });

    const candidates = Array.from(document.body.querySelectorAll('*')).filter((el)=>{
      if (!(el instanceof HTMLElement)) return false;
      if (dispatchShell && (el.contains(dispatchShell) || dispatchShell.contains(el))) return false;
      const rect = el.getBoundingClientRect();
      if (rect.width < 160 || rect.width > 420) return false;
      if (rect.height < window.innerHeight * 0.65) return false;
      if (rect.left > 40) return false;
      const cs = window.getComputedStyle(el);
      if (!['fixed','sticky'].includes(cs.position)) return false;
      return true;
    });

    candidates.forEach((el)=>{
      rememberOriginalStyle(hiddenFocusNodes, el);
      el.style.setProperty('display', 'none', 'important');
    });

    const expandSelectors = ['main', '.main', '.main-content', '.content', '.content-wrap', '.page', '.layout-main', '.app-main'];
    expandSelectors.forEach((sel)=>{
      document.querySelectorAll(sel).forEach((el)=>{
        if (!(el instanceof HTMLElement)) return;
        if (dispatchShell && !el.contains(dispatchShell) && !dispatchShell.contains(el)) return;
        rememberOriginalStyle(focusExpandedNodes, el);
        el.style.setProperty('margin-left', '0', 'important');
        el.style.setProperty('width', '100%', 'important');
        el.style.setProperty('max-width', 'none', 'important');
      });
    });
  }

  function clearFocusLayout(){
    restoreOriginalStyle(hiddenFocusNodes);
    restoreOriginalStyle(focusExpandedNodes);
    document.body.classList.remove('dispatch-focus');
  }

  function updateRefreshInfo(){
    const d = new Date();
    const pad = (n) => String(n).padStart(2,'0');
    refreshInfo.textContent = `Dernière mise à jour : ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
  }

  personModal.addEventListener('click', (e)=>{ if (e.target?.dataset?.close) closeModal(personModal); });
  unitModal.addEventListener('click', (e)=>{ if (e.target?.dataset?.closeUnit) closeModal(unitModal); });
  editPersonModal.addEventListener('click', (e)=>{ if (e.target?.dataset?.closeEdit) closeModal(editPersonModal); });

  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape'){
      if (personModal.getAttribute('aria-hidden') === 'false') closeModal(personModal);
      if (unitModal.getAttribute('aria-hidden') === 'false') closeModal(unitModal);
      if (editPersonModal.getAttribute('aria-hidden') === 'false') closeModal(editPersonModal);
    }
  });

  document.getElementById('btnNewPerson').addEventListener('click', ()=>{
    clearErr(personErr);
    personForm.reset();
    personHospitalSite.value = '';
    openModal(personModal);
    setTimeout(()=>personName.focus(), 0);
  });

  document.getElementById('btnNewUnit').addEventListener('click', ()=>{
    clearErr(unitErr);
    unitForm.reset();
    unitLabel.value = '';
    openModal(unitModal);
    setTimeout(()=>unitLabel.focus(), 0);
  });

  async function apiPost(payload){
    const resp = await fetch('/api/board.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });
    const data = await resp.json().catch(()=>null);
    if (!resp.ok){
      throw new Error((data && data.error) ? data.error : 'Erreur API');
    }
    return data;
  }

  const CAP = { lincoln: 1, adam: 2, tango: 3, queen: 4 };

  function naturalCompare(a, b){
    return String(a || '').localeCompare(String(b || ''), 'fr', { numeric:true, sensitivity:'base' });
  }

  function isPerson106(p){
    return (Number(p.status_106) === 1) || (p.status_text && String(p.status_text).toUpperCase().includes('10-6'));
  }

  function hasPersonRadio(p){
    return Number(p?.has_radio) === 1 || Number(p?.radio) === 1;
  }

  async function togglePersonDuty(personId){
    const targetId = Number(personId);
    if (!Number.isFinite(targetId)) return;

    const onIds = Array.from(document.querySelectorAll('.people-list[data-list="on"] .person')).map((el)=> Number(el.dataset.personId)).filter((id)=> id !== targetId);
    const offIds = Array.from(document.querySelectorAll('.people-list[data-list="off"] .person')).map((el)=> Number(el.dataset.personId)).filter((id)=> id !== targetId);

    document.querySelectorAll('.member-list .person').forEach((el)=>{
      if (Number(el.dataset.personId) === targetId) el.remove();
    });

    const wasOff = Array.isArray(latestBoardData?.lists?.off) && latestBoardData.lists.off.map(Number).includes(targetId);
    if (wasOff) onIds.unshift(targetId);
    else offIds.unshift(targetId);

    const groups = {};
    document.querySelectorAll('.member-list').forEach((el)=>{
      groups[el.dataset.groupId] = Array.from(el.querySelectorAll('.person')).map((p)=> Number(p.dataset.personId));
    });

    const groupOrder = {};
    document.querySelectorAll('.group-col').forEach((el)=>{
      groupOrder[el.dataset.type] = Array.from(el.querySelectorAll('.group')).map((g)=> Number(g.dataset.groupId));
    });

    try{
      await apiPost({ action:'save_state', csrf, lists:{ on: onIds, off: offIds }, groups, group_order: groupOrder });
      await loadBoard();
    }catch(e){
      alert(e.message || 'Impossible de modifier le service.');
    }
  }

  function updateSummary(data){
    const people = Array.isArray(data?.people) ? data.people : [];
    const groupMembers = data?.group_members || {};
    const groupedIds = new Set();
    for (const ids of Object.values(groupMembers)){
      for (const id of (Array.isArray(ids) ? ids : [])) groupedIds.add(Number(id));
    }

    let active = 0;
    let available = 0;
    let grouped = groupedIds.size;
    let intervention = 0;
    let s106 = 0;
    let off = Array.isArray(data?.lists?.off) ? data.lists.off.length : 0;

    for (const p of people){
      const pid = Number(p.id);
      const isOff = Array.isArray(data?.lists?.off) && data.lists.off.includes(pid);
      if (!isOff){
        active++;
        if (!groupedIds.has(pid)) available++;
      }
      if (Number(p.in_intervention) === 1) intervention++;
      if (isPerson106(p)) s106++;
    }

    if (countActive) countActive.textContent = String(active);
    if (countAvailable) countAvailable.textContent = String(available);
    if (countGrouped) countGrouped.textContent = String(grouped);
    if (countIntervention) countIntervention.textContent = String(intervention);
    if (count106) count106.textContent = String(s106);
    if (countOff) countOff.textContent = String(off);
  }

  function renderModalFormations(formations){
    if (!editFormations) return;
    editFormations.innerHTML = '';
    const items = Array.isArray(formations) ? formations : [];
    let count = 0;
    for (const f of items){
      const emoji = String(f.emoji || '').trim();
      if (!emoji) continue;
      const span = document.createElement('span');
      span.className = 'modal-formation';
      span.textContent = emoji;
      span.title = String(f.name || '');
      editFormations.appendChild(span);
      count++;
    }
    if (!count){
      editFormations.textContent = 'Aucune formation';
      editFormations.classList.add('muted');
    } else {
      editFormations.classList.remove('muted');
    }
  }

  function renderPerson(p){
    const div = document.createElement('div');

    const is106 = isPerson106(p);
    const hasRadio = hasPersonRadio(p);
    const isOff = Array.isArray(latestBoardData?.lists?.off) && latestBoardData.lists.off.map(Number).includes(Number(p.id));
    const photoUrl = String(p.profile_photo_url || '').trim();
    const initials = String(p.name || '')
      .split(/[\s|]+/)
      .filter(Boolean)
      .slice(0,2)
      .map(part => (part[0] || '').toUpperCase())
      .join('') || '?';

    div.className = is106 ? 'person is-106' : 'person';
    div.dataset.personId = String(p.id);
    div.dataset.personName = String(p.name || '');

    const head = document.createElement('div');
    head.className = 'person-head';

    const mainWrap = document.createElement('div');
    mainWrap.className = 'person-main-wrap';

    const avatar = document.createElement('div');
    avatar.className = 'person-avatar';
    if (photoUrl){
      const img = document.createElement('img');
      img.src = photoUrl;
      img.alt = p.name || 'Photo de profil';
      avatar.appendChild(img);
    } else {
      avatar.textContent = initials;
    }

    const main = document.createElement('div');
    main.className = 'person-main';

    const n = document.createElement('div');
    n.className = 'person-name';
    n.textContent = p.name;

    const r = document.createElement('div');
    r.className = 'person-role';
    r.textContent = p.role ? p.role : '';

    main.appendChild(n);
    main.appendChild(r);
    mainWrap.appendChild(avatar);
    mainWrap.appendChild(main);

    const actions = document.createElement('div');
    actions.className = 'person-actions';

    if (hasRadio){
      const radioIcon = document.createElement('div');
      radioIcon.className = 'person-radio';
      radioIcon.title = 'Radio';
      radioIcon.setAttribute('aria-label', 'Radio');
      radioIcon.textContent = '📻';
      actions.appendChild(radioIcon);
    }

    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'person-toggle-btn';
    toggleBtn.textContent = isOff ? 'En service' : 'Hors service';
    toggleBtn.title = isOff ? 'Passer la carte en service' : 'Passer la carte hors service';
    toggleBtn.addEventListener('click', async (e)=>{
      e.stopPropagation();
      await togglePersonDuty(p.id);
    });
    actions.appendChild(toggleBtn);

    head.appendChild(mainWrap);
    head.appendChild(actions);
    div.appendChild(head);

    const badges = document.createElement('div');
    badges.className = 'badges';

    if (p.status_text){
      const b = document.createElement('div');
      b.className = 'badge';
      b.textContent = p.status_text;
      badges.appendChild(b);
    }
    if (Number(p.in_intervention) === 1){
      const b = document.createElement('div');
      b.className = 'badge';
      b.textContent = 'EN INTER';
      badges.appendChild(b);
    }
    if (isPerson106(p)){
      const b = document.createElement("div");
      b.className = "badge badge106";
      b.textContent = "10-6";
      badges.appendChild(b);
    }
    if (hasRadio){
      const b = document.createElement('div');
      b.className = 'badge';
      b.textContent = '📻 Dispatch';
      badges.appendChild(b);
    }
    if (p.hospital_site === 'north'){
      const b = document.createElement('div');
      b.className = 'badge badge-site-north';
      b.textContent = '🏥 Nord';
      badges.appendChild(b);
    }
    if (p.hospital_site === 'south'){
      const b = document.createElement('div');
      b.className = 'badge badge-site-south';
      b.textContent = '🏥 Sud';
      badges.appendChild(b);
    }

    div.appendChild(badges);

    div.addEventListener('click', ()=>{
      selectedPersonId = Number(p.id);
      selectedPersonData = p;
      clearErr(editPersonErr);
      editStatus.value = p.status_text || '';
      editInter.checked = Number(p.in_intervention) === 1;
      edit106.checked = isPerson106(p);
      if (editHasRadio) editHasRadio.checked = hasRadio;
      editHospitalSite.value = p.hospital_site || '';
      renderModalFormations(p.formations || []);
      openModal(editPersonModal);
    });

    return div;
  }

  function renderGroup(g){
    const wrap = document.createElement('div');
    wrap.className = 'group';
    wrap.dataset.groupId = String(g.id);
    wrap.dataset.type = g.group_type;

    const head = document.createElement('div');
    head.className = 'group-head';

    const title = document.createElement('div');
    title.className = 'group-title';
    title.textContent = g.label;
    title.title = 'Double-clic pour renommer';

    const meta = document.createElement('div');
    meta.className = 'group-meta';

    const cap = document.createElement('div');
    cap.className = 'group-cap';
    cap.textContent = `0/${CAP[g.group_type] || 0}`;

    const actions = document.createElement('div');
    actions.className = 'group-actions';

    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'iconbtn';
    del.textContent = '🗑';
    del.title = 'Supprimer l’unité';

    del.addEventListener('click', async ()=>{
      if (!confirm('Supprimer cette unité ? Les membres seront renvoyés en service.')) return;
      try{
        await apiPost({ action:'delete_group', id: Number(g.id), csrf, force: 1 });
        await loadBoard();
      }catch(e){
        alert(e.message || 'Suppression impossible.');
      }
    });

    actions.appendChild(del);

    meta.appendChild(cap);
    meta.appendChild(actions);

    head.appendChild(title);
    head.appendChild(meta);

    const list = document.createElement('div');
    list.className = 'member-list';
    list.dataset.groupId = String(g.id);

    wrap.appendChild(head);
    wrap.appendChild(list);

    title.addEventListener('dblclick', async ()=>{
      const cur = title.textContent.trim();
      const next = prompt('Nom de l’unité :', cur);
      if (!next || !next.trim()) return;
      const label = next.trim();
      try{
        await apiPost({ action:'rename_group', id: Number(g.id), label, csrf });
        await loadBoard();
      }catch(e){
        alert(e.message || 'Impossible de renommer.');
      }
    });

    wrap._setCount = (n) => { cap.textContent = `${n}/${CAP[g.group_type] || 0}`; };
    return wrap;
  }

  function clearAll(){
    document.querySelectorAll('.people-list').forEach(el => el.innerHTML='');
    document.querySelectorAll('.group-col').forEach(el => el.innerHTML='');
  }

  function updateCompactHeights(){
    const BASE_VISIBLE_CARDS = 8;
    document.querySelectorAll('.compact-list').forEach(el => {
      el.style.setProperty('--base-visible-cards', String(BASE_VISIBLE_CARDS));
      const count = el.querySelectorAll('.person').length;
      const extra = Math.max(0, count - BASE_VISIBLE_CARDS);
      el.style.minHeight = `calc(${BASE_VISIBLE_CARDS} * 76px + ${extra} * 76px)`;
    });
    document.querySelectorAll('.compact-group-col').forEach(el => {
      const groupCount = el.querySelectorAll('.group').length;
      const min = Math.max(210, 130 + (groupCount * 92));
      el.style.minHeight = min + 'px';
    });
  }

  function setupSortable(){
    document.querySelectorAll('.people-list').forEach(listEl=>{
      new Sortable(listEl, {
        group: 'people',
        animation: 150,
        forceFallback: true,
        fallbackTolerance: 4,
        ghostClass: 'drag-ghost',
        chosenClass: 'drag-chosen',
        dragClass: 'drag-active',
        sort: true,
        draggable: '.person',
        onStart: function(){ isDragging = true; },
        onEnd: function(){
          isDragging = false;
          debounceSave();
        }
      });
    });

    document.querySelectorAll('.group-col').forEach(colEl=>{
      new Sortable(colEl, {
        group: 'groups',
        animation: 150,
        forceFallback: true,
        fallbackTolerance: 4,
        ghostClass: 'drag-ghost',
        chosenClass: 'drag-chosen',
        dragClass: 'drag-active',
        sort: true,
        draggable: '.group',
        onStart: function(){ isDragging = true; },
        onEnd: function(){
          isDragging = false;
          debounceSave();
        }
      });
    });

    document.querySelectorAll('.member-list').forEach(mEl=>{
      new Sortable(mEl, {
        group: 'people',
        animation: 150,
        forceFallback: true,
        fallbackTolerance: 4,
        ghostClass: 'drag-ghost',
        chosenClass: 'drag-chosen',
        dragClass: 'drag-active',
        sort: true,
        draggable: '.person',
        onStart: function(){ isDragging = true; },
        onAdd: function(evt){
          const groupWrap = evt.to.closest('.group');
          const type = groupWrap?.dataset?.type || '';
          const cap = CAP[type] || 0;
          const count = evt.to.querySelectorAll('.person').length;
          if (cap && count > cap){
            evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] || null);
            isDragging = false;
            alert(`${type.toUpperCase()} max ${cap} personnes`);
            return;
          }
          isDragging = false;
          debounceSave();
        },
        onEnd: function(){
          isDragging = false;
          debounceSave();
        }
      });
    });
  }

  let saveTimer = null;
  function debounceSave(){
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(saveState, 250);
  }

  async function saveState(){
    isSaving = true;
    const lists = {};
    document.querySelectorAll('.people-list').forEach(el=>{
      const k = el.dataset.list;
      lists[k] = Array.from(el.querySelectorAll('.person')).map(p => Number(p.dataset.personId));
    });

    const groups = {};
    document.querySelectorAll('.member-list').forEach(el=>{
      const gid = el.dataset.groupId;
      groups[gid] = Array.from(el.querySelectorAll('.person')).map(p => Number(p.dataset.personId));
    });

    const groupOrder = {};
    document.querySelectorAll('.group-col').forEach(el=>{
      const type = el.dataset.type;
      groupOrder[type] = Array.from(el.querySelectorAll('.group')).map(g => Number(g.dataset.groupId));
    });

    try{
      await apiPost({ action:'save_state', csrf, lists, groups, group_order: groupOrder });
      document.querySelectorAll('.group').forEach(g=>{
        const gid = g.dataset.groupId;
        const n = document.querySelectorAll(`.member-list[data-group-id="${gid}"] .person`).length;
        if (typeof g._setCount === 'function') g._setCount(n);
      });
      updateRefreshInfo();
    }catch(e){
      alert(e.message || 'Sauvegarde impossible.');
      await loadBoard();
    }finally{
      isSaving = false;
    }
  }

  let lastBoardSig = null;
  async function loadBoard(force = true){
    const resp = await fetch('/api/board.php', {credentials:'same-origin'});
    const data = await resp.json().catch(()=>null);
    if (!data || !data.ok){
      alert((data && data.error) ? data.error : 'Impossible de charger le tableau.');
      return;
    }

    const sig = JSON.stringify([data.hospital_status, data.groups, data.people, data.lists, data.group_members]);
    if (!force && sig === lastBoardSig){ updateRefreshInfo(); return; }
    lastBoardSig = sig;

    latestBoardData = data;
    if (data.hospital_status) hospitalSel.value = data.hospital_status;

    clearAll();

    const groupByType = { lincoln: [], adam: [], tango: [], queen: [] };
    for (const g of (data.groups || [])){
      if (groupByType[g.group_type]) groupByType[g.group_type].push(g);
    }
    for (const type of Object.keys(groupByType)){
      groupByType[type].sort((a, b) => naturalCompare(a.label, b.label));
      const col = document.querySelector(`.group-col[data-type="${type}"]`);
      if (!col) continue;
      for (const g of groupByType[type]) col.appendChild(renderGroup(g));
    }

    const persons = {};
    for (const p of (data.people || [])) persons[p.id] = p;

    const onEl = document.querySelector('.people-list[data-list="on"]');
    const offEl = document.querySelector('.people-list[data-list="off"]');

    for (const pid of (data.lists?.on || [])){
      if (persons[pid]) onEl.appendChild(renderPerson(persons[pid]));
    }
    for (const pid of (data.lists?.off || [])){
      if (persons[pid]) offEl.appendChild(renderPerson(persons[pid]));
    }

    for (const [gid, memberIds] of Object.entries(data.group_members || {})){
      const listEl = document.querySelector(`.member-list[data-group-id="${gid}"]`);
      if (!listEl) continue;
      for (const pid of memberIds){
        if (persons[pid]) listEl.appendChild(renderPerson(persons[pid]));
      }
    }

    document.querySelectorAll('.group').forEach(g=>{
      const gid = g.dataset.groupId;
      const n = document.querySelectorAll(`.member-list[data-group-id="${gid}"] .person`).length;
      if (typeof g._setCount === 'function') g._setCount(n);
    });

    setupSortable();
    updateCompactHeights();
    updateSummary(data);
    updateRefreshInfo();
    updateSelfDutyButton();
    document.dispatchEvent(new CustomEvent('samr:boardLoaded'));
  }

  function startAutoRefresh(){
    if (autoRefreshTimer) clearInterval(autoRefreshTimer);
    autoRefreshTimer = setInterval(async ()=>{
      if (isSaving || isDragging) return;
      if (personModal.getAttribute('aria-hidden') === 'false') return;
      if (unitModal.getAttribute('aria-hidden') === 'false') return;
      if (editPersonModal.getAttribute('aria-hidden') === 'false') return;

      try{
        await loadBoard(false);
      }catch(e){
        console.error('Auto-refresh dispatch failed', e);
      }
    }, 5000);
  }
  btnFullscreen?.addEventListener('click', ()=>{
    dispatchFocus = !dispatchFocus;
    if (dispatchFocus){
      document.body.classList.add('dispatch-focus');
      applyFocusLayout();
    } else {
      clearFocusLayout();
    }
    btnFullscreen.textContent = dispatchFocus ? '↔ Quitter focus' : '🖥 Mode focus';
  });

  window.addEventListener('resize', ()=>{
    if (dispatchFocus){
      clearFocusLayout();
      applyFocusLayout();
    }
  });

  btnSelfDuty?.addEventListener('click', async ()=>{
    await toggleSelfDuty();
  });

  hospitalSel.addEventListener('change', async ()=>{
    try{
      await apiPost({ action:'set_hospital_status', hospital_status: hospitalSel.value, csrf });
      updateRefreshInfo();
    }catch(e){
      alert(e.message || 'Impossible de mettre à jour.');
    }
  });

  personForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    clearErr(personErr);
    try{
      await apiPost({
        action:'create_person',
        name: personName.value.trim(),
        role: personRole.value.trim(),
        list: personList.value,
        hospital_site: personHospitalSite.value,
        csrf
      });
      closeModal(personModal);
      await loadBoard();
    }catch(err){
      showErr(personErr, err.message || 'Création impossible.');
    }
  });

  unitForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    clearErr(unitErr);
    const type = unitType.value;
    const label = unitLabel.value.trim();
    if (!label){
      showErr(unitErr, 'Nom requis.');
      return;
    }
    try{
      await apiPost({ action:'create_group', group_type: type, label, csrf });
      closeModal(unitModal);
      await loadBoard();
    }catch(err){
      showErr(unitErr, err.message || 'Création impossible.');
    }
  });

  btnSavePerson.addEventListener('click', async ()=>{
    if (!selectedPersonId) return;
    clearErr(editPersonErr);
    try{
      await apiPost({
        action:'update_person',
        id: selectedPersonId,
        status_text: editStatus.value.trim(),
        in_intervention: editInter.checked ? 1 : 0,
        status_106: edit106.checked ? 1 : 0,
        has_radio: editHasRadio && editHasRadio.checked ? 1 : 0,
        hospital_site: editHospitalSite.value,
        csrf
      });
      closeModal(editPersonModal);
      await loadBoard();
    }catch(e){
      showErr(editPersonErr, e.message || 'Sauvegarde impossible.');
    }
  });

  loadBoard();
  startAutoRefresh();

  (function layoutManager(){
    const STORAGE_KEY = 'samr_dispatch_layout_v4';
    const board = document.getElementById('board');
    const banner = document.getElementById('layoutBanner');
    const btnLayout = document.getElementById('btnLayoutMode');
    const btnExit = document.getElementById('btnExitLayout');
    const btnReset = document.getElementById('btnResetLayout');
    const btnCompact = document.getElementById('btnCompactLayout');
    const btnDefault = document.getElementById('btnDefaultLayout');
    const btnLarge = document.getElementById('btnLargeLayout');
    const btnToggleSizePanel = document.getElementById('btnToggleSizePanel');
    const sizePanel = document.getElementById('layoutSizePanel');
    const sizeGrid = document.getElementById('layoutSizeGrid');

    let isLayoutMode = false;
    let colSortable = null;

    const DEFAULT = {
      service_on: { w: 270 },
      service_off: { w: 270 },
      lincoln: { w: 250 },
      adam: { w: 250 },
      tango: { w: 250 },
      queen: { w: 250 }
    };
    const PRESETS = {
      compact: { service_on: 230, service_off: 230, lincoln: 210, adam: 210, tango: 210, queen: 210 },
      default: { service_on: 270, service_off: 270, lincoln: 250, adam: 250, tango: 250, queen: 250 },
      large: { service_on: 320, service_off: 320, lincoln: 290, adam: 290, tango: 290, queen: 290 }
    };
    const DEFAULT_ORDER = ['service_on', 'service_off', 'lincoln', 'adam', 'tango', 'queen'];
    const MIN_COL_WIDTH = 210;
    const MAX_COL_WIDTH = 420;

    function getColEls(){
      return Array.from(board.children).filter(el => el.dataset?.colId);
    }

    function getWidthFor(colId){
      const el = board.querySelector(`[data-col-id="${colId}"]`);
      return el ? Math.round(el.getBoundingClientRect().width || el.offsetWidth || DEFAULT[colId]?.w || 280) : (DEFAULT[colId]?.w || 280);
    }

    function setColumnWidth(colEl, width){
      const safe = Math.max(MIN_COL_WIDTH, Math.min(MAX_COL_WIDTH, Number(width) || DEFAULT[colEl.dataset.colId]?.w || 280));
      colEl.style.width = safe + 'px';
      colEl.style.setProperty('--col-min-width', safe + 'px');
      return safe;
    }

    function updateBoardMinWidth(){
      const cols = getColEls();
      const total = cols.reduce((sum, col) => sum + col.getBoundingClientRect().width, 0) + Math.max(0, cols.length - 1) * 12 + 8;
      board.style.minWidth = Math.ceil(total) + 'px';
    }

    function saveLayout(){
      const cols = getColEls();
      const order = cols.map(c => c.dataset.colId);
      const sizes = {};
      cols.forEach(c => {
        const id = c.dataset.colId;
        sizes[id] = { w: getWidthFor(id) };
      });
      localStorage.setItem(STORAGE_KEY, JSON.stringify({ order, sizes }));
    }

    function loadSaved(){
      try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null'); }
      catch(e){ return null; }
    }

    function applyLayout(layout){
      const sizes = layout?.sizes || {};
      const order = layout?.order || DEFAULT_ORDER;

      order.forEach(id => {
        const el = board.querySelector(`[data-col-id="${id}"]`);
        if (el) board.appendChild(el);
      });

      getColEls().forEach(el => {
        const id = el.dataset.colId;
        const w = sizes[id]?.w || DEFAULT[id]?.w || 280;
        setColumnWidth(el, w);
      });

      updateBoardMinWidth();
      renderSizeControls();
    }

    function resetToDefaults(){
      localStorage.removeItem(STORAGE_KEY);
      DEFAULT_ORDER.forEach(id => {
        const el = board.querySelector(`[data-col-id="${id}"]`);
        if (!el) return;
        setColumnWidth(el, DEFAULT[id]?.w || 280);
        board.appendChild(el);
      });
      updateBoardMinWidth();
      renderSizeControls();
    }

    function applyPreset(name){
      const preset = PRESETS[name];
      if (!preset) return;
      getColEls().forEach(el => {
        const colId = el.dataset.colId;
        setColumnWidth(el, preset[colId] || DEFAULT[colId]?.w || 280);
      });
      updateBoardMinWidth();
      renderSizeControls();
      saveLayout();
    }

    function injectDragIndicators(){
      getColEls().forEach(colEl => {
        const head = colEl.querySelector('.col-head');
        if (!head || head.querySelector('.col-drag-indicator')) return;
        const ind = document.createElement('span');
        ind.className = 'col-drag-indicator';
        ind.textContent = '⠿';
        ind.title = 'Glisser pour déplacer la colonne';
        head.insertBefore(ind, head.firstChild);
      });
    }

    function addWidthHandles(){
      getColEls().forEach(colEl => {
        if (colEl.querySelector('.col-resize-w')) return;
        const handle = document.createElement('div');
        handle.className = 'col-resize-w';
        handle.title = 'Glisser pour changer la largeur';
        colEl.appendChild(handle);

        let startX, startW;
        const start = cx => {
          startX = cx;
          startW = getWidthFor(colEl.dataset.colId);
          document.body.style.cursor = 'col-resize';
          document.body.style.userSelect = 'none';
        };
        const move = cx => {
          setColumnWidth(colEl, startW + (cx - startX));
          updateBoardMinWidth();
          syncSlider(colEl.dataset.colId);
        };
        const end = () => {
          document.body.style.cursor = '';
          document.body.style.userSelect = '';
          saveLayout();
        };

        handle.addEventListener('mousedown', e => {
          if (!isLayoutMode) return;
          e.preventDefault();
          e.stopPropagation();
          start(e.clientX);
          const mm = evt => move(evt.clientX);
          const mu = () => {
            document.removeEventListener('mousemove', mm);
            document.removeEventListener('mouseup', mu);
            end();
          };
          document.addEventListener('mousemove', mm);
          document.addEventListener('mouseup', mu);
        });

        handle.addEventListener('touchstart', e => {
          if (!isLayoutMode) return;
          e.preventDefault();
          start(e.touches[0].clientX);
          const tm = evt => move(evt.touches[0].clientX);
          const te = () => {
            handle.removeEventListener('touchmove', tm);
            handle.removeEventListener('touchend', te);
            end();
          };
          handle.addEventListener('touchmove', tm, { passive: false });
          handle.addEventListener('touchend', te);
        }, { passive: false });
      });
    }

    function addHeightHandles(){}

    function syncSlider(colId){
      const input = sizeGrid?.querySelector(`input[data-col-id="${colId}"]`);
      const output = sizeGrid?.querySelector(`output[data-col-id="${colId}"]`);
      const width = getWidthFor(colId);
      if (input) input.value = String(width);
      if (output) output.textContent = width + ' px';
    }

    function renderSizeControls(){
      if (!sizeGrid) return;
      const cols = getColEls();
      sizeGrid.innerHTML = '';
      cols.forEach(colEl => {
        const colId = colEl.dataset.colId;
        const title = colEl.querySelector('.col-head')?.textContent?.replace(/\s+/g, ' ')?.trim() || colId;
        const row = document.createElement('div');
        row.className = 'layout-size-item';
        row.innerHTML = `<label><span>${title}</span><output data-col-id="${colId}">${getWidthFor(colId)} px</output></label><input type="range" min="${MIN_COL_WIDTH}" max="${MAX_COL_WIDTH}" step="10" value="${getWidthFor(colId)}" data-col-id="${colId}">`;
        const input = row.querySelector('input');
        input.addEventListener('input', evt => {
          setColumnWidth(colEl, evt.target.value);
          updateBoardMinWidth();
          syncSlider(colId);
        });
        input.addEventListener('change', () => saveLayout());
        sizeGrid.appendChild(row);
      });
    }

    function enableColSort(){
      if (colSortable) colSortable.destroy();
      colSortable = Sortable.create(board, {
        animation: 200,
        handle: '.col-drag-indicator',
        draggable: '[data-col-id]',
        ghostClass: 'col-ghost',
        chosenClass: 'sortable-chosen',
        filter: '.col-resize-w,.col-resize-h,input[type="range"]',
        onEnd(){
          updateBoardMinWidth();
          renderSizeControls();
          saveLayout();
        }
      });
    }
    function disableColSort(){ if (colSortable){ colSortable.destroy(); colSortable = null; } }

    function enterLayoutMode(){
      isLayoutMode = true;
      board.classList.add('layout-mode');
      banner.classList.add('visible');
      btnLayout.textContent = '✓ Mise en page active';
      injectDragIndicators();
      addWidthHandles();
      renderSizeControls();
      enableColSort();
    }

    function exitLayoutMode(){
      isLayoutMode = false;
      board.classList.remove('layout-mode');
      banner.classList.remove('visible');
      sizePanel?.classList.remove('visible');
      if (sizePanel) sizePanel.hidden = true;
      btnLayout.textContent = '⚙️ Mise en page';
      disableColSort();
      saveLayout();
    }

    btnLayout?.addEventListener('click', () => isLayoutMode ? exitLayoutMode() : enterLayoutMode());
    btnExit?.addEventListener('click', exitLayoutMode);
    btnReset?.addEventListener('click', () => { resetToDefaults(); if (isLayoutMode) exitLayoutMode(); });
    btnCompact?.addEventListener('click', () => applyPreset('compact'));
    btnDefault?.addEventListener('click', () => applyPreset('default'));
    btnLarge?.addEventListener('click', () => applyPreset('large'));
    btnToggleSizePanel?.addEventListener('click', () => {
      if (!sizePanel) return;
      const nextVisible = sizePanel.hidden || !sizePanel.classList.contains('visible');
      sizePanel.hidden = !nextVisible;
      sizePanel.classList.toggle('visible', nextVisible);
      if (nextVisible) renderSizeControls();
    });

    applyLayout(loadSaved());

    document.addEventListener('samr:boardLoaded', () => {
      applyLayout(loadSaved());
      if (isLayoutMode){
        injectDragIndicators();
        addWidthHandles();
        renderSizeControls();
      }
    });

    window.addEventListener('resize', () => {
      updateBoardMinWidth();
      updateCompactHeights();
      if (!sizePanel?.hidden) renderSizeControls();
    });
  })();

})();
</script>

<?php samr_layout_end(); ?>
