<?php

defined('PPA2_ROOT')      || define('PPA2_ROOT',      __DIR__);
defined('PPA2_SITE_ROOT') || define('PPA2_SITE_ROOT', dirname(__DIR__));

$ppa_cfg = require PPA2_ROOT . '/config.php';

function ppa_cfg(string $key, $default = null) {
    global $ppa_cfg;
    return $ppa_cfg[$key] ?? $default;
}

require_once PPA2_SITE_ROOT . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $httpsOn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_name('samr_session');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $httpsOn,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 7));
    $samrSessions = '/var/lib/samr-sessions';
    if (is_dir($samrSessions) && is_writable($samrSessions)) {
        ini_set('session.save_path', $samrSessions);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
    }
    session_start();
}

defined('PPA_TABLE') || define('PPA_TABLE', 'ppa_candidates');

function ppa_db(): PDO {
    $pdo = db();

    $pdo->exec("CREATE TABLE IF NOT EXISTS `" . PPA_TABLE . "` (
        `id`                   INT(11)      NOT NULL AUTO_INCREMENT,
        `last_name`            VARCHAR(120) NOT NULL,
        `first_name`           VARCHAR(120) NOT NULL,
        `birth_date`           DATE         NOT NULL,
        `ppa_type`             VARCHAR(20)  NOT NULL,
        `paid`                 TINYINT(1)   NOT NULL DEFAULT 0,
        `ticket_channel_id`    VARCHAR(64)  DEFAULT NULL,
        `created_by_user_id`   INT(11)      DEFAULT NULL,
        `status`               VARCHAR(30)  NOT NULL DEFAULT 'draft',
        `activation_token`     VARCHAR(128) DEFAULT NULL,
        `activation_expires_at` DATETIME    DEFAULT NULL,
        `activation_sent_at`   DATETIME     DEFAULT NULL,
        `activation_used_at`   DATETIME     DEFAULT NULL,
        `activated_at`         DATETIME     DEFAULT NULL,
        `test_started_at`      DATETIME     DEFAULT NULL,
        `test_finished_at`     DATETIME     DEFAULT NULL,
        `test_score`           INT(11)      DEFAULT NULL,
        `test_max`             INT(11)      DEFAULT NULL,
        `test_result`          VARCHAR(20)  DEFAULT NULL,
        `result_payload`       LONGTEXT     DEFAULT NULL,
        `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_token` (`activation_token`),
        KEY `idx_status` (`status`),
        KEY `idx_type` (`ppa_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $alters = [
        "ALTER TABLE `" . PPA_TABLE . "` ADD COLUMN IF NOT EXISTS `activation_token`      VARCHAR(128) DEFAULT NULL",
        "ALTER TABLE `" . PPA_TABLE . "` ADD COLUMN IF NOT EXISTS `activation_expires_at` DATETIME     DEFAULT NULL",
        "ALTER TABLE `" . PPA_TABLE . "` ADD COLUMN IF NOT EXISTS `activation_sent_at`    DATETIME     DEFAULT NULL",
        "ALTER TABLE `" . PPA_TABLE . "` ADD COLUMN IF NOT EXISTS `activation_used_at`    DATETIME     DEFAULT NULL",
        "ALTER TABLE `" . PPA_TABLE . "` ADD COLUMN IF NOT EXISTS `activated_at`          DATETIME     DEFAULT NULL",
        "ALTER TABLE `" . PPA_TABLE . "` ADD COLUMN IF NOT EXISTS `ticket_channel_id`     VARCHAR(64)  DEFAULT NULL",
        "ALTER TABLE `" . PPA_TABLE . "` ADD COLUMN IF NOT EXISTS `test_started_at`       DATETIME     DEFAULT NULL",
        "ALTER TABLE `" . PPA_TABLE . "` ADD COLUMN IF NOT EXISTS `test_finished_at`      DATETIME     DEFAULT NULL",
        "ALTER TABLE `" . PPA_TABLE . "` ADD COLUMN IF NOT EXISTS `test_score`            INT(11)      DEFAULT NULL",
        "ALTER TABLE `" . PPA_TABLE . "` ADD COLUMN IF NOT EXISTS `test_max`              INT(11)      DEFAULT NULL",
        "ALTER TABLE `" . PPA_TABLE . "` ADD COLUMN IF NOT EXISTS `test_result`           VARCHAR(20)  DEFAULT NULL",
        "ALTER TABLE `" . PPA_TABLE . "` ADD COLUMN IF NOT EXISTS `result_payload`        LONGTEXT     DEFAULT NULL",
    ];
    foreach ($alters as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
    try { $pdo->exec("ALTER TABLE `" . PPA_TABLE . "` ADD UNIQUE KEY `uniq_token` (`activation_token`)"); } catch (Throwable $e) {}

    return $pdo;
}

function ppa_h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function ppa_now(): string {
    return date('Y-m-d H:i:s');
}

function ppa_candidate_by_id(int $id): ?array {
    $st = ppa_db()->prepare("SELECT * FROM `" . PPA_TABLE . "` WHERE id=? LIMIT 1");
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function ppa_candidate_by_token(string $token): ?array {
    $st = ppa_db()->prepare("SELECT * FROM `" . PPA_TABLE . "` WHERE activation_token=? LIMIT 1");
    $st->execute([$token]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function ppa_activation_url(string $token): string {
    return rtrim(ppa_cfg('base_url', ''), '/') . '/activate.php?token=' . urlencode($token);
}

function ppa_load_bank(string $type, bool $dys = false): array {

    $bankType = ($type === 'ammunation') ? 'sasp' : $type;
    $map = ['civil'=>'civil_bank.json', 'sasp'=>'sasp_bank.json', 'chasse'=>'chasse_bank.json'];
    if (!isset($map[$bankType])) throw new RuntimeException("Type PPA invalide: {$type}");
    $file = $map[$bankType];
    if ($dys) {
        $easy = 'easy' . $bankType . '_bank.json';
        if (is_file(PPA2_ROOT . '/data/' . $easy)) $file = $easy;
    }
    return json_decode(file_get_contents(PPA2_ROOT . '/data/' . $file), true);
}

function ppa_select_questions(string $type, int $count = 20, bool $dys = false): array {
    $bank = ppa_load_bank($type, $dys);
    shuffle($bank);
    return array_slice($bank, 0, min($count, count($bank)));
}

function ppa_axes(string $type): array {

    if ($type === 'sasp' || $type === 'ammunation') return [
        'controle_emotionnel' => 'Contrôle émotionnel',
        'desescalade'         => 'Désescalade',
        'proportionnalite'    => 'Proportionnalité / usage de la force',
        'incertitude'         => 'Tolérance à l\'incertitude',
        'pression'            => 'Gestion de la pression',
        'ego_autorite'        => 'Rapport à l\'autorité / ego',
        'cooperation'         => 'Coopération & ajustement',
        'jugement'            => 'Jugement professionnel',
        'fatigue'             => 'Fatigue & altération cognitive',
    ];
    if ($type === 'chasse') return [
        'psychologie'    => 'Psychologie & posture',
        'securite'       => 'Sécurité & environnement',
        'reglementation' => 'Réglementation & cadre',
    ];
    return [];
}

function ppa_axis_comment(string $axisKey, string $label, float $avg, int $negCount): string {
    if ($negCount >= 2)
        return "Indicateur de risque sur \"{$label}\" : plusieurs réponses impulsives ou rigides ont été relevées. Recommandation : encadrement renforcé et suivi en situation.";
    if ($avg >= 0.75) return "Niveau stable sur \"{$label}\" : réponses globalement mesurées, cohérentes et procédurales.";
    if ($avg >= 0.25) return "Niveau correct sur \"{$label}\" : posture globalement adaptée, avec quelques tendances à surveiller.";
    if ($avg >= -0.25) return "Niveau intermédiaire sur \"{$label}\" : résultats hétérogènes ; vigilance recommandée.";
    return "Niveau préoccupant sur \"{$label}\" : tendance à privilégier des options plus fermes ou moins réversibles.";
}

function ppa_global_comment(string $type, float $pct, int $critEmotional, int $critForce, int $totalNeg): string {
    if ($type === 'sasp' || $type === 'ammunation') {
        if ($critEmotional >= 2 || $critForce >= 2)
            return "Profil défavorable : présence de signaux répétitifs d'impulsivité/rigidité sur des axes critiques. Port d'arme déconseillé sans encadrement et nouvelle évaluation.";
        if ($pct >= 0.85) return "Profil favorable : candidat stable, mesuré et procédural. Indices compatibles avec un port d'arme en service RP.";
        if ($pct >= 0.70) return "Profil globalement favorable : bonnes bases, avec quelques points à surveiller. Port d'arme envisageable sous cadre clair.";
        if ($pct >= 0.55) return "Profil intermédiaire : réussite possible mais avec variabilité ; encadrement recommandé.";
        return "Profil défavorable : score insuffisant et cohérence décisionnelle limitée sous pression. Port d'arme non recommandé.";
    }
    if ($type === 'chasse') {
        if ($totalNeg >= 4) return "Profil défavorable : présence répétée de choix dangereux. Entretien médical indispensable avant toute décision.";
        if ($pct >= 0.70) return "Profil favorable : prudence et responsabilité globalement cohérentes.";
        if ($pct >= 0.50) return "Profil intermédiaire : bases présentes, mais variabilité ; l'entretien doit confirmer la stabilité.";
        return "Profil défavorable : prudence insuffisante ou prise de risque trop fréquente.";
    }
    if ($pct >= 0.85) return "Profil favorable : résultats cohérents et mesurés.";
    if ($pct >= 0.70) return "Profil globalement favorable : quelques points à surveiller.";
    if ($pct >= 0.55) return "Profil intermédiaire : variabilité décisionnelle ; suivi recommandé.";
    return "Profil défavorable : réponses insuffisamment adaptées dans l'ensemble.";
}

function ppa_grade(string $type, array $questionIds, array $answers, bool $dys = false): array {
    $bank = ppa_load_bank($type, $dys);
    $byId = array_column($bank, null, 'id');
    $thresholds = ppa_cfg('thresholds', ['civil'=>14,'sasp'=>14,'chasse'=>12,'ammunation'=>14]);
    $pass = (float)($thresholds[$type] ?? 14);

    $axes    = ppa_axes($type);
    $perAxis = [];
    foreach ($axes as $k => $label) {
        $perAxis[$k] = ['label'=>$label,'sum'=>0.0,'n'=>0,'neg'=>0];
    }

    $score = 0.0;
    $details = [];
    $critEmotional = 0;
    $critForce     = 0;

    foreach ($questionIds as $qid) {
        if (!isset($byId[$qid])) continue;
        $q   = $byId[$qid];
        $key = $answers[$qid] ?? null;
        $ans = ($key && isset($q['answers'][$key])) ? $q['answers'][$key] : null;
        $pts = $ans ? (float)($ans['score'] ?? 0) : 0.0;
        $score += $pts;

        $axisKey = (string)($q['axis'] ?? '');
        if ($axisKey !== '' && isset($perAxis[$axisKey])) {
            $perAxis[$axisKey]['sum'] += $pts;
            $perAxis[$axisKey]['n']   += 1;
            if ($pts < 0) {
                $perAxis[$axisKey]['neg'] += 1;
                if ($axisKey === 'controle_emotionnel') $critEmotional++;
                if ($axisKey === 'proportionnalite')    $critForce++;
            }
        }
        $details[] = [
            'id'           => $qid,
            'axis'         => $axisKey,
            'question'     => $q['text'],
            'answer_key'   => $key,
            'answer_label' => $ans['label'] ?? '(non répondu)',
            'score'        => $pts,
        ];
    }

    $max = count($questionIds);
    $pct = $max > 0 ? ($score / $max) : 0.0;

    $axisResults = [];
    foreach ($perAxis as $k => $a) {
        if ($a['n'] === 0) continue;
        $avg = $a['sum'] / $a['n'];
        $axisResults[$k] = [
            'label'   => $a['label'],
            'sum'     => $a['sum'],
            'n'       => $a['n'],
            'neg'     => $a['neg'],
            'avg'     => $avg,
            'comment' => ppa_axis_comment($k, $a['label'], $avg, $a['neg']),
        ];
    }

    $totalNeg = array_sum(array_column(array_values($perAxis), 'neg'));

    return [
        'score'          => $score,
        'max'            => $max,
        'pass_score'     => $pass,
        'result'         => $score >= $pass ? 'REUSSITE' : 'ECHEC',
        'details'        => $details,
        'per_axis'       => $axisResults,
        'global_comment' => ppa_global_comment($type, $pct, $critEmotional, $critForce, $totalNeg),
        'critical'       => ['emotional'=>$critEmotional,'force'=>$critForce],
    ];
}

function ppa_build_report(array $cand, array $graded, string $startedAt, string $endedAt, bool $dys): string {
    $e    = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $name = $e(trim($cand['first_name'].' '.$cand['last_name']));
    $type = strtoupper($e($cand['ppa_type']));
    $isOk = $graded['result'] === 'REUSSITE';
    $scoreStr = number_format((float)$graded['score'], 1) . '/' . (int)$graded['max'];

    $axisRows = '';
    foreach (($graded['per_axis'] ?? []) as $a) {
        $axisRows .= '<tr>'
            . '<td><b>'.$e($a['label']).'</b></td>'
            . '<td style="text-align:center">'.number_format((float)$a['sum'],1).'</td>'
            . '<td style="text-align:center">'.(int)$a['n'].'</td>'
            . '<td style="text-align:center">'.number_format((float)$a['avg'],2).'</td>'
            . '<td>'.$e($a['comment'] ?? '').'</td>'
            . '</tr>';
    }
    $axisSection = $axisRows ? '
  <div class="card">
    <div class="muted" style="margin-bottom:8px;">Synthèse par axe</div>
    <table><thead><tr><th>Axe</th><th style="width:80px;text-align:center">Score</th><th style="width:60px;text-align:center">N</th><th style="width:80px;text-align:center">Moy.</th><th>Commentaire</th></tr></thead>
    <tbody>'.$axisRows.'</tbody></table>
  </div>' : '';

    $detailRows = '';
    foreach (($graded['details'] ?? []) as $i => $d) {
        $detailRows .= '<tr data-qid="'.$e((string)($d['id'] ?? '')).'"><td>Q'.($i+1).'</td><td>'.$e($d['question']).'</td><td>'.$e($d['answer_label'] ?? '(non répondu)').'</td><td style="text-align:center;font-weight:800;">'.number_format((float)$d['score'],1).'</td></tr>';
    }

    $badgeCls = $isOk ? 'ok' : 'bad';
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rapport PPA '.$type.' — '.$name.'</title>
<style>
:root{color-scheme:dark}body{font-family:system-ui,-apple-system,sans-serif;margin:24px;background:#0b0d10;color:#e9eef5}
.wrap{max-width:980px;margin:0 auto}.head{display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:4px}
.title{font-weight:900;font-size:20px}.card{background:#11151b;border:1px solid #263041;border-radius:16px;padding:16px;margin:12px 0}
.badge{display:inline-block;padding:7px 14px;border-radius:999px;border:1px solid #263041;background:#0f141c;font-weight:900;font-size:15px}
.muted{color:#a9b4c3}table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid #263041;padding:10px;vertical-align:top}
th{text-align:left;color:#a9b4c3;font-weight:800}.ok{color:#7CFF9E;font-weight:900}.bad{color:#FF7C7C;font-weight:900}
.meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.comment-box{background:#0f141c;border-left:3px solid #3b9eff;padding:10px 14px;border-radius:0 10px 10px 0;margin-top:10px;font-style:italic}
@media(max-width:640px){.meta-grid{grid-template-columns:1fr}}
</style></head><body><div class="wrap">
  <div class="head"><div><div class="title">Évaluation PPA — '.$type.'</div><div class="muted">Rapport automatique — SAMR</div></div>
  <div class="badge '.$badgeCls.'">'.($isOk?'✅':'❌').' '.$graded['result'].' · '.$scoreStr.' (seuil '.(int)$graded['pass_score'].')</div></div>
  <div class="card"><div class="meta-grid">
    <div><b>Candidat :</b> '.$name.'</div><div><b>Type :</b> '.$type.'</div>
    <div><b>Début :</b> '.$e($startedAt).'</div><div><b>Fin :</b> '.$e($endedAt).'</div>
    <div><b>Mode DYS :</b> '.($dys?'OUI':'NON').'</div><div><b>Score :</b> '.$scoreStr.' (seuil '.(int)$graded['pass_score'].')</div>
  </div><div class="comment-box">'.$e($graded['global_comment'] ?? '').'</div></div>
  '.$axisSection.'
  <div class="card"><div class="muted" style="margin-bottom:8px;">Détail des réponses</div>
    <table><thead><tr><th style="width:60px">N°</th><th>Question</th><th style="width:38%">Réponse</th><th style="width:70px;text-align:center">Pts</th></tr></thead>
    <tbody>'.$detailRows.'</tbody></table>
  </div>
</div></body></html>';
}

function ppa_channel_name(string $type, string $firstName, string $lastName, string $step): string {
    $slugify = function(string $s): string {
        $s = mb_strtolower(trim($s), 'UTF-8');

        $from = ['à','â','ä','é','è','ê','ë','î','ï','ô','ö','ù','û','ü','ç','ñ'];
        $to   = ['a','a','a','e','e','e','e','i','i','o','o','u','u','u','c','n'];
        $s = str_replace($from, $to, $s);

        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim($s, '-');
    };

    $parts = [
        'tp',
        $slugify($type),
        $slugify($firstName),
        $slugify($lastName),
        $slugify($step),
    ];
    $name = implode('-', array_filter($parts));
    return mb_substr($name, 0, 100);
}

function ppa_rename_channel(string $channelId, string $newName): array {
    $token = ppa_cfg('discord_bot_token', '');
    if ($token === '') return ['ok'=>false,'error'=>'Bot token manquant'];
    if ($channelId === '') return ['ok'=>false,'error'=>'ID canal manquant'];

    $ch = curl_init("https://discord.com/api/v10/channels/{$channelId}");
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bot ' . $token,
        ],
        CURLOPT_POSTFIELDS => json_encode(['name' => $newName], JSON_UNESCAPED_UNICODE),
    ]);
    $resp   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);
    return ['ok'=>$status>=200&&$status<300,'status'=>$status,'error'=>$err,'response'=>$resp];
}

function ppa_send_discord(string $channelId, string $content): array {
    $token = ppa_cfg('discord_bot_token', '');
    if ($token === '') return ['ok'=>false,'error'=>'Bot token manquant'];
    if ($channelId === '') return ['ok'=>false,'error'=>'ID canal manquant'];
    $ch = curl_init("https://discord.com/api/v10/channels/{$channelId}/messages");
    curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bot '.$token],
        CURLOPT_POSTFIELDS=>json_encode(['content'=>$content],JSON_UNESCAPED_UNICODE)]);
    $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    return ['ok'=>$status>=200&&$status<300,'status'=>$status,'error'=>$err];
}

function ppa_send_payment_request(array $cand): array {
    $channelId = (string)($cand['ticket_channel_id'] ?? '');
    if ($channelId === '') {
        return ['ok' => false, 'error' => 'Aucun ID de ticket Discord renseigné.'];
    }

    $type      = (string)($cand['ppa_type'] ?? '');
    $firstName = (string)($cand['first_name'] ?? '');
    $lastName  = (string)($cand['last_name'] ?? '');

    $messages = [
        'civil' => sprintf(
            "Bonjour **%s %s**,\n\n"
            . "Votre demande de **Permis de Port d'Arme Civil** a bien été prise en compte par le SAMR. ✅\n\n"
            . "Cependant, la procédure ne pourra se poursuivre qu'une fois que vous serez venu·e à l'hôpital vous acquitter d'une facture de **10 000 \$**.\n\n"
            . "📎 Merci de déposer la **preuve de paiement en pièce jointe** de votre demande ainsi qu'une photocopie de votre piéce d'identité **afin que nous puissions valider votre dossier.\n\n"
            . "*L'équipe SAMR reste disponible pour toute question.*",
            $firstName, $lastName
        ),
        'chasse' => sprintf(
            "<@&1486501434030821436>\n\n"
            . "Bonjour **%s %s**,\n\n"
            . "Votre demande de **Permis de Chasse** a bien été prise en compte. ✅\n\n"
            . "Afin de poursuivre la procédure, merci de vous rendre à l'hôpital pour vous acquitter d'une facture de **3 000 \$**.\n\n"
            . "📎 Merci de déposer la **preuve de paiement en pièce jointe de votre demande ainsi qu'une photocopie de votre piéce d'identité ** afin que nous puissions valider votre dossier.\n\n"
            . "*L'équipe SAMR reste disponible pour toute question.*",
            $firstName, $lastName
        ),
    ];

    if (!isset($messages[$type])) {

        return ['ok' => true, 'error' => null, 'skipped' => true];
    }

    $result = ppa_send_discord($channelId, $messages[$type]);

    if ($type === 'chasse' && $result['ok']) {
        ppa_add_role_to_channel($channelId, 'CHANGE_ME_DISCORD_ROLE_ID_PPA');
    }

    return $result;
}

function ppa_add_role_to_channel(string $channelId, string $roleId): array {
    $token = ppa_cfg('discord_bot_token', '');
    if ($token === '') return ['ok' => false, 'error' => 'Bot token manquant'];
    if ($channelId === '') return ['ok' => false, 'error' => 'ID canal manquant'];
    if ($roleId === '')    return ['ok' => false, 'error' => 'ID rôle manquant'];

    $allow = strval(0x400 | 0x10000 | 0x800);

    $ch = curl_init("https://discord.com/api/v10/channels/{$channelId}/permissions/{$roleId}");
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bot ' . $token,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'allow' => $allow,
            'deny'  => '0',
            'type'  => 0,
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $resp   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'error' => $err, 'response' => $resp];
}

function ppa_send_webhook(string $type, string $content, string $reportHtml = '', ?array $cand = null): array {
    $webhooks = ppa_cfg('webhooks', []);

    $url = (string)($webhooks[$type] ?? $webhooks['sasp'] ?? '');
    if ($url === '') return ['ok'=>false,'error'=>"Webhook '{$type}' non configuré"];

    $boundary = '----PPABoundary'.bin2hex(random_bytes(8));
    $body  = "--{$boundary}\r\nContent-Disposition: form-data; name=\"payload_json\"\r\n\r\n";
    $body .= json_encode(['content'=>$content],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\r\n";

    if ($reportHtml !== '') {
        $safeName = $cand ? preg_replace('/[^a-z0-9_\-]+/i','_',strtolower($cand['first_name'].'_'.$cand['last_name'])) : 'candidat';
        $filename = 'rapport_'.$type.'_'.$safeName.'_'.date('Y-m-d_H-i-s').'.html';
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"files[0]\"; filename=\"{$filename}\"\r\nContent-Type: text/html\r\n\r\n".$reportHtml."\r\n";
    }
    $body .= "--{$boundary}--\r\n";

    $ch = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,
        CURLOPT_HTTPHEADER=>['Content-Type: multipart/form-data; boundary='.$boundary],
        CURLOPT_POSTFIELDS=>$body]);
    $resp=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    return ['ok'=>$status>=200&&$status<300,'status'=>$status,'error'=>$err];
}
