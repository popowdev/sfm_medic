<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';

if (file_exists(__DIR__ . '/ppa2/bootstrap.php')) {
    require_once __DIR__ . '/ppa2/bootstrap.php';
}

require_login();
$u       = current_user();
$uid     = (int)($u['id'] ?? 0);
$isAdmin = ($u['role'] ?? '') === 'admin';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$ppaCfgFile = __DIR__ . '/ppa2/config.php';
$ppaCfg     = file_exists($ppaCfgFile) ? require $ppaCfgFile : [];
$baseUrl    = rtrim((string)($ppaCfg['base_url'] ?? 'https://exemple.tld/ppa2'), '/');
$botToken   = (string)($ppaCfg['discord_bot_token'] ?? '');

$pdo      = db();
$fStmt    = $pdo->prepare("SELECT a.formation_id FROM personnel_formations_assign a WHERE a.user_id=?");
$fStmt->execute([$uid]);
$userForms = array_column($fStmt->fetchAll(PDO::FETCH_ASSOC), 'formation_id');

$hasSasp   = $isAdmin || in_array(5, $userForms);
$hasChasse = $isAdmin || in_array(6, $userForms);
$canAccess = $isAdmin || $hasSasp || $hasChasse;

if (!$canAccess) {
    samr_layout_start('SAMR — PPA');
    echo '<div class="card" style="text-align:center;padding:40px;">
        <div style="font-size:32px;margin-bottom:12px;">🔒</div>
        <div style="font-family:var(--font-head);font-size:16px;font-weight:700;margin-bottom:8px;">Accès refusé</div>
        <div class="muted">Page réservée aux membres avec la formation PPA SASP ou PPA Chasse.</div>
    </div>';
    samr_layout_end();
    exit;
}

$allowedTypes = array_values(array_unique(array_filter([
    'civil',
    $hasSasp   ? 'sasp'       : null,
    $hasSasp   ? 'ammunation' : null,
    $hasChasse ? 'chasse'     : null,
])));

$table = 'ppa_candidates';
$pdo->exec("CREATE TABLE IF NOT EXISTS `{$table}` (
    `id`                    INT(11)      NOT NULL AUTO_INCREMENT,
    `last_name`             VARCHAR(120) NOT NULL,
    `first_name`            VARCHAR(120) NOT NULL,
    `phone`                 VARCHAR(30)  DEFAULT NULL,
    `ppa_type`              VARCHAR(20)  NOT NULL,
    `paid`                  TINYINT(1)   NOT NULL DEFAULT 0,
    `ticket_channel_id`     VARCHAR(64)  DEFAULT NULL,
    `created_by_user_id`    INT(11)      DEFAULT NULL,
    `status`                VARCHAR(30)  NOT NULL DEFAULT 'draft',
    `activation_token`      VARCHAR(128) DEFAULT NULL,
    `activation_expires_at` DATETIME     DEFAULT NULL,
    `activation_sent_at`    DATETIME     DEFAULT NULL,
    `activation_used_at`    DATETIME     DEFAULT NULL,
    `activated_at`          DATETIME     DEFAULT NULL,
    `test_started_at`       DATETIME     DEFAULT NULL,
    `test_finished_at`      DATETIME     DEFAULT NULL,
    `test_score`            INT(11)      DEFAULT NULL,
    `test_max`              INT(11)      DEFAULT NULL,
    `test_result`           VARCHAR(20)  DEFAULT NULL,
    `result_payload`        LONGTEXT     DEFAULT NULL,
    `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_token` (`activation_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

foreach ([
    "ALTER TABLE `{$table}` ADD COLUMN IF NOT EXISTS `phone`                VARCHAR(30)  DEFAULT NULL",
    "ALTER TABLE `{$table}` ADD COLUMN IF NOT EXISTS `activation_token`      VARCHAR(128) DEFAULT NULL",
    "ALTER TABLE `{$table}` ADD COLUMN IF NOT EXISTS `activation_expires_at` DATETIME     DEFAULT NULL",
    "ALTER TABLE `{$table}` ADD COLUMN IF NOT EXISTS `activation_sent_at`    DATETIME     DEFAULT NULL",
    "ALTER TABLE `{$table}` ADD COLUMN IF NOT EXISTS `activation_used_at`    DATETIME     DEFAULT NULL",
    "ALTER TABLE `{$table}` ADD COLUMN IF NOT EXISTS `activated_at`          DATETIME     DEFAULT NULL",
    "ALTER TABLE `{$table}` ADD COLUMN IF NOT EXISTS `ticket_channel_id`     VARCHAR(64)  DEFAULT NULL",
    "ALTER TABLE `{$table}` ADD COLUMN IF NOT EXISTS `test_score`            INT(11)      DEFAULT NULL",
    "ALTER TABLE `{$table}` ADD COLUMN IF NOT EXISTS `test_max`              INT(11)      DEFAULT NULL",
    "ALTER TABLE `{$table}` ADD COLUMN IF NOT EXISTS `test_result`           VARCHAR(20)  DEFAULT NULL",
    "ALTER TABLE `{$table}` ADD COLUMN IF NOT EXISTS `result_payload`        LONGTEXT     DEFAULT NULL",
] as $alter) {
    try { $pdo->exec($alter); } catch (Throwable $e) {}
}
try { $pdo->exec("ALTER TABLE `{$table}` ADD UNIQUE KEY `uniq_token` (`activation_token`)"); } catch (Throwable $e) {}

function ppa_send_discord_msg(string $channelId, string $content, string $botToken, array $allowedMentions = []): array {
    if ($botToken === '') return ['ok'=>false,'error'=>'Bot token manquant'];
    if ($channelId === '') return ['ok'=>false,'error'=>'ID canal Discord manquant'];
    $payload = ['content' => $content];
    if (!empty($allowedMentions)) {
        $payload['allowed_mentions'] = $allowedMentions;
    }
    $ch = curl_init("https://discord.com/api/v10/channels/{$channelId}/messages");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Authorization: Bot '.$botToken],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $resp   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);
    return ['ok'=>$status>=200&&$status<300,'status'=>$status,'error'=>$err,'response'=>$resp];
}

function ppa_discord_err(array $send): string {
    $st = (int)($send['status'] ?? 0);
    if ($st === 403) return "le bot n'a pas la permission de poster dans ce salon (donne-lui « Voir le salon » + « Envoyer des messages »)";
    if ($st === 404) return "salon Discord introuvable — vérifie l'ID du salon sur la fiche (clic droit sur le salon → Copier l'identifiant), ou le bot n'a pas accès à ce salon";
    if ($st === 401) return "token du bot invalide";
    return ($send['error'] ?: 'HTTP '.$st);
}

$typeLabels  = ['civil'=>'Civil','sasp'=>'Police (SASP)','chasse'=>'Chasse','ammunation'=>'Ammunation'];
$statusLabels= [
    'draft'     => ['label'=>'Brouillon',   'color'=>'var(--muted)'],
    'sent'      => ['label'=>'Lien envoyé', 'color'=>'#3b9eff'],
    'activated' => ['label'=>'Activé',      'color'=>'#ffb060'],
    'testing'   => ['label'=>'En cours',    'color'=>'#ffb060'],
    'finished'  => ['label'=>'Terminé',     'color'=>'var(--accent2)'],
];

$flash  = null;
$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $postId    = (int)($_POST['id'] ?? 0);
        $lastName  = trim((string)($_POST['last_name']  ?? ''));
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $phone     = trim((string)($_POST['phone']      ?? ''));
        $ppaType   = trim((string)($_POST['ppa_type']   ?? 'civil'));
        $paid      = !empty($_POST['paid']) ? 1 : 0;
        $channelId = trim((string)($_POST['ticket_channel_id'] ?? ''));

        if (!in_array($ppaType, $allowedTypes)) $ppaType = $allowedTypes[0];

        if ($lastName === '' || $firstName === '') {
            $flash = ['type'=>'err','msg'=>'Nom et prénom obligatoires.'];
        } else {
            if ($postId > 0) {
                $pdo->prepare("UPDATE `{$table}` SET last_name=?,first_name=?,phone=?,ppa_type=?,paid=?,ticket_channel_id=?,updated_at=NOW() WHERE id=?")
                    ->execute([$lastName,$firstName,$phone?:null,$ppaType,$paid,$channelId?:null,$postId]);
                $flash = ['type'=>'ok','msg'=>'Fiche mise à jour.'];
                $editId = $postId;
            } else {
                $pdo->prepare("INSERT INTO `{$table}` (last_name,first_name,phone,ppa_type,paid,ticket_channel_id,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())")
                    ->execute([$lastName,$firstName,$phone?:null,$ppaType,$paid,$channelId?:null,$uid]);
                $editId = (int)$pdo->lastInsertId();
                $flash = ['type'=>'ok','msg'=>'Fiche créée.'];

                if ($channelId !== '' && $botToken !== '') {
                    $paymentMessages = [
                        'civil' => sprintf(
                            "Bonjour **%s %s**,\n\n"
                            . "Votre demande de **Permis de Port d'Arme Civil** a bien été prise en compte par le SAMR. ✅\n\n"
                            . "Celui ci se déroule en deux parties, un test écrit et un entretient individuel\n\n"
                            . "Cependant, la procédure ne pourra se poursuivre qu'une fois que vous serez venu·e à l'hôpital vous acquitter d'une facture de **10 000 \$**.\n\n"
                            . "📎 Merci de déposer la **preuve de paiement en pièce jointe** de votre demande afin que nous puissions valider votre dossier.\n\n"
                            . "*L'équipe SAMR reste disponible pour toute question.*",
                            $firstName, $lastName
                        ),
                        'chasse' => sprintf(
                            "<@&1433218049086984323>\n\n"
                            . "Bonjour **%s %s**,\n\n"
                            . "Votre demande de **Permis de Chasse** a bien été prise en compte. ✅\n\n"
                            . "Celui ci se déroule en deux parties, un test écrit et un entretient individuel\n\n"
                            . "Afin de poursuivre la procédure, merci de vous rendre à l'hôpital pour vous acquitter d'une facture de **3 000 \$**.\n\n"
                            . "📎 Merci de déposer la **preuve de paiement en pièce jointe** de votre demande afin que nous puissions valider votre dossier.\n\n"
                            . "*L'équipe SAMR reste disponible pour toute question.*",
                            $firstName, $lastName
                        ),
                    ];

                    if (isset($paymentMessages[$ppaType])) {

                        $allowedMentions = $ppaType === 'chasse'
                            ? ['roles' => ['CHANGE_ME_DISCORD_ROLE_ID_PPA']]
                            : [];
                        $paySend = ppa_send_discord_msg($channelId, $paymentMessages[$ppaType], $botToken, $allowedMentions);
                        if (!$paySend['ok']) {
                            $flash = ['type'=>'warn','msg'=>'Fiche enregistrée, mais le message de paiement Discord n\'a pas pu être envoyé : '.ppa_discord_err($paySend).'.'];
                        }
                    }

                    if ($ppaType === 'chasse') {
                        $roleId = 'CHANGE_ME_DISCORD_ROLE_ID_PPA';

                        $allow  = 1024 | 65536 | 2048;
                        $rch = curl_init("https://discord.com/api/v10/channels/{$channelId}/permissions/{$roleId}");
                        curl_setopt_array($rch, [
                            CURLOPT_CUSTOMREQUEST  => 'PUT',
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT        => 10,
                            CURLOPT_HTTPHEADER     => [
                                'Content-Type: application/json',
                                'Authorization: Bot ' . $botToken,
                            ],
                            CURLOPT_POSTFIELDS => json_encode(['allow' => $allow, 'deny' => 0, 'type' => 0], JSON_UNESCAPED_UNICODE),
                        ]);
                        curl_exec($rch);
                        curl_close($rch);
                    }
                }
            }

            if ($channelId !== '') {
                $newName = ppa_channel_name($ppaType, $firstName, $lastName, 'att-pay');
                ppa_rename_channel($channelId, $newName);
            }
        }
    }

    if ($action === 'send_link') {
        $postId = (int)($_POST['id'] ?? 0);
        $stmt   = $pdo->prepare("SELECT * FROM `{$table}` WHERE id=? LIMIT 1");
        $stmt->execute([$postId]);
        $cand   = $stmt->fetch(PDO::FETCH_ASSOC);
        $editId = $postId;

        if (!$cand) {
            $flash = ['type'=>'err','msg'=>'Fiche introuvable.'];
        } elseif (!(int)$cand['paid']) {
            $flash = ['type'=>'err','msg'=>'Marquer le PPA comme payé avant envoi.'];
        } else {

            $token   = bin2hex(random_bytes(24));
            $expires = date('Y-m-d H:i:s', time() + 72 * 3600);

            $pdo->prepare("UPDATE `{$table}` SET activation_token=NULL WHERE id=?")->execute([$postId]);
            $pdo->prepare("UPDATE `{$table}` SET activation_token=?,activation_expires_at=?,activation_sent_at=NOW(),status='sent',updated_at=NOW() WHERE id=?")
                ->execute([$token,$expires,$postId]);

            $check = $pdo->prepare("SELECT activation_token FROM `{$table}` WHERE id=? LIMIT 1");
            $check->execute([$postId]);
            $saved = (string)($check->fetchColumn() ?: '');

            if ($saved !== $token) {
                $flash = ['type'=>'err','msg'=>'Erreur : impossible de sauvegarder le token. Vérifie la structure de la table ppa_candidates.'];
            } else {
                $link      = $baseUrl . '/activate.php?token=' . urlencode($token);
                $typeLabel = strtoupper((string)$cand['ppa_type']);
                $content   = "🔐 **Accès Test PPA**\n"
                           . "👤 Candidat : {$cand['first_name']} {$cand['last_name']}\n"
                           . "🧾 Type : {$typeLabel}\n"
                           . "💳 Payé : Oui\n"
                           . "⏳ Lien valable 72h\n"
                           . "🔗 {$link}";

                $channelId = (string)($cand['ticket_channel_id'] ?? '');
                $send = ppa_send_discord_msg($channelId, $content, $botToken);

                if ($channelId !== '') {
                    $newName = ppa_channel_name(
                        (string)$cand['ppa_type'],
                        (string)$cand['first_name'],
                        (string)$cand['last_name'],
                        'att-formulaire'
                    );
                    ppa_rename_channel($channelId, $newName);
                }

                if ($send['ok']) {
                    $flash = ['type'=>'ok','msg'=>'✅ Lien envoyé sur Discord — canal renommé.'];
                } else {
                    $flash = ['type'=>'warn','msg'=>'⚠️ Lien généré mais l\'envoi Discord a échoué ('.ppa_discord_err($send).'). '
                        .'Copie ce lien et envoie-le manuellement au candidat : '.$link];
                }
            }
        }
    }

    if ($action === 'resend_payment') {
        $postId = (int)($_POST['id'] ?? 0);
        $stmt   = $pdo->prepare("SELECT * FROM `{$table}` WHERE id=? LIMIT 1");
        $stmt->execute([$postId]);
        $cand   = $stmt->fetch(PDO::FETCH_ASSOC);
        $editId = $postId;

        if (!$cand) {
            $flash = ['type'=>'err','msg'=>'Fiche introuvable.'];
        } else {
            $rChannelId = (string)($cand['ticket_channel_id'] ?? '');
            $rType      = (string)($cand['ppa_type'] ?? '');
            $rFirst     = (string)($cand['first_name'] ?? '');
            $rLast      = (string)($cand['last_name'] ?? '');

            if ($rChannelId === '') {
                $flash = ['type'=>'err','msg'=>'Aucun ID de canal Discord sur cette fiche.'];
            } elseif (!in_array($rType, ['civil','chasse'])) {
                $flash = ['type'=>'err','msg'=>"Renvoi non applicable pour ce type de PPA."];
            } else {
                $payMsgs = [
                    'civil'  => "Bonjour **{$rFirst} {$rLast}**,\n\nVotre demande de **Permis de Port d'Arme Civil** a bien ete prise en compte par le SAMR.\n\nCependant, la procedure ne pourra se poursuivre qu'une fois que vous serez venu.e a l'hopital vous acquitter d'une facture de **10 000 $**.\n\nMerci de deposer la **preuve de paiement en piece jointe** de votre demande.\n\n*L'equipe SAMR reste disponible pour toute question.*",
                    'chasse' => "Bonjour **{$rFirst} {$rLast}**,\n\nVotre demande de **Permis de Chasse** a bien ete prise en compte.\n\nAfin de poursuivre la procedure, merci de vous rendre a l'hopital pour vous acquitter d'une facture de **3 000 $**.\n\nMerci de deposer la **preuve de paiement en piece jointe** de votre demande.\n\n*L'equipe SAMR reste disponible pour toute question.*",
                ];
                $send = ppa_send_discord_msg($rChannelId, $payMsgs[$rType], $botToken);
                if ($send['ok']) {
                    $flash = ['type'=>'ok','msg'=>'Message de paiement renvoye sur Discord.'];
                } else {
                    $flash = ['type'=>'err','msg'=>'Échec de l\'envoi Discord : '.ppa_discord_err($send)];
                }
            }
        }
    }

    if ($action === 'delete') {
        $postId = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM `{$table}` WHERE id=?")->execute([$postId]);
        $flash  = ['type'=>'ok','msg'=>'Fiche supprimée.'];
        $editId = 0;
    }
}

$editCand = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id=? LIMIT 1");
    $stmt->execute([$editId]);
    $editCand = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$q    = trim((string)($_GET['q']    ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'recent'));

$where  = [];
$params = [];
if (!$isAdmin) {
    $where[]  = "ppa_type IN (" . implode(',', array_fill(0, count($allowedTypes), '?')) . ")";
    $params   = array_merge($params, $allowedTypes);
}
if ($q !== '') {
    $where[]  = "(last_name LIKE ? OR first_name LIKE ?)";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
}

$sql = "SELECT c.*, u.username AS created_by_name FROM `{$table}` c LEFT JOIN users u ON u.id=c.created_by_user_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= match($sort) {
    'name'   => ' ORDER BY c.last_name ASC, c.first_name ASC',
    'paid'   => ' ORDER BY c.paid DESC, c.created_at DESC',
    'status' => ' ORDER BY c.status ASC, c.created_at DESC',
    default  => ' ORDER BY c.created_at DESC',
};

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

samr_layout_start('SAMR — PPA');
?>

<style>
.ppa-grid{display:grid;grid-template-columns:360px 1fr;gap:16px;align-items:start}
@media(max-width:900px){.ppa-grid{grid-template-columns:1fr}}
.ppa-form-card{background:linear-gradient(160deg,rgba(13,18,25,.98),rgba(8,12,16,.98));border:1px solid var(--line2);border-radius:16px;padding:20px;position:sticky;top:16px}
.ppa-form-title{font-family:var(--font-head);font-size:15px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.ppa-field{margin-bottom:11px}
.ppa-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:5px}
.ppa-type-btns{display:flex;gap:6px}
.ppa-type-btn{flex:1;padding:8px 6px;text-align:center;border-radius:9px;border:1px solid var(--line2);background:transparent;color:var(--muted);font-size:12px;font-weight:700;cursor:pointer;font-family:var(--font-body);transition:all .12s}
.ppa-type-btn.active-civil{border-color:rgba(0,212,170,.5);color:var(--accent2);background:rgba(0,212,170,.08)}
.ppa-type-btn.active-sasp{border-color:rgba(59,158,255,.5);color:#3b9eff;background:rgba(59,158,255,.08)}
.ppa-type-btn.active-chasse{border-color:rgba(255,180,50,.5);color:#ffb060;background:rgba(255,180,50,.08)}
.ppa-type-btn.active-ammunation{border-color:rgba(255,79,79,.5);color:#ff7a7a;background:rgba(255,79,79,.08)}
.paid-toggle{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;border:1px solid var(--line2);background:rgba(255,255,255,.02);cursor:pointer;transition:all .15s;user-select:none}
.paid-toggle.active{border-color:rgba(0,212,170,.4);background:rgba(0,212,170,.06)}
.toggle-dot{width:18px;height:18px;border-radius:50%;border:2px solid var(--line2);flex-shrink:0;transition:all .15s;display:flex;align-items:center;justify-content:center;font-size:0}
.paid-toggle.active .toggle-dot{border-color:var(--accent2);background:var(--accent2);font-size:10px;color:#000;font-weight:900}
.btn-discord{width:100%;padding:12px;border-radius:11px;background:linear-gradient(135deg,rgba(59,158,255,.2),rgba(0,212,170,.12));border:1px solid rgba(59,158,255,.4);color:var(--text);font-family:var(--font-head);font-size:13px;font-weight:700;cursor:pointer;transition:all .15s;margin-top:8px}
.btn-discord:hover{background:linear-gradient(135deg,rgba(59,158,255,.3),rgba(0,212,170,.2))}
.btn-discord:disabled{opacity:.4;cursor:not-allowed}
.activation-box{margin-top:10px;padding:11px 13px;border-radius:10px;background:rgba(0,212,170,.06);border:1px solid rgba(0,212,170,.25);font-size:12px}
.activation-link{font-family:monospace;font-size:11px;color:var(--accent);word-break:break-all;margin-top:5px}
.flash-box{padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:14px;word-break:break-all}
.flash-ok{background:rgba(0,212,170,.08);border:1px solid rgba(0,212,170,.3);color:var(--accent2)}
.flash-warn{background:rgba(255,180,50,.08);border:1px solid rgba(255,180,50,.3);color:#ffb060}
.flash-err{background:rgba(255,79,79,.08);border:1px solid rgba(255,79,79,.3);color:#ff7a7a}
.ppa-row{display:grid;grid-template-columns:1fr auto auto auto auto;gap:10px;align-items:center;padding:10px 14px;border-radius:10px;border:1px solid var(--line);background:rgba(13,18,25,.7);margin-bottom:7px;transition:border-color .12s;cursor:pointer}
.ppa-row:hover{border-color:rgba(59,158,255,.3)}
.ppa-row.editing{border-color:rgba(59,158,255,.5);background:rgba(59,158,255,.04)}
.type-badge{font-size:10px;font-weight:700;padding:2px 9px;border-radius:999px;white-space:nowrap}
.type-civil{background:rgba(0,212,170,.1);color:var(--accent2);border:1px solid rgba(0,212,170,.3)}
.type-sasp{background:rgba(59,158,255,.1);color:#3b9eff;border:1px solid rgba(59,158,255,.3)}
.type-chasse{background:rgba(255,180,50,.1);color:#ffb060;border:1px solid rgba(255,180,50,.3)}
.type-ammunation{background:rgba(255,79,79,.1);color:#ff7a7a;border:1px solid rgba(255,79,79,.3)}
.status-dot{width:8px;height:8px;border-radius:50%}
</style>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
  <div>
    <h1>🔫 Permis de Port d'Arme</h1>
    <div class="sub">Gestion des accès aux tests PPA</div>
  </div>
  <div style="display:flex;gap:6px;flex-wrap:wrap;">
    <?php if($hasSasp):   ?><span class="pill pill-blue">PPA SASP</span><?php endif; ?>
    <?php if($hasChasse): ?><span class="pill" style="border-color:rgba(255,180,50,.3);color:#ffb060;background:rgba(255,180,50,.06)">PPA Chasse</span><?php endif; ?>
    <?php if($isAdmin):   ?><span class="pill">Admin</span><?php endif; ?>
  </div>
</div>

<?php if ($flash): ?>
<div class="flash-box flash-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
<?php endif; ?>

<div class="ppa-grid">

  <!-- ── Formulaire ── -->
  <div class="ppa-form-card">
    <div class="ppa-form-title">
      <?= $editCand ? '✏️ Modifier' : '➕ Nouvelle fiche' ?>
      <?php if($editCand): ?><a href="/ppa.php" style="font-size:11px;color:var(--muted);font-weight:400;margin-left:auto;">+ Nouvelle</a><?php endif; ?>
    </div>

    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id"     value="<?= $editCand ? (int)$editCand['id'] : 0 ?>">
      <input type="hidden" name="ppa_type" id="ppaTypeInput" value="<?= h($editCand['ppa_type'] ?? 'civil') ?>">

      <div class="ppa-field">
        <label>Nom</label>
        <input class="inp" name="last_name" required placeholder="Nom de famille" value="<?= h($editCand['last_name'] ?? '') ?>">
      </div>
      <div class="ppa-field">
        <label>Prénom</label>
        <input class="inp" name="first_name" required placeholder="Prénom" value="<?= h($editCand['first_name'] ?? '') ?>">
      </div>
      <div class="ppa-field">
        <label>Numéro de téléphone <span style="font-weight:400;color:var(--muted)">(facultatif)</span></label>
        <input class="inp" type="tel" name="phone" placeholder="Ex: 555-1234" value="<?= h($editCand['phone'] ?? '') ?>">
      </div>
      <div class="ppa-field">
        <label>Type de PPA</label>
        <div class="ppa-type-btns">
          <?php foreach($allowedTypes as $t): ?>
          <button type="button" class="ppa-type-btn <?= ($editCand['ppa_type'] ?? 'civil')===$t?"active-{$t}":'' ?>" data-type="<?= h($t) ?>">
            <?= h($typeLabels[$t] ?? ucfirst($t)) ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="ppa-field">
        <label>ID du canal Discord (ticket)</label>
        <input class="inp" name="ticket_channel_id" placeholder="Ex: 1234567890123456789" value="<?= h($editCand['ticket_channel_id'] ?? '') ?>">
        <div style="font-size:11px;color:var(--muted);margin-top:4px;">Clic droit sur le salon → Copier l'identifiant (mode développeur activé)</div>
      </div>
      <div class="ppa-field">
        <label>Paiement</label>
        <div class="paid-toggle <?= !empty($editCand['paid'])?'active':'' ?>" id="paidToggle">
          <div class="toggle-dot"><?= !empty($editCand['paid'])?'✓':'' ?></div>
          <div style="flex:1;font-size:13px;font-weight:600;">PPA payé</div>
          <div style="font-size:11px;color:var(--muted);">Requis avant envoi</div>
        </div>
        <input type="hidden" name="paid" id="paidInput" value="<?= !empty($editCand['paid'])?'1':'0' ?>">
      </div>

      <button type="submit" class="btn success" style="width:100%;">
        <?= $editCand ? '💾 Mettre à jour' : '✅ Créer la fiche' ?>
      </button>
    </form>

    <?php if($editCand): ?>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--line);">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:10px;">Lien d'activation Discord</div>

      <?php if(!empty($editCand['activation_token'])): ?>
      <div class="activation-box">
        <div style="font-size:11px;font-weight:700;color:var(--accent2);">🔗 Lien actif</div>
        <div class="activation-link"><?= h($baseUrl . '/activate.php?token=' . urlencode($editCand['activation_token'])) ?></div>
        <div style="font-size:10px;color:var(--muted);margin-top:5px;">Expire : <?= h($editCand['activation_expires_at'] ?? '') ?></div>
      </div>
      <?php endif; ?>

      <form method="post" style="margin-top:8px;">
        <input type="hidden" name="action" value="send_link">
        <input type="hidden" name="id"     value="<?= (int)$editCand['id'] ?>">
        <button type="submit" class="btn-discord" <?= empty($editCand['paid'])?'disabled':'' ?>>
          📨 Envoyer le lien sur Discord
        </button>
        <?php if(empty($editCand['paid'])): ?>
        <div style="font-size:11px;color:#ff7a7a;margin-top:6px;text-align:center;">⚠ Marquer comme payé d'abord</div>
        <?php endif; ?>
      </form>

      <?php if($editCand['test_result']): ?>
      <div style="margin-top:12px;padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:rgba(255,255,255,.02);">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:6px;">Résultat</div>
        <div style="font-family:var(--font-head);font-size:18px;font-weight:800;color:<?= $editCand['test_result']==='REUSSITE'?'var(--accent2)':'#ff7a7a' ?>;">
          <?= $editCand['test_result']==='REUSSITE'?'✅ RÉUSSITE':'❌ ÉCHEC' ?>
        </div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px;">Score : <?= (int)$editCand['test_score'] ?> / <?= (int)$editCand['test_max'] ?></div>
      </div>
      <?php endif; ?>

      <?php if(in_array($editCand['ppa_type'], ['civil','chasse']) && !empty($editCand['ticket_channel_id'])): ?>
      <form method="post" style="margin-top:8px;">
        <input type="hidden" name="action" value="resend_payment">
        <input type="hidden" name="id"     value="<?= (int)$editCand['id'] ?>">
        <button type="submit" class="btn" style="width:100%;font-size:12px;border-color:rgba(255,180,50,.3);background:rgba(255,180,50,.08);color:#ffb060;"
                onclick="return confirm('Renvoyer le message de demande de paiement sur Discord ?')">
          📨 Renvoyer le message de paiement
        </button>
      </form>
      <?php endif; ?>

            <form method="post" style="margin-top:10px;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id"     value="<?= (int)$editCand['id'] ?>">
        <button type="submit" class="btn danger" style="width:100%;font-size:12px;" onclick="return confirm('Supprimer cette fiche ?')">
          🗑 Supprimer
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Liste ── -->
  <div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
      <div style="font-family:var(--font-head);font-size:14px;font-weight:700;">
        Fiches <span style="font-weight:400;color:var(--muted);">(<?= count($list) ?>)</span>
      </div>
    </div>
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
      <?php if($editId): ?><input type="hidden" name="edit" value="<?= $editId ?>"><?php endif; ?>
      <input class="inp" name="q" value="<?= h($q) ?>" placeholder="🔍 Rechercher…" style="flex:1;min-width:140px;">
      <select class="inp" name="sort" style="min-width:140px;">
        <option value="recent" <?= $sort==='recent'?'selected':'' ?>>Plus récents</option>
        <option value="name"   <?= $sort==='name'  ?'selected':'' ?>>Nom</option>
        <option value="paid"   <?= $sort==='paid'  ?'selected':'' ?>>Payé d'abord</option>
        <option value="status" <?= $sort==='status'?'selected':'' ?>>Statut</option>
      </select>
      <button type="submit" class="btn secondary" style="font-size:12px;">Filtrer</button>
    </form>

    <?php if(!$list): ?>
      <div class="muted" style="text-align:center;padding:32px;">Aucune fiche.</div>
    <?php else: foreach($list as $row):
      $si = $statusLabels[$row['status']] ?? ['label'=>$row['status'],'color'=>'var(--muted)'];
      $isEditing = ($editId===(int)$row['id']);
    ?>
    <div class="ppa-row <?= $isEditing?'editing':'' ?>"
         onclick="window.location='/ppa.php?edit=<?= (int)$row['id'] ?><?= $q?'&q='.urlencode($q):'' ?><?= $sort!=='recent'?'&sort='.urlencode($sort):'' ?>'">
      <div>
        <div style="font-weight:700;font-size:13px;"><?= h($row['last_name'].' '.$row['first_name']) ?></div>
        <div style="font-size:11px;color:var(--muted);">
          <?= $row['phone'] ? h($row['phone']) : '<span style="opacity:.4">—</span>' ?>
          <?php if($row['created_by_name']): ?> · <?= h($row['created_by_name']) ?><?php endif; ?>
        </div>
      </div>
      <span class="type-badge type-<?= h($row['ppa_type']) ?>"><?= h($typeLabels[$row['ppa_type']]??strtoupper($row['ppa_type'])) ?></span>
      <span style="font-size:11px;font-weight:700;color:<?= $row['paid']?'var(--accent2)':'var(--muted)' ?>"><?= $row['paid']?'💳 Payé':'⏳' ?></span>
      <div style="display:flex;align-items:center;gap:5px;">
        <div class="status-dot" style="background:<?= h($si['color']) ?>"></div>
        <span style="font-size:11px;color:<?= h($si['color']) ?>;font-weight:600;"><?= h($si['label']) ?></span>
      </div>
      <?php if($row['test_result']): ?>
      <span style="font-size:12px;font-weight:700;color:<?= $row['test_result']==='REUSSITE'?'var(--accent2)':'#ff7a7a' ?>">
        <?= $row['test_result']==='REUSSITE'?'✅':'❌' ?>
      </span>
      <?php else: ?><span></span><?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
  </div>

</div>

<script>
document.querySelectorAll('[data-type]').forEach(btn => {
  btn.addEventListener('click', () => {
    const t = btn.dataset.type;
    document.getElementById('ppaTypeInput').value = t;
    document.querySelectorAll('[data-type]').forEach(b => {
      b.className = `ppa-type-btn${b.dataset.type===t?' active-'+t:''}`;
    });
  });
});
const paidToggle = document.getElementById('paidToggle');
const paidInput  = document.getElementById('paidInput');
paidToggle?.addEventListener('click', () => {
  const active = paidToggle.classList.toggle('active');
  paidInput.value = active ? '1' : '0';
  paidToggle.querySelector('.toggle-dot').textContent = active ? '✓' : '';
});
</script>

<?php samr_layout_end();
