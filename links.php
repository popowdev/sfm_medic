<?php
 
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/header.php';

require_login();
?>

<div class="card">
  <h2>Liens spécifiques SAMR</h2>
  <ul>
    <li><a href="#" target="_blank" rel="noopener">Procédures (SOP)</a></li>
    <li><a href="#" target="_blank" rel="noopener">Planning / Disponibilités</a></li>
    <li><a href="#" target="_blank" rel="noopener">Formulaires internes</a></li>
  </ul>
  <p class="muted">Astuce : remplace les # par tes URL réelles.</p>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>