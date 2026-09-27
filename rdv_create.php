<?php
require_once 'rdv_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $telephone = $_POST['telephone'];
    $disponibilites = $_POST['disponibilites'];
    $categorie = $_POST['categorie'];
    $motif = $_POST['motif'];

    $id = createRDV($nom, $prenom, $telephone, $disponibilites, $categorie, $motif);

    header("Location: /rdv/" . $id);
    exit;
}
?>

<div class="max-w-4xl mx-auto mt-10 p-6 rounded-xl bg-[#0B1220] border border-[#1E293B]">

    <h2 class="text-2xl font-bold mb-2">📅 Demande de rendez-vous</h2>
    <p class="text-sm text-gray-400 mb-6">
        Remplis ce formulaire pour envoyer une demande au SAMR. Après validation, un numéro de suivi te sera donné sous la forme /rdv/numéro.
    </p>

    <form method="POST" class="space-y-6">

        <!-- NOM / PRENOM / TELEPHONE -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <div>
                <label class="block mb-1">Nom *</label>
                <input type="text" name="nom" class="w-full p-2 rounded bg-[#020617] border border-[#1E293B]" placeholder="Nom" required>
            </div>

            <div>
                <label class="block mb-1">Prénom *</label>
                <input type="text" name="prenom" class="w-full p-2 rounded bg-[#020617] border border-[#1E293B]" placeholder="Prénom" required>
            </div>

            <div>
                <label class="block mb-1">Téléphone *</label>
                <input type="text" name="telephone" class="w-full p-2 rounded bg-[#020617] border border-[#1E293B]" placeholder="Numéro" required>
            </div>

        </div>

        <!-- CATEGORIE -->
        <div>
            <label class="block mb-1">Catégorie *</label>
            <select name="categorie" class="w-full p-2 rounded bg-[#020617] border border-[#1E293B]" required>
                <option value="">Sélectionner une catégorie</option>
                <option value="Visite médicale">Visite médicale</option>
                <option value="Permis de port d'arme / Permis de chasse">Permis de port d'arme / Permis de chasse</option>
                <option value="Pôle Chirurgie">Pôle Chirurgie</option>
                <option value="Pôle Psychologie et Psychiatrie">Pôle Psychologie et Psychiatrie</option>
                <option value="Pôle Kinésithérapie">Pôle Kinésithérapie</option>
                <option value="Pôle Gynécologie">Pôle Gynécologie</option>
                <option value="Pôle Laboratoire">Pôle Laboratoire</option>
                <option value="Pôle Neurologie">Pôle Neurologie</option>
                <option value="Pôle Allergologie">Pôle Allergologie</option>
                <option value="Pôle Grands Brulés">Pôle Grands Brulés</option>
                <option value="Pôle Ophtalmo">Pôle Ophtalmo</option>
                <option value="Pôle Esthétique">Pôle Esthétique</option>
                <option value="Pôle Vétérinaire">Pôle Vétérinaire</option>
                <option value="Pôle Cardiologie">Pôle Cardiologie</option>
                <option value="Pôle Dentiste">Pôle Dentiste</option>
                <option value="Pôle UMJ">Pôle UMJ</option>
                <option value="Pôle Recrutement">Pôle Recrutement</option>
                <option value="Direction">Direction</option>
            </select>
        </div>

        <!-- DISPONIBILITES -->
        <div>
            <label class="block mb-1">Disponibilités *</label>
            <textarea name="disponibilites" rows="3"
                class="w-full p-2 rounded bg-[#020617] border border-[#1E293B]"
                placeholder="Exemple : lundi soir, mardi après-midi, urgence possible..."
                required></textarea>
        </div>

        <!-- MOTIF -->
        <div>
            <label class="block mb-1">Motif / détails de la demande *</label>
            <textarea name="motif" rows="4"
                class="w-full p-2 rounded bg-[#020617] border border-[#1E293B]"
                placeholder="Explique ta demande avec les informations utiles."
                required></textarea>
        </div>

        <!-- BOUTON -->
        <div class="flex items-center gap-4">
            <button type="submit"
                class="bg-emerald-500 hover:bg-emerald-600 px-4 py-2 rounded text-black font-semibold">
                Envoyer la demande
            </button>

            <span class="text-sm text-gray-400">
                Tu pourras revenir sur ta demande avec le lien de suivi.
            </span>
        </div>

    </form>

</div>