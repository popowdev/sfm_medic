<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';

require_login();
$u        = current_user();
$username = (string)($u['username'] ?? '');

$pdo  = db();
$stmt = $pdo->prepare("SELECT grade FROM users WHERE id=? LIMIT 1");
$stmt->execute([(int)($u['id'] ?? 0)]);
$grade = (string)($stmt->fetchColumn() ?: '');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

samr_layout_start('SAMR — Fiche de Visite Médicale');
?>

<style>
.vm-wrap{ max-width:760px; margin:0 auto; }

.vm-header{
  display:flex; align-items:flex-start; justify-content:space-between;
  flex-wrap:wrap; gap:12px; margin-bottom:20px;
}
.vm-header h1{ font-family:var(--font-head); font-size:20px; font-weight:800; margin:0 0 4px; }
.vm-header .sub{ font-size:13px; color:var(--muted); }

/* Sections */
.vm-section{
  background:linear-gradient(160deg, rgba(13,18,25,.95), rgba(10,15,21,.98));
  border:1px solid var(--line); border-radius:14px;
  overflow:hidden; margin-bottom:12px;
}
.vm-section-head{
  display:flex; align-items:center; gap:10px;
  padding:11px 16px;
  background:rgba(255,255,255,.025);
  border-bottom:1px solid var(--line);
}
.vm-section-icon{ font-size:16px; }
.vm-section-title{
  font-family:var(--font-head); font-size:13px; font-weight:700;
}
.vm-section-body{ padding:14px 16px; display:flex; flex-direction:column; gap:10px; }

/* Grille de champs */
.vm-row{ display:grid; gap:10px; }
.vm-row-2{ grid-template-columns:1fr 1fr; }
.vm-row-3{ grid-template-columns:1fr 1fr 1fr; }
.vm-row-4{ grid-template-columns:1fr 1fr 1fr 1fr; }

@media(max-width:640px){
  .vm-row-2,.vm-row-3,.vm-row-4{ grid-template-columns:1fr; }
}

/* Champ */
.vm-field{ display:flex; flex-direction:column; gap:4px; }
.vm-field label{
  font-size:10px; font-weight:700; text-transform:uppercase;
  letter-spacing:.6px; color:var(--muted);
}
.vm-field .inp{ font-size:13px; padding:8px 11px; }
.vm-field select.inp{ cursor:pointer; }
.vm-field textarea.inp{ resize:none; min-height:60px; line-height:1.5; }

/* Champ inline avec unité */
.vm-field-unit{
  display:flex; align-items:center; gap:6px;
}
.vm-field-unit .inp{ flex:1; }
.unit-label{ font-size:12px; color:var(--muted); white-space:nowrap; flex-shrink:0; }

/* Toggle Oui/Non */
.yn-group{ display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
.yn-btn{
  padding:5px 14px; border-radius:999px; border:1px solid var(--line2);
  background:transparent; color:var(--muted); font-size:12px; font-weight:700;
  cursor:pointer; font-family:var(--font-body); transition:all .12s;
}
.yn-btn.active-oui{ border-color:rgba(255,79,79,.5); color:#ff7a7a; background:rgba(255,79,79,.1); }
.yn-btn.active-non{ border-color:rgba(0,212,170,.4); color:var(--accent2); background:rgba(0,212,170,.08); }
.yn-detail{ flex:1; min-width:120px; }
.yn-detail .inp{ font-size:12px; padding:6px 10px; }
.yn-detail.hidden{ display:none; }

/* Aptitude */
.aptitude-group{ display:flex; gap:8px; }
.apt-btn{
  flex:1; padding:10px; border-radius:10px; border:2px solid var(--line2);
  background:transparent; color:var(--muted); font-size:13px; font-weight:700;
  cursor:pointer; font-family:var(--font-body); transition:all .15s; text-align:center;
}
.apt-btn.active-apte{ border-color:rgba(0,212,170,.6); color:var(--accent2); background:rgba(0,212,170,.1); }
.apt-btn.active-inapte{ border-color:rgba(255,79,79,.6); color:#ff7a7a; background:rgba(255,79,79,.1); }

/* Bouton copier */
.copy-bar{
  position:sticky; bottom:16px; z-index:10;
  display:flex; justify-content:center; padding-top:8px;
}
.btn-copy{
  display:inline-flex; align-items:center; gap:10px;
  padding:13px 28px; border-radius:12px;
  background:linear-gradient(135deg, rgba(59,158,255,.25), rgba(0,212,170,.15));
  border:1px solid rgba(59,158,255,.4); color:var(--text);
  font-family:var(--font-head); font-size:14px; font-weight:700;
  cursor:pointer; transition:all .15s;
  box-shadow:0 8px 24px rgba(0,0,0,.4);
}
.btn-copy:hover{
  background:linear-gradient(135deg, rgba(59,158,255,.38), rgba(0,212,170,.22));
  transform:translateY(-1px); box-shadow:0 12px 32px rgba(0,0,0,.5);
}
.btn-copy.copied{
  background:linear-gradient(135deg, rgba(0,212,170,.25), rgba(0,212,170,.15));
  border-color:rgba(0,212,170,.5);
}

/* Toast */
.vm-toast{
  position:fixed; bottom:80px; left:50%; transform:translateX(-50%) translateY(20px);
  background:#0d1219; border:1px solid var(--accent2); border-radius:10px;
  padding:10px 20px; font-size:13px; color:var(--accent2);
  opacity:0; transition:opacity .3s, transform .3s; pointer-events:none; z-index:9999;
}
.vm-toast.show{ opacity:1; transform:translateX(-50%) translateY(0); }
</style>

<div class="vm-wrap">

<div class="vm-header">
  <div>
    <h1>🩺 Fiche de Visite Médicale</h1>
    <div class="sub">SAMR — San Andreas Medical Response</div>
  </div>
  <button class="btn secondary" id="btnReset" style="font-size:12px;">↺ Réinitialiser</button>
</div>

<!-- ── 1. Identité ── -->
<div class="vm-section">
  <div class="vm-section-head">
    <span class="vm-section-icon">👤</span>
    <span class="vm-section-title">Identité du Patient / Employé</span>
  </div>
  <div class="vm-section-body">
    <div class="vm-row vm-row-2">
      <div class="vm-field">
        <label>Nom & Prénom</label>
        <input class="inp" id="f_nom" placeholder="Ex : John Doe">
      </div>
      <div class="vm-field">
        <label>Date de naissance</label>
        <input class="inp" id="f_ddn" type="date">
      </div>
    </div>
    <div class="vm-row vm-row-3">
      <div class="vm-field">
        <label>Numéro de téléphone</label>
        <input class="inp" id="f_tel" placeholder="555 - xxxxx">
      </div>
      <div class="vm-field">
        <label>Poste / Fonction</label>
        <input class="inp" id="f_poste" placeholder="Ex : Agent SASP">
      </div>
      <div class="vm-field">
        <label>Date de la visite</label>
        <input class="inp" id="f_date_visite" type="date">
      </div>
    </div>
    <div class="vm-field">
      <label>Contact(s) d'urgence</label>
      <input class="inp" id="f_urgence" placeholder="Nom — Téléphone">
    </div>
  </div>
</div>

<!-- ── 2. Antécédents ── -->
<div class="vm-section">
  <div class="vm-section-head">
    <span class="vm-section-icon">📋</span>
    <span class="vm-section-title">Antécédents médicaux</span>
  </div>
  <div class="vm-section-body">
    <div class="vm-row vm-row-2">
      <div class="vm-field">
        <label>Allergies</label>
        <div class="yn-group">
          <button class="yn-btn" data-yn="f_allergies" data-val="Oui">Oui</button>
          <button class="yn-btn active-non" data-yn="f_allergies" data-val="Non">Non</button>
          <div class="yn-detail hidden" id="f_allergies_detail">
            <input class="inp" id="f_allergies_txt" placeholder="Préciser…">
          </div>
        </div>
      </div>
      <div class="vm-field">
        <label>Groupe sanguin</label>
        <select class="inp" id="f_groupe">
          <option value="">— Inconnu —</option>
          <option>A+</option><option>A−</option>
          <option>B+</option><option>B−</option>
          <option>AB+</option><option>AB−</option>
          <option>O+</option><option>O−</option>
        </select>
      </div>
    </div>
    <div class="vm-field">
      <label>Pathologies connues</label>
      <input class="inp" id="f_patho" placeholder="Ex : Diabète, hypertension… ou Aucune">
    </div>
    <div class="vm-row vm-row-2">
      <div class="vm-field">
        <label>Traitement(s) en cours</label>
        <input class="inp" id="f_traitement" placeholder="Médicament(s) ou Aucun">
      </div>
      <div class="vm-field">
        <label>Antécédents chirurgicaux</label>
        <input class="inp" id="f_chir" placeholder="Ex : Appendicite 2020… ou Aucun">
      </div>
    </div>
  </div>
</div>

<!-- ── 3. État général ── -->
<div class="vm-section">
  <div class="vm-section-head">
    <span class="vm-section-icon">🩺</span>
    <span class="vm-section-title">État général lors de la visite</span>
  </div>
  <div class="vm-section-body">

    <div class="vm-row" style="grid-template-columns:1fr 1fr 1fr 1fr 1fr;">
      <div class="vm-field">
        <label>Poids</label>
        <div class="vm-field-unit">
          <input class="inp" id="f_poids" type="number" min="0" placeholder="70">
          <span class="unit-label">kg</span>
        </div>
      </div>
      <div class="vm-field">
        <label>Taille</label>
        <div class="vm-field-unit">
          <input class="inp" id="f_taille" type="number" min="0" placeholder="175">
          <span class="unit-label">cm</span>
        </div>
      </div>
      <div class="vm-field">
        <label>Température</label>
        <div class="vm-field-unit">
          <input class="inp" id="f_temp" type="number" step="0.1" min="30" max="45" placeholder="37.0">
          <span class="unit-label">°C</span>
        </div>
      </div>
      <div class="vm-field">
        <label>Tension artérielle</label>
        <div class="vm-field-unit">
          <input class="inp" id="f_tension" placeholder="120/80">
          <span class="unit-label">mmHg</span>
        </div>
      </div>
      <div class="vm-field">
        <label>Pouls</label>
        <div class="vm-field-unit">
          <input class="inp" id="f_pouls" type="number" min="0" placeholder="70">
          <span class="unit-label">bpm</span>
        </div>
      </div>
    </div>

    <div class="vm-row vm-row-3">
      <div class="vm-field">
        <label>Vision</label>
        <select class="inp" id="f_vision">
          <option value="">— Sélectionner —</option>
          <option>Bonne</option>
          <option>Moyenne</option>
          <option>Mauvaise</option>
        </select>
      </div>
      <div class="vm-field">
        <label>Audition</label>
        <select class="inp" id="f_audition">
          <option value="">— Sélectionner —</option>
          <option>Normale</option>
          <option>Altérée</option>
        </select>
      </div>
      <div class="vm-field">
        <label>État mental</label>
        <select class="inp" id="f_mental">
          <option value="">— Sélectionner —</option>
          <option>Stable</option>
          <option>Agité</option>
          <option>Autre</option>
        </select>
      </div>
    </div>
    <div class="vm-field" id="f_mental_detail_wrap" style="display:none;">
      <label>Préciser l'état mental</label>
      <input class="inp" id="f_mental_detail" placeholder="Décrire…">
    </div>

    <div class="vm-row vm-row-3">
      <div class="vm-field">
        <label>Usage de stupéfiants</label>
        <div class="yn-group">
          <button class="yn-btn" data-yn="f_stup" data-val="Oui">Oui</button>
          <button class="yn-btn active-non" data-yn="f_stup" data-val="Non">Non</button>
          <div class="yn-detail hidden" id="f_stup_detail">
            <input class="inp" id="f_stup_txt" placeholder="Préciser…">
          </div>
        </div>
      </div>
      <div class="vm-field">
        <label>Alcool</label>
        <div class="yn-group">
          <button class="yn-btn" data-yn="f_alcool" data-val="Oui">Oui</button>
          <button class="yn-btn active-non" data-yn="f_alcool" data-val="Non">Non</button>
          <div class="yn-detail hidden" id="f_alcool_detail">
            <input class="inp" id="f_alcool_txt" placeholder="Préciser…">
          </div>
        </div>
      </div>
      <div class="vm-field">
        <label>Tabac</label>
        <div class="yn-group">
          <button class="yn-btn" data-yn="f_tabac" data-val="Oui">Oui</button>
          <button class="yn-btn active-non" data-yn="f_tabac" data-val="Non">Non</button>
          <div class="yn-detail hidden" id="f_tabac_detail">
            <input class="inp" id="f_tabac_txt" placeholder="Préciser…">
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ── 4. Aptitude ── -->
<div class="vm-section">
  <div class="vm-section-head">
    <span class="vm-section-icon">✅</span>
    <span class="vm-section-title">Aptitude au poste</span>
  </div>
  <div class="vm-section-body">
    <div class="vm-field">
      <label>Résultat</label>
      <div class="aptitude-group">
        <button class="apt-btn" id="apt_apte" data-apt="apte">✅ Apte</button>
        <button class="apt-btn" id="apt_inapte" data-apt="inapte">❌ Inapte</button>
      </div>
    </div>
    <div class="vm-field">
      <label>Restrictions éventuelles</label>
      <input class="inp" id="f_restrictions" placeholder="Ex : Port de charges limité… ou Aucune">
    </div>
    <div class="vm-field">
      <label>Observation(s) complémentaire(s)</label>
      <textarea class="inp" id="f_observations" placeholder="Remarques ou informations complémentaires…"></textarea>
    </div>
  </div>
</div>

<!-- ── 5. Signature ── -->
<div class="vm-section">
  <div class="vm-section-head">
    <span class="vm-section-icon">✍️</span>
    <span class="vm-section-title">Signature du Médecin</span>
  </div>
  <div class="vm-section-body">
    <div class="vm-row vm-row-2">
      <div class="vm-field">
        <label>Nom du médecin</label>
        <input class="inp" id="f_medecin" placeholder="Dr. …" value="Dr. <?= h($username) ?>">
      </div>
      <div class="vm-field">
        <label>Grade</label>
        <input class="inp" id="f_grade" placeholder="Ex : Médecin en chef" value="<?= h($grade) ?>">
      </div>
    </div>
  </div>
</div>

<!-- ── Bouton copier ── -->
<div class="copy-bar">
  <button class="btn-copy" id="btnCopy">📋 Copier dans le presse-papier</button>
</div>

</div>

<div class="vm-toast" id="vmToast">✓ Fiche copiée dans le presse-papier !</div>

<script>
(function(){

  const ynState = { f_allergies:'Non', f_stup:'Non', f_alcool:'Non', f_tabac:'Non' };

  document.querySelectorAll('[data-yn]').forEach(btn => {
    btn.addEventListener('click', () => {
      const key = btn.dataset.yn;
      const val = btn.dataset.val;
      ynState[key] = val;

      document.querySelectorAll(`[data-yn="${key}"]`).forEach(b => {
        b.className = `yn-btn${b.dataset.val === val ? (val==='Oui' ? ' active-oui' : ' active-non') : ''}`;
      });

      const detail = document.getElementById(key + '_detail');
      if (detail) detail.classList.toggle('hidden', val !== 'Oui');
    });
  });

  document.getElementById('f_mental').addEventListener('change', function() {
    document.getElementById('f_mental_detail_wrap').style.display =
      this.value === 'Autre' ? '' : 'none';
  });

  let aptitude = '';
  document.querySelectorAll('[data-apt]').forEach(btn => {
    btn.addEventListener('click', () => {
      aptitude = btn.dataset.apt;
      document.getElementById('apt_apte').className   = 'apt-btn' + (aptitude==='apte'   ? ' active-apte'   : '');
      document.getElementById('apt_inapte').className = 'apt-btn' + (aptitude==='inapte' ? ' active-inapte' : '');
    });
  });

  document.getElementById('btnReset').addEventListener('click', () => {
    if (!confirm('Réinitialiser tous les champs ?')) return;
    document.querySelectorAll('.inp').forEach(el => {
      if (el.tagName === 'SELECT') el.selectedIndex = 0;
      else el.value = '';
    });
    aptitude = '';
    document.getElementById('apt_apte').className   = 'apt-btn';
    document.getElementById('apt_inapte').className = 'apt-btn';
    Object.keys(ynState).forEach(k => {
      ynState[k] = 'Non';
      document.querySelectorAll(`[data-yn="${k}"]`).forEach(b => {
        b.className = `yn-btn${b.dataset.val==='Non' ? ' active-non' : ''}`;
      });
      const detail = document.getElementById(k + '_detail');
      if (detail) detail.classList.add('hidden');
    });
    document.getElementById('f_mental_detail_wrap').style.display = 'none';
    document.getElementById('f_date_visite').value = new Date().toISOString().slice(0,10);
  });

  function val(id) { return (document.getElementById(id)?.value || '').trim() || '—'; }
  function yn(key, detailId) {
    const v = ynState[key];
    if (v === 'Oui' && detailId) {
      const detail = (document.getElementById(detailId)?.value || '').trim();
      return detail ? `Oui — ${detail}` : 'Oui';
    }
    return v;
  }
  function fmtDate(id) {
    const v = document.getElementById(id)?.value;
    if (!v) return '—';
    const d = new Date(v);
    return d.toLocaleDateString('fr-FR', {day:'2-digit', month:'2-digit', year:'numeric'});
  }
  function mentalVal() {
    const m = document.getElementById('f_mental')?.value || '';
    if (m === 'Autre') {
      const d = (document.getElementById('f_mental_detail')?.value || '').trim();
      return d ? `Autre : ${d}` : 'Autre';
    }
    return m || '—';
  }

  function buildText() {
    const lines = [
      '╔══════════════════════════════════════════════╗',
      '       FICHE DE VISITE MÉDICALE — SAMR',
      '╚══════════════════════════════════════════════╝',
      '',
      '━━━ IDENTITÉ DU PATIENT / EMPLOYÉ ━━━',
      `Nom & Prénom        : ${val('f_nom')}`,
      `Date de naissance   : ${fmtDate('f_ddn')}`,
      `Téléphone           : ${val('f_tel')}`,
      `Poste / Fonction    : ${val('f_poste')}`,
      `Contact d'urgence   : ${val('f_urgence')}`,
      `Date de la visite   : ${fmtDate('f_date_visite')}`,
      '',
      '━━━ ANTÉCÉDENTS MÉDICAUX ━━━',
      `Allergies           : ${yn('f_allergies', 'f_allergies_txt')}`,
      `Pathologies connues : ${val('f_patho')}`,
      `Traitements en cours: ${val('f_traitement')}`,
      `Antécédents chir.   : ${val('f_chir')}`,
      `Groupe sanguin      : ${val('f_groupe')}`,
      '',
      '━━━ ÉTAT GÉNÉRAL LORS DE LA VISITE ━━━',
      `Poids               : ${val('f_poids')} kg`,
      `Taille              : ${val('f_taille')} cm`,
      `Température         : ${val('f_temp')} °C`,
      `Tension artérielle  : ${val('f_tension')} mmHg`,
      `Pouls               : ${val('f_pouls')} bpm`,
      `Vision              : ${val('f_vision')}`,
      `Audition            : ${val('f_audition')}`,
      `État mental         : ${mentalVal()}`,
      `Stupéfiants         : ${yn('f_stup', 'f_stup_txt')}`,
      `Alcool              : ${yn('f_alcool', 'f_alcool_txt')}`,
      `Tabac               : ${yn('f_tabac', 'f_tabac_txt')}`,
      '',
      '━━━ APTITUDE AU POSTE ━━━',
      `Aptitude            : ${aptitude === 'apte' ? '✅ APTE' : aptitude === 'inapte' ? '❌ INAPTE' : '—'}`,
      `Restrictions        : ${val('f_restrictions')}`,
      `Observations        : ${val('f_observations')}`,
      '',
      '━━━ SIGNATURE DU MÉDECIN ━━━',
      `Médecin             : ${val('f_medecin')}`,
      `Grade               : ${val('f_grade')}`,
      `Date                : ${new Date().toLocaleDateString('fr-FR', {day:'2-digit',month:'2-digit',year:'numeric'})}`,
      '',
      '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━',
    ];
    return lines.join('\n');
  }

  const btnCopy = document.getElementById('btnCopy');
  const toast   = document.getElementById('vmToast');
  let toastTimer = null;

  btnCopy.addEventListener('click', async () => {
    const text = buildText();
    try {
      await navigator.clipboard.writeText(text);
    } catch {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select(); document.execCommand('copy');
      document.body.removeChild(ta);
    }
    btnCopy.classList.add('copied');
    btnCopy.textContent = '✓ Copié !';
    toast.classList.add('show');
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      btnCopy.classList.remove('copied');
      btnCopy.textContent = '📋 Copier dans le presse-papier';
      toast.classList.remove('show');
    }, 2500);
  });

  document.getElementById('f_date_visite').value = new Date().toISOString().slice(0,10);

})();
</script>

<?php samr_layout_end(); ?>
