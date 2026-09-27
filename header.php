<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$u = current_user();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SAMR - San Andreas Medical Response</title>
  <style>
    body { font-family: Arial, sans-serif; margin:0; background:#0b0f14; color:#e6eef8; }
    header { background:#0f1722; padding:16px 18px; display:flex; gap:16px; align-items:center; justify-content:space-between; border-bottom:1px solid #1d2a3a; }
    a { color:#66b2ff; text-decoration:none; }
    .wrap { max-width: 1000px; margin: 0 auto; padding: 18px; }
    .card { background:#0f1722; border:1px solid #1d2a3a; border-radius:14px; padding:16px; margin: 12px 0; }
    .muted { color:#9fb3c8; }
    input, textarea, select { width:100%; padding:10px; border-radius:10px; border:1px solid #1d2a3a; background:#0b0f14; color:#e6eef8; }
    button { padding:10px 14px; border-radius:10px; border:1px solid #1d2a3a; background:#102235; color:#e6eef8; cursor:pointer; }
    .topnav a { margin-right:12px; }
    .badge { display:inline-block; padding:3px 8px; border:1px solid #1d2a3a; border-radius:999px; font-size:12px; color:#9fb3c8; }
  </style>
</head>
<body>
<header>
  <div class="topnav">
    <a href="/">Accueil</a>
    <?php if (is_logged_in()): ?>
      <a href="/links.php">Liens</a>
      <a href="/profile.php">Profil</a>
      <?php if (($u['role'] ?? '') === 'admin'): ?>
        <a href="/admin/index.php">Admin</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <div>
    <?php if (is_logged_in()): ?>
      <span class="badge">Connecté : <?= htmlspecialchars($u['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
      <a href="/logout.php" style="margin-left:12px;">Déconnexion</a>
    <?php else: ?>
      <a href="/login.php">Connexion</a>
    <?php endif; ?>
  </div>
</header>
<div class="wrap">