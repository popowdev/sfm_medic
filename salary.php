<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';

require_login();
$u = current_user();
if (($u['role'] ?? '') !== 'admin') { http_response_code(403); exit('Accès refusé.'); }

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

samr_layout_start('SAMR - Salaires');
?>

<div class="page-header">
  <h1>💲 Gestion des salaires</h1>
  <div class="sub">Suivi des heures par agent — semaine débutant le dimanche</div>
</div>

<div class="card" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
  <label style="flex:1;min-width:140px;">
    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:6px;">Du (dimanche)</div>
    <input class="inp" type="date" id="fromDate">
  </label>
  <label style="flex:1;min-width:140px;">
    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:6px;">Au (samedi)</div>
    <input class="inp" type="date" id="toDate">
  </label>
  <button class="btn" id="btnLoad">🔄 Charger</button>
</div>

<div id="statsRow" class="stats-grid" style="display:none;"></div>

<div class="card" id="salaryCard" style="display:none;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
    <div style="font-family:var(--font-head);font-size:14px;font-weight:700;">Récapitulatif par agent</div>
    <button class="btn secondary" id="btnToggleHistory" style="font-size:12px;">Voir historique détaillé</button>
  </div>
  <div id="summaryBody" style="display:flex;flex-direction:column;gap:8px;"></div>
</div>

<!-- Modal détail shifts d'un agent -->
<div id="shiftModal" style="display:none;position:fixed;inset:0;z-index:9999;">
  <div id="shiftBackdrop" style="position:absolute;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);"></div>
  <div style="position:relative;width:min(620px,calc(100% - 32px));margin:5vh auto 0;background:linear-gradient(160deg,#0d1219,#080c10);border:1px solid var(--line2);border-radius:18px;padding:22px;box-shadow:0 32px 80px rgba(0,0,0,.6);max-height:88vh;display:flex;flex-direction:column;">

    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;gap:10px;">
      <div>
        <div style="font-family:var(--font-head);font-size:16px;font-weight:700;" id="shiftModalName">—</div>
        <div style="font-size:12px;color:var(--muted);margin-top:3px;" id="shiftModalMeta">—</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="btn success" id="btnAddShift" style="font-size:12px;padding:7px 12px;">➕ Ajouter</button>
        <button class="btn secondary" id="btnCloseShift" style="padding:6px 10px;">✕</button>
      </div>
    </div>

    <!-- Liste des shifts -->
    <div id="shiftList" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:6px;"></div>

    <div id="shiftErr" style="margin-top:10px;padding:10px;border-radius:9px;background:rgba(255,79,79,.08);border:1px solid rgba(255,79,79,.25);color:#ff7a7a;font-size:13px;display:none;"></div>

    <!-- Formulaire ajout/édition inline -->
    <div id="shiftForm" style="display:none;margin-top:14px;padding-top:14px;border-top:1px solid var(--line);">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:10px;" id="shiftFormTitle">Nouveau service</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
        <label>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:5px;">Début</div>
          <input class="inp" type="datetime-local" id="shiftStart">
        </label>
        <label>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:5px;">Fin</div>
          <input class="inp" type="datetime-local" id="shiftEnd">
        </label>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button class="btn secondary" id="btnCancelShift" style="font-size:12px;">Annuler</button>
        <button class="btn success" id="btnSaveShift" style="font-size:12px;">Enregistrer</button>
      </div>
    </div>

  </div>
</div>

<div class="card" id="historyCard" style="display:none;">
  <div style="font-family:var(--font-head);font-size:14px;font-weight:700;margin-bottom:14px;">Historique des services</div>
  <table class="data-table" id="historyTable">
    <thead>
      <tr>
        <th>Agent</th>
        <th>Début</th>
        <th>Fin</th>
        <th>Durée</th>
        <th style="text-align:right;">Montant</th>
      </tr>
    </thead>
    <tbody id="historyBody"></tbody>
  </table>
</div>

<div id="loadErr" class="muted" style="display:none;color:#ff7a7a;padding:12px;"></div>

<script>
(function(){
  const CSRF = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;

  function getWeekBounds() {
    const now = new Date();
    const dow = now.getDay();
    const sun = new Date(now); sun.setDate(now.getDate() - dow); sun.setHours(0,0,0,0);
    const sat = new Date(sun); sat.setDate(sun.getDate() + 6);
    return [sun, sat];
  }
  function toInputDate(d) { return d.toISOString().slice(0,10); }
  function fmtDuration(s) {
    const h = Math.floor(s/3600), m = Math.floor((s%3600)/60);
    return h+'h'+String(m).padStart(2,'0');
  }
  function fmtMoney(n) { return '$'+Number(n).toLocaleString('fr-FR'); }
  function fmtDt(s) {
    if (!s) return '—';
    return new Date(s).toLocaleString('fr-FR',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
  }
  function toInputDt(s) {
    if (!s) return '';
    const d = new Date(s);
    const pad = n => String(n).padStart(2,'0');
    return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes());
  }
  function toLocalIso(v) {
    if (!v) return '';
    const d = new Date(v);
    const pad = n => String(n).padStart(2,'0');
    return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+' '+pad(d.getHours())+':'+pad(d.getMinutes())+':00';
  }
  function secBetween(a, b) {
    return Math.max(0, (new Date(b||'9999')-new Date(a))/1000);
  }

  async function api(payload) {
    const r = await fetch('/api/salary.php', {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({...payload, csrf: CSRF})
    });
    const d = await r.json().catch(()=>null);
    if (!r.ok) throw new Error(d?.error || 'Erreur API');
    return d;
  }

  const [sun, sat] = getWeekBounds();
  document.getElementById('fromDate').value = toInputDate(sun);
  document.getElementById('toDate').value   = toInputDate(sat);

  let showHistory = false;
  let currentPeriod = { from: toInputDate(sun), to: toInputDate(sat) };
  let lastData = null;

  document.getElementById('btnToggleHistory').addEventListener('click', () => {
    showHistory = !showHistory;
    document.getElementById('historyCard').style.display = showHistory ? '' : 'none';
    document.getElementById('btnToggleHistory').textContent = showHistory ? 'Masquer historique' : 'Voir historique détaillé';
  });

  async function load() {
    const from = document.getElementById('fromDate').value;
    const to   = document.getElementById('toDate').value;
    currentPeriod = { from, to };
    const err  = document.getElementById('loadErr');
    err.style.display = 'none';

    try {
      const resp = await fetch(`/api/salary.php?from=${from}&to=${to}`, { credentials:'same-origin' });
      const data = await resp.json();
      if (!data.ok) throw new Error(data.error || 'Erreur API');
      lastData = data;

      const statsRow = document.getElementById('statsRow');
      const grand = data.grand || {};
      statsRow.style.display = '';
      statsRow.innerHTML = `
        <div class="stat-card green">
          <div class="s-label">Salaire total</div>
          <div class="s-val">${fmtMoney(grand.amount||0)}</div>
          <div class="s-sub">sur la période</div>
        </div>
        <div class="stat-card blue">
          <div class="s-label">Temps total</div>
          <div class="s-val">${fmtDuration(grand.seconds||0)}</div>
          <div class="s-sub">toutes équipes</div>
        </div>
        <div class="stat-card">
          <div class="s-label">Agents actifs</div>
          <div class="s-val">${(data.active||[]).length}</div>
          <div class="s-sub">en ce moment</div>
        </div>
        <div class="stat-card">
          <div class="s-label">Agents rémunérés</div>
          <div class="s-val">${(data.people||[]).length}</div>
          <div class="s-sub">sur la période</div>
        </div>
      `;

      renderAgentCards(data.people || []);
      document.getElementById('salaryCard').style.display = '';

      const hb = document.getElementById('historyBody');
      hb.innerHTML = '';
      for (const s of (data.history||[])) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${s.person_name}</td>
          <td>${fmtDt(s.started_at)}</td>
          <td>${s.ended_at ? fmtDt(s.ended_at) : '<span class="pill pill-green">En cours</span>'}</td>
          <td class="muted">${fmtDuration(s.seconds||0)}</td>
          <td style="text-align:right;font-weight:600;color:var(--accent2);">${fmtMoney(s.amount||0)}</td>
        `;
        hb.appendChild(tr);
      }
      if (showHistory) document.getElementById('historyCard').style.display = '';

    } catch(e) {
      err.textContent = '⚠ '+e.message;
      err.style.display = 'block';
    }
  }

  const PRIME_RATES = {
    'Ambulancier':       15000,
    'Chef Ambulancier':  15000,
    'Infirmier':         20000,
    'Infirmier en chef': 25000,
    'Médecin':           30000,
    'Médecin en chef':   30000,
    "Chef d'équipe":     35000,
    'Co-Directeur':      50000,
    'Directeur':         50000,
  };

  function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
      const orig = btn.innerHTML;
      btn.innerHTML = '✅';
      btn.style.color = 'var(--accent2)';
      setTimeout(() => { btn.innerHTML = orig; btn.style.color = ''; }, 1800);
    }).catch(() => {
      const ta = document.createElement('textarea');
      ta.value = text; ta.style.cssText = 'position:fixed;opacity:0;';
      document.body.appendChild(ta); ta.select(); document.execCommand('copy');
      document.body.removeChild(ta);
      const orig = btn.innerHTML;
      btn.innerHTML = '✅';
      setTimeout(() => { btn.innerHTML = orig; }, 1800);
    });
  }

  function renderAgentCards(people) {
    const sb = document.getElementById('summaryBody');
    sb.innerHTML = '';
    for (const p of people) {
      const primeAmt = PRIME_RATES[p.grade] ?? null;
      const hasIban  = p.iban && p.iban.trim() !== '';

      const card = document.createElement('div');
      card.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-radius:12px;border:1px solid var(--line);background:rgba(255,255,255,.02);gap:16px;flex-wrap:wrap;transition:border-color .15s,background .15s;';
      card.innerHTML = `
        <div style="min-width:0;flex:1;cursor:pointer;" class="cz">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
            <span style="font-weight:800;font-size:14px;">${p.person_name}</span>
            <span class="pill" style="font-size:11px;">${p.grade||'—'}</span>
          </div>
          <div style="display:flex;gap:18px;flex-wrap:wrap;">
            <div>
              <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:2px;">Taux</div>
              <div style="font-size:13px;font-weight:600;color:var(--muted);">${p.rate}$/h</div>
            </div>
            <div>
              <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:2px;">Temps</div>
              <div style="font-size:13px;font-weight:600;">${fmtDuration(p.seconds||0)}</div>
            </div>
          </div>
        </div>

        <div style="display:flex;gap:20px;align-items:flex-end;flex-shrink:0;text-align:right;flex-wrap:wrap;">

          <div>
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:4px;">IBAN</div>
            <div style="display:flex;align-items:center;gap:6px;">
              <span style="font-size:13px;font-weight:600;font-family:monospace;">${hasIban ? p.iban : '<span style="color:var(--muted);font-style:italic;">—</span>'}</span>
              ${hasIban ? `<button class="btn-copy-iban" data-iban="${p.iban}" title="Copier l'IBAN"
                style="background:none;border:none;cursor:pointer;font-size:14px;padding:2px 5px;border-radius:5px;color:var(--muted);transition:color .12s;line-height:1;">📄</button>` : ''}
            </div>
          </div>

          <div>
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:4px;">Prime</div>
            ${primeAmt !== null
              ? `<button class="btn-prime"
                  data-name="${p.person_name}" data-iban="${p.iban||''}"
                  data-amt="${primeAmt}" data-grade="${p.grade||''}"
                  style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:8px;
                         border:1px solid rgba(255,176,96,.3);background:rgba(255,176,96,.07);
                         color:#ffb060;font-size:12px;font-weight:700;cursor:pointer;
                         font-family:var(--font-body);transition:all .12s;">
                  🎁 ${fmtMoney(primeAmt)}
                </button>`
              : `<span style="font-size:12px;color:var(--muted);">—</span>`
            }
          </div>

          <div style="cursor:pointer;" class="cz">
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:2px;">Salaire</div>
            <div style="font-size:20px;font-weight:800;font-family:var(--font-head);color:var(--accent2);">${fmtMoney(p.amount||0)}</div>
          </div>
          <div style="color:var(--muted);font-size:18px;opacity:.4;cursor:pointer;" class="cz">›</div>
        </div>
      `;

      card.querySelectorAll('.cz').forEach(z => z.addEventListener('click', () => openShiftModal(p)));

      const copyBtn = card.querySelector('.btn-copy-iban');
      if (copyBtn) {
        copyBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          copyToClipboard(copyBtn.dataset.iban, copyBtn);
        });
      }

      const primeBtn = card.querySelector('.btn-prime');
      if (primeBtn) {
        primeBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          openPrimeModal({ name: primeBtn.dataset.name, iban: primeBtn.dataset.iban, amt: parseInt(primeBtn.dataset.amt), grade: primeBtn.dataset.grade, user_id: p.user_id });
        });
      }

      card.addEventListener('mouseenter', ()=>{ card.style.borderColor='rgba(59,158,255,.3)'; card.style.background='rgba(59,158,255,.04)'; });
      card.addEventListener('mouseleave', ()=>{ card.style.borderColor='var(--line)'; card.style.background='rgba(255,255,255,.02)'; });
      sb.appendChild(card);
    }
  }

  function openPrimeModal(p) {
    const existing = document.getElementById('primeModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'primeModal';
    modal.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(0,0,0,.65);backdrop-filter:blur(5px);';
    modal.innerHTML = `
      <div style="width:min(460px,100%);background:linear-gradient(160deg,#0d1219,#080c10);border:1px solid rgba(255,176,96,.3);border-radius:18px;padding:24px;box-shadow:0 40px 80px rgba(0,0,0,.6);">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;">
          <div style="font-size:32px;">🎁</div>
          <div>
            <div style="font-family:var(--font-head);font-size:16px;font-weight:800;">Verser une prime</div>
            <div style="font-size:12px;color:var(--muted);margin-top:2px;">${p.name} · ${p.grade}</div>
          </div>
        </div>
        <div style="background:rgba(255,176,96,.05);border:1px solid rgba(255,176,96,.15);border-radius:12px;padding:16px;margin-bottom:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <span style="font-size:12px;color:var(--muted);">Montant de la prime</span>
            <span style="font-family:var(--font-head);font-size:22px;font-weight:800;color:#ffb060;">${fmtMoney(p.amt)}</span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:12px;color:var(--muted);">IBAN destinataire</span>
            <div style="display:flex;align-items:center;gap:6px;">
              <span style="font-size:13px;font-weight:600;font-family:monospace;color:var(--text);">${p.iban||'<span style="color:var(--muted);font-style:italic;">Non renseigné</span>'}</span>
              ${p.iban ? `<button id="primeCopyIban" title="Copier" style="background:none;border:none;cursor:pointer;font-size:14px;padding:2px 5px;color:var(--muted);line-height:1;">📄</button>` : ''}
            </div>
          </div>
        </div>
        <div style="font-size:12px;color:var(--muted);line-height:1.7;margin-bottom:18px;">
          Confirmez le versement de la prime de <strong style="color:#ffb060;">${fmtMoney(p.amt)}</strong> à <strong style="color:var(--text);">${p.name}</strong>.<br>
          Le paiement RP doit être effectué manuellement en jeu.
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
          <button id="primeCancelBtn" class="btn secondary" style="font-size:13px;">Annuler</button>
          <button id="primeConfirmBtn" style="display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border-radius:9px;border:1px solid rgba(255,176,96,.4);background:rgba(255,176,96,.12);color:#ffb060;font-size:13px;font-weight:700;cursor:pointer;font-family:var(--font-body);transition:all .12s;">
            ✅ Confirmer la prime
          </button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    const ibanCopyBtn = modal.querySelector('#primeCopyIban');
    if (ibanCopyBtn) ibanCopyBtn.addEventListener('click', () => copyToClipboard(p.iban, ibanCopyBtn));

    modal.querySelector('#primeCancelBtn').addEventListener('click', () => modal.remove());
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });

    modal.querySelector('#primeConfirmBtn').addEventListener('click', async () => {
      const btn = modal.querySelector('#primeConfirmBtn');
      btn.disabled = true;
      btn.innerHTML = '⏳ Enregistrement…';

      try {
        await api({ action: 'add_prime', user_id: p.user_id, amount: p.amt });

        btn.innerHTML = '✅ Prime notée !';
        btn.style.cssText += 'background:rgba(0,212,170,.12);border-color:rgba(0,212,170,.3);color:var(--accent2);';

        if (p.iban) copyToClipboard(p.iban, { innerHTML: '', style: {} });

        setTimeout(async () => {
          modal.remove();
          await load();
        }, 1600);

      } catch(e) {
        btn.disabled = false;
        btn.innerHTML = '✅ Confirmer la prime';
        const errDiv = document.createElement('div');
        errDiv.style.cssText = 'color:#ff7a7a;font-size:12px;margin-top:8px;text-align:right;';
        errDiv.textContent = '⚠ ' + e.message;
        btn.parentNode.appendChild(errDiv);
      }
    });
  }

  const shiftModal   = document.getElementById('shiftModal');
  const shiftList    = document.getElementById('shiftList');
  const shiftErr     = document.getElementById('shiftErr');
  const shiftForm    = document.getElementById('shiftForm');
  const shiftStart   = document.getElementById('shiftStart');
  const shiftEnd     = document.getElementById('shiftEnd');
  const shiftFormTitle = document.getElementById('shiftFormTitle');
  const btnSaveShift = document.getElementById('btnSaveShift');

  let modalPerson    = null;
  let editingShiftId = null;

  function showShiftErr(msg) { shiftErr.textContent=msg; shiftErr.style.display='block'; }
  function clearShiftErr()   { shiftErr.textContent='';  shiftErr.style.display='none'; }

  async function openShiftModal(person) {
    modalPerson    = person;
    editingShiftId = null;
    shiftForm.style.display = 'none';
    clearShiftErr();

    document.getElementById('shiftModalName').textContent = person.person_name;
    document.getElementById('shiftModalMeta').textContent =
      (person.grade||'—') + ' • ' + person.rate + '$/h • IBAN : ' + (person.iban||'—');

    shiftModal.style.display = 'block';
    await loadShifts();
  }

  async function loadShifts() {
    shiftList.innerHTML = '<div class="muted" style="padding:10px 0;font-size:13px;">Chargement…</div>';
    clearShiftErr();
    try {
      const { from, to } = currentPeriod;
      const uid = modalPerson.user_id;
      const resp = await fetch(
        `/api/salary.php?admin_user_id=${uid}&from=${from}&to=${to}`,
        { credentials: 'same-origin' }
      );
      const d = await resp.json().catch(()=>null);
      if (!d || !d.ok) throw new Error(d?.error || 'Erreur API');
      renderShifts(d.shifts || []);
    } catch(e) {
      showShiftErr(e.message);
      shiftList.innerHTML = '';
    }
  }

  function renderShifts(shifts) {
    shiftList.innerHTML = '';
    if (!shifts.length) {
      shiftList.innerHTML = '<div class="muted" style="padding:10px 0;font-size:13px;">Aucun service sur cette période.</div>';
      return;
    }
    const rate = modalPerson.rate || 0;
    for (const s of shifts) {
      const sec    = secBetween(s.started_at, s.ended_at);
      const amount = Math.round((sec/3600)*rate);
      const active = !s.ended_at;

      const row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:rgba(255,255,255,.02);gap:12px;flex-wrap:wrap;';
      row.innerHTML = `
        <div style="flex:1;min-width:0;">
          <div style="font-size:12px;font-weight:600;margin-bottom:2px;">
            ${fmtDt(s.started_at)}
            <span style="color:var(--muted);font-weight:400;"> → </span>
            ${active ? '<span style="color:var(--accent2);font-weight:700;">En cours</span>' : fmtDt(s.ended_at)}
          </div>
          <div style="font-size:11px;color:var(--muted);">
            ${fmtDuration(sec)}
            <span style="margin-left:10px;color:var(--accent2);font-weight:700;">${fmtMoney(amount)}</span>
          </div>
        </div>
        <div style="display:flex;gap:6px;flex-shrink:0;">
          <button class="btn secondary" style="padding:5px 10px;font-size:11px;" data-edit="${s.id}">✏️</button>
          <button class="btn danger" style="padding:5px 10px;font-size:11px;" data-del="${s.id}">🗑</button>
        </div>
      `;

      row.querySelector('[data-edit]').addEventListener('click', ()=> openEditShift(s));
      row.querySelector('[data-del]').addEventListener('click',  ()=> deleteShift(s.id));
      shiftList.appendChild(row);
    }
  }

  function openEditShift(shift) {
    editingShiftId     = shift.id;
    shiftFormTitle.textContent = 'Modifier le service';
    shiftStart.value   = toInputDt(shift.started_at);
    shiftEnd.value     = shift.ended_at ? toInputDt(shift.ended_at) : '';
    shiftForm.style.display = 'block';
    clearShiftErr();
    shiftStart.focus();
  }

  document.getElementById('btnAddShift').addEventListener('click', ()=>{
    editingShiftId     = null;
    shiftFormTitle.textContent = 'Nouveau service';
    shiftStart.value   = '';
    shiftEnd.value     = '';
    shiftForm.style.display = 'block';
    clearShiftErr();
    shiftStart.focus();
  });

  document.getElementById('btnCancelShift').addEventListener('click', ()=>{
    shiftForm.style.display = 'none';
    editingShiftId = null;
    clearShiftErr();
  });

  btnSaveShift.addEventListener('click', async ()=>{
    clearShiftErr();
    const started = shiftStart.value;
    const ended   = shiftEnd.value;
    if (!started) return showShiftErr('Date de début requise.');
    if (ended && ended <= started) return showShiftErr('La fin doit être après le début.');

    btnSaveShift.disabled = true;
    try {
      if (editingShiftId) {
        await api({ action:'admin_update_shift', shift_id:editingShiftId, started_at:toLocalIso(started), ended_at:ended?toLocalIso(ended):'' });
      } else {
        await api({ action:'admin_create_shift', user_id:modalPerson.user_id, started_at:toLocalIso(started), ended_at:ended?toLocalIso(ended):'' });
      }
      shiftForm.style.display = 'none';
      editingShiftId = null;
      await loadShifts();
      await load();
    } catch(e) {
      showShiftErr(e.message);
    } finally {
      btnSaveShift.disabled = false;
    }
  });

  async function deleteShift(id) {
    if (!confirm('Supprimer ce service ?')) return;
    clearShiftErr();
    try {
      await api({ action:'admin_delete_shift', shift_id:id });
      await loadShifts();
      await load();
    } catch(e) {
      showShiftErr(e.message);
    }
  }

  document.getElementById('btnCloseShift').addEventListener('click',  ()=> shiftModal.style.display='none');
  document.getElementById('shiftBackdrop').addEventListener('click',  ()=> shiftModal.style.display='none');
  document.addEventListener('keydown', e=>{ if(e.key==='Escape') shiftModal.style.display='none'; });

  document.getElementById('btnLoad').addEventListener('click', load);
  load();
})();
</script>

<?php samr_layout_end(); ?>
