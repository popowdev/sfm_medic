<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';

start_session();

if (is_logged_in()) {
    header('Location: /');
    exit;
}

$redirect = preg_replace('/[^a-zA-Z0-9\/_\-\.?=&]/', '', (string)($_GET['redirect'] ?? '/'));
if (!str_starts_with($redirect, '/')) $redirect = '/';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        $sentToken = (string)($_POST['csrf'] ?? '');
        $sessToken = (string)($_SESSION['csrf'] ?? '');
        if ($sessToken === '' || !hash_equals($sessToken, $sentToken)) {
            throw new RuntimeException('Requête invalide, veuillez recharger la page.');
        }

        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new RuntimeException('Identifiants manquants.');
        }

        $stmt = db()->prepare("SELECT * FROM users WHERE username=? AND is_active=1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            throw new RuntimeException('Nom d\'utilisateur ou mot de passe incorrect.');
        }

        login_user($user);

        header('Location: ' . $redirect);
        exit;

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

samr_layout_start('SAMR — Connexion');
?>

<style>
.login-wrap{
  min-height: calc(100vh - 60px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.login-box{
  width: 100%;
  max-width: 420px;
}
.login-header{
  text-align: center;
  margin-bottom: 32px;
}
.login-logo{
  width: 64px; height: 64px;
  border-radius: 18px;
  background: linear-gradient(135deg, rgba(59,158,255,.2), rgba(0,212,170,.15));
  border: 1px solid rgba(59,158,255,.25);
  display: flex; align-items: center; justify-content: center;
  font-size: 30px;
  margin: 0 auto 16px;
}
.login-title{
  font-family: var(--font-head);
  font-size: 26px;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 4px;
}
.login-sub{
  font-size: 13px;
  color: var(--muted);
}
.login-card{
  background: linear-gradient(160deg, rgba(13,18,25,.95), rgba(10,15,21,.98));
  border: 1px solid var(--line);
  border-radius: 18px;
  padding: 28px;
  box-shadow: 0 24px 60px rgba(0,0,0,.4);
}
.login-field{
  margin-bottom: 14px;
}
.login-field label{
  display: block;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .6px;
  color: var(--muted);
  margin-bottom: 7px;
}
.login-field .inp{
  font-size: 14px;
  padding: 11px 14px;
}
.login-field .inp:focus{
  border-color: rgba(59,158,255,.5);
  background: rgba(59,158,255,.04);
}
.btn-login{
  width: 100%;
  padding: 12px;
  font-size: 14px;
  font-weight: 700;
  border-radius: 11px;
  background: linear-gradient(135deg, rgba(59,158,255,.25), rgba(0,212,170,.15));
  border: 1px solid rgba(59,158,255,.35);
  color: var(--text);
  cursor: pointer;
  font-family: var(--font-body);
  transition: all .15s;
  margin-top: 4px;
}
.btn-login:hover{
  background: linear-gradient(135deg, rgba(59,158,255,.38), rgba(0,212,170,.22));
  border-color: rgba(59,158,255,.55);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(59,158,255,.15);
}
.divider{
  display: flex; align-items: center; gap: 12px;
  margin: 20px 0;
  color: var(--muted); font-size: 12px;
}
.divider::before, .divider::after{
  content: ''; flex: 1;
  height: 1px; background: var(--line);
}
.btn-discord{
  display: flex; align-items: center; justify-content: center; gap: 10px;
  width: 100%; padding: 12px;
  border-radius: 11px;
  background: rgba(88,101,242,.15);
  border: 1px solid rgba(88,101,242,.35);
  color: var(--text);
  text-decoration: none;
  font-size: 14px; font-weight: 700;
  font-family: var(--font-body);
  transition: all .15s;
  cursor: pointer;
}
.btn-discord:hover{
  background: rgba(88,101,242,.28);
  border-color: rgba(88,101,242,.6);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(88,101,242,.2);
  color: var(--text);
}
.btn-discord svg{ flex-shrink: 0; }
.login-error{
  padding: 11px 14px;
  border-radius: 10px;
  background: rgba(255,79,79,.08);
  border: 1px solid rgba(255,79,79,.25);
  color: #ff7a7a;
  font-size: 13px;
  margin-bottom: 18px;
  display: flex; align-items: center; gap: 8px;
}
.login-note{
  text-align: center;
  margin-top: 18px;
  font-size: 12px;
  color: var(--muted);
  line-height: 1.5;
}
</style>

<div class="login-wrap">
  <div class="login-box">

    <div class="login-header">
      <div class="login-logo">🚑</div>
      <div class="login-title">SAMR</div>
      <div class="login-sub">San Andreas Medical Response — Accès interne</div>
    </div>

    <div class="login-card">

      <?php if ($error): ?>
        <div class="login-error">
          <span>⚠</span>
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="on">
        <?= csrf_input() ?>
        <?php if ($redirect !== '/'): ?>
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES) ?>">
        <?php endif; ?>

        <div class="login-field">
          <label for="username">Nom d'utilisateur</label>
          <input class="inp" id="username" name="username"
                 autocomplete="username" required
                 placeholder="Ton nom dans le site"
                 value="<?= htmlspecialchars((string)($_POST['username'] ?? ''), ENT_QUOTES) ?>">
        </div>

        <div class="login-field">
          <label for="password">Mot de passe</label>
          <input class="inp" id="password" name="password"
                 type="password" autocomplete="current-password" required
                 placeholder="••••••••">
        </div>

        <button type="submit" class="btn-login">Se connecter →</button>
      </form>

      <div class="divider">ou</div>

      <a class="btn-discord" href="/discord_login.php">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path fill="#5865F2" d="M19.54 0c-1.27.58-2.43 1.05-3.54 1.32-.16.04-.33-.01-.45-.13-.3-.31-.63-.64-1.02-.99-.15-.13-.35-.18-.54-.12-1.28.4-2.63.4-3.91 0-.19-.06-.39-.01-.54.12-.39.35-.72.68-1.02.99-.12.12-.29.17-.45.13C6.96 1.05 5.8.58 4.53 0c-.2-.09-.44-.04-.59.13C1.63 2.98.46 5.73.02 8.55c-.02.13.03.27.14.35 1.95 1.47 3.84 2.37 5.67 2.96.18.06.38 0 .5-.14.45-.53.85-1.09 1.19-1.68.12-.2.02-.46-.21-.54-.61-.23-1.19-.51-1.74-.83-.19-.11-.2-.38-.03-.52.12-.1.24-.2.36-.29.1-.08.24-.1.35-.04 3.65 1.67 7.6 1.67 11.2 0 .11-.05.25-.04.35.04.12.1.24.19.36.29.17.14.16.41-.03.52-.55.32-1.13.6-1.74.83-.23.09-.33.34-.21.54.35.6.75 1.16 1.19 1.68.12.14.32.2.5.14 1.83-.59 3.72-1.49 5.67-2.96.11-.08.16-.22.14-.35-.46-3.05-1.78-5.8-3.92-8.42-.15-.17-.39-.22-.59-.13ZM8.9 14.63c-1.1 0-2-.99-2-2.21s.89-2.21 2-2.21c1.11 0 2 .99 2 2.21s-.89 2.21-2 2.21Zm6.2 0c-1.1 0-2-.99-2-2.21s.89-2.21 2-2.21c1.11 0 2 .99 2 2.21s-.89 2.21-2 2.21Z"/>
        </svg>
        Connexion avec Discord
      </a>

      <div class="login-note">
        Rôle <strong>SAMR</strong> requis sur le serveur Discord.<br>
        Session active pendant <strong>7 jours</strong>.
      </div>

    </div>
  </div>
</div>

<?php samr_layout_end(); ?>
