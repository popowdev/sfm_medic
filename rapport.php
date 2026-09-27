<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_login();
$u = current_user();

samr_layout_start('Rapports vocaux — SAMR');
?>

<style>
  .rapport-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    flex-wrap: wrap;
    gap: 10px;
  }

  /* ── Micro ── */
  .mic-zone {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    padding: 28px 20px;
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    margin-bottom: 20px;
  }
  .mic-btn {
    width: 72px; height: 72px;
    border-radius: 50%;
    border: 2px solid var(--line2);
    background: rgba(59,158,255,.08);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.2s, border-color 0.2s, box-shadow 0.2s;
    font-size: 28px;
  }
  .mic-btn:hover { background: rgba(59,158,255,.14); }
  .mic-btn.listening {
    background: rgba(255,79,79,.1);
    border-color: rgba(255,79,79,.5);
    box-shadow: 0 0 0 6px rgba(255,79,79,.12), 0 0 24px rgba(255,79,79,.2);
    animation: pulse 1.4s infinite;
  }
  @keyframes pulse {
    0%   { box-shadow: 0 0 0 0   rgba(255,79,79,.35), 0 0 24px rgba(255,79,79,.2); }
    70%  { box-shadow: 0 0 0 14px rgba(255,79,79,0),  0 0 24px rgba(255,79,79,.08); }
    100% { box-shadow: 0 0 0 0   rgba(255,79,79,0),  0 0 24px rgba(255,79,79,.2); }
  }
  .mic-status { font-size: 13px; color: var(--muted); letter-spacing: .03em; }
  .mic-status.active { color: var(--danger); }
  .live-preview {
    min-height: 36px; width: 100%; max-width: 600px;
    text-align: center; font-size: 13px; color: var(--accent);
    font-style: italic;
    background: rgba(59,158,255,.04);
    border: 1px dashed rgba(59,158,255,.2);
    border-radius: 9px;
    padding: 10px 14px;
    display: none;
  }
  .lang-select-wrap {
    display: flex; align-items: center; gap: 8px;
    font-size: 12px; color: var(--muted);
  }
  .lang-select-wrap select { width: auto; padding: 6px 10px; font-size: 12px; }

  /* ── Transcription ── */
  .transcript-card {
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 18px; margin-bottom: 20px;
  }
  .transcript-card h3 { margin: 0 0 12px; font-size: 13px; color: var(--muted); display:flex;align-items:center;gap:8px; }
  #transcriptBox {
    width: 100%; min-height: 180px; resize: vertical;
    background: var(--panel2); color: var(--text);
    border: 1px solid var(--line2); border-radius: 9px;
    padding: 12px; font-size: 13px; line-height: 1.6;
    box-sizing: border-box; font-family: var(--font-body);
  }
  #transcriptBox:focus { outline: none; border-color: rgba(59,158,255,.4); }
  .word-count { font-size: 11px; color: var(--muted); margin-top: 6px; text-align: right; }
  .transcript-actions { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }

  /* ── Save section ── */
  .save-section {
    background: var(--panel); border: 1px solid var(--line);
    border-radius: var(--radius); padding: 18px; margin-bottom: 20px;
  }
  .save-section h3 { margin: 0 0 14px; font-size: 13px; color: var(--muted); }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
  @media(max-width:580px){ .form-row { grid-template-columns: 1fr; } }
  .form-group { display: flex; flex-direction: column; gap: 5px; }
  .form-group label { font-size: 11px; color: var(--muted); text-transform:uppercase;letter-spacing:.6px; }
  .form-group input, .form-group select {
    padding: 9px 12px; border-radius: 9px;
    border: 1px solid var(--line2); background: var(--panel2);
    color: var(--text); font-size: 13px; font-family: var(--font-body);
  }
  .form-group input:focus, .form-group select:focus { outline:none; border-color:rgba(59,158,255,.4); }

  /* ── History ── */
  .history-section {
    background: var(--panel); border: 1px solid var(--line);
    border-radius: var(--radius); padding: 18px;
  }
  .history-section h3 { margin: 0 0 14px; font-size: 13px; color: var(--muted); }
  .rapport-item {
    border: 1px solid var(--line); border-radius: 10px;
    padding: 14px; margin-bottom: 10px; background: var(--panel2);
  }
  .rapport-item-header { display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:8px; }
  .rapport-item-title { font-weight: 600; color: var(--text); font-size: 13px; }
  .rapport-item-meta { font-size: 11px; color: var(--muted); margin-top: 2px; }
  .rapport-item-body {
    font-size: 12px; color: #8aa8c0; line-height: 1.55;
    white-space: pre-wrap; max-height: 100px; overflow: hidden;
    position: relative; cursor: pointer; transition: max-height 0.3s;
  }
  .rapport-item-body.expanded { max-height: none; }
  .rapport-item-body:not(.expanded)::after {
    content: ''; position: absolute; bottom:0;left:0;right:0;height:36px;
    background: linear-gradient(transparent, var(--panel2));
  }
  .rapport-item-actions { display:flex;gap:8px;margin-top:10px;flex-wrap:wrap; }

  .no-support {
    background: rgba(255,79,79,.06); border: 1px solid rgba(255,79,79,.25);
    border-radius: 10px; padding: 14px; color: var(--danger);
    font-size: 13px; text-align: center; display: none; margin-bottom:16px;
  }

  .toast {
    position: fixed; bottom: 24px; right: 24px;
    background: rgba(0,212,170,.12); border: 1px solid rgba(0,212,170,.3); color: var(--accent2);
    padding: 12px 20px; border-radius: 12px; font-size: 13px;
    z-index: 9999; opacity: 0; transform: translateY(10px);
    transition: opacity 0.3s, transform 0.3s; pointer-events: none;
  }
  .toast.show { opacity: 1; transform: translateY(0); }
  .toast.error { background: rgba(255,79,79,.1); border-color: rgba(255,79,79,.3); color: var(--danger); }
</style>

<div class="page-header">
  <h1>🎙️ Rapports vocaux</h1>
  <div class="sub">Dictez votre rapport à voix haute — la transcription se fait en temps réel</div>
</div>

<div class="no-support" id="noSupport">
  ⚠️ Votre navigateur ne supporte pas la reconnaissance vocale. Utilisez Chrome ou Edge.
</div>

<!-- Zone microphone -->
<div class="mic-zone">
  <div class="lang-select-wrap" style="align-self:flex-end;">
    <span>Langue :</span>
    <select id="langSelect" class="inp">
      <option value="fr-FR" selected>🇫🇷 Français</option>
      <option value="en-US">🇺🇸 English</option>
      <option value="es-ES">🇪🇸 Español</option>
    </select>
  </div>
  <button class="mic-btn" id="micBtn" title="Démarrer / Arrêter la dictée">🎙️</button>
  <div class="mic-status" id="micStatus">Cliquez pour démarrer la dictée</div>
  <div class="live-preview" id="livePreview"></div>
</div>

<!-- Zone transcription -->
<div class="transcript-card">
  <h3>📝 Transcription</h3>
  <textarea id="transcriptBox" class="inp" placeholder="Votre rapport apparaîtra ici au fur et à mesure que vous parlez..." style="min-height:180px;resize:vertical;"></textarea>
  <div class="word-count" id="wordCount">0 mot(s)</div>
  <div class="transcript-actions">
    <button class="btn secondary" id="btnCopy">📋 Copier</button>
    <button class="btn secondary" id="btnPrint">🖨️ Imprimer</button>
    <button class="btn danger" id="btnClear">🗑️ Effacer</button>
  </div>
</div>

<!-- Sauvegarde -->
<div class="save-section">
  <h3>💾 Sauvegarder le rapport</h3>
  <div class="form-row">
    <div class="form-group">
      <label>Titre *</label>
      <input type="text" id="saveTitle" class="inp" placeholder="Ex: Rapport de service — 3 avril 2026">
    </div>
    <div class="form-group">
      <label>Type</label>
      <select id="saveType" class="inp">
        <option value="service">Rapport de service</option>
        <option value="incident">Rapport d'incident</option>
        <option value="trauma">Rapport trauma</option>
        <option value="patient">Suivi patient</option>
        <option value="reunion">Compte-rendu réunion</option>
        <option value="autre">Autre</option>
      </select>
    </div>
  </div>
  <div class="form-group" style="margin-bottom:14px;">
    <label>Patient / Sujet (optionnel)</label>
    <input type="text" id="saveSubject" class="inp" placeholder="Nom du patient ou sujet">
  </div>
  <button class="btn success" id="btnSave">💾 Sauvegarder</button>
</div>

<!-- Historique -->
<div class="history-section">
  <h3>🗂️ Historique des rapports</h3>
  <div id="historyList">
    <p class="muted" style="font-size:13px;">Aucun rapport sauvegardé pour le moment.</p>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
(function(){
  const STORAGE_KEY = 'samr_rapports_<?= (int)$u['id'] ?>';

  let recognition = null, isListening = false;
  let finalTranscript = '', interimTranscript = '';

  const micBtn        = document.getElementById('micBtn');
  const micStatus     = document.getElementById('micStatus');
  const livePreview   = document.getElementById('livePreview');
  const transcriptBox = document.getElementById('transcriptBox');
  const wordCount     = document.getElementById('wordCount');
  const langSelect    = document.getElementById('langSelect');
  const noSupport     = document.getElementById('noSupport');
  const toast         = document.getElementById('toast');

  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SpeechRecognition) {
    noSupport.style.display = 'block';
    micBtn.disabled = true;
    micBtn.style.opacity = '0.4';
    micBtn.style.cursor = 'not-allowed';
  }

  function showToast(msg, isError = false) {
    toast.textContent = msg;
    toast.className = 'toast show' + (isError ? ' error' : '');
    setTimeout(() => { toast.className = 'toast'; }, 3000);
  }

  function updateWordCount() {
    const txt = transcriptBox.value.trim();
    wordCount.textContent = (txt ? txt.split(/\s+/).length : 0) + ' mot(s)';
  }
  transcriptBox.addEventListener('input', updateWordCount);

  function buildRecognition() {
    const r = new SpeechRecognition();
    r.continuous = true;
    r.interimResults = true;
    r.lang = langSelect.value;
    r.maxAlternatives = 1;

    r.onstart = () => {
      isListening = true;
      micBtn.classList.add('listening');
      micBtn.textContent = '⏹️';
      micStatus.textContent = 'Écoute en cours…';
      micStatus.className = 'mic-status active';
      livePreview.style.display = 'block';
    };

    r.onresult = (e) => {
      interimTranscript = '';
      for (let i = e.resultIndex; i < e.results.length; i++) {
        const t = e.results[i][0].transcript;
        if (e.results[i].isFinal) finalTranscript += t + ' ';
        else interimTranscript += t;
      }
      transcriptBox.value = finalTranscript;
      livePreview.textContent = interimTranscript || '…';
      updateWordCount();
    };

    r.onerror = (e) => {
      if (e.error === 'no-speech') return;
      if (e.error === 'not-allowed') { showToast('Accès au microphone refusé.', true); stopListening(); return; }
    };

    r.onend = () => {
      if (isListening) r.start();
      else resetMicUI();
    };

    return r;
  }

  function stopListening() {
    isListening = false;
    if (recognition) recognition.stop();
    if (interimTranscript) { finalTranscript += interimTranscript + ' '; interimTranscript = ''; }
    transcriptBox.value = finalTranscript;
    updateWordCount();
    livePreview.style.display = 'none';
    livePreview.textContent = '';
  }

  function resetMicUI() {
    micBtn.classList.remove('listening');
    micBtn.textContent = '🎙️';
    micStatus.textContent = 'Cliquez pour démarrer la dictée';
    micStatus.className = 'mic-status';
  }

  micBtn.addEventListener('click', () => {
    if (!SpeechRecognition) return;
    if (isListening) {
      stopListening();
    } else {
      finalTranscript = transcriptBox.value;
      recognition = buildRecognition();
      try { recognition.start(); }
      catch(err) { showToast('Impossible de démarrer le micro.', true); }
    }
  });

  langSelect.addEventListener('change', () => {
    if (isListening) { stopListening(); showToast('Langue changée. Relancez la dictée.'); }
  });

  document.getElementById('btnCopy').addEventListener('click', () => {
    const txt = transcriptBox.value.trim();
    if (!txt) { showToast('Rien à copier.', true); return; }
    navigator.clipboard.writeText(txt).then(() => showToast('Texte copié !')).catch(() => {
      transcriptBox.select(); document.execCommand('copy'); showToast('Texte copié !');
    });
  });

  document.getElementById('btnPrint').addEventListener('click', () => {
    const txt = transcriptBox.value.trim();
    if (!txt) { showToast('Rien à imprimer.', true); return; }
    const w = window.open('', '_blank');
    const title   = document.getElementById('saveTitle').value || 'Rapport SAMR';
    const subject = document.getElementById('saveSubject').value;
    const type    = document.getElementById('saveType').options[document.getElementById('saveType').selectedIndex].text;
    const now     = new Date().toLocaleString('fr-FR');
    w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${title}</title>
    <style>body{font-family:Arial,sans-serif;max-width:700px;margin:40px auto;color:#111;}
    h1{font-size:1.3rem;margin-bottom:4px;}.meta{color:#666;font-size:.85rem;margin-bottom:20px;}
    pre{white-space:pre-wrap;font-size:.95rem;line-height:1.6;border-top:1px solid #ddd;padding-top:16px;}</style>
    </head><body>
    <h1>${title}</h1>
    <div class="meta">Type : ${type}${subject ? ' · Sujet : '+subject : ''} · ${now} · <?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></div>
    <pre>${txt.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</pre>
    </body></html>`);
    w.document.close(); w.print();
  });

  document.getElementById('btnClear').addEventListener('click', () => {
    if (!transcriptBox.value.trim()) return;
    if (!confirm('Effacer tout le texte transcrit ?')) return;
    transcriptBox.value = ''; finalTranscript = ''; interimTranscript = '';
    updateWordCount(); showToast('Texte effacé.');
  });

  function loadHistory() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); } catch(e) { return []; }
  }
  function saveHistory(arr) { localStorage.setItem(STORAGE_KEY, JSON.stringify(arr)); }

  function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function renderHistory() {
    const list = document.getElementById('historyList');
    const data = loadHistory();
    if (!data.length) {
      list.innerHTML = '<p class="muted" style="font-size:13px;">Aucun rapport sauvegardé pour le moment.</p>';
      return;
    }
    const labels = { service:'Rapport de service', incident:"Rapport d'incident", trauma:'Rapport trauma', patient:'Suivi patient', reunion:'Compte-rendu réunion', autre:'Autre' };
    list.innerHTML = data.slice().reverse().map((item, i) => {
      const realIdx = data.length - 1 - i;
      return `<div class="rapport-item">
        <div class="rapport-item-header">
          <div>
            <div class="rapport-item-title">${esc(item.title)}</div>
            <div class="rapport-item-meta">${item.subject ? '👤 '+esc(item.subject)+' · ' : ''}📅 ${new Date(item.created_at).toLocaleString('fr-FR')}</div>
          </div>
          <span class="pill">${labels[item.type] || item.type}</span>
        </div>
        <div class="rapport-item-body" id="rb-${realIdx}" onclick="toggleBody(${realIdx})">${esc(item.body)}</div>
        <div class="rapport-item-actions">
          <button class="btn secondary" style="font-size:12px;padding:6px 12px;" onclick="loadIntoEditor(${realIdx})">✏️ Charger</button>
          <button class="btn secondary" style="font-size:12px;padding:6px 12px;" onclick="copyItem(${realIdx})">📋 Copier</button>
          <button class="btn danger"    style="font-size:12px;padding:6px 12px;" onclick="deleteItem(${realIdx})">🗑️ Supprimer</button>
        </div>
      </div>`;
    }).join('');
  }

  window.toggleBody = (idx) => { const el = document.getElementById('rb-'+idx); if(el) el.classList.toggle('expanded'); };
  window.loadIntoEditor = (idx) => {
    const item = loadHistory()[idx]; if(!item) return;
    transcriptBox.value = item.body; finalTranscript = item.body;
    document.getElementById('saveTitle').value = item.title;
    document.getElementById('saveType').value = item.type;
    document.getElementById('saveSubject').value = item.subject || '';
    updateWordCount();
    transcriptBox.scrollIntoView({ behavior:'smooth' });
    showToast('Rapport chargé dans l\'éditeur.');
  };
  window.copyItem = (idx) => {
    const item = loadHistory()[idx]; if(!item) return;
    navigator.clipboard.writeText(item.body).then(() => showToast('Texte copié !'));
  };
  window.deleteItem = (idx) => {
    if(!confirm('Supprimer ce rapport ?')) return;
    const data = loadHistory(); data.splice(idx,1); saveHistory(data); renderHistory(); showToast('Rapport supprimé.');
  };

  document.getElementById('btnSave').addEventListener('click', () => {
    const body  = transcriptBox.value.trim();
    const title = document.getElementById('saveTitle').value.trim();
    if (!body)  { showToast('Transcription vide — rien à sauvegarder.', true); return; }
    if (!title) { showToast('Veuillez saisir un titre.', true); document.getElementById('saveTitle').focus(); return; }
    const data = loadHistory();
    data.push({ title, type: document.getElementById('saveType').value, subject: document.getElementById('saveSubject').value.trim(), body, created_at: new Date().toISOString() });
    saveHistory(data); renderHistory(); showToast('Rapport sauvegardé ✓');
  });

  renderHistory();
  updateWordCount();
})();
</script>

<?php samr_layout_end(); ?>
