<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../csrf.php';

require_admin();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    verify_csrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = (string)($_POST['role'] ?? 'member');

    if ($username === '' || $password === '') {
      throw new RuntimeException("Nom d'utilisateur et mot de passe requis.");
    }
    if ($role !== 'admin' && $role !== 'member') {
      $role = 'member';
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = db()->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?,?,?,?)");
    $stmt->execute([$username, ($email !== '' ? $email : null), $hash, $role]);

    $msg = "Compte créé : " . $username;
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

samr_layout_start('Admin - Créer un compte');
?>

<div class="card">
  <h2 style="margin:0 0 8px 0;">Créer un compte</h2>
  <div class="muted">Créer un utilisateur (member ou admin).</div>
</div>

<div class="card">
  <?php if ($msg): ?>
    <div style="color:#b6f0c2; margin-bottom:10px;"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div style="color:#ff9aa2; margin-bottom:10px;"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post">
    <?= csrf_input() ?>

    <label class="muted">Nom d'utilisateur</label>
    <input name="username" required style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)">

    <div style="height:10px;"></div>

    <label class="muted">Email (optionnel)</label>
    <input name="email" type="email" style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)">

    <div style="height:10px;"></div>

    <label class="muted">Mot de passe</label>
    <input name="password" type="password" required style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)">

    <div style="height:10px;"></div>

    <label class="muted">Rôle</label>
    <select name="role" style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--line);background:#0b0f14;color:var(--text)">
      <option value="member">member</option>
      <option value="admin">admin</option>
    </select>

    <div style="height:14px;"></div>

    <button class="btn" type="submit">Créer</button>
    <a class="btn secondary" href="/admin/index.php" style="margin-left:10px;">Retour</a>
  </form>
</div>

<?php
samr_layout_end();
