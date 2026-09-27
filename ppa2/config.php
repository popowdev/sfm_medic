<?php

(function () {
  $f = __DIR__ . '/../.env';
  if (!is_readable($f)) return;
  foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    $l = trim($l);
    if ($l === '' || $l[0] === '#' || strpos($l, '=') === false) continue;
    [$k, $v] = explode('=', $l, 2);
    $k = trim($k); $v = trim($v);
    if ($k !== '' && getenv($k) === false) { putenv("$k=$v"); }
  }
})();

return [
    'base_url'               => 'https://exemple.tld/ppa2',
    'activation_valid_hours' => 72,
    'discord_bot_token'      => getenv('DISCORD_BOT_TOKEN') ?: '',
    'questions_per_test'     => 20,

    'thresholds' => [
        'civil'      => 14,
        'sasp'       => 14,
        'chasse'     => 12,
        'ammunation' => 14,
    ],

    'webhooks' => [
        'civil'  => '',
        'sasp'   => '',
        'chasse' => '',
    ],
];
