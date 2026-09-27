<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

start_session();

function http_post_form(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_TIMEOUT        => 15,
    ]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) throw new RuntimeException('cURL error: ' . curl_error($ch));
    curl_close($ch);
    $json = json_decode($raw, true);
    if (!is_array($json)) throw new RuntimeException("Réponse Discord non-JSON (HTTP $code).");
    $json['_http'] = $code;
    return $json;
}

function http_get_json(string $url, array $headers): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) throw new RuntimeException('cURL error: ' . curl_error($ch));
    curl_close($ch);
    $json = json_decode($raw, true);
    if (!is_array($json)) throw new RuntimeException("Réponse Discord non-JSON (HTTP $code).");
    $json['_http'] = $code;
    return $json;
}

try {
    foreach (['DISCORD_CLIENT_ID','DISCORD_CLIENT_SECRET','DISCORD_BOT_TOKEN','DISCORD_REDIRECT_URI','DISCORD_GUILD_ID','DISCORD_ROLE_SAMR_ID'] as $k) {
        if (!defined($k) || (string)constant($k) === '' || str_starts_with((string)constant($k), 'CHANGE_ME')) {
            http_response_code(500);
            exit('Configuration invalide : ' . $k . ' manquant.');
        }
    }

    if (!isset($_GET['code'], $_GET['state'])) {
        http_response_code(400);
        exit('Paramètres manquants.');
    }
    if (!hash_equals($_SESSION['discord_oauth_state'] ?? '', (string)$_GET['state'])) {
        http_response_code(400);
        exit('State invalide.');
    }
    unset($_SESSION['discord_oauth_state']);

    $token = http_post_form('https://discord.com/api/oauth2/token', [
        'client_id'     => DISCORD_CLIENT_ID,
        'client_secret' => DISCORD_CLIENT_SECRET,
        'grant_type'    => 'authorization_code',
        'code'          => (string)$_GET['code'],
        'redirect_uri'  => DISCORD_REDIRECT_URI,
    ]);

    $accessToken = (string)($token['access_token'] ?? '');
    if ($accessToken === '') {
        http_response_code(400);
        $desc = (string)($token['error_description'] ?? '');
        exit($desc !== '' ? $desc : 'Token Discord manquant (échange OAuth).');
    }

    $me        = http_get_json('https://discord.com/api/v10/users/@me', ['Authorization: Bearer ' . $accessToken]);
    $discordId = (string)($me['id'] ?? '');
    if ($discordId === '') { http_response_code(400); exit('ID Discord introuvable.'); }

    $member = http_get_json(
        'https://discord.com/api/v10/guilds/' . rawurlencode(DISCORD_GUILD_ID) . '/members/' . rawurlencode($discordId),
        ['Authorization: Bot ' . DISCORD_BOT_TOKEN]
    );

    $http = (int)($member['_http'] ?? 0);
    if ($http === 404) { http_response_code(403); exit('Accès refusé : vous devez être membre du serveur Discord.'); }
    if ($http < 200 || $http >= 300) { http_response_code(502); exit('Erreur Discord (vérification serveur) : HTTP ' . $http); }

    $roles = $member['roles'] ?? [];
    if (!is_array($roles)) { $roles = []; }
    $hasSamr  = in_array(DISCORD_ROLE_SAMR_ID, $roles, true);
    $hasStaff = defined('DISCORD_ROLE_STAFF_ID') && (string)DISCORD_ROLE_STAFF_ID !== '' && in_array(DISCORD_ROLE_STAFF_ID, $roles, true);
    if (!$hasSamr && !$hasStaff) {
        http_response_code(403);
        exit('Accès refusé : rôle SAMR ou Staff requis.');
    }

    $pdo = db();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE discord_id=? LIMIT 1');
    $stmt->execute([$discordId]);
    $user = $stmt->fetch();

    if (!$user) {

        $nickname = trim((string)($member['nick'] ?? ''));
        $username = $nickname !== '' ? $nickname : 'discord_' . $discordId;

        $ins = $pdo->prepare('INSERT INTO users (username, password_hash, role, can_supervise, discord_id, dispatch_list_id, created_at) VALUES (?,?,?,?,?,?,NOW())');
        $ins->execute([$username, '', $hasStaff ? 'admin' : 'member', $hasStaff ? 1 : 0, $discordId, 'off']);
        $uid = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
    } elseif ($hasStaff && (($user['role'] ?? '') !== 'admin' || (int)($user['can_supervise'] ?? 0) !== 1)) {
        $upd = $pdo->prepare('UPDATE users SET role=?, can_supervise=1 WHERE id=?');
        $upd->execute(['admin', (int)$user['id']]);
        $user['role'] = 'admin';
        $user['can_supervise'] = 1;
    }

    if (!$user) throw new RuntimeException('Utilisateur local introuvable après création/chargement.');

    login_user($user);

    header('Location: /');
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Erreur serveur lors du callback Discord.\n";
    echo "Détail: " . $e->getMessage();
    exit;
}
