<?php
require_once '../includes/auth.php';
verifierRole('Manager');
require_once '../config/db.php';

// Récupérer l'id_employe du manager connecté
$stmt = $pdo->prepare("SELECT id_employe FROM Employe WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['id_utilisateur']]);
$idEmployeManager = $stmt->fetchColumn();

if (!$idEmployeManager) {
    die("Erreur : Aucun profil employé trouvé pour ce compte manager.");
}

// Traitement des actions de validation/refus des demandes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_demande'])) {
    $idDemande = (int)$_POST['id_demande'];
    $action = $_POST['action_demande'];
    
    if ($action === 'accepter' || $action === 'refuser') {
        $nouveauStatut = $action === 'accepter' ? 'Accepté' : 'Refusé';
        $stmt = $pdo->prepare("
            UPDATE Demande d 
            INNER JOIN Employe e ON d.id_employe = e.id_employe 
            SET d.statut = ?, d.id_validateur = ?
            WHERE d.id_demande = ? AND e.manager_referent = ?
        ");
        $stmt->execute([$nouveauStatut, $_SESSION['id_utilisateur'], $idDemande, $idEmployeManager]);
    }
}

// Récupération de l'équipe avec recherche
$recherche = $_GET['recherche'] ?? '';
$sql = "
    SELECT e.id_employe, u.nom, u.prenom, u.email, e.poste, e.service
    FROM Employe e 
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur 
    WHERE e.manager_referent = ? AND e.statut = 'Actif'
";
$params = [$idEmployeManager];

if ($recherche !== '') {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR e.poste LIKE ?)";
    $rechercheLike = '%' . $recherche . '%';
    $params[] = $rechercheLike;
    $params[] = $rechercheLike;
    $params[] = $rechercheLike;
}

$sql .= " ORDER BY u.nom, u.prenom";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipe = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des demandes en attente de l'équipe
$stmtDemandes = $pdo->prepare("
    SELECT d.id_demande, d.type, d.date_debut, d.date_fin, d.date_demande,
           u.nom, u.prenom
    FROM Demande d
    INNER JOIN Employe e ON d.id_employe = e.id_employe
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    WHERE e.manager_referent = ? AND d.statut = 'En attente'
    ORDER BY d.date_demande DESC
");
$stmtDemandes->execute([$idEmployeManager]);
$demandesEnAttente = $stmtDemandes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Équipe — Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">

    <!-- Menu latéral Manager -->
    <div class="menu-lateral">
        <div class="logo-menu">
            <div class="icone-logo-menu">RH</div>
            <span>Système RH</span>
        </div>

        <a href="dashboard.php" class="lien-menu">Tableau de bord</a>
        <a href="equipe.php" class="lien-menu actif">Mon équipe</a>
        <!-- <a href="tout_evaluer.php" class="lien-menu">Tout évaluer</a> -->
        <a href="objectifs.php" class="lien-menu">Objectifs</a>
        <a href="demandes.php" class="lien-menu">Demandes de mon équipe</a>

        <div class="pied-menu">
            <div class="avatar-mini"><?= strtoupper(substr($_SESSION['prenom'],0,1) . substr($_SESSION['nom'],0,1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['prenom']) ?></span>
            <a href="../logout.php" class="icone-deconnexion" title="Déconnexion">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </a>
        </div>
    </div>

    <!-- Zone de contenu -->
    <div class="zone-contenu">

        <div class="entete-page">
            <div>
                <p class="titre-page">Mon Équipe</p>
                <p class="sous-titre-page">Gérez et évaluez les membres de votre équipe</p>
            </div>
            <div class="actions-header">
                <a href="objectifs.php" class="bouton-secondaire">+ Fixer un objectif</a>
                <!-- <a href="tout_evaluer.php" class="bouton-principal-inline">Tout évaluer</a> -->
            </div>
        </div>

        <!-- Barre de recherche -->
    
<div class="barre-recherche-equipe">
    <input type="text" id="recherche-input" placeholder="Rechercher par nom ou poste..." style="flex: 1;">
</div>

        <!-- Tableau de l'équipe -->
        <?php if (empty($equipe)): ?>
            <div class="panneau">
                <p class="texte-vide">Aucun employé dans votre équipe.</p>
            </div>
        <?php else: ?>
            <table class="tableau-equipe">
                <thead>
                    <tr>
                        <th>Membre</th>
                        <th>Poste</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
               <tbody id="corps-tableau">
    <?php foreach ($equipe as $membre): ?>
        <tr class="ligne-membre"> <!-- Ajout de la class ici -->
            <td>
                <div class="membre-cell">
                    <div class="avatar-membre">
                        <?= strtoupper(substr($membre['prenom'], 0, 1) . substr($membre['nom'], 0, 1)) ?>
                    </div>
                    <div class="membre-info">
                        <p class="membre-nom"><?= htmlspecialchars($membre['prenom'] . ' ' . $membre['nom']) ?></p>
                        <p class="membre-email"><?= htmlspecialchars($membre['email']) ?></p>
                    </div>
                </div>
            </td>
            <td class="poste-cell"><?= htmlspecialchars($membre['poste']) ?></td>
            <td style="text-align: right;">
                <a href="evaluer.php?id=<?= $membre['id_employe'] ?>" class="btn-evaluer">Évaluer</a>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
            </table>
        <?php endif; ?>

        <!-- Section Demandes de mon équipe -->
        <div class="section-demandes">
            <h2 class="titre-section-demandes">Demandes de mon équipe</h2>
            <?php if (empty($demandesEnAttente)): ?>
                <div class="liste-demandes">
                    <p class="texte-vide-demandes">Aucune demande en attente de validation</p>
                </div>
            <?php else: ?>
                <div class="liste-demandes">
                    <?php foreach ($demandesEnAttente as $demande): ?>
                        <div class="demande-item">
                            <div class="demande-info">
                                <p class="demande-nom"><?= htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']) ?></p>
                                <p class="demande-detail">
                                    <?= htmlspecialchars($demande['type']) ?> · 
                                    <?= date('d/m', strtotime($demande['date_debut'])) ?> - <?= date('d/m', strtotime($demande['date_fin'])) ?>
                                </p>
                            </div>
                            <div class="demande-actions">
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="id_demande" value="<?= $demande['id_demande'] ?>">
                                    <input type="hidden" name="action_demande" value="accepter">
                                    <button type="submit" class="btn-action btn-accepter" title="Accepter">✓</button>
                                </form>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="id_demande" value="<?= $demande['id_demande'] ?>">
                                    <input type="hidden" name="action_demande" value="refuser">
                                    <button type="submit" class="btn-action btn-refuser" title="Refuser">✗</button>
                                </form>
                            </div>
                        </div>
                        <a href="evaluer.php?id=<?= $membre['id_employe'] ?>" class="btn-secondaire" style="margin-right: 5px;">Évaluer</a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>
<script>
    document.getElementById('recherche-input').addEventListener('input', function() {
        const recherche = this.value.toLowerCase();
        const lignes = document.querySelectorAll('.ligne-membre');

        lignes.forEach(function(ligne) {
            const texteLigne = ligne.textContent.toLowerCase();
            if (texteLigne.includes(recherche)) {
                ligne.style.display = ''; // Affiche la ligne
            } else {
                ligne.style.display = 'none'; // Cache la ligne
            }
        });
    });
</script>
</body>
</html>