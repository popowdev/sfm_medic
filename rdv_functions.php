<?php
require_once 'config.php';

function createRDV($nom, $prenom, $telephone, $disponibilites, $categorie, $motif) {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO rdv_requests (nom, prenom, telephone, disponibilites, categorie, motif, statut, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'En attente', NOW())
    ");

    $stmt->execute([$nom, $prenom, $telephone, $disponibilites, $categorie, $motif]);

    return $pdo->lastInsertId();
}
?>
