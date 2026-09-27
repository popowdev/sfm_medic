<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/rdv_common.php';

$u = current_user();
$isAdmin = $u && (($u['role'] ?? '') === 'admin');

$sql = $isAdmin
  ? "SELECT a.*, u.username FROM announcements a JOIN users u ON u.id=a.created_by ORDER BY a.created_at DESC LIMIT 50"
  : "SELECT a.*, u.username FROM announcements a JOIN users u ON u.id=a.created_by WHERE a.is_published=1 ORDER BY a.created_at DESC LIMIT 50";

$rows = db()->query($sql)->fetchAll();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

samr_layout_start('SAMR - Accueil');
?>

<div class="home-grid">

<div class="home-main">

<div class="card">
  <h1 style="margin:0 0 6px 0;">SAMR – San Andreas Medical Response</h1>
  <div class="muted">Annonces officielles et informations internes.</div>

  <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">

    <?php if ($isAdmin): ?>
      <a class="btn" href="/admin/announcements_manage.php">📢 Gérer les annonces</a>
    <?php endif; ?>

    <?php if (is_logged_in()): ?>
      <a class="btn secondary" href="/calendar.php">📅 Calendrier</a>
      <a class="btn secondary" href="/board.php">🧩 Dispatch</a>
    <?php else: ?>
      <a class="btn" href="/login.php">🔐 Se connecter</a>
    <?php endif; ?>

    <a href="https://discord.gg/HkAYdfxN5m" target="_blank" class="btn discord-btn">
      <img src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/discord.svg" alt="Discord">
      Discord SAMR
    </a>

  </div>
</div>

<div class="card card-accent" id="demande-rdv">
  <h2 style="margin:0 0 6px 0;">📅 Demande de rendez-vous</h2>
  <div class="muted" style="margin-bottom:14px;">
    Remplis ce formulaire pour envoyer une demande au SAMR. Après validation, un numéro de suivi te sera donné sous la forme <strong>/rdv/numéro</strong>.
  </div>

  <form method="post" action="/rdv.php" style="display:grid;gap:12px;">
    <input type="hidden" name="action" value="create">
    <input type="text" name="website" value="" autocomplete="off" tabindex="-1" style="position:absolute;left:-9999px;opacity:0;">

    <div class="rdv-fields">
      <div>
        <label class="muted" for="rdv_nom">Nom *</label>
        <input class="inp" id="rdv_nom" name="rp_name" required placeholder="Nom">
      </div>
      <div>
        <label class="muted" for="rdv_prenom">Prénom *</label>
        <input class="inp" id="rdv_prenom" name="discord_name" required placeholder="Prénom">
      </div>
      <div>
        <label class="muted" for="rdv_telephone">Téléphone</label>
        <input class="inp" id="rdv_telephone" name="rp_phone" placeholder="Numéro">
      </div>
    </div>

    <div>
      <label class="muted" for="rdv_category">Catégorie *</label>
      <select class="inp rdv-select" id="rdv_category" name="category" required>
        <option value="">Sélectionner une catégorie</option>
        <?php foreach (rdv_categories() as $cat => $desc): ?>
          <option value="<?= h($cat) ?>"><?= h($cat . ' — ' . $desc) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="muted" for="rdv_availability">Disponibilités</label>
      <textarea class="inp" id="rdv_availability" name="availability" rows="3" placeholder="Exemple : lundi soir, mardi après-midi, urgence possible..."></textarea>
    </div>

    <div>
      <label class="muted" for="rdv_message">Motif / détails de la demande *</label>
      <textarea class="inp" id="rdv_message" name="message" rows="5" required placeholder="Explique ta demande avec les informations utiles."></textarea>
    </div>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <button class="btn success" type="submit">Envoyer la demande</button>
      <span class="muted">Tu pourras revenir sur ta demande avec le lien de suivi.</span>
    </div>
  </form>
</div>

<?php foreach ($rows as $r): ?>
  <article class="card">
    <div class="muted" style="font-size:13px;display:flex;justify-content:space-between;">
      <span>Par <?= h((string)$r['username']) ?> • <?= h((string)$r['created_at']) ?></span>
      <?php if (!(int)$r['is_published']): ?><span class="pill">Brouillon</span><?php endif; ?>
    </div>

    <h3 style="margin:10px 0 8px 0;"><?= h((string)$r['title']) ?></h3>

    <div class="ann-body" style="line-height:1.55;">
      <?= (string)$r['body'] ?>
    </div>
  </article>
<?php endforeach; ?>

<?php if (!$rows): ?>
  <div class="card"><span class="muted">Aucune annonce pour le moment.</span></div>
<?php endif; ?>

</div>

</div>

<style>
.discord-btn{
  background:#5865F2;
  color:white;
  display:inline-flex;
  align-items:center;
  gap:8px;
}
.discord-btn img{
  width:18px;
  height:18px;
  filter:invert(1);
}

.home-grid{
  display:block;
  width:100%;
  max-width:100%;
}
.home-main{
  width:100%;
  max-width:100%;
  min-width:0;
}

#demande-rdv,
#demande-rdv form,
#demande-rdv .inp{
  max-width:100%;
}

.rdv-fields{
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:12px;
}

.rdv-select{
  background-color:#020617 !important;
  color:#dbeafe !important;
  border:1px solid #1e3a5f !important;
  color-scheme:dark;
}

.rdv-select option{
  background-color:#020617;
  color:#dbeafe;
}

.inp{
  box-sizing:border-box;
}

@media (max-width:700px){
  .main{
    padding:12px !important;
  }
  .card{
    padding:14px !important;
    border-radius:12px;
  }
  .card h1{
    font-size:22px;
    line-height:1.2;
  }
  .card h2{
    font-size:19px;
    line-height:1.2;
  }
  .rdv-fields{
    grid-template-columns:1fr;
  }
  #demande-rdv form{
    gap:10px !important;
  }
  .inp,
  .btn{
    font-size:13px;
  }
  textarea.inp{
    min-height:76px;
  }
}

@media (max-width:430px){
  .main{
    padding:10px !important;
  }
  .card{
    padding:12px !important;
  }
  .btn{
    width:100%;
    justify-content:center;
  }
  .discord-btn{
    width:100%;
    justify-content:center;
  }
}
</style>

<?php
samr_layout_end();