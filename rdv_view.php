<?php
require_once 'config.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM rdv_requests WHERE id = ?");
$stmt->execute([$id]);
$rdv = $stmt->fetch();
?>

<h2>Demande #<?= $rdv['id'] ?></h2>

<p><b>Nom :</b> <?= $rdv['nom'] ?> <?= $rdv['prenom'] ?></p>
<p><b>Téléphone :</b> <?= $rdv['telephone'] ?></p>
<p><b>Disponibilités :</b> <?= $rdv['disponibilites'] ?></p>
<p><b>Catégorie :</b> <?= $rdv['categorie'] ?></p>
<p><b>Motif :</b> <?= $rdv['motif'] ?></p>
<p><b>Statut :</b> <?= $rdv['statut'] ?></p>
