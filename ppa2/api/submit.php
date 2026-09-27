<?php
require __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

function je(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

try {

$candidateId = (int)($_SESSION['ppa_candidate_id'] ?? 0);
$cand        = $candidateId ? ppa_candidate_by_id($candidateId) : null;
$test        = $_SESSION['ppa_test'] ?? null;

if (!$cand || !$test) {
    je(['ok'=>false,'error'=>'Session invalide ou expirée. Recharge la page et réessaie.']);
}
if (!empty($cand['test_result'])) {
    je(['ok'=>false,'error'=>'Ce test a déjà été soumis.']);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    je(['ok'=>false,'error'=>'Données invalides.']);
}

$answers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
$dys     = !empty($test['dys']);
$now     = ppa_now();
$type    = (string)$cand['ppa_type'];

$graded = ppa_grade($type, $test['question_ids'], $answers, $dys);

ppa_db()->prepare("UPDATE `" . PPA_TABLE . "` SET
    status='finished', test_finished_at=?, test_score=?, test_max=?,
    test_result=?, result_payload=?, updated_at=? WHERE id=?
")->execute([
    $now, $graded['score'], $graded['max'],
    $graded['result'], json_encode($graded, JSON_UNESCAPED_UNICODE),
    $now, $cand['id'],
]);

$_SESSION['ppa_result'] = ['graded'=>$graded,'dys'=>$dys,'started_at'=>$test['started_at'],'ended_at'=>$now];
unset($_SESSION['ppa_test']);

$reportHtml = ppa_build_report($cand, $graded, $test['started_at'], $now, $dys);

$emoji   = $graded['result'] === 'REUSSITE' ? '✅' : '❌';
$content = "🧠 **Résultat Test PPA**\n"
         . "👤 Candidat : {$cand['first_name']} {$cand['last_name']}\n"
         . "🧾 Type : " . strtoupper($type) . "\n"
         . "🧮 Score : {$graded['score']}/{$graded['max']} (seuil {$graded['pass_score']})\n"
         . "{$emoji} Résultat : **{$graded['result']}**\n"
         . "⏱️ Mode DYS : " . ($dys ? 'Oui' : 'Non') . "\n"
         . "💬 " . ($graded['global_comment'] ?? '');

ppa_send_webhook($type, $content, $reportHtml, $cand);

je(['ok' => true]);

} catch (Throwable $e) {
    je(['ok' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()]);
}
