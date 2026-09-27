<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';
require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../discord_roles.php';

require_specialisation('osteopathe');

$u       = current_user();
$isAdmin = ($u['role'] ?? '') === 'admin';

$SPEC_KEY   = 'osteopathe';
$SPEC_LABEL = 'Ostéopathe';
$SPEC_ICON  = '🖐️';

samr_layout_start('SAMR — Ostéopathe');

require_once __DIR__ . '/_template.php';

samr_layout_end();
?>
