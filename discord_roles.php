<?php

declare(strict_types=1);

const DISCORD_ROLES = [
    'chirurgien'               => '1433215276350050314',
    'kinesitherapeute'         => '1433215388765917184',
    'osteopathe'               => '1433215731754991686',
    'psychologue'              => '1433215537391079545',
    'psychiatre'               => '1433215581498245140',
    'veterinaire'              => '1433215703800086688',
    'gynecologue'              => '1433215996004405308',
    'gynecologue_obstetricien' => '1433216027973652632',
    'legiste'                  => '1433219247211020512',
    'laborantin'               => '1433214830747324498',
    'chercheur'                => '1433366931645403177',
    'oncologue'                => '1433950438062817300',
    'grands_brules'            => '1433957743047217203',
    'neurologue'               => '1433952300925456434',
    'allergologue'             => '1433952465807605770',
    'ophtalmo'                 => '1433952566311653417',
    'cardiologue'              => '1433952735056756817',
    'dentiste'                 => '1433952835837362277',
    'esthetique'               => '1433952662029729884',
    'addictologue'             => '1434209758801297459',
    'diabetologue'             => '1455339326450696306',
];

const DISCORD_ROLE_LABELS = [
    'chirurgien'               => 'Chirurgien',
    'kinesitherapeute'         => 'Kinésithérapeute',
    'osteopathe'               => 'Ostéopathe',
    'psychologue'              => 'Psychologue',
    'psychiatre'               => 'Psychiatre',
    'veterinaire'              => 'Vétérinaire',
    'gynecologue'              => 'Gynécologue',
    'gynecologue_obstetricien' => 'Gynécologue-obstétricien',
    'legiste'                  => 'Légiste',
    'laborantin'               => 'Laborantin',
    'chercheur'                => 'Chercheur',
    'oncologue'                => 'Oncologue',
    'grands_brules'            => 'Grands brûlés',
    'neurologue'               => 'Neurologue',
    'allergologue'             => 'Allergologue',
    'ophtalmo'                 => 'Ophtalmologue',
    'cardiologue'              => 'Cardiologue',
    'dentiste'                 => 'Dentiste',
    'esthetique'               => 'Esthétique',
    'addictologue'             => 'Addictologue',
    'diabetologue'             => 'Diabétologue',
];

const DISCORD_ROLE_ICONS = [
    'chirurgien'               => '🔪',
    'kinesitherapeute'         => '🦴',
    'osteopathe'               => '🖐️',
    'psychologue'              => '🧠',
    'psychiatre'               => '💊',
    'veterinaire'              => '🐾',
    'gynecologue'              => '♀️',
    'gynecologue_obstetricien' => '🤱',
    'legiste'                  => '🔬',
    'laborantin'               => '🧪',
    'chercheur'                => '📡',
    'oncologue'                => '🎗️',
    'grands_brules'            => '🔥',
    'neurologue'               => '⚡',
    'allergologue'             => '🌿',
    'ophtalmo'                 => '👁️',
    'cardiologue'              => '❤️',
    'dentiste'                 => '🦷',
    'esthetique'               => '✨',
    'addictologue'             => '🔄',
    'diabetologue'             => '💉',
];

const DISCORD_ROLES_CACHE_TTL = 300;

function get_discord_role_ids(string $discordId): array {
    if ($discordId === '') return [];
    require_once __DIR__ . '/db.php';
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS `discord_roles_cache` (
        `discord_id` VARCHAR(30) NOT NULL,
        `role_ids`   TEXT NOT NULL,
        `fetched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`discord_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("SELECT role_ids, fetched_at FROM discord_roles_cache WHERE discord_id=? LIMIT 1");
    $stmt->execute([$discordId]);
    $cached = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cached && (time() - strtotime((string)$cached['fetched_at'])) < DISCORD_ROLES_CACHE_TTL) {
        return json_decode((string)$cached['role_ids'], true) ?: [];
    }

    $roleIds = fetch_discord_member_roles($discordId);
    $pdo->prepare("REPLACE INTO discord_roles_cache (discord_id, role_ids, fetched_at) VALUES (?,?,NOW())")
        ->execute([$discordId, json_encode($roleIds)]);
    return $roleIds;
}

function fetch_discord_member_roles(string $discordId): array {
    $guildId  = defined('DISCORD_GUILD_ID')  ? DISCORD_GUILD_ID  : '';
    $botToken = defined('DISCORD_BOT_TOKEN') ? DISCORD_BOT_TOKEN : '';
    if ($guildId === '' || $botToken === '') return [];
    $url = "https://discord.com/api/v10/guilds/{$guildId}/members/{$discordId}";
    $ctx = stream_context_create(['http'=>[
        'method'  => 'GET',
        'header'  => "Authorization: Bot {$botToken}\r\nUser-Agent: SAMR-Bot/1.0\r\n",
        'timeout' => 5,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return [];
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['roles'])) return [];
    return array_values(array_filter($data['roles'], fn($r) => is_string($r)));
}

function user_has_specialisation(string $roleKey): bool {
    $u = current_user();
    if (!$u) return false;
    $discordId = (string)($u['discord_id'] ?? '');
    if ($discordId === '') return false;
    $roleId = DISCORD_ROLES[$roleKey] ?? '';
    if ($roleId === '') return false;
    return in_array($roleId, get_discord_role_ids($discordId), true);
}

function get_user_specialisations(): array {
    $u = current_user();
    if (!$u) return [];
    $discordId = (string)($u['discord_id'] ?? '');
    if ($discordId === '') return [];
    $userRoles = get_discord_role_ids($discordId);
    return array_values(array_filter(array_keys(DISCORD_ROLES), fn($k) => in_array(DISCORD_ROLES[$k], $userRoles, true)));
}

function require_specialisation(string $roleKey): void {
    require_login();
    if (!user_has_specialisation($roleKey)) {
        http_response_code(403);
        require_once __DIR__ . '/layout.php';
        samr_layout_start('Accès refusé');
        $label = DISCORD_ROLE_LABELS[$roleKey] ?? $roleKey;
        echo '<div style="text-align:center;padding:80px 20px;">';
        echo '<div style="font-size:52px;margin-bottom:16px;">🔒</div>';
        echo '<div style="font-family:var(--font-head);font-size:20px;font-weight:800;margin-bottom:10px;">Accès restreint</div>';
        echo '<div style="font-size:13px;color:var(--muted);max-width:400px;margin:0 auto;line-height:1.75;">Cette page est réservée aux membres avec le rôle <strong style="color:var(--accent);">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong> sur le serveur Discord SAMR.</div>';
        echo '<div style="margin-top:24px;"><a href="/specialisations.php" class="btn secondary">← Retour aux spécialisations</a></div>';
        echo '</div>';
        samr_layout_end();
        exit;
    }
}
