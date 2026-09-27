<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
start_session();

if (!defined('DISCORD_CLIENT_ID') || (string)DISCORD_CLIENT_ID === '' || (string)DISCORD_CLIENT_ID === 'CHANGE_ME_DISCORD_CLIENT_ID') {
  http_response_code(500);
  exit('Configuration invalide : DISCORD_CLIENT_ID manquant.');
}
if (!defined('DISCORD_REDIRECT_URI') || (string)DISCORD_REDIRECT_URI === '') {
  http_response_code(500);
  exit('Configuration invalide : DISCORD_REDIRECT_URI manquant.');
}

$state = bin2hex(random_bytes(16));
$_SESSION['discord_oauth_state'] = $state;

$params = [
  'client_id' => DISCORD_CLIENT_ID,
  'redirect_uri' => DISCORD_REDIRECT_URI,
  'response_type' => 'code',
  'scope' => 'identify',
  'state' => $state,
  'prompt' => 'consent',
];

header('Location: https://discord.com/api/oauth2/authorize?' . http_build_query($params));
exit;
