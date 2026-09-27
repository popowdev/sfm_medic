<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

require_login();
$u = current_user();

samr_layout_start('SAMR — Test de dépistage TDAH');
?>

<style>
/* ── Page header ── */
.tdah-header { margin-bottom: 24px; }
.tdah-header h1 { font-family: var(--font-head); font-size: 22px; font-weight: 800; margin-bottom: 4px; }

/* ── Intro card ── */
.intro-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-bottom: 20px; }
.intro-stat { background: linear-gradient(160deg, rgba(13,18,25,.9), rgba(10,15,21,.95)); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; }
.intro-stat .s-label { font-size: 10px; text-transform: uppercase; letter-spacing: .8px; color: var(--muted); margin-bottom: 6px; }
.intro-stat .s-val   { font-family: var(--font-head); font-size: 20px; font-weight: 800; color: var(--accent); }
.intro-stat .s-sub   { font-size: 11px; color: var(--muted); margin-top: 3px; }

/* ── Progress ── */
.progress-wrap { margin-bottom: 22px; }
.progress-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 12px; color: var(--muted); }
.progress-meta strong { color: var(--text); font-family: var(--font-head); }
.progress-track { height: 6px; background: var(--line); border-radius: 3px; overflow: hidden; }
.progress-fill  { height: 100%; border-radius: 3px; background: linear-gradient(90deg, var(--accent), var(--accent2)); transition: width .4s ease; }

/* ── Section badge ── */
.section-badge { display: inline-flex; align-items: center; gap: 8px; padding: 5px 14px; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; border: 1px solid rgba(59,158,255,.25); background: rgba(59,158,255,.07); color: var(--accent); margin-bottom: 18px; }

/* ── Question ── */
.question-block { background: linear-gradient(160deg, rgba(13,18,25,.9), rgba(10,15,21,.95)); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 10px; transition: border-color .15s; }
.question-block.answered { border-color: rgba(59,158,255,.18); }
.q-num { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 6px; }
.q-text { font-size: 14px; color: var(--text); line-height: 1.6; margin-bottom: 14px; font-weight: 500; }
.scale-row { display: flex; gap: 5px; flex-wrap: wrap; }
.scale-btn {
  flex: 1; min-width: 0; padding: 7px 4px; font-size: 11px; font-weight: 600;
  text-align: center; border: 1px solid var(--line2); border-radius: 9px;
  background: transparent; color: var(--muted); cursor: pointer;
  transition: all .12s; font-family: var(--font-body); line-height: 1.3; white-space: nowrap;
}
.scale-btn:hover { background: rgba(255,255,255,.04); color: var(--text); border-color: rgba(59,158,255,.3); }
.scale-btn.active { background: rgba(59,158,255,.12); color: var(--accent); border-color: rgba(59,158,255,.4); }

/* ── Nav buttons ── */
.q-nav { display: flex; gap: 10px; margin-top: 20px; }
.q-nav .btn-prev { background: transparent; }
.q-nav .btn-next { flex: 1; }
.q-nav .btn-next:disabled { opacity: .35; cursor: default; pointer-events: none; }

/* ── Résultats ── */
.result-section { animation: fadeUp .4s ease; }
@keyframes fadeUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:none; } }

.score-hero { background: linear-gradient(160deg, rgba(13,18,25,.95), rgba(8,12,16,.98)); border: 1px solid var(--line2); border-radius: 16px; padding: 28px; text-align: center; margin-bottom: 20px; position: relative; overflow: hidden; }
.score-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse 400px 300px at 50% 0%, rgba(59,158,255,.06), transparent); pointer-events: none; }
.score-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 10px; }
.score-number { font-family: var(--font-head); font-size: 64px; font-weight: 800; line-height: 1; margin-bottom: 12px; }
.score-number.low  { color: var(--accent2); }
.score-number.med  { color: #ffb060; }
.score-number.high { color: #ff7a7a; }
.verdict-pill { display: inline-block; padding: 6px 18px; border-radius: 999px; font-size: 13px; font-weight: 700; letter-spacing: .3px; }
.verdict-pill.low  { background: rgba(0,212,170,.1); border: 1px solid rgba(0,212,170,.3); color: var(--accent2); }
.verdict-pill.med  { background: rgba(255,176,96,.08); border: 1px solid rgba(255,176,96,.3); color: #ffb060; }
.verdict-pill.high { background: rgba(255,79,79,.08); border: 1px solid rgba(255,79,79,.3); color: #ff7a7a; }

.dim-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
@media(max-width:600px){ .dim-grid { grid-template-columns: 1fr; } }
.dim-card { background: linear-gradient(160deg, rgba(13,18,25,.9), rgba(10,15,21,.95)); border: 1px solid var(--line); border-radius: 12px; padding: 16px; }
.dim-name { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: var(--muted); margin-bottom: 10px; }
.dim-bar-bg { height: 6px; border-radius: 3px; background: var(--line); overflow: hidden; margin-bottom: 8px; }
.dim-bar-fill { height: 100%; border-radius: 3px; transition: width .8s cubic-bezier(.16,1,.3,1); }
.dim-score-row { display: flex; justify-content: space-between; align-items: center; }
.dim-pct { font-family: var(--font-head); font-size: 18px; font-weight: 800; }
.dim-level { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; }

.advice-card { background: linear-gradient(160deg, rgba(13,18,25,.9), rgba(10,15,21,.95)); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 12px; }
.advice-card-title { font-family: var(--font-head); font-size: 14px; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
.advice-card-body { font-size: 13px; color: #8aa8c0; line-height: 1.7; }

.disclaimer-box { background: rgba(59,158,255,.04); border: 1px solid rgba(59,158,255,.12); border-radius: 12px; padding: 14px 16px; font-size: 12px; color: var(--muted); line-height: 1.65; margin-top: 20px; }
.disclaimer-box strong { color: var(--accent); }

/* ── Intro screen ── */
.intro-screen { max-width: 560px; }
.intro-screen h2 { font-family: var(--font-head); font-size: 18px; font-weight: 800; margin-bottom: 6px; }
.intro-screen p  { font-size: 13px; color: #8aa8c0; line-height: 1.7; margin-bottom: 18px; }
.intro-checklist { background: rgba(59,158,255,.04); border: 1px solid rgba(59,158,255,.12); border-radius: 12px; padding: 14px 16px; margin-bottom: 20px; }
.intro-check-item { display: flex; align-items: flex-start; gap: 10px; padding: 6px 0; font-size: 13px; color: var(--muted); border-bottom: 1px solid rgba(59,158,255,.07); }
.intro-check-item:last-child { border-bottom: none; }
.check-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); flex-shrink: 0; margin-top: 6px; }
</style>

<div class="tdah-header">
  <h1>🧠 Test de dépistage TDAH</h1>
  <div style="font-size:13px;color:var(--muted);">Questionnaire basé sur les critères DSM-5 — 24 questions, 4 dimensions</div>
</div>

<!-- ── Intro ── -->
<div id="viewIntro">
  <div class="intro-screen">
    <div class="intro-grid">
      <div class="intro-stat"><div class="s-label">Questions</div><div class="s-val">24</div><div class="s-sub">Réparties en 4 sections</div></div>
      <div class="intro-stat"><div class="s-label">Durée</div><div class="s-val">5–8</div><div class="s-sub">Minutes estimées</div></div>
      <div class="intro-stat"><div class="s-label">Dimensions</div><div class="s-val">4</div><div class="s-sub">Attention · Organisation · Hyperactivité · Impulsivité</div></div>
    </div>
    <div class="intro-checklist">
      <div class="intro-check-item"><span class="check-dot"></span><span>Répondez en fonction de votre fonctionnement habituel sur les <strong>6 derniers mois</strong></span></div>
      <div class="intro-check-item"><span class="check-dot"></span><span>Soyez le plus honnête possible — il n'y a pas de bonne ou mauvaise réponse</span></div>
      <div class="intro-check-item"><span class="check-dot"></span><span>Ce test est à usage indicatif, il ne remplace pas un diagnostic médical</span></div>
    </div>
    <button class="btn success" id="btnStart" style="font-size:14px;padding:11px 28px;">▶ Commencer le test</button>
  </div>
</div>

<!-- ── Test ── -->
<div id="viewTest" style="display:none;">
  <div class="progress-wrap">
    <div class="progress-meta">
      <span>Section <strong id="sectionNum">1</strong> / 4</span>
      <span id="progressPct">0%</span>
    </div>
    <div class="progress-track"><div class="progress-fill" id="progressFill" style="width:0%"></div></div>
  </div>
  <div class="section-badge" id="sectionBadge"></div>
  <div id="questionList"></div>
  <div class="q-nav">
    <button class="btn secondary btn-prev" id="btnPrev" style="display:none;">← Retour</button>
    <button class="btn success btn-next" id="btnNext" disabled>Section suivante →</button>
  </div>
</div>

<!-- ── Résultats ── -->
<div id="viewResult" style="display:none;"></div>

<script>
(function(){

const SECTIONS = [
  { id:'attention', icon:'👁️', label:'Attention & concentration', color:'#3b9eff', questions:[
    "Vous avez du mal à maintenir votre attention sur une tâche longue ou répétitive",
    "Vous faites des erreurs d'inattention dans votre travail ou vos activités quotidiennes",
    "Vous semblez ne pas écouter quand on vous parle directement",
    "Vous évitez ou remettez à plus tard les tâches qui demandent un effort mental soutenu",
    "Vous perdez facilement le fil d'une conversation ou d'une lecture",
    "Vous avez du mal à suivre des instructions ou à terminer vos tâches"
  ]},
  { id:'organisation', icon:'📂', label:'Organisation & mémoire', color:'#00d4aa', questions:[
    "Vous perdez des objets nécessaires (clés, téléphone, papiers…)",
    "Vous oubliez régulièrement des rendez-vous, obligations ou tâches quotidiennes",
    "Votre espace de travail ou vos affaires sont souvent désorganisés",
    "Vous avez du mal à planifier vos tâches ou à respecter les délais",
    "Vous avez tendance à commencer plusieurs choses sans en finir aucune",
    "Les distractions extérieures vous perturbent facilement lors d'une tâche"
  ]},
  { id:'hyperactivite', icon:'⚡', label:'Hyperactivité & agitation', color:'#ffb060', questions:[
    "Vous vous agitez, bougez les mains ou les pieds, ou vous tortillez sur votre siège",
    "Vous ressentez une agitation intérieure, une sensation d'être 'sur le qui-vive'",
    "Vous avez du mal à rester assis(e) dans des situations où cela est attendu",
    "Vous parlez excessivement ou avez du mal à vous arrêter une fois lancé(e)",
    "Vous avez du mal à vous détendre ou à faire des activités calmes",
    "Vous agissez comme si vous étiez 'propulsé(e) par un moteur'"
  ]},
  { id:'impulsivite', icon:'⚠️', label:'Impulsivité & contrôle', color:'#ff7a7a', questions:[
    "Vous répondez avant qu'une question soit terminée ou finissez les phrases des autres",
    "Vous avez du mal à attendre votre tour",
    "Vous interrompez les autres ou vous vous immiscez dans leurs conversations",
    "Vous prenez des décisions impulsives sans réfléchir aux conséquences",
    "Vous avez des difficultés à gérer votre frustration ou vos émotions",
    "Vous commencez les choses sans lire les instructions ou sans planifier"
  ]}
];

const SCALE = [
  {val:0, label:"Jamais"},
  {val:1, label:"Rarement"},
  {val:2, label:"Parfois"},
  {val:3, label:"Souvent"},
  {val:4, label:"Très souvent"}
];

let currentSection = 0;
let answers = {};

const totalQ = SECTIONS.reduce((s,sec)=>s+sec.questions.length, 0);

function answeredInSection(si){
  return SECTIONS[si].questions.every((_,qi)=>answers[`${SECTIONS[si].id}_${qi}`]!==undefined);
}

function answeredCount(){
  return Object.keys(answers).length;
}

function renderSection(){
  const sec = SECTIONS[currentSection];
  document.getElementById('sectionNum').textContent = currentSection + 1;
  document.getElementById('sectionBadge').textContent = sec.icon + ' ' + sec.label;

  const pct = Math.round((answeredCount() / totalQ) * 100);
  document.getElementById('progressPct').textContent = pct + '%';
  document.getElementById('progressFill').style.width = pct + '%';

  const list = document.getElementById('questionList');
  let html = '';
  sec.questions.forEach((q, qi) => {
    const key = `${sec.id}_${qi}`;
    const val = answers[key];
    const answered = val !== undefined;
    html += `<div class="question-block ${answered?'answered':''}" id="qblock_${qi}">
      <div class="q-num">Question ${qi+1} / ${sec.questions.length}</div>
      <div class="q-text">${q}</div>
      <div class="scale-row">`;
    SCALE.forEach(s => {
      html += `<button class="scale-btn${val===s.val?' active':''}" onclick="setAnswer('${key}',${s.val},${qi})">${s.label}</button>`;
    });
    html += `</div></div>`;
  });
  list.innerHTML = html;
  updateNav();
}

function updateNav(){
  const allDone = answeredInSection(currentSection);
  const btnNext = document.getElementById('btnNext');
  const btnPrev = document.getElementById('btnPrev');
  btnNext.disabled = !allDone;
  const isLast = currentSection === SECTIONS.length - 1;
  btnNext.textContent = isLast ? '✅ Voir les résultats' : 'Section suivante →';
  btnPrev.style.display = currentSection > 0 ? '' : 'none';
}

window.setAnswer = function(key, val, qi){
  answers[key] = val;
  const sec = SECTIONS[currentSection];
  const pct = Math.round((answeredCount() / totalQ) * 100);
  document.getElementById('progressPct').textContent = pct + '%';
  document.getElementById('progressFill').style.width = pct + '%';

  const block = document.getElementById('qblock_' + qi);
  block.classList.add('answered');
  block.querySelectorAll('.scale-btn').forEach((btn, i) => {
    btn.classList.toggle('active', i === val);
  });
  updateNav();
};

document.getElementById('btnNext').addEventListener('click', () => {
  if (!answeredInSection(currentSection)) return;
  if (currentSection < SECTIONS.length - 1) {
    currentSection++;
    renderSection();
    document.getElementById('viewTest').scrollIntoView({behavior:'smooth'});
  } else {
    showResults();
  }
});

document.getElementById('btnPrev').addEventListener('click', () => {
  if (currentSection > 0) { currentSection--; renderSection(); document.getElementById('viewTest').scrollIntoView({behavior:'smooth'}); }
});

document.getElementById('btnStart').addEventListener('click', () => {
  document.getElementById('viewIntro').style.display = 'none';
  document.getElementById('viewTest').style.display   = '';
  renderSection();
});

function showResults(){
  document.getElementById('viewTest').style.display   = 'none';
  document.getElementById('viewResult').style.display = '';

  const maxPerDim = SECTIONS[0].questions.length * 4;
  const scores = {};
  SECTIONS.forEach(sec => {
    let s = 0;
    sec.questions.forEach((_,qi)=>{ s += answers[`${sec.id}_${qi}`]||0; });
    scores[sec.id] = { raw:s, pct: Math.round((s/maxPerDim)*100), label:sec.label, icon:sec.icon, color:sec.color };
  });

  const totalMax = totalQ * 4;
  const totalRaw = Object.values(scores).reduce((s,d)=>s+d.raw, 0);
  const totalPct = Math.round((totalRaw/totalMax)*100);

  let level, levelClass, verdictText, verdictAdvice;
  if (totalPct < 30) {
    level='low'; levelClass='verdict-pill low'; verdictText='Peu de signes détectés';
    verdictAdvice="Le questionnaire ne détecte pas de pattern significatif associé au TDAH. Les quelques difficultés identifiées peuvent être liées au stress, à la fatigue ou à d'autres facteurs contextuels.";
  } else if (totalPct < 55) {
    level='med'; levelClass='verdict-pill med'; verdictText='Signes modérés';
    verdictAdvice="Le questionnaire identifie des difficultés dans plusieurs domaines associés au TDAH. Ces résultats méritent attention. Une consultation avec un médecin ou un psychiatre permettrait d'explorer la situation plus en profondeur.";
  } else {
    level='high'; levelClass='verdict-pill high'; verdictText='Signes significatifs';
    verdictAdvice="Le questionnaire met en évidence un niveau élevé de symptômes associés au TDAH sur plusieurs dimensions. Il est recommandé de consulter un professionnel de santé (psychiatre, neuropsychologue) pour un bilan approfondi.";
  }

  const dimAdvices = {
    attention:"Les difficultés d'attention sont souvent la dimension la plus impactante du TDAH chez l'adulte (travail, études). Stratégies utiles : découpage des tâches, minuteries Pomodoro, listes externalisées.",
    organisation:"Les problèmes d'organisation et de mémoire de travail sont caractéristiques. Des outils externes (agenda, rappels automatiques, routines fixes) peuvent compenser efficacement au quotidien.",
    hyperactivite:"L'hyperactivité chez l'adulte se manifeste souvent de façon internalisée : agitation intérieure, impatience. L'activité physique régulière et les pauses actives aident à canaliser cette énergie.",
    impulsivite:"L'impulsivité peut affecter les relations et la prise de décision. La thérapie cognitivo-comportementale (TCC) et les techniques de pause avant de réagir ont montré leur efficacité."
  };

  const highest = SECTIONS.reduce((best,sec) => scores[sec.id].pct > scores[best.id].pct ? sec : best, SECTIONS[0]);

  function dimLevelLabel(pct){
    if(pct<30) return {text:'Faible', css:'background:rgba(0,212,170,.1);color:var(--accent2);'};
    if(pct<55) return {text:'Modéré', css:'background:rgba(255,176,96,.08);color:#ffb060;'};
    return {text:'Élevé', css:'background:rgba(255,79,79,.08);color:#ff7a7a;'};
  }

  let dimHtml = '';
  SECTIONS.forEach(sec => {
    const d = scores[sec.id];
    const lv = dimLevelLabel(d.pct);
    dimHtml += `<div class="dim-card">
      <div class="dim-name">${sec.icon} ${d.label}</div>
      <div class="dim-bar-bg"><div class="dim-bar-fill" style="width:${d.pct}%;background:${d.color};"></div></div>
      <div class="dim-score-row">
        <span class="dim-pct" style="color:${d.color}">${d.pct}%</span>
        <span class="dim-level" style="${lv.css}">${lv.text}</span>
      </div>
    </div>`;
  });

  document.getElementById('viewResult').innerHTML = `
    <div class="result-section">
      <div class="score-hero">
        <div class="score-label">Score global</div>
        <div class="score-number ${level}">${totalPct}<span style="font-size:28px;opacity:.6;">%</span></div>
        <span class="${levelClass}">${verdictText}</span>
      </div>

      <div class="dim-grid">${dimHtml}</div>

      <div class="advice-card">
        <div class="advice-card-title">📊 Interprétation globale</div>
        <div class="advice-card-body">${verdictAdvice}</div>
      </div>

      <div class="advice-card">
        <div class="advice-card-title" style="color:${highest.color}">${highest.icon} Dimension principale : ${highest.label}</div>
        <div class="advice-card-body">${dimAdvices[highest.id]}</div>
      </div>

      <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap;">
        <button class="btn secondary" onclick="restartTest()">🔄 Recommencer le test</button>
      </div>

      <div class="disclaimer-box">
        <strong>⚕️ Avertissement médical</strong> — Ce questionnaire est un outil de dépistage indicatif basé sur les critères DSM-5. Il ne constitue <strong>pas un diagnostic médical</strong>. Seul un professionnel de santé qualifié (psychiatre, neuropsychologue) peut poser un diagnostic de TDAH après un bilan clinique complet incluant entretien, observation et évaluation neuropsychologique.
      </div>
    </div>
  `;

  document.getElementById('viewResult').scrollIntoView({behavior:'smooth'});
}

window.restartTest = function(){
  currentSection = 0;
  answers = {};
  document.getElementById('viewResult').style.display = 'none';
  document.getElementById('viewIntro').style.display  = '';
  window.scrollTo({top:0,behavior:'smooth'});
};

})();
</script>

<?php samr_layout_end(); ?>
