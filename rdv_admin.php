<?php
require_once 'auth.php';
require_once 'config.php';

$stmt = $pdo->query("SELECT * FROM rdv_requests ORDER BY created_at DESC");
$rdvs = $stmt->fetchAll();
?>

<h2>RDV en attente</h2>

<?php foreach ($rdvs as $r): ?>
    <div style="border:1px solid #ccc; margin:10px; padding:10px;">
        <b>#<?= $r['id'] ?></b> - <?= $r['nom'] ?> <?= $r['prenom'] ?><br>
        <?= $r['categorie'] ?><br>
        <?= $r['statut'] ?><br>
        <a href="/rdv/<?= $r['id'] ?>">Voir</a>
    </div>
<?php endforeach; ?>
