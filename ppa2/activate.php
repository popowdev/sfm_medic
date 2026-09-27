<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/ppa_layout.php';

$token = trim($_GET['token'] ?? '');
ppa_layout_head('Activation');

if ($token === '') {
    echo '<div class="card"><h2>🔒 Lien invalide</h2><p class="muted" style="margin-top:8px">Aucun token fourni.</p></div>';
    ppa_layout_foot(); exit;
}

$cand = ppa_candidate_by_token($token);

if (!$cand) {
    echo '<div class="card"><h2>🔒 Lien invalide</h2><p class="muted" style="margin-top:8px">Ce lien n\'existe pas ou a expiré.</p></div>';
    ppa_layout_foot(); exit;
}

if (!empty($cand['test_result'])) {
    echo '<div class="card" style="text-align:center;padding:32px;">
        <div style="font-size:40px;margin-bottom:12px;">✅</div>
        <div style="font-family:var(--font-head);font-size:16px;font-weight:700;margin-bottom:8px;">Test déjà complété</div>
        <p class="muted">Ce test a déjà été soumis. Contacte le SAMR pour plus d\'informations.</p>
    </div>';
    ppa_layout_foot(); exit;
}

if (!empty($cand['activation_expires_at']) && strtotime($cand['activation_expires_at']) < time()) {
    echo '<div class="card"><h2>⏰ Lien expiré</h2><p class="muted" style="margin-top:8px">Ce lien a expiré. Demande un nouveau lien au SAMR.</p></div>';
    ppa_layout_foot(); exit;
}

$_SESSION['ppa_candidate_id']   = (int)$cand['id'];
$_SESSION['ppa_candidate_type'] = (string)$cand['ppa_type'];
$_SESSION['ppa_token']          = $token;
unset($_SESSION['ppa_test'], $_SESSION['ppa_dys']);

$typeLabel = ['civil'=>'Civil','sasp'=>'Police (SASP)','chasse'=>'Chasse','ammunation'=>'Ammunation'][$cand['ppa_type']] ?? strtoupper($cand['ppa_type']);
$startUrl  = 'index.php?token=' . urlencode($token);
?>
<div class="card" style="text-align:center;padding:32px 20px;">
  <div style="font-size:48px;margin-bottom:16px;">✅</div>
  <div style="font-family:var(--font-head);font-size:20px;font-weight:800;margin-bottom:8px;">Accès activé !</div>
  <p style="color:var(--muted);margin-bottom:4px;">Bienvenue, <strong style="color:var(--text)"><?= ppa_h($cand['first_name'] . ' ' . $cand['last_name']) ?></strong></p>
  <p style="color:var(--muted);margin-bottom:24px;">
    Test PPA : <span class="pill pill-blue"><?= ppa_h($typeLabel) ?></span>
  </p>

  <div style="max-width:400px;margin:0 auto 8px;padding:18px;border-radius:13px;border:1px solid var(--line2);background:rgba(255,255,255,.02);text-align:left;">
    <div style="font-family:var(--font-head);font-size:13px;font-weight:700;margin-bottom:6px;">⏱ Durée du test</div>
    <p style="font-size:12px;color:var(--muted);margin-bottom:16px;line-height:1.6;">
      Le test comporte <strong style="color:var(--text)">20 questions</strong>.
      Si tu es reconnu·e DYS, le <strong style="color:#ffb060">mode adapté</strong> te propose un test
      <strong style="color:#ffb060">reformulé pour une lecture plus facile</strong> (phrases plus courtes et plus claires)
      et <strong style="color:#ffb060">20 minutes</strong> au lieu de 15.
    </p>
    <div style="display:flex;gap:8px;">
      <a href="<?= ppa_h($startUrl . '&dys=0') ?>" class="btn" style="flex:1;justify-content:center;padding:11px 8px;">
        ⏱ 15 min — Standard
      </a>
      <a href="<?= ppa_h($startUrl . '&dys=1') ?>" class="btn" style="flex:1;justify-content:center;padding:11px 8px;border-color:rgba(255,180,50,.35);background:rgba(255,180,50,.1);color:#ffb060;">
        📖 20 min — Adapté (DYS)
      </a>
    </div>
  </div>
</div>
<?php
ppa_layout_foot();
