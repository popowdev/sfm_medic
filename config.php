<?php

declare(strict_types=1);
date_default_timezone_set('Europe/Paris');

const DB_HOST = '127.0.0.1';
const DB_NAME = 'samr';
const DB_USER = 'samr';
const DB_PASS = 'REMPLACER_MOT_DE_PASSE';

const APP_SECRET = 'e8851e21f61621f20dcc4a1c5fda479c7c26913a116845940177c50e1763207a';

const SESSION_NAME = 'samr_session';

const REQUIRE_HTTPS = true;

if (!function_exists('envv')) {
  function envv(string $key, string $default = ''): string {
    $v = getenv($key);
    return ($v !== false && $v !== '') ? (string)$v : $default;
  }

(function () {
  $f = __DIR__ . '/.env';
  if (!is_readable($f)) return;
  foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    $l = trim($l);
    if ($l === '' || $l[0] === '#' || strpos($l, '=') === false) continue;
    [$k, $v] = explode('=', $l, 2);
    $k = trim($k); $v = trim($v);
    if ($k !== '' && getenv($k) === false) { putenv("$k=$v"); }
  }
})();

}

if (!defined('DISCORD_CLIENT_ID')) {
  define('DISCORD_CLIENT_ID', envv('DISCORD_CLIENT_ID', 'REMPLACER_CLIENT_ID'));
}
if (!defined('DISCORD_CLIENT_SECRET')) {
  define('DISCORD_CLIENT_SECRET', envv('DISCORD_CLIENT_SECRET', ''));
}
if (!defined('DISCORD_BOT_TOKEN')) {
  define('DISCORD_BOT_TOKEN', envv('DISCORD_BOT_TOKEN', ''));
}
if (!defined('DISCORD_REDIRECT_URI')) {
  define('DISCORD_REDIRECT_URI', envv('DISCORD_REDIRECT_URI', 'https://exemple.tld/discord_callback.php'));
}
if (!defined('DISCORD_GUILD_ID')) {
  define('DISCORD_GUILD_ID', envv('DISCORD_GUILD_ID', 'REMPLACER_GUILD_ID'));
}
if (!defined('DISCORD_ROLE_SAMR_ID')) {
  define('DISCORD_ROLE_SAMR_ID', envv('DISCORD_ROLE_SAMR_ID', 'REMPLACER_ROLE_SAMR_ID'));
}
if (!defined('DISCORD_ROLE_STAFF_ID')) {
  define('DISCORD_ROLE_STAFF_ID', envv('DISCORD_ROLE_STAFF_ID', 'REMPLACER_ROLE_STAFF_ID'));
}
