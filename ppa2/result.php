<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/ppa_layout.php';

$candidateId = (int)($_SESSION['ppa_candidate_id'] ?? 0);
$cand        = $candidateId ? ppa_candidate_by_id($candidateId) : null;
$result      = $_SESSION['ppa_result'] ?? null;

ppa_layout_head('Test terminé');

if (!$cand || !$result) {
    if ($cand && !empty($cand['test_result'])) {

    } else { ?>
      <div class="card" style="text-align:center;padding:32px;">
        <div style="font-size:40px;margin-bottom:12px;">⚠️</div>
        <div style="font-family:var(--font-head);font-size:16px;font-weight:700;">Page introuvable</div>
        <p class="muted" style="margin-top:8px;">La session a peut-être expiré.</p>
      </div>
    <?php ppa_layout_foot(); exit;
    }
}

$typeLabel = ['civil'=>'Civil','sasp'=>'Police (SASP)','chasse'=>'Chasse'][$cand['ppa_type']] ?? strtoupper($cand['ppa_type']);
?>

<div class="card" style="text-align:center;padding:40px 24px;">
  <div style="font-size:56px;margin-bottom:20px;">📋</div>
  <div style="font-family:var(--font-head);font-size:22px;font-weight:800;margin-bottom:12px;">
    Test terminé
  </div>
  <p style="color:var(--muted);font-size:14px;line-height:1.7;max-width:440px;margin:0 auto 20px;">
    Votre test PPA <strong style="color:var(--text)"><?= ppa_h($typeLabel) ?></strong> a bien été enregistré et transmis à un examinateur SAMR.<br><br>
    Un membre de l'équipe reprendra contact avec vous prochainement pour vous communiquer la suite de la procédure.
  </p>
  <div style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:10px;background:rgba(0,212,170,.08);border:1px solid rgba(0,212,170,.25);color:var(--accent2);font-size:13px;font-weight:600;">
    ✅ Résultat transmis à l'équipe SAMR
  </div>
</div>

<?php

unset($_SESSION['ppa_result'], $_SESSION['ppa_candidate_id'], $_SESSION['ppa_candidate_type'], $_SESSION['ppa_dys']);
ppa_layout_foot();

