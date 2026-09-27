<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_login();
$u        = current_user();
$username = (string)($u['username'] ?? 'Utilisateur');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

samr_layout_start('SAMR — Facturation');
?>

<style>
/* ── Layout ── */
.billing-wrap{
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 20px;
  align-items: start;
}
@media(max-width:1000px){
  .billing-wrap{ grid-template-columns:1fr; }
  .billing-sidebar{ position:static !important; }
}

/* ── Header ── */
.billing-header{
  display:flex; align-items:center; justify-content:space-between;
  flex-wrap:wrap; gap:12px; margin-bottom:20px;
}
.billing-header h1{ font-family:var(--font-head); font-size:20px; font-weight:800; margin:0; }

/* ── Infos patient ── */
.patient-info-grid{
  display:grid; grid-template-columns:1fr 1fr; gap:10px;
  margin-bottom:4px;
}
@media(max-width:600px){ .patient-info-grid{ grid-template-columns:1fr; } }

/* ── Sections ── */
.billing-section{
  border:1px solid var(--line);
  border-radius:14px;
  overflow:hidden;
  margin-bottom:10px;
}
.section-head{
  display:flex; align-items:center; justify-content:space-between;
  padding:11px 16px;
  background:rgba(255,255,255,.025);
  cursor:pointer;
  user-select:none;
  gap:12px;
}
.section-head:hover{ background:rgba(255,255,255,.04); }
.section-title{
  font-family:var(--font-head); font-size:13px; font-weight:700;
  display:flex; align-items:center; gap:8px;
}
.section-subtotal{
  font-size:12px; font-weight:700; color:var(--accent2);
  white-space:nowrap;
}
.section-subtotal.zero{ color:var(--muted); font-weight:400; }
.section-chevron{
  font-size:10px; color:var(--muted); transition:transform .2s; flex-shrink:0;
}
.section-chevron.open{ transform:rotate(180deg); }

.section-body{ display:none; }
.section-body.open{ display:block; }

/* ── Lignes d'actes ── */
.act-row{
  display:flex; align-items:center; justify-content:space-between;
  padding:9px 16px; gap:12px;
  border-top:1px solid rgba(255,255,255,.04);
  transition:background .1s;
}
.act-row:hover{ background:rgba(255,255,255,.02); }
.act-row.has-qty{ background:rgba(59,158,255,.04); }

.act-label{ font-size:13px; flex:1; min-width:0; }
.act-price{ font-size:12px; color:var(--muted); white-space:nowrap; flex-shrink:0; }
.act-qty-wrap{
  display:flex; align-items:center; gap:6px; flex-shrink:0;
}
.qty-btn{
  width:26px; height:26px; border-radius:7px;
  border:1px solid var(--line2); background:rgba(255,255,255,.04);
  color:var(--text); font-size:16px; line-height:1;
  cursor:pointer; display:flex; align-items:center; justify-content:center;
  transition:all .1s; font-family:var(--font-body);
}
.qty-btn:hover{ background:rgba(59,158,255,.15); border-color:rgba(59,158,255,.4); }
.qty-display{
  width:28px; text-align:center; font-size:13px; font-weight:700;
  color:var(--text);
}
.act-total{
  font-size:13px; font-weight:700; color:var(--accent2);
  width:60px; text-align:right; flex-shrink:0;
}
.act-total.zero{ color:var(--muted); font-weight:400; }

/* ── Sidebar flottante ── */
.billing-sidebar{
  position:sticky; top:16px;
}
.total-card{
  background:linear-gradient(160deg, rgba(13,18,25,.98), rgba(8,12,16,.98));
  border:1px solid var(--line2); border-radius:16px;
  padding:20px; box-shadow:0 8px 32px rgba(0,0,0,.35);
}
.total-label{
  font-size:10px; text-transform:uppercase; letter-spacing:.8px; color:var(--muted);
  margin-bottom:4px;
}
.total-amount{
  font-family:var(--font-head); font-size:36px; font-weight:800;
  color:var(--accent2); line-height:1; margin-bottom:16px;
}
.total-breakdown{
  display:flex; flex-direction:column; gap:6px;
  margin-bottom:16px; padding-bottom:16px;
  border-bottom:1px solid var(--line);
  max-height:240px; overflow-y:auto;
}
.breakdown-line{
  display:flex; justify-content:space-between; align-items:flex-start;
  gap:8px; font-size:11px;
}
.breakdown-name{ color:var(--muted); flex:1; min-width:0; }
.breakdown-val{ font-weight:700; color:var(--text); white-space:nowrap; flex-shrink:0; }
.total-empty{ font-size:12px; color:var(--muted); text-align:center; padding:8px 0; }

.sidebar-actions{ display:flex; flex-direction:column; gap:8px; }

/* ── Majoration CI ── */
.ci-row{
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 14px;
  background:rgba(255,180,50,.05);
  border:1px solid rgba(255,180,50,.2);
  border-radius:10px;
  margin-bottom:10px;
  gap:10px;
}
.ci-label{ font-size:13px; flex:1; }
.ci-toggle{
  display:flex; align-items:center; gap:8px;
}
.toggle-switch{
  width:36px; height:20px; border-radius:999px;
  background:var(--line2); cursor:pointer; position:relative;
  transition:background .2s; flex-shrink:0; border:none;
}
.toggle-switch::after{
  content:''; position:absolute; top:2px; left:2px;
  width:16px; height:16px; border-radius:50%;
  background:#fff; transition:transform .2s;
}
.toggle-switch.on{ background:var(--accent2); }
.toggle-switch.on::after{ transform:translateX(16px); }
.ci-amount{ font-size:12px; font-weight:700; color:#ffb060; white-space:nowrap; }

/* Print */
@media print{
  .billing-sidebar, .billing-header .btn, .no-print{ display:none!important; }
  .billing-wrap{ display:block; }
  .billing-section{ break-inside:avoid; }
  .section-body{ display:block!important; }
  .act-row:not(.has-qty){ display:none!important; }
  body{ background:#fff!important; color:#000!important; }
  .section-head{ background:#f5f5f5!important; color:#000!important; }
  .act-label, .act-price, .act-total{ color:#000!important; }
}
</style>

<div class="billing-header">
  <div>
    <h1>💳 Facturation</h1>
    <div style="font-size:13px;color:var(--muted);">Sélectionnez les actes — le total se met à jour en temps réel</div>
  </div>
  <div style="display:flex;gap:8px;" class="no-print">
    <button class="btn secondary" id="btnReset">↺ Réinitialiser</button>
    <button class="btn" onclick="window.print()">🖨️ Imprimer / PDF</button>
  </div>
</div>

<!-- Infos patient -->
<div class="card" style="margin-bottom:16px;">
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:10px;">Informations</div>
  <div class="patient-info-grid">
    <input class="inp" id="patientName" placeholder="Nom du patient (optionnel)">
    <input class="inp" id="dossierRef" placeholder="Référence dossier">
    <input class="inp" id="billingDate" type="date">
    <input class="inp" id="practitioner" placeholder="Praticien" value="<?= h($username) ?>">
  </div>
</div>

<div class="billing-wrap">

  <!-- Colonne gauche : actes -->
  <div id="actsList">

    <!-- Majoration CI -->
    <div class="ci-row no-print">
      <div class="ci-label">📋 Carte d'identité non présentée</div>
      <div class="ci-toggle">
        <span class="ci-amount" id="ciAmount" style="display:none;">+</span>
        <button class="toggle-switch" id="ciToggle" aria-label="Majoration CI"></button>
      </div>
    </div>

  </div>

  <!-- Sidebar total -->
  <div class="billing-sidebar">
    <div class="total-card">
      <div class="total-label">Total à facturer</div>
      <div class="total-amount" id="grandTotal">$0</div>

      <div class="total-breakdown" id="breakdown">
        <div class="total-empty" id="emptyBreakdown">Aucun acte sélectionné</div>
      </div>

      <div style="font-size:11px;color:var(--muted);margin-bottom:14px;" id="actCount">0 acte(s)</div>

      <div class="sidebar-actions">
        <button class="btn success" onclick="window.print()" style="justify-content:center;">🖨️ Imprimer</button>
        <button class="btn secondary" id="btnReset2" style="justify-content:center;font-size:12px;">↺ Tout réinitialiser</button>
      </div>
    </div>
  </div>

</div>

<script>
(function(){

const SECTIONS = [
  {
    title: 'Pôle Urgences',
    icon: '🚨',
    items: [
      { label: 'Soin simple',                    price: 300 },
      { label: 'Déshydration / Hypoglycémie',    price: 600 },
      { label: 'Réanimation',                    price: 600 },
    ]
  },
  {
    title: 'Imagerie médicale',
    icon: '🩻',
    items: [
      { label: 'Radio',          price: 300 },
      { label: 'IRM / Scanner',  price: 900 },
      { label: 'Échographie',    price: 900 },
    ]
  },
  {
    title: 'Pôle Chirurgie',
    icon: '🔪',
    items: [
      { label: 'Chirurgie simple (type fracture)',    price: 1200  },
      { label: 'Chirurgie reconstructrive',          price: 1200  },
      { label: 'Blessure par balle (BPB)',           price: 1500  },
      { label: 'Blessure par arme blanche (BPA)',    price: 1500  },
      { label: 'Chirurgie urologique complexe (UV)', price: 2850  },
      { label: 'Neurochirurgie',                     price: 3300  },
      { label: 'Chirurgie cardiovasculaire',         price: 3300  },
      { label: 'Transplantation / greffe d\'organe', price: 10000 },
      { label: 'Chirurgie esthétique',               price: 20000 },
    ]
  },
  {
    title: 'Pôle Gynécologie / Obstétrique',
    icon: '🌸',
    items: [
      { label: 'Échographie',                                                price: 900   },
      { label: 'Consultation gynécologique',                                 price: 1500  },
      { label: 'Consultation obstétrique',                                   price: 1500  },
      { label: 'Consultation moyen de contraception',                        price: 1500  },
      { label: 'Grosse intervention (opération, avortement)',                price: 2000  },
      { label: 'Suivi grossesse (prise de sang, 7 consultations, accouchement)', price: 12500 },
    ]
  },
  {
    title: 'Pôle Psychologie / Psychiatrie',
    icon: '🧠',
    items: [
      { label: 'Bilan psychologique',                      price: 500   },
      { label: 'RDV psychologique (par séance)',           price: 700   },
      { label: 'Bilan psychiatrique',                      price: 800   },
      { label: 'RDV psychiatrique (par séance)',           price: 900   },
      { label: 'Test PPA',                                 price: 3000  },
      { label: 'Test PPA professionnel (SASP, Amunation…)', price: 5000 },
      { label: 'Test PPA civil',                           price: 10000 },
    ]
  },
  {
    title: 'Pôle Rééducation',
    icon: '🦴',
    items: [
      { label: 'Bilan de kinésithérapie',                    price: 450 },
      { label: 'Rééducation pour rachialgies (par séance)',  price: 600 },
      { label: 'Rééducation d\'un membre (par séance)',      price: 600 },
      { label: 'Rééducation respiratoire (par séance)',      price: 750 },
      { label: 'Rééducation neurologique (AVC…)',            price: 900 },
    ]
  },
  {
    title: 'Pôle Biologie',
    icon: '🧪',
    items: [
      { label: 'Test d\'alcoolémie',   price: 150 },
      { label: 'Test de grossesse',    price: 200 },
      { label: 'Test groupe sanguin',  price: 300 },
      { label: 'Test stupéfiant',      price: 400 },
      { label: 'Test MST',             price: 500 },
      { label: 'Test ADN',             price: 600 },
      { label: 'Bilan Sanguin',        price: 600 },
    ]
  },
  {
    title: 'Pôle Ophtalmologique',
    icon: '👁️',
    items: [
      { label: 'Consultation', price: 500  },
      { label: 'Opération',    price: 1500 },
    ]
  },
  {
    title: 'Pôle Addictologie',
    icon: '💊',
    items: [
      { label: 'Cure de désintoxication', price: 3000 },
    ]
  },
  {
    title: 'Pôle Unité Médico-Judiciaire (UMJ)',
    icon: '⚖️',
    items: [
      { label: 'Autopsie hors procédure judiciaire', price: 750  },
      { label: 'Préparation du corps',               price: 1000 },
      { label: 'Location + chauffeur corbillard',    price: 2500 },
      { label: 'Rapport circonstancié',              price: 750 },
    ]
  },
  {
    title: 'Service & Transport',
    icon: '🚑',
    items: [
      { label: 'Transport Ambulance (hors prise en charge)', price: 200 },
      { label: 'Transport Hélicoptère',                      price: 500 },
    ]
  },
  {
    title: 'Divers',
    icon: '📋',
    items: [
      { label: 'Visite médicale (si non salarié)',         price: 200  },
      { label: 'Bandage (petit) — maximum 2 par achat',   price: 1000 },
    ]
  },
];

const CI_SURCHARGE = 0;

const quantities = SECTIONS.map(s => new Array(s.items.length).fill(0));
let ciActive = false;

const actsList = document.getElementById('actsList');
const ciRow    = actsList.querySelector('.ci-row');

SECTIONS.forEach((section, si) => {
  const wrap = document.createElement('div');
  wrap.className = 'billing-section';

  const head = document.createElement('div');
  head.className = 'section-head';
  head.innerHTML = `
    <div class="section-title">${section.icon} ${section.title}</div>
    <div style="display:flex;align-items:center;gap:8px;">
      <span class="section-subtotal zero" id="subtotal-${si}">$0</span>
      <span class="section-chevron" id="chev-${si}">▼</span>
    </div>
  `;

  const body = document.createElement('div');
  body.className = 'section-body';
  body.id = `body-${si}`;

  head.addEventListener('click', () => {
    body.classList.toggle('open');
    document.getElementById(`chev-${si}`).classList.toggle('open');
  });

  section.items.forEach((item, ii) => {
    const row = document.createElement('div');
    row.className = 'act-row';
    row.id = `row-${si}-${ii}`;
    row.innerHTML = `
      <div class="act-label">${item.label}</div>
      <div class="act-price">$${item.price.toLocaleString('fr-FR')}</div>
      <div class="act-qty-wrap">
        <button class="qty-btn" data-action="dec" data-si="${si}" data-ii="${ii}">−</button>
        <span class="qty-display" id="qty-${si}-${ii}">0</span>
        <button class="qty-btn" data-action="inc" data-si="${si}" data-ii="${ii}">+</button>
      </div>
      <div class="act-total zero" id="total-${si}-${ii}">$0</div>
    `;
    body.appendChild(row);
  });

  wrap.appendChild(head);
  wrap.appendChild(body);

  actsList.appendChild(wrap);
});

actsList.addEventListener('click', e => {
  const btn = e.target.closest('[data-action]');
  if (!btn) return;
  const si = parseInt(btn.dataset.si);
  const ii = parseInt(btn.dataset.ii);
  const act = btn.dataset.action;

  if (act === 'inc') quantities[si][ii]++;
  if (act === 'dec') quantities[si][ii] = Math.max(0, quantities[si][ii] - 1);

  updateRow(si, ii);
  updateTotals();
});

function updateRow(si, ii) {
  const qty   = quantities[si][ii];
  const price = SECTIONS[si].items[ii].price;
  const total = qty * price;

  document.getElementById(`qty-${si}-${ii}`).textContent = qty;

  const totalEl = document.getElementById(`total-${si}-${ii}`);
  totalEl.textContent = total > 0 ? '$' + total.toLocaleString('fr-FR') : '$0';
  totalEl.className   = 'act-total' + (total > 0 ? '' : ' zero');

  const rowEl = document.getElementById(`row-${si}-${ii}`);
  rowEl.classList.toggle('has-qty', qty > 0);
}

const ciToggle = document.getElementById('ciToggle');
const ciAmount = document.getElementById('ciAmount');
ciToggle.addEventListener('click', () => {
  ciActive = !ciActive;
  ciToggle.classList.toggle('on', ciActive);
  ciAmount.style.display = ciActive ? '' : 'none';
  updateTotals();
});

function updateTotals() {
  let grand = ciActive ? CI_SURCHARGE : 0;
  const lines = [];

  if (ciActive) lines.push({ name: 'Majoration CI non présentée', val: CI_SURCHARGE });

  SECTIONS.forEach((section, si) => {
    let sectionTotal = 0;
    section.items.forEach((item, ii) => {
      const qty   = quantities[si][ii];
      const total = qty * item.price;
      sectionTotal += total;
      if (qty > 0) {
        lines.push({ name: `${item.label}${qty > 1 ? ' ×'+qty : ''}`, val: total });
      }
    });
    grand += sectionTotal;

    const subtEl = document.getElementById(`subtotal-${si}`);
    if (subtEl) {
      subtEl.textContent = sectionTotal > 0 ? '$' + sectionTotal.toLocaleString('fr-FR') : '$0';
      subtEl.className   = 'section-subtotal' + (sectionTotal > 0 ? '' : ' zero');
    }
  });

  document.getElementById('grandTotal').textContent = '$' + grand.toLocaleString('fr-FR');

  const breakdownEl = document.getElementById('breakdown');
  const emptyEl     = document.getElementById('emptyBreakdown');
  const countEl     = document.getElementById('actCount');

  if (lines.length === 0) {
    breakdownEl.innerHTML = '<div class="total-empty" id="emptyBreakdown">Aucun acte sélectionné</div>';
  } else {
    breakdownEl.innerHTML = lines.map(l => `
      <div class="breakdown-line">
        <span class="breakdown-name">${l.name}</span>
        <span class="breakdown-val">$${l.val.toLocaleString('fr-FR')}</span>
      </div>
    `).join('');
  }

  countEl.textContent = lines.length + ' acte' + (lines.length > 1 ? 's' : '') + ' sélectionné' + (lines.length > 1 ? 's' : '');
}

function resetAll() {
  SECTIONS.forEach((section, si) => {
    section.items.forEach((_, ii) => {
      quantities[si][ii] = 0;
      updateRow(si, ii);
    });
  });
  ciActive = false;
  ciToggle.classList.remove('on');
  ciAmount.style.display = 'none';
  document.getElementById('patientName').value  = '';
  document.getElementById('dossierRef').value   = '';
  document.getElementById('billingDate').value  = '';
  updateTotals();
}

document.getElementById('btnReset').addEventListener('click',  resetAll);
document.getElementById('btnReset2').addEventListener('click', resetAll);

document.getElementById('billingDate').value = new Date().toISOString().slice(0,10);

updateTotals();

})();
</script>

<?php samr_layout_end(); ?>
