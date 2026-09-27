<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/discord_roles.php';

require_login();
$u            = current_user();
$userSpecs    = get_user_specialisations();
$discordId    = (string)($u['discord_id'] ?? '');
$hasDiscordId = $discordId !== '';

samr_layout_start('SAMR — Spécialisations médicales');
?>
<style>
.specs-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:24px;}
.spec-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;}
.spec-card{background:linear-gradient(160deg,rgba(13,18,25,.9),rgba(10,15,21,.95));border:1px solid var(--line);border-radius:14px;padding:16px;display:flex;flex-direction:column;gap:10px;transition:border-color .15s,transform .12s;text-decoration:none;color:inherit;}
.spec-card.unlocked:hover{border-color:rgba(0,212,170,.45);transform:translateY(-2px);}
.spec-card.locked{opacity:.5;cursor:default;}
.spec-icon{font-size:26px;}
.spec-name{font-family:var(--font-head);font-size:14px;font-weight:700;}
.spec-status{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;width:fit-content;margin-top:auto;}
.spec-status.ok{background:rgba(0,212,170,.1);border:1px solid rgba(0,212,170,.25);color:var(--accent2);}
.spec-status.no{background:rgba(255,255,255,.03);border:1px solid var(--line);color:var(--muted);}
.sec-label{font-family:var(--font-head);font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin:22px 0 12px;display:flex;align-items:center;gap:8px;}
.sec-label::after{content:'';flex:1;height:1px;background:var(--line);}
.no-discord{background:rgba(255,176,96,.06);border:1px solid rgba(255,176,96,.25);border-radius:12px;padding:14px 18px;margin-bottom:20px;font-size:13px;color:#ffb060;display:flex;align-items:flex-start;gap:10px;}
</style>

<div class="page-header">
  <h1>🏥 Spécialisations médicales</h1>
  <div class="sub">Modules accessibles selon vos rôles Discord SAMR</div>
</div>

<?php if(!$hasDiscordId): ?>
<div class="no-discord">⚠️ Votre compte n'est pas lié à un Discord ID. Reconnectez-vous via Discord pour accéder aux spécialisations.</div>
<?php endif; ?>

<div class="specs-summary">
  <div class="stat-card blue">
    <div class="s-label">Vos spécialisations</div>
    <div class="s-val"><?= count($userSpecs) ?></div>
    <div class="s-sub">sur <?= count(DISCORD_ROLES) ?> disponibles</div>
  </div>
  <div class="stat-card green">
    <div class="s-label">Modules actifs</div>
    <div class="s-val"><?= count($userSpecs) ?></div>
    <div class="s-sub">pages déverrouillées</div>
  </div>
  <div class="stat-card">
    <div class="s-label">Discord</div>
    <div class="s-val" style="font-size:14px;margin-top:4px;"><?= $hasDiscordId?'✅ Lié':'❌ Non lié' ?></div>
    <div class="s-sub"><?= $hasDiscordId?htmlspecialchars($discordId,ENT_QUOTES,'UTF-8'):'Reconnectez-vous' ?></div>
  </div>
</div>

<?php
$unlocked = array_filter(array_keys(DISCORD_ROLES), fn($k)=>in_array($k,$userSpecs));
$locked   = array_filter(array_keys(DISCORD_ROLES), fn($k)=>!in_array($k,$userSpecs));

if(!empty($unlocked)):?>
<div class="sec-label">✅ Vos modules déverrouillés</div>
<div class="spec-grid">
<?php foreach($unlocked as $key):
  $label = DISCORD_ROLE_LABELS[$key]  ?? $key;
  $icon  = DISCORD_ROLE_ICONS[$key]   ?? '🏥';
?>
  <a class="spec-card unlocked" href="/spec/<?= htmlspecialchars($key,ENT_QUOTES,'UTF-8') ?>.php">
    <div class="spec-icon"><?= $icon ?></div>
    <div class="spec-name"><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?></div>
    <div class="spec-status ok">✓ Accès autorisé →</div>
  </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if(!empty($locked)):?>
<div class="sec-label">🔒 Non accessibles</div>
<div class="spec-grid">
<?php foreach($locked as $key):
  $label = DISCORD_ROLE_LABELS[$key] ?? $key;
  $icon  = DISCORD_ROLE_ICONS[$key]  ?? '🏥';
?>
  <div class="spec-card locked">
    <div class="spec-icon"><?= $icon ?></div>
    <div class="spec-name"><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?></div>
    <div class="spec-status no">🔒 Rôle requis</div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php samr_layout_end(); ?>
