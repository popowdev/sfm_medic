<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';
require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../discord_roles.php';

require_specialisation('esthetique');

$u       = current_user();
$isAdmin = ($u['role'] ?? '') === 'admin';

$SPEC_KEY   = 'esthetique';
$SPEC_LABEL = 'Esthétique';
$SPEC_ICON  = '✨';

samr_layout_start('SAMR — Esthétique');

require_once __DIR__ . '/_template.php';

samr_layout_end();
?>
