<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/ppa_layout.php';

$candidateId = (int)($_SESSION['ppa_candidate_id'] ?? 0);

if (!$candidateId && !empty($_GET['token'])) {
    $recover = ppa_candidate_by_token((string)$_GET['token']);

    if ($recover
        && empty($recover['test_result'])
        && (empty($recover['activation_expires_at']) || strtotime($recover['activation_expires_at']) > time())
    ) {
        $candidateId = (int)$recover['id'];
        $_SESSION['ppa_candidate_id']   = $candidateId;
        $_SESSION['ppa_candidate_type'] = (string)$recover['ppa_type'];
    }
}

if (isset($_GET['dys'])) {
    $_SESSION['ppa_dys'] = ($_GET['dys'] === '1');
}
$isDys = !empty($_SESSION['ppa_dys']);

$cand = $candidateId ? ppa_candidate_by_id($candidateId) : null;

ppa_layout_head('Test PPA');

if (!$cand) { ?>
  <div class="card" style="text-align:center;padding:32px 20px;">
    <div style="font-size:40px;margin-bottom:14px;">🔒</div>
    <div style="font-family:var(--font-head);font-size:18px;font-weight:700;margin-bottom:8px;">Accès protégé</div>
    <p class="muted">Utilise le lien d'activation reçu sur Discord pour accéder à ton test PPA.</p>
  </div>
  <?php ppa_layout_foot(); exit;
}

if (!empty($cand['test_result']) && isset($_SESSION['ppa_result'])) {
    header('Location: result.php'); exit;
}

if (empty($_SESSION['ppa_test'])) {

    if (!empty($cand['activation_token'])) {
        ppa_db()->prepare("UPDATE `" . PPA_TABLE . "` SET activation_used_at=?, activated_at=?, status='activated', updated_at=? WHERE id=?")
            ->execute([ppa_now(), ppa_now(), ppa_now(), $cand['id']]);
    }

    $n = (int)ppa_cfg('questions_per_test', 20);
    $questions = ppa_select_questions((string)$cand['ppa_type'], $n, $isDys);
    $_SESSION['ppa_test'] = [
        'question_ids' => array_column($questions, 'id'),
        'questions'    => array_map(fn($q) => [
            'id'      => $q['id'],
            'text'    => $q['text'],
            'choices' => array_map(fn($key, $ans) => ['key'=>$key,'label'=>$ans['label']],
                            array_keys($q['answers']), $q['answers']),
        ], $questions),
        'started_at'   => ppa_now(),
        'dys'          => $isDys,
    ];
    ppa_db()->prepare("UPDATE `" . PPA_TABLE . "` SET status='testing', test_started_at=?, updated_at=? WHERE id=?")
        ->execute([$_SESSION['ppa_test']['started_at'], ppa_now(), $cand['id']]);
}

$test      = $_SESSION['ppa_test'];
$typeLabel = ['civil'=>'Civil','sasp'=>'Police (SASP)','chasse'=>'Chasse','ammunation'=>'Ammunation'][$cand['ppa_type']] ?? strtoupper($cand['ppa_type']);
?>

<style>
.sticky-bar{
  position:sticky; top:0; z-index:100;
  background:linear-gradient(160deg,rgba(8,12,16,.97),rgba(13,18,25,.97));
  border-bottom:1px solid var(--line2);
  backdrop-filter:blur(12px);
  padding:10px 16px;
  display:flex; align-items:center; justify-content:space-between; gap:16px;
  flex-wrap:wrap;
  margin:-32px -20px 20px; /* compenser le padding du .wrap */
}
.sticky-left{ display:flex; flex-direction:column; }
.sticky-name{ font-family:var(--font-head); font-size:13px; font-weight:700; }
.sticky-info{ font-size:11px; color:var(--muted); margin-top:2px; }
.sticky-right{ display:flex; align-items:center; gap:16px; flex-shrink:0; }
.timer{ font-family:var(--font-head); font-size:28px; font-weight:800; color:var(--accent2); line-height:1; }
.timer.warning{ color:#ffb060; }
.timer.danger{ color:#ff7a7a; animation:pulse-timer .8s infinite; }
@keyframes pulse-timer{ 0%,100%{opacity:1} 50%{opacity:.6} }
.q-counter{ text-align:center; }
.q-counter-num{ font-family:var(--font-head); font-size:18px; font-weight:800; color:var(--text); }
.q-counter-label{ font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:.6px; }
.progress-bar{ height:3px; background:var(--line); overflow:hidden; margin:0 -20px; }
.progress-fill{ height:100%; background:linear-gradient(90deg,var(--accent),var(--accent2)); transition:width .3s; }
.dys-mode .question-text{ font-size:1.2em; line-height:1.95; letter-spacing:.03em; word-spacing:.14em; text-align:left; }
.dys-mode .choice-text{ font-size:1.1em; line-height:1.8; letter-spacing:.02em; word-spacing:.1em; text-align:left; }
.dys-mode .choice-label{ padding:14px 16px; }
.dys-mode .question-card{ margin-bottom:18px; }
</style>

<div class="sticky-bar" id="stickyBar">
  <div class="sticky-left">
    <div class="sticky-name"><?= ppa_h($cand['first_name'] . ' ' . $cand['last_name']) ?></div>
    <div class="sticky-info">
      Test <?= ppa_h($typeLabel) ?>
      <?php if($isDys): ?> · <span style="color:#ffb060">📖 Mode DYS</span><?php endif; ?>
    </div>
  </div>
  <div class="progress-bar" style="flex:1;min-width:100px;height:5px;border-radius:3px;margin:0;">
    <div class="progress-fill" id="progressFill" style="width:0%"></div>
  </div>
  <div class="sticky-right">
    <div class="q-counter">
      <div class="q-counter-num"><span id="answeredCount">0</span> / <?= (int)ppa_cfg('questions_per_test', 20) ?></div>
      <div class="q-counter-label">Réponses</div>
    </div>
    <div style="width:1px;height:32px;background:var(--line2);"></div>
    <div class="timer" id="timer"><?= $isDys ? '20:00' : '15:00' ?></div>
  </div>
</div>

<div id="questionsContainer"<?= $isDys ? ' class="dys-mode"' : '' ?>></div>

<div class="card" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
  <div id="submitStatus" class="muted" style="font-size:13px;"></div>
  <button class="btn primary" id="submitBtn" style="font-size:14px;padding:11px 24px;">
    ✅ Terminer le test
  </button>
</div>

<script>
(function(){
const STATE = <?= json_encode([
  'candidateId' => (int)$cand['id'],
  'type'        => $cand['ppa_type'],
  'questions'   => $test['questions'],
  'isDys'       => $isDys,
], JSON_UNESCAPED_UNICODE) ?>;

const answers       = {};
const total         = STATE.questions.length;
const answeredEl    = document.getElementById('answeredCount');
let   timeLeft      = STATE.isDys ? 20 * 60 : 15 * 60;
let   timerInterval;

const container = document.getElementById('questionsContainer');
STATE.questions.forEach((q, i) => {
  const card = document.createElement('div');
  card.className = 'card question-card';
  card.id = 'qcard-' + q.id;

  const shuffled = [...q.choices].sort(() => Math.random() - .5);

  card.innerHTML = `
    <div class="question-number">Question ${i+1} / ${total}</div>
    <div class="question-text">${escHtml(q.text)}</div>
    <div class="choices">
      ${shuffled.map(c => `
        <label class="choice-label" data-qid="${q.id}" data-key="${c.key}">
          <input type="radio" name="q_${q.id}" value="${c.key}">
          <div class="choice-dot"></div>
          <div class="choice-text">${escHtml(c.label)}</div>
        </label>
      `).join('')}
    </div>
  `;
  container.appendChild(card);
});

container.addEventListener('click', e => {
  const lbl = e.target.closest('.choice-label');
  if (!lbl) return;
  const qid = lbl.dataset.qid;
  const key = lbl.dataset.key;
  answers[qid] = key;

  document.querySelectorAll(`.choice-label[data-qid="${qid}"]`).forEach(l => l.classList.remove('selected'));
  lbl.classList.add('selected');
  document.querySelector(`input[name="q_${qid}"]`).checked = true;
  document.getElementById('qcard-' + qid).classList.add('answered');

  const done = Object.keys(answers).length;
  document.getElementById('progressFill').style.width = (done / total * 100) + '%';
  answeredEl.textContent = done;
});

const timerEl = document.getElementById('timer');
function updateTimer() {
  timeLeft = Math.max(0, timeLeft - 1);
  const m = String(Math.floor(timeLeft/60)).padStart(2,'0');
  const s = String(timeLeft%60).padStart(2,'0');
  timerEl.textContent = `${m}:${s}`;
  if      (timeLeft <= 60)  timerEl.className = 'timer danger';
  else if (timeLeft <= 180) timerEl.className = 'timer warning';
  if (timeLeft === 0) submitTest();
}
timerInterval = setInterval(updateTimer, 1000);

const submitBtn = document.getElementById('submitBtn');
const statusEl  = document.getElementById('submitStatus');

async function submitTest() {
  clearInterval(timerInterval);
  const unanswered = STATE.questions.filter(q => !answers[q.id]).length;
  if (unanswered > 0) {
    statusEl.textContent = `⚠ ${unanswered} question${unanswered>1?'s':''} sans réponse.`;
    statusEl.style.color = '#ffb060';
    timerInterval = setInterval(updateTimer, 1000);
    return;
  }
  submitBtn.disabled = true;
  statusEl.textContent = 'Envoi en cours…';
  statusEl.style.color = '';

  try {
    const r = await fetch('api/submit.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ answers, dys: STATE.isDys })
    });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Erreur serveur');
    window.location.href = 'result.php';
  } catch(err) {
    statusEl.textContent = '❌ ' + err.message;
    statusEl.style.color = '#ff7a7a';
    submitBtn.disabled = false;
  }
}

submitBtn.addEventListener('click', () => {
  const unanswered = STATE.questions.filter(q => !answers[q.id]).length;
  if (unanswered > 0 && !confirm(`${unanswered} question(s) sans réponse. Terminer quand même ?`)) return;
  submitTest();
});

function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
})();
</script>

<?php ppa_layout_foot();
