<?php
declare(strict_types=1);

if (!function_exists('current_user')) {
  require_once __DIR__ . '/auth.php';
}

function samr_layout_start(string $pageTitle = 'SAMR'): void {
  $u        = current_user();
  $isLogged = is_logged_in();
  $isAdmin  = $u && (($u['role'] ?? '') === 'admin');

  $GLOBALS['__SAMR_LAYOUT__'] = [
    'u' => $u, 'isLogged' => $isLogged, 'isAdmin' => $isAdmin,
  ];

  $pt = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8');

  echo '<!doctype html><html lang="fr"><head>';
  echo '<meta charset="utf-8">';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
  echo "<title>{$pt}</title>";
  echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
  echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
  echo '<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">';

  echo <<<CSS
<style>
:root {
  --bg:       #080c10;
  --panel:    #0d1219;
  --panel2:   #0a0f15;
  --line:     #182030;
  --line2:    #1e2d40;
  --text:     #dce8f5;
  --muted:    #5a7a96;
  --accent:   #3b9eff;
  --accent2:  #00d4aa;
  --danger:   #ff4f4f;
  --radius:   14px;
  --sidebar:  260px;
  --font-head: 'Syne', sans-serif;
  --font-body: 'DM Sans', sans-serif;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--font-body);
  font-size: 14px;
  line-height: 1.6;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  overflow-x: hidden;
}

/* ── Background noise & grid ── */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background:
    radial-gradient(ellipse 900px 600px at 15% 0%, rgba(59,158,255,.07) 0%, transparent 65%),
    radial-gradient(ellipse 600px 500px at 85% 100%, rgba(0,212,170,.05) 0%, transparent 60%);
  pointer-events: none;
  z-index: 0;
}

a { color: var(--accent); text-decoration: none; }

/* ── LAYOUT ── */
.layout { display: flex; min-height: 100vh; position: relative; z-index: 1; }

.main {
  flex: 1;
  min-width: 0;
  padding: 28px 32px;
  margin-left: var(--sidebar);
}

/* ── SIDEBAR ── */
.sidebar {
  width: var(--sidebar);
  position: fixed;
  left: 0; top: 0; bottom: 0;
  background: linear-gradient(180deg, rgba(13,18,25,.98) 0%, rgba(8,12,16,.97) 100%);
  border-right: 1px solid var(--line);
  display: flex;
  flex-direction: column;
  padding: 0;
  z-index: 100;
  overflow: hidden;
}

/* top decoration bar */
.sidebar::before {
  content: '';
  display: block;
  height: 3px;
  background: linear-gradient(90deg, var(--accent), var(--accent2));
  flex-shrink: 0;
}

.sidebar-inner {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 20px 16px 20px;
  overflow-y: auto;
  overflow-x: hidden;
}

/* Brand */
.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-bottom: 18px;
  margin-bottom: 16px;
  border-bottom: 1px solid var(--line);
}
.brand-icon {
  width: 40px; height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, rgba(59,158,255,.2), rgba(0,212,170,.15));
  border: 1px solid rgba(59,158,255,.25);
  display: flex; align-items: center; justify-content: center;
  font-size: 20px;
  flex-shrink: 0;
}
.brand-text .t1 {
  font-family: var(--font-head);
  font-weight: 800;
  font-size: 15px;
  letter-spacing: .5px;
  color: var(--text);
}
.brand-text .t2 {
  font-size: 10px;
  color: var(--muted);
  letter-spacing: .3px;
  text-transform: uppercase;
  margin-top: 1px;
}

/* User badge */
.user-badge {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: rgba(59,158,255,.06);
  border: 1px solid rgba(59,158,255,.12);
  border-radius: 10px;
  margin-bottom: 18px;
}
.user-avatar {
  width: 34px; height: 34px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 800;
  color: #fff;
  flex-shrink: 0;
  overflow: hidden;
  border: 1px solid rgba(255,255,255,.08);
}
.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.user-info .u-name { font-size: 12px; font-weight: 600; color: var(--text); }
.user-info .u-role { font-size: 10px; color: var(--muted); }

/* Nav sections */
.nav-section-label {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  color: var(--muted);
  padding: 0 8px;
  margin: 14px 0 6px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  cursor: pointer;
  user-select: none;
}
.nav-section-label::after {
  content: '▾';
  font-size: 10px;
  color: var(--muted);
  transition: transform .15s ease;
}
.nav-section-label.is-collapsed::after { transform: rotate(-90deg); }
.nav {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.nav.nav-collapsed { display: none; }
.nav a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 9px;
  border: 1px solid transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 500;
  transition: all .15s ease;
  white-space: nowrap;
}
.nav a:hover {
  color: var(--text);
  border-color: var(--line2);
  background: rgba(255,255,255,.04);
}
.nav a.active {
  color: var(--accent);
  border-color: rgba(59,158,255,.2);
  background: rgba(59,158,255,.08);
}
.nav-icon { font-size: 15px; width: 20px; text-align: center; flex-shrink: 0; }

.sidebar-footer {
  border-top: 1px solid var(--line);
  padding: 14px 16px;
  font-size: 10px;
  color: var(--muted);
  letter-spacing: .3px;
}

/* ── SHARED COMPONENTS ── */
.card {
  background: linear-gradient(160deg, rgba(13,18,25,.9) 0%, rgba(10,15,21,.95) 100%);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 20px;
  margin-bottom: 16px;
  position: relative;
}
.card-accent {
  border-color: rgba(59,158,255,.2);
  background: linear-gradient(160deg, rgba(59,158,255,.04) 0%, rgba(10,15,21,.95) 100%);
}

.muted { color: var(--muted); }

.pill {
  display: inline-flex;
  align-items: center;
  font-size: 11px;
  border: 1px solid var(--line2);
  border-radius: 999px;
  padding: 3px 10px;
  color: var(--muted);
  gap: 5px;
}
.pill-green  { border-color: rgba(0,212,170,.3); color: var(--accent2); background: rgba(0,212,170,.06); }
.pill-blue   { border-color: rgba(59,158,255,.3); color: var(--accent);  background: rgba(59,158,255,.06); }
.pill-red    { border-color: rgba(255,79,79,.3);  color: #ff7a7a;        background: rgba(255,79,79,.06); }

/* Buttons */
.btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 9px 16px;
  border-radius: 9px;
  border: 1px solid var(--line2);
  background: rgba(59,158,255,.1);
  color: var(--text);
  font-family: var(--font-body);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all .15s;
  white-space: nowrap;
}
.btn:hover { background: rgba(59,158,255,.18); border-color: rgba(59,158,255,.35); }
.btn.secondary { background: transparent; }
.btn.secondary:hover { background: rgba(255,255,255,.05); }
.btn.success { background: rgba(0,212,170,.12); border-color: rgba(0,212,170,.3); color: var(--accent2); }
.btn.success:hover { background: rgba(0,212,170,.2); }
.btn.danger { background: rgba(255,79,79,.1); border-color: rgba(255,79,79,.3); color: #ff7a7a; }
.btn.danger:hover { background: rgba(255,79,79,.18); }

/* Page title */
.page-header { margin-bottom: 20px; }
.page-header h1 {
  font-family: var(--font-head);
  font-size: 22px;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 4px;
}
.page-header .sub { font-size: 13px; color: var(--muted); }

/* Stat cards */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px,1fr)); gap: 12px; margin-bottom: 16px; }
.stat-card {
  background: linear-gradient(135deg, rgba(13,18,25,.9), rgba(10,15,21,.95));
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 14px 16px;
}
.stat-card .s-label { font-size: 10px; text-transform: uppercase; letter-spacing: .8px; color: var(--muted); margin-bottom: 6px; }
.stat-card .s-val   { font-family: var(--font-head); font-size: 22px; font-weight: 800; color: var(--text); line-height: 1; }
.stat-card .s-sub   { font-size: 11px; color: var(--muted); margin-top: 4px; }
.stat-card.blue { border-color: rgba(59,158,255,.2); }
.stat-card.blue .s-val { color: var(--accent); }
.stat-card.green { border-color: rgba(0,212,170,.2); }
.stat-card.green .s-val { color: var(--accent2); }

/* Inputs */
.inp {
  width: 100%;
  padding: 9px 13px;
  border-radius: 9px;
  border: 1px solid var(--line2);
  background: rgba(255,255,255,.03);
  color: var(--text);
  font-family: var(--font-body);
  font-size: 13px;
  transition: border-color .15s;
}
.inp:focus { outline: none; border-color: rgba(59,158,255,.4); background: rgba(59,158,255,.04); }
select.inp { cursor: pointer; }

/* Modals */
.samr-modal { position: fixed; inset: 0; display: none; z-index: 9999; }
.samr-modal[aria-hidden="false"] { display: block; }
.samr-modal__backdrop { position: absolute; inset: 0; background: rgba(0,0,0,.65); backdrop-filter: blur(4px); }
.samr-modal__panel {
  position: relative;
  width: min(540px, calc(100% - 32px));
  margin: 7vh auto 0;
  background: linear-gradient(160deg, #0d1219 0%, #080c10 100%);
  border: 1px solid var(--line2);
  border-radius: 16px;
  padding: 22px;
  box-shadow: 0 32px 80px rgba(0,0,0,.6), 0 0 0 1px rgba(59,158,255,.05);
}
.samr-modal__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
.samr-modal__title { font-family: var(--font-head); font-size: 16px; font-weight: 700; }

/* Tables */
.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
  text-align: left; padding: 10px 12px;
  font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .8px;
  color: var(--muted);
  border-bottom: 1px solid var(--line);
}
.data-table td { padding: 11px 12px; border-bottom: 1px solid rgba(24,32,48,.8); font-size: 13px; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: rgba(255,255,255,.02); }

/* Scrollbar */
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--line2); border-radius: 4px; }

/* Mobile */
@media (max-width: 900px) {
  .main { margin-left: 0; padding: 16px; }
  .sidebar { position: static; width: 100%; border-right: none; border-bottom: 1px solid var(--line); }
  .sidebar-inner { padding: 14px; }
  .layout { flex-direction: column; }
}
</style>
CSS;

  echo '</head><body><div class="layout"><main class="main">';
}

function samr_layout_end(): void {
  $ctx      = $GLOBALS['__SAMR_LAYOUT__'] ?? ['u'=>null,'isLogged'=>false,'isAdmin'=>false];
  $u        = $ctx['u'];
  $isLogged = (bool)$ctx['isLogged'];
  $isAdmin  = (bool)$ctx['isAdmin'];

  $username        = '';
  $grade           = '';
  $canSupervise    = false;
  $hasPpaChasse    = false;
  $profilePhotoUrl = '';

  if ($isLogged && is_array($u)) {
    $username = htmlspecialchars((string)($u['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    try {
      require_once __DIR__ . '/db.php';
      $pdo  = db();
      $stmt = $pdo->prepare("SELECT can_supervise, grade, profile_photo_url FROM users WHERE id=? LIMIT 1");
      $stmt->execute([(int)($u['id'] ?? 0)]);
      $row  = $stmt->fetch(PDO::FETCH_ASSOC);
      $canSupervise = ((int)($row['can_supervise'] ?? 0) === 1);
      $grade = htmlspecialchars((string)($row['grade'] ?? ''), ENT_QUOTES, 'UTF-8');
      $profilePhotoUrl = htmlspecialchars((string)($row['profile_photo_url'] ?? ''), ENT_QUOTES, 'UTF-8');

      $fStmt = $pdo->prepare("SELECT COUNT(*) FROM personnel_formations_assign WHERE user_id=? AND formation_id=6");
      $fStmt->execute([(int)($u['id'] ?? 0)]);
      $hasPpaChasse = ((int)$fStmt->fetchColumn() > 0);
    } catch (Throwable $e) {}
  }

  $initials = '';
  if ($username) {
    $parts = preg_split('/[\s|]+/', strip_tags($username));
    foreach (array_slice($parts, 0, 2) as $p) {
      if (trim($p)) $initials .= mb_strtoupper(mb_substr(trim($p), 0, 1));
    }
  }

  $currentPath = $_SERVER['REQUEST_URI'] ?? '/';
  $currentPath = strtok($currentPath, '?');

  $currentPage = basename($currentPath, '.php') ?: 'index';

  echo '</main><aside class="sidebar">';
  echo '<div class="sidebar-inner">';

  echo '<div class="brand">';
  echo '<div class="brand-icon">🚑</div>';
  echo '<div class="brand-text"><div class="t1">SAMR</div><div class="t2">Medical Response</div></div>';
  echo '</div>';

  if ($isLogged && $username) {
    echo '<div class="user-badge">';
    if ($profilePhotoUrl !== '') {
      echo '<div class="user-avatar"><img src="' . $profilePhotoUrl . '" alt="Avatar"></div>';
    } else {
      echo '<div class="user-avatar">' . htmlspecialchars($initials ?: '?', ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo '<div class="user-info">';
    echo '<div class="u-name">' . $username . '</div>';
    echo '<div class="u-role">' . ($grade ?: ($isAdmin ? 'Administrateur' : 'Membre')) . '</div>';
    echo '</div></div>';
  }

  function navlink(string $href, string $icon, string $label, string $currentPage): void {
    $slug   = basename($href, '.php') ?: 'index';
    $active = ($slug === $currentPage) ? ' active' : '';
    echo "<a href=\"{$href}\" class=\"{$active}\"><span class=\"nav-icon\">{$icon}</span>{$label}</a>";
  }

  echo '<div class="nav-section-label">Navigation</div>';
  echo '<nav class="nav">';
  navlink('/', '🏠', 'Accueil', $currentPage);

  if ($isLogged) {
    navlink('/profile.php',      '🧑‍⚕️', 'Mon profil',     $currentPage);
    navlink('/feed.php',         '💬', 'Notes internes',  $currentPage);
    navlink('/formation.php', '🎓', 'Formation', $currentPage);
    navlink('/organigramme.php', '👥', 'Organigramme',    $currentPage);

    echo '</nav>';
    echo '<div class="nav-section-label">SAMR</div>';
    echo '<nav class="nav">';
    navlink('/board.php',           '🚑', 'Dispatch SAMR',        $currentPage);
    navlink('/calendar.php',   '📅', 'Calendrier',    $currentPage);
    navlink('/service_notes.php',   '📋', 'Notes de service',     $currentPage);
    navlink('/generateur_rapport_medical.php',    '📋', 'Rapport Médical',     $currentPage);
    navlink('/visite_medicale.php', '🩺', 'Visite médicale',      $currentPage);
    navlink('/rdv_pending.php', '📆', 'RDV en attente', $currentPage);
    navlink('/trauma.php', '🩸', 'Traumatologie',      $currentPage);
    navlink('/bandages.php', '🩹', 'Bandages',      $currentPage);
    navlink('/lab_results.php', '🔬', 'Laboratoire',      $currentPage);
    navlink('/blacklist.php', '⛔', 'Blacklist', $currentPage);
    navlink('/gynecology_generator.php', '♀️', 'Gynécologie', $currentPage);
    navlink('/documents.php',  '📄', 'Documents',     $currentPage);
    navlink('/billing.php',    '💳', 'Facturation',   $currentPage);
    navlink('/ward.php',       '🛏️', 'Chambres',      $currentPage);
    navlink('/rapport.php',    '🎙️', 'Rapports vocaux', $currentPage);
    navlink('/tdah.php',       '🧠', 'Test TDAH',        $currentPage);
    navlink('/ophtalmologie.php', '👁️', 'Ophtalmologie', $currentPage);

    if (function_exists('get_user_specialisations')) {
      $userSpecs = get_user_specialisations();
    } else {

      $drFile = __DIR__ . '/discord_roles.php';
      if (file_exists($drFile)) {
        require_once $drFile;
        $userSpecs = get_user_specialisations();
      } else {
        $userSpecs = [];
      }
    }

    $hasTpPpa = false;
    if (function_exists('get_discord_role_ids')) {
      $tpDiscordId = (string)($u['discord_id'] ?? '');
      if ($tpDiscordId !== '') {
        $analyzerRoles = ['1433218103428517909', '1433218049086984323'];
        $hasTpPpa = count(array_intersect($analyzerRoles, get_discord_role_ids($tpDiscordId))) > 0;
      }
    }

    if (!empty($userSpecs)) {
      $specLabels = defined('DISCORD_ROLE_LABELS') ? DISCORD_ROLE_LABELS : [];
      $specIcons  = defined('DISCORD_ROLE_ICONS')  ? DISCORD_ROLE_ICONS  : [];
      echo '</nav>';
      echo '<div class="nav-section-label">Spécialisations</div>';
      echo '<nav class="nav">';
      foreach ($userSpecs as $specKey) {
        $icon  = $specIcons[$specKey]  ?? '🏥';
        $label = $specLabels[$specKey] ?? $specKey;
        navlink("/spec/{$specKey}.php", $icon, $label, $currentPage);
      }
    } else {
      echo '</nav>';
      echo '<div class="nav-section-label">Spécialisations</div>';
      echo '<nav class="nav">';
      navlink('/specialisations.php', '🏥', 'Mes spécialisations', $currentPage);
    }

    if ($isAdmin || $canSupervise) {
      echo '</nav>';
      echo '<div class="nav-section-label">Gestion</div>';
      echo '<nav class="nav">';
      navlink('/personnel.php', '👤', 'Personnel', $currentPage);
    }
    if ($isAdmin || $canSupervise || $hasPpaChasse || $hasTpPpa) {
      if (!($isAdmin || $canSupervise)) { echo '</nav><div class="nav-section-label">Gestion</div><nav class="nav">'; }
      if ($isAdmin || $canSupervise || $hasPpaChasse) { navlink('/ppa.php', '🔫', 'PPA', $currentPage); }
      navlink('/ppa_result.php', '🔫', 'PPA Analyser', $currentPage);
    }

    if ($isAdmin) {
      if (!($isAdmin || $canSupervise)) { echo '</nav><div class="nav-section-label">Admin</div><nav class="nav">'; }
      navlink('/salary.php',            '💲', 'Salaires',      $currentPage);
      navlink('/admin/index.php',       '🛠️', 'Panneau admin',  $currentPage);
      navlink('/logements.html', '🏨', 'Logements', $currentPage);
      navlink('/admin/users_manage.php','🗑️', 'Utilisateurs',  $currentPage);
    }

    echo '</nav>';
    echo '<div class="nav-section-label">Compte</div>';
    echo '<nav class="nav">';
    navlink('/logout.php', '🚪', 'Déconnexion', $currentPage);
  } else {
    navlink('/login.php', '🔐', 'Connexion', $currentPage);
  }

  echo '</nav>';
  echo '</div>';
  echo '<div class="sidebar-footer">© ' . date('Y') . ' SAMR — Interne</div>';
  echo '</aside></div>';

  echo '<script>'
    . '(function(){'
    . 'var labels=document.querySelectorAll(".nav-section-label");'
    . 'labels.forEach(function(label,idx){'
    . '  var nav=label.nextElementSibling;'
    . '  if(!nav || !nav.classList.contains("nav")) return;'
    . '  var key="samr_nav_section_"+idx+"_"+label.textContent.trim();'
    . '  label.setAttribute("role","button");'
    . '  label.setAttribute("tabindex","0");'
    . '  function setCollapsed(collapsed){'
    . '    label.classList.toggle("is-collapsed", collapsed);'
    . '    nav.classList.toggle("nav-collapsed", collapsed);'
    . '    label.setAttribute("aria-expanded", collapsed ? "false" : "true");'
    . '    try{localStorage.setItem(key, collapsed ? "1" : "0");}catch(e){}'
    . '  }'
    . '  try{ if(localStorage.getItem(key)==="1") setCollapsed(true); else label.setAttribute("aria-expanded","true"); }catch(e){ label.setAttribute("aria-expanded","true"); }'
    . '  label.addEventListener("click",function(){setCollapsed(!nav.classList.contains("nav-collapsed"));});'
    . '  label.addEventListener("keydown",function(e){if(e.key==="Enter" || e.key===" "){e.preventDefault();label.click();}});'
    . '});'
    . '})();'
    . '</script>';

  if ($isLogged && is_array($u)) {
    $uid = (int)($u['id'] ?? 0);
    if ($uid > 0) {
      try {
        $pdo = db();

        $pdo->exec("CREATE TABLE IF NOT EXISTS `service_notes_seen` (
          `user_id` INT(11) NOT NULL,
          `post_id` INT(11) NOT NULL,
          `seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`user_id`, `post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $hasType = false;
        try {
          $pdo->query("SELECT type FROM feed_posts LIMIT 1");
          $hasType = true;
        } catch (Throwable $e) {}

        if ($hasType) {
          $stmt = $pdo->prepare("
            SELECT p.id, p.body, p.created_at, u.username
            FROM feed_posts p
            JOIN users u ON u.id = p.user_id
            WHERE p.type = 'service_note'
              AND p.id NOT IN (
                SELECT post_id FROM service_notes_seen WHERE user_id = ?
              )
            ORDER BY p.pinned DESC, p.created_at DESC
            LIMIT 5
          ");
        } else {

          $stmt = $pdo->prepare("SELECT p.id, p.body, p.created_at, u.username FROM feed_posts p JOIN users u ON u.id = p.user_id WHERE 1=0");
        }
        $stmt->execute([$uid]);
        $unseen = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($unseen) {
          $postsJson = json_encode(array_values($unseen), JSON_UNESCAPED_UNICODE);
          $uidJs = (int)$uid;

          echo '<style>'
            . '.sn-popup-backdrop{position:fixed;inset:0;z-index:99998;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:20px;}'
            . '.sn-popup{width:min(560px,100%);max-height:80vh;background:linear-gradient(160deg,#0d1219,#080c10);border:1px solid rgba(255,180,50,.35);border-radius:18px;box-shadow:0 32px 80px rgba(0,0,0,.6);display:flex;flex-direction:column;overflow:hidden;}'
            . '.sn-popup-head{padding:18px 20px 14px;border-bottom:1px solid rgba(255,180,50,.15);background:rgba(255,180,50,.05);flex-shrink:0;}'
            . '.sn-popup-title{font-family:\'Syne\',sans-serif;font-size:15px;font-weight:800;display:flex;align-items:center;gap:8px;margin-bottom:4px;}'
            . '.sn-popup-sub{font-size:12px;color:#5a7a96;}'
            . '.sn-popup-counter{display:inline-block;padding:2px 9px;border-radius:999px;background:rgba(255,180,50,.15);border:1px solid rgba(255,180,50,.3);color:#ffb060;font-size:11px;font-weight:700;}'
            . '.sn-popup-body{flex:1;overflow-y:auto;padding:16px 20px;}'
            . '.sn-popup-note{padding:14px;border-radius:12px;border:1px solid rgba(255,255,255,.06);background:rgba(255,255,255,.02);margin-bottom:10px;}'
            . '.sn-popup-note:last-child{margin-bottom:0}'
            . '.sn-popup-note-meta{font-size:11px;color:#5a7a96;margin-bottom:8px;}'
            . '.sn-popup-note-body{font-size:13px;line-height:1.65;white-space:pre-wrap;word-break:break-word;}'
            . '.sn-popup-foot{padding:14px 20px;border-top:1px solid rgba(255,255,255,.06);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-shrink:0;}'
            . '.sn-nav{display:flex;gap:6px;align-items:center;}'
            . '.sn-dot{width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.15);transition:background .15s;}'
            . '.sn-dot.active{background:#ffb060;}'
            . '</style>';

          echo '<div class="sn-popup-backdrop" id="snPopup">'
            . '<div class="sn-popup">'
            . '<div class="sn-popup-head">'
            . '<div class="sn-popup-title">&#128203; Note de service <span class="sn-popup-counter" id="snCounter">1 / 1</span></div>'
            . '<div class="sn-popup-sub">Merci de lire et confirmer la prise en compte.</div>'
            . '</div>'
            . '<div class="sn-popup-body" id="snBody"></div>'
            . '<div class="sn-popup-foot">'
            . '<div class="sn-nav" id="snDots"></div>'
            . '<div style="display:flex;gap:8px;">'
            . '<button id="snPrev" style="display:none;padding:8px 14px;border-radius:9px;border:1px solid rgba(255,255,255,.1);background:transparent;color:#dce8f5;font-family:\'DM Sans\',sans-serif;cursor:pointer;">&larr; Pr&eacute;c&eacute;dent</button>'
            . '<button id="snNext" style="padding:9px 20px;border-radius:9px;border:1px solid rgba(255,180,50,.35);background:rgba(255,180,50,.12);color:#ffb060;font-family:\'DM Sans\',sans-serif;font-weight:700;cursor:pointer;">J\'ai lu &#10003;</button>'
            . '</div></div></div></div>';

          echo '<script>'
            . '(function(){'
            . 'var POSTS=' . $postsJson . ';'
            . 'var current=0;'
            . 'var popup=document.getElementById("snPopup");'
            . 'var bodyEl=document.getElementById("snBody");'
            . 'var counter=document.getElementById("snCounter");'
            . 'var dots=document.getElementById("snDots");'
            . 'var btnNext=document.getElementById("snNext");'
            . 'var btnPrev=document.getElementById("snPrev");'
            . 'function fmtDate(s){return new Date(s).toLocaleDateString("fr-FR",{day:"2-digit",month:"long",year:"numeric"});}'
            . 'function esc(s){return String(s||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");}'
            . 'function render(){'
            . '  var p=POSTS[current];'
            . '  bodyEl.innerHTML="<div class=\"sn-popup-note\"><div class=\"sn-popup-note-meta\">&#128226; "+esc(p.username)+" &middot; "+fmtDate(p.created_at)+"</div><div class=\"sn-popup-note-body\">"+esc(p.body)+"</div></div>";'
            . '  counter.textContent=(current+1)+" / "+POSTS.length;'
            . '  btnPrev.style.display=current>0?"":"none";'
            . '  btnNext.textContent=current<POSTS.length-1?"Suivant \u2192":"\u2713 J\'ai lu";'
            . '  dots.innerHTML="";'
            . '  POSTS.forEach(function(_,i){var d=document.createElement("div");d.className="sn-dot"+(i===current?" active":"");dots.appendChild(d);});'
            . '}'
            . 'function markSeen(postId){'
            . '  return fetch("/api/service_notes_seen.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({post_id:postId})}).catch(function(){});'
            . '}'
            . 'btnNext.addEventListener("click",function(){'
            . '  markSeen(POSTS[current].id).then(function(){'
            . '    if(current<POSTS.length-1){current++;render();}'
            . '    else{popup.style.opacity="0";popup.style.transition="opacity .3s";setTimeout(function(){popup.remove();},300);}'
            . '  });'
            . '});'
            . 'btnPrev.addEventListener("click",function(){if(current>0){current--;render();}});'
            . 'render();'
            . '})();'
            . '</script>';
        }
      } catch (Throwable $e) {}
    }
  }

  echo '<footer style="padding:14px 18px;text-align:center;font-size:13px;line-height:1.5;color:#7b8794">'
     . 'D&eacute;velopp&eacute; par <a href="https://surfsmart.fr" target="_blank" rel="noopener" style="color:#4d90f0;text-decoration:underline">Surf Smart</a>'
     . '</footer>';

  echo '</body></html>';
}
