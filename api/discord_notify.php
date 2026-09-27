<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !csrf_verify($body['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF invalide']);
    exit;
}

$channelId = $body['channel'] ?? '';
$message   = $body['message'] ?? '';

if (!$channelId || !$message) {
    echo json_encode(['ok' => false, 'error' => 'Paramètres manquants']);
    exit;
}

$discordToken = defined('DISCORD_BOT_TOKEN') ? DISCORD_BOT_TOKEN : (getenv('DISCORD_BOT_TOKEN') ?: '');

if (!$discordToken) {

    error_log('[discord_notify] DISCORD_BOT_TOKEN non configuré');
    echo json_encode(['ok' => true, 'warn' => 'Token non configuré']);
    exit;
}

$url     = "https://discord.com/api/v10/channels/{$channelId}/messages";
$payload = json_encode(['content' => $message]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bot ' . $discordToken,
    ],
    CURLOPT_TIMEOUT        => 5,
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code >= 200 && $code < 300) {
    echo json_encode(['ok' => true]);
} else {
    error_log("[discord_notify] Erreur Discord HTTP {$code}: {$res}");
    echo json_encode(['ok' => false, 'error' => "Discord HTTP {$code}"]);
}
