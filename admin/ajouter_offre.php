<?php
require_once '../includes/auth.php';
verifierRole('Administrateur RH');
require_once '../config/db.php';

$message = "";
$typeMessage = "";
$titre = $_POST['titre'] ?? '';
$type_contrat = $_POST['type_contrat'] ?? 'CDI';
$lieu = $_POST['lieu'] ?? '';
$description = $_POST['description'] ?? '';
$date_limite = $_POST['date_limite'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($titre) && !empty($description) && !empty($date_limite)) {
        try {
            // On ajoute l'id_utilisateur de l'admin connecté
            $stmt = $pdo->prepare("
                INSERT INTO offre_emploi (id_utilisateur, titre, type_contrat, lieu, description, date_limite, date_publication, statut)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), 'Publiée')
            ");
            $stmt->execute([$_SESSION['id_utilisateur'], $titre, $type_contrat, $lieu, $description, $date_limite]);
            $message = "✅ L'offre d'emploi a été publiée avec succès !";
            $typeMessage = "succes";
            $titre = $lieu = $description = $date_limite = '';
        } catch (PDOException $e) {
            $message = "⚠️ Erreur : " . $e->getMessage();
            $typeMessage = "erreur";
        }
    } else {
        $message = "⚠️ Veuillez remplir tous les champs obligatoires (*).";
        $typeMessage = "erreur";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publier une offre d'emploi — Admin RH</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="conteneur-app">
<div class="menu-lateral">
    <div class="logo-menu" style="padding: 20px 15px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
    <div style="background: white; border-radius: 10px; padding: 8px 12px; display: inline-block; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
        <img src="../assets/images/logo-bsm-groupe - Copie.png" alt="BSM groupe" style="height: 35px; width: auto; display: block;">
    </div>
    <!-- <p style="color: white; font-size: 13px; font-weight: 600; margin: 0; letter-spacing: 1px;">BSM groupe</p> -->
</div>

    <!-- GROUPE 1 : RECRUTEMENT -->
    <p style="color: #cbd5e1; font-size: 11px; font-weight: bold; text-transform: uppercase; margin: 20px 0 8px 15px; letter-spacing: 1px; opacity: 0.7;">Recrutement</p>
    
    <a href="ajouter_offre.php" class="lien-menu">Offres d'emploi</a>
    <a href="candidatures.php" class="lien-menu">Candidatures</a>

    <!-- GROUPE 2 : GESTION -->
    <p style="color: #cbd5e1; font-size: 11px; font-weight: bold; text-transform: uppercase; margin: 20px 0 8px 15px; letter-spacing: 1px; opacity: 0.7;">Gestion</p>
    
    <a href="dashboard.php" class="lien-menu">Tableau de bord</a>
    <a href="employes.php" class="lien-menu">Employés</a>
    <a href="criteres.php" class="lien-menu">Critères</a>
    <a href="mon_equipe.php" class="lien-menu">Mon équipe</a>
    <a href="evaluations.php" class="lien-menu">Évaluations</a>
    <a href="demandes.php" class="lien-menu">Demandes</a>
    

    <!-- PIED DE MENU (Profil et Déconnexion) -->
    <div class="pied-menu">
        <div class="avatar-mini"><?= strtoupper(substr($_SESSION['prenom'],0,1) . substr($_SESSION['nom'],0,1)) ?></div>
        <span><?= htmlspecialchars($_SESSION['prenom']) ?></span>
        <a href="../logout.php" class="icone-deconnexion" title="Déconnexion">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        </a>
    </div>
</div>
    <div class="zone-contenu">
        <div class="entete-page">
            <div>
                <p class="titre-page">Publier une offre d'emploi</p>
                <p class="sous-titre-page">Créez une nouvelle offre visible par les candidats</p>
            </div>
            <a href="offres.php" class="bouton-secondaire">← Retour aux offres</a>
        </div>
        <?php if ($message): ?>
            <div style="padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; background: <?= $typeMessage === 'erreur' ? '#fee2e2' : '#dcfce7' ?>; color: <?= $typeMessage === 'erreur' ? '#991b1b' : '#166534' ?>; border-left: 4px solid <?= $typeMessage === 'erreur' ? '#dc2626' : '#22c55e' ?>;">
                <?= $message ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="" class="panneau">
            <h3 style="margin-top: 0; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; margin-bottom: 20px;">Informations sur le poste</h3>
            <div class="champ" style="margin-bottom: 20px;">
                <label>Titre du poste *</label>
                <input type="text" name="titre" value="<?= htmlspecialchars($titre) ?>" placeholder="Ex: Développeur Fullstack" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div class="champ">
                    <label>Type de contrat *</label>
                    <select name="type_contrat" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                        <option value="CDI" <?= $type_contrat === 'CDI' ? 'selected' : '' ?>>CDI</option>
                        <option value="CDD" <?= $type_contrat === 'CDD' ? 'selected' : '' ?>>CDD</option>
                        <option value="Stage" <?= $type_contrat === 'Stage' ? 'selected' : '' ?>>Stage</option>
                        <option value="Alternance" <?= $type_contrat === 'Alternance' ? 'selected' : '' ?>>Alternance</option>
                        <option value="Freelance" <?= $type_contrat === 'Freelance' ? 'selected' : '' ?>>Freelance</option>
                    </select>
                </div>
                <div class="champ">
                    <label>Lieu de travail</label>
                    <input type="text" name="lieu" value="<?= htmlspecialchars($lieu) ?>" placeholder="Ex: Paris, Télétravail..." style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                </div>
                <div class="champ">
                    <label>Date limite de candidature *</label>
                    <input type="date" name="date_limite" value="<?= htmlspecialchars($date_limite) ?>" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                </div>
            </div>
            <h3 style="color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; margin: 30px 0 20px 0;">Description du poste</h3>
            <div class="champ" style="margin-bottom: 24px;">
                <label>Description détaillée (Missions, profil recherché...) *</label>
                <textarea name="description" rows="8" required placeholder="Décrivez les missions, le profil recherché, les avantages..." style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; resize: vertical;"><?= htmlspecialchars($description) ?></textarea>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="offres.php" class="bouton-secondaire" style="padding: 12px 24px; text-decoration: none;">Annuler</a>
                <button type="submit" class="bouton-principal" style="padding: 12px 24px;">Publier l'offre</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>