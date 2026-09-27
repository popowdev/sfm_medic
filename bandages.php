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

samr_layout_start('SAMR — Vente de bandages');
?>

<style>
/* ── En-tête ── */
.band-header { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px; }

/* ── Formulaire de vente ── */
.sale-form-card {
  background: linear-gradient(160deg,rgba(13,18,25,.95),rgba(10,15,21,.98));
  border: 1px solid rgba(0,212,170,.2);
  border-radius: 16px; padding: 20px; margin-bottom: 20px;
}
.sale-form-title {
  font-family: var(--font-head); font-size: 14px; font-weight: 700;
  margin-bottom: 16px; display:flex;align-items:center;gap:8px;
  color: var(--accent2);
}
.sale-grid { display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px; }
@media(max-width:580px){ .sale-grid { grid-template-columns:1fr; } }
.field-label { font-size:10px;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:6px; }

/* Sélecteur quantité */
.qty-selector { display:flex;align-items:center;gap:0;border:1px solid var(--line2);border-radius:9px;overflow:hidden;width:fit-content; }
.qty-btn { width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.03);border:none;color:var(--muted);font-size:18px;font-weight:700;cursor:pointer;transition:background .12s;font-family:var(--font-body); }
.qty-btn:hover { background:rgba(59,158,255,.12);color:var(--accent); }
.qty-btn:disabled { opacity:.3;cursor:default; }
.qty-display { min-width:52px;text-align:center;font-family:var(--font-head);font-size:18px;font-weight:800;color:var(--text);padding:0 4px; }

/* Preview prix */
.price-preview {
  display:flex;align-items:center;justify-content:space-between;
  padding:12px 16px; border-radius:10px;
  background:rgba(0,212,170,.05); border:1px solid rgba(0,212,170,.15);
  margin-bottom:14px;
}
.price-preview .label { font-size:12px;color:var(--muted); }
.price-preview .amount { font-family:var(--font-head);font-size:22px;font-weight:800;color:var(--accent2); }

/* ── Tableau historique global ── */
.history-card { background:linear-gradient(160deg,rgba(13,18,25,.9),rgba(10,15,21,.95));border:1px solid var(--line);border-radius:16px;overflow:hidden; }
.history-card-head { padding:16px 20px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px; }
.history-card-title { font-family:var(--font-head);font-size:14px;font-weight:700; }

/* Lignes tableau */
.sale-row { display:flex;align-items:center;gap:0;padding:13px 20px;border-bottom:1px solid rgba(24,32,48,.6);transition:background .1s; }
.sale-row:last-child { border-bottom:none; }
.sale-row:hover { background:rgba(255,255,255,.02); }
.sr-name { flex:2;min-width:0; }
.sr-name-main { font-size:13px;font-weight:600;color:var(--text); }
.sr-name-date { font-size:11px;color:var(--muted);margin-top:1px; }
.sr-qty { flex:0 0 90px;text-align:center; }
.sr-qty-badge { display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;background:rgba(59,158,255,.08);border:1px solid rgba(59,158,255,.2);color:var(--accent); }
.sr-total { flex:0 0 120px;text-align:right;font-family:var(--font-head);font-size:14px;font-weight:800;color:var(--accent2); }
.sr-actions { flex:0 0 60px;text-align:right; }

/* Stats bandages */
.band-stats { display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:20px; }

/* Filtre recherche */
.filter-row { padding:12px 20px;border-bottom:1px solid var(--line);display:flex;gap:10px;align-items:center;flex-wrap:wrap; }

/* Vue par personne */
.person-section { margin-bottom:2px; }
.person-header-row {
  display:flex;align-items:center;justify-content:space-between;
  padding:12px 20px;cursor:pointer;transition:background .12s;
  border-bottom:1px solid rgba(24,32,48,.4);
}
.person-header-row:hover { background:rgba(59,158,255,.04); }
.person-initials { width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff;flex-shrink:0; }
.person-name-col { flex:1;padding:0 12px; }
.person-name-main { font-size:13px;font-weight:700;color:var(--text); }
.person-name-sub { font-size:11px;color:var(--muted);margin-top:1px; }
.person-total-col { font-family:var(--font-head);font-size:14px;font-weight:800;color:var(--accent2);flex-shrink:0; }
.person-chevron { color:var(--muted);font-size:11px;margin-left:10px;transition:transform .2s;flex-shrink:0; }
.person-chevron.open { transform:rotate(180deg); }
.person-sales-list { display:none;background:rgba(0,0,0,.15); }
.person-sales-list.open { display:block; }
.person-sale-row { display:flex;align-items:center;justify-content:space-between;padding:10px 20px 10px 64px;border-bottom:1px solid rgba(24,32,48,.3);font-size:12px; }
.person-sale-row:last-child { border-bottom:none; }

.empty-state { padding:40px 20px;text-align:center;color:var(--muted);font-size:13px; }
.empty-icon { font-size:36px;margin-bottom:10px; }

.tab-row { display:flex;gap:0;border-bottom:1px solid var(--line); }
.htab { padding:11px 18px;font-size:13px;font-weight:600;color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;background:none;border-top:none;border-left:none;border-right:none;transition:all .12s;font-family:var(--font-body); }
.htab:hover { color:var(--text); }
.htab.active { color:var(--accent);border-bottom-color:var(--accent); }

.toast-band { position:fixed;bottom:24px;right:24px;background:rgba(0,212,170,.12);border:1px solid rgba(0,212,170,.3);color:var(--accent2);padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;z-index:9999;opacity:0;transform:translateY(8px);transition:opacity .25s,transform .25s;pointer-events:none; }
.toast-band.show { opacity:1;transform:none; }
.toast-band.error { background:rgba(255,79,79,.1);border-color:rgba(255,79,79,.3);color:#ff7a7a; }
</style>

<div class="band-header">
  <div class="page-header" style="margin-bottom:0;">
    <h1>🩹 Vente de bandages</h1>
    <div class="sub">1 000$ l'unité · maximum 2 par vente</div>
  </div>
</div>

<!-- Statistiques -->
<div class="band-stats" id="bandStats">
  <div class="stat-card green"><div class="s-label">Ventes totales</div><div class="s-val" id="statTotal">—</div><div class="s-sub">bandages vendus</div></div>
  <div class="stat-card blue"><div class="s-label">Revenus totaux</div><div class="s-val" id="statRevenu">—</div><div class="s-sub">générés</div></div>
  <div class="stat-card"><div class="s-label">Transactions</div><div class="s-val" id="statCount">—</div><div class="s-sub">enregistrées</div></div>
  <div class="stat-card"><div class="s-label">Acheteurs uniques</div><div class="s-val" id="statBuyers">—</div><div class="s-sub">clients</div></div>
</div>

<!-- Formulaire de vente -->
<div class="sale-form-card">
  <div class="sale-form-title">🛒 Enregistrer une vente</div>
  <div class="sale-grid">
    <div>
      <div class="field-label">Nom du patient *</div>
      <input class="inp" id="saleLastName" placeholder="Nom de famille">
    </div>
    <div>
      <div class="field-label">Prénom du patient *</div>
      <input class="inp" id="saleFirstName" placeholder="Prénom">
    </div>
    <div>
      <div class="field-label">Date et heure</div>
      <input class="inp" type="datetime-local" id="saleDate">
    </div>
    <div>
      <div class="field-label">Quantité (max 2)</div>
      <div class="qty-selector">
        <button class="qty-btn" id="qtyMinus">−</button>
        <span class="qty-display" id="qtyDisplay">1</span>
        <button class="qty-btn" id="qtyPlus">+</button>
      </div>
    </div>
  </div>

  <div class="price-preview">
    <span class="label">Total à encaisser</span>
    <span class="amount" id="pricePreview">1 000$</span>
  </div>

  <div style="display:flex;justify-content:flex-end;">
    <button class="btn success" id="btnSaveSale" style="font-size:14px;padding:11px 24px;">💾 Enregistrer la vente</button>
  </div>
</div>

<!-- Historique -->
<div class="history-card">
  <div class="history-card-head">
    <div class="history-card-title">📋 Historique des ventes</div>
    <input class="inp" id="searchSales" placeholder="🔍 Rechercher un patient…" style="width:200px;">
  </div>
  <div class="tab-row">
    <button class="htab active" data-htab="bydate">Par date</button>
    <button class="htab" data-htab="byperson">Par personne</button>
  </div>

  <!-- Vue par date -->
  <div id="viewByDate">
    <div id="saleDateList">
      <div class="empty-state"><div class="empty-icon">🩹</div>Aucune vente enregistrée.</div>
    </div>
  </div>

  <!-- Vue par personne -->
  <div id="viewByPerson" style="display:none;">
    <div id="salePersonList">
      <div class="empty-state"><div class="empty-icon">🩹</div>Aucune vente enregistrée.</div>
    </div>
  </div>
</div>

<div class="toast-band" id="toastBand"></div>

<script>
(function(){
  const CSRF     = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
  const IS_ADMIN = <?= json_encode($isAdmin) ?>;
  const UNIT_PRICE = 1000;
  let qty = 1;
  let allSales = [];
  let activeTab = 'bydate';

  function fmtMoney(n){ return '$'+Number(n).toLocaleString('fr-FR'); }
  function fmtDT(s){ if(!s)return '—'; return new Date(s).toLocaleString('fr-FR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}); }
  function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function showToast(msg, isError=false){
    const t = document.getElementById('toastBand');
    t.textContent = msg;
    t.className = 'toast-band show' + (isError?' error':'');
    setTimeout(()=>{ t.className='toast-band'; }, 3000);
  }

  function initDate(){
    const now = new Date();
    const pad = n => String(n).padStart(2,'0');
    document.getElementById('saleDate').value =
      `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
  }

  function updateQty(){
    document.getElementById('qtyDisplay').textContent = qty;
    document.getElementById('qtyMinus').disabled = qty <= 1;
    document.getElementById('qtyPlus').disabled  = qty >= 2;
    document.getElementById('pricePreview').textContent = fmtMoney(qty * UNIT_PRICE);
  }
  document.getElementById('qtyMinus').addEventListener('click',()=>{ if(qty>1){ qty--; updateQty(); } });
  document.getElementById('qtyPlus').addEventListener('click', ()=>{ if(qty<2){ qty++; updateQty(); } });

  async function apiPost(data){
    const r = await fetch('/api/bandages.php', {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({...data, csrf: CSRF})
    });
    return r.json().catch(()=>({ok:false,error:'Erreur réseau'}));
  }

  async function loadSales(){
    const r = await fetch('/api/bandages.php', {credentials:'same-origin'});
    const d = await r.json().catch(()=>({ok:false,sales:[]}));
    if(d.ok){ allSales = d.sales||[]; updateStats(); renderAll(); }
  }

  function updateStats(){
    const total   = allSales.reduce((s,v)=>s+(parseInt(v.qty)||0), 0);
    const revenu  = allSales.reduce((s,v)=>s+(parseInt(v.total)||0), 0);
    const buyers  = new Set(allSales.map(v=>(v.last_name+v.first_name).toLowerCase())).size;
    document.getElementById('statTotal').textContent  = total;
    document.getElementById('statRevenu').textContent = fmtMoney(revenu);
    document.getElementById('statCount').textContent  = allSales.length;
    document.getElementById('statBuyers').textContent = buyers;
  }

  document.getElementById('btnSaveSale').addEventListener('click', async()=>{
    const ln = document.getElementById('saleLastName').value.trim();
    const fn = document.getElementById('saleFirstName').value.trim();
    if(!ln||!fn){ showToast('Nom et prénom requis.', true); return; }
    const saleDate = document.getElementById('saleDate').value;

    const btn = document.getElementById('btnSaveSale');
    btn.disabled = true;
    const d = await apiPost({action:'add_sale', last_name:ln, first_name:fn, qty, sale_date:saleDate||null});
    btn.disabled = false;

    if(!d.ok){ showToast(d.error||'Erreur.', true); return; }
    showToast(`✅ Vente enregistrée — ${qty} bandage(s) · ${fmtMoney(qty*UNIT_PRICE)}`);
    document.getElementById('saleLastName').value = '';
    document.getElementById('saleFirstName').value = '';
    qty = 1; updateQty(); initDate();
    loadSales();
  });

  function renderAll(){
    const q = document.getElementById('searchSales').value.trim().toLowerCase();
    const filtered = q
      ? allSales.filter(s=>(s.last_name+' '+s.first_name).toLowerCase().includes(q))
      : allSales;
    if(activeTab==='bydate') renderByDate(filtered);
    else renderByPerson(filtered);
  }

  function renderByDate(sales){
    const el = document.getElementById('saleDateList');
    if(!sales.length){
      el.innerHTML='<div class="empty-state"><div class="empty-icon">🩹</div>Aucune vente enregistrée.</div>';
      return;
    }
    el.innerHTML = sales.map(s=>`
      <div class="sale-row">
        <div class="sr-name">
          <div class="sr-name-main">${esc(s.last_name)} ${esc(s.first_name)}</div>
          <div class="sr-name-date">📅 ${fmtDT(s.sale_date)} · par ${esc(s.seller_name||'—')}</div>
        </div>
        <div class="sr-qty">
          <span class="sr-qty-badge">🩹 ${s.qty} bandage${s.qty>1?'s':''}</span>
        </div>
        <div class="sr-total">${fmtMoney(s.total)}</div>
        <div class="sr-actions">
          ${IS_ADMIN?`<button class="btn danger" style="padding:4px 9px;font-size:11px;" onclick="deleteSale(${s.id})">🗑</button>`:''}
        </div>
      </div>
    `).join('');
  }

  function renderByPerson(sales){
    const el = document.getElementById('salePersonList');
    if(!sales.length){
      el.innerHTML='<div class="empty-state"><div class="empty-icon">🩹</div>Aucune vente enregistrée.</div>';
      return;
    }

    const byPerson = {};
    for(const s of sales){
      const key = (s.last_name+' '+s.first_name).toLowerCase();
      if(!byPerson[key]) byPerson[key] = { name:s.last_name+' '+s.first_name, last:s.last_name, first:s.first_name, sales:[], totalQty:0, totalAmt:0 };
      byPerson[key].sales.push(s);
      byPerson[key].totalQty += parseInt(s.qty)||0;
      byPerson[key].totalAmt += parseInt(s.total)||0;
    }

    el.innerHTML = Object.values(byPerson).map((p,pi)=>{
      const initials = ((p.last[0]||'')+(p.first[0]||'')).toUpperCase();
      const subRows = p.sales.map(s=>`
        <div class="person-sale-row">
          <span style="color:var(--muted);">${fmtDT(s.sale_date)}</span>
          <span style="display:flex;align-items:center;gap:8px;">
            <span class="sr-qty-badge" style="font-size:11px;">🩹 ${s.qty}</span>
            <span style="font-family:var(--font-head);font-weight:700;color:var(--accent2);">${fmtMoney(s.total)}</span>
            ${IS_ADMIN?`<button class="btn danger" style="padding:3px 8px;font-size:10px;" onclick="deleteSale(${s.id})">🗑</button>`:''}
          </span>
        </div>
      `).join('');

      return `<div class="person-section">
        <div class="person-header-row" onclick="togglePerson(${pi})">
          <div class="person-initials">${initials}</div>
          <div class="person-name-col">
            <div class="person-name-main">${esc(p.name)}</div>
            <div class="person-name-sub">${p.sales.length} vente(s) · ${p.totalQty} bandage(s)</div>
          </div>
          <div class="person-total-col">${fmtMoney(p.totalAmt)}</div>
          <span class="person-chevron" id="chev-${pi}">▼</span>
        </div>
        <div class="person-sales-list" id="psl-${pi}">${subRows}</div>
      </div>`;
    }).join('');
  }

  window.togglePerson = function(i){
    document.getElementById('psl-'+i).classList.toggle('open');
    document.getElementById('chev-'+i).classList.toggle('open');
  };

  window.deleteSale = async function(id){
    if(!confirm('Supprimer cette vente ?')) return;
    const d = await apiPost({action:'delete_sale', id});
    if(!d.ok){ showToast(d.error||'Erreur.', true); return; }
    showToast('Vente supprimée.');
    loadSales();
  };

  document.querySelectorAll('.htab').forEach(tab=>{
    tab.addEventListener('click',()=>{
      activeTab = tab.dataset.htab;
      document.querySelectorAll('.htab').forEach(t=>t.classList.toggle('active',t===tab));
      document.getElementById('viewByDate').style.display   = activeTab==='bydate'   ? '' : 'none';
      document.getElementById('viewByPerson').style.display = activeTab==='byperson' ? '' : 'none';
      renderAll();
    });
  });

  let st;
  document.getElementById('searchSales').addEventListener('input',()=>{ clearTimeout(st); st=setTimeout(renderAll,250); });

  initDate();
  updateQty();
  loadSales();
})();
</script>

<?php samr_layout_end(); ?>
