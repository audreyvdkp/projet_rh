<?php
require_once '../includes/auth.php';
verifierRole('Administrateur RH');
require_once '../config/db.php';

$message = "";
$typeMessage = "";

// Suppression d'une offre
if (isset($_GET['supprimer']) && !empty($_GET['supprimer'])) {
    $idOffre = (int)$_GET['supprimer'];
    try {
        $stmt = $pdo->prepare("DELETE FROM offre_emploi WHERE id_offre = ?");
        $stmt->execute([$idOffre]);
        $message = "✅ Offre supprimée avec succès !";
        $typeMessage = "succes";
    } catch (PDOException $e) {
        $message = "⚠️ Erreur lors de la suppression : " . $e->getMessage();
        $typeMessage = "erreur";
    }
}

// Récupérer toutes les offres
$stmt = $pdo->prepare("
    SELECT * FROM offre_emploi 
    ORDER BY date_publication DESC
");
$stmt->execute();
$offres = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des offres — Admin RH</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">
    <div class="menu-lateral">
    <div class="logo-menu">
        <div class="icone-logo-menu">RH</div>
        <span>Système RH</span>
    </div>

    <!-- GROUPE 1 : RECRUTEMENT -->
    <p style="color: #cbd5e1; font-size: 11px; font-weight: bold; text-transform: uppercase; margin: 20px 0 8px 15px; letter-spacing: 1px; opacity: 0.7;">Recrutement</p>
    
    <a href="ajouter_offre.php" class="lien-menu actif">Offres d'emploi</a>
    <a href="candidatures.php" class="lien-menu">Candidatures</a>

    <!-- GROUPE 2 : GESTION -->
    <p style="color: #cbd5e1; font-size: 11px; font-weight: bold; text-transform: uppercase; margin: 20px 0 8px 15px; letter-spacing: 1px; opacity: 0.7;">Gestion</p>
    
    <a href="dashboard.php" class="lien-menu">Tableau de bord</a>
    <a href="employes.php" class="lien-menu">Employés</a>
    <a href="mon_equipe.php" class="lien-menu">Mon équipe</a>
    <a href="evaluations.php" class="lien-menu">Évaluations</a>
    <a href="demandes.php" class="lien-menu">Demandes</a>
    <a href="criteres.php" class="lien-menu">Critères</a>

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
                <p class="titre-page">Gestion des offres d'emploi</p>
                <p class="sous-titre-page">Consultez et gérez toutes les offres publiées</p>
            </div>
            <a href="ajouter_offre.php" class="bouton-principal">+ Nouvelle offre</a>
        </div>

        <?php if ($message): ?>
            <div style="padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; background: <?= $typeMessage === 'erreur' ? '#fee2e2' : '#dcfce7' ?>; color: <?= $typeMessage === 'erreur' ? '#991b1b' : '#166534' ?>; border-left: 4px solid <?= $typeMessage === 'erreur' ? '#dc2626' : '#22c55e' ?>;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if (empty($offres)): ?>
            <div class="panneau">
                <p style="color: #9ca3af; text-align: center; padding: 40px 0;">
                    Aucune offre d'emploi publiée pour le moment.<br>
                    <a href="ajouter_offre.php" style="color: #4a5bd4; text-decoration: underline;">Créer votre première offre →</a>
                </p>
            </div>
        <?php else: ?>
            <div class="panneau" style="padding: 0;">
                <table class="tableau-donnees" style="margin: 0;">
                    <thead style="background: #f9fafb;">
                        <tr>
                            <th style="padding: 16px; text-align: left;">Titre du poste</th>
                            <th style="padding: 16px; text-align: left;">Type de contrat</th>
                            <th style="padding: 16px; text-align: left;">Lieu</th>
                            <th style="padding: 16px; text-align: left;">Date limite</th>
                            <th style="padding: 16px; text-align: center;">Statut</th>
                            <th style="padding: 16px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($offres as $offre): 
                            $estExpiree = $offre['date_limite'] < date('Y-m-d');
                            $statut = $estExpiree ? 'Expirée' : $offre['statut'];
                            $couleurStatut = $estExpiree ? '#ef4444' : '#22c55e';
                            $bgStatut = $estExpiree ? '#fee2e2' : '#dcfce7';
                        ?>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 16px;">
                                    <strong style="color: #1f2937;"><?= htmlspecialchars($offre['titre']) ?></strong>
                                </td>
                                <td style="padding: 16px;">
                                    <span style="background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 12px; font-size: 13px; font-weight: 500;">
                                        <?= htmlspecialchars($offre['type_contrat']) ?>
                                    </span>
                                </td>
                                <td style="padding: 16px; color: #6b7280;"><?= htmlspecialchars($offre['lieu'] ?: 'Non précisé') ?></td>
                                <td style="padding: 16px; color: <?= $estExpiree ? '#ef4444' : '#6b7280' ?>;">
                                    <?= date('d/m/Y', strtotime($offre['date_limite'])) ?>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <span style="background: <?= $bgStatut ?>; color: <?= $couleurStatut ?>; padding: 6px 12px; border-radius: 12px; font-size: 13px; font-weight: 600;">
                                        <?= $statut ?>
                                    </span>
                                </td>
                                <td style="padding: 16px; text-align: right;">
                                    <a href="offres.php?supprimer=<?= $offre['id_offre'] ?>" 
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette offre ?')"
                                       style="color: #ef4444; text-decoration: none; font-size: 13px; font-weight: 500;">
                                        🗑 Supprimer
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>