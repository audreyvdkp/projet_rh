<?php
require_once '../includes/auth.php';
verifierRole('Administrateur RH');
require_once '../config/db.php';

$message = "";
$typeMessage = "";

// Filtrer par offre
$idOffreFiltre = $_GET['offre'] ?? '';

// Récupérer toutes les offres pour le filtre
$stmtOffres = $pdo->query("SELECT id_offre, titre FROM offre_emploi WHERE statut = 'Publiée' ORDER BY titre");
$offres = $stmtOffres->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les candidatures filtrées
$sql = "
    SELECT c.*, o.titre as titre_offre 
    FROM candidature c
    LEFT JOIN offre_emploi o ON c.id_offre = o.id_offre
    WHERE 1=1
";
$params = [];

if ($idOffreFiltre !== '') {
    $sql .= " AND c.id_offre = ?";
    $params[] = $idOffreFiltre;
}

$sql .= " ORDER BY o.titre ASC, c.date_soumission DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$total = count($candidatures);
$enAttente = count(array_filter($candidatures, fn($c) => $c['statut'] === 'En attente'));
$acceptees = count(array_filter($candidatures, fn($c) => $c['statut'] === 'Accepté'));
$refusees = count(array_filter($candidatures, fn($c) => $c['statut'] === 'Refusé'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des candidatures — Admin RH</title>
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
    
    <a href="ajouter_offre.php" class="lien-menu">Offres d'emploi</a>
    <a href="candidatures.php" class="lien-menu actif">Candidatures</a>

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
                <p class="titre-page">Gestion des candidatures</p>
                <p class="sous-titre-page">Consultez et gérez toutes les candidatures reçues</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div style="padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; background: <?= $typeMessage === 'erreur' ? '#fee2e2' : '#dcfce7' ?>; color: <?= $typeMessage === 'erreur' ? '#991b1b' : '#166534' ?>; border-left: 4px solid <?= $typeMessage === 'erreur' ? '#dc2626' : '#22c55e' ?>;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Filtre par offre -->
        <div class="panneau" style="margin-bottom: 20px;">
            <form method="GET" action="" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <label style="font-weight: 500; color: #1f2937;">Filtrer par poste :</label>
                <select name="offre" onchange="this.form.submit()" style="flex: 1; min-width: 250px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                    <option value="">Tous les postes</option>
                    <?php foreach ($offres as $offre): ?>
                        <option value="<?= $offre['id_offre'] ?>" <?= $idOffreFiltre == $offre['id_offre'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($offre['titre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($idOffreFiltre !== ''): ?>
                    <a href="candidatures.php" class="bouton-secondaire" style="padding: 10px 16px; text-decoration: none;">Voir tout</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($candidatures)): ?>
        <!-- Statistiques -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
            <div class="panneau" style="margin-bottom: 0; padding: 20px;">
                <p style="font-size: 14px; color: #6b7280; margin-bottom: 8px;">Total</p>
                <p style="font-size: 32px; font-weight: 700; color: #1f2937; margin: 0;"><?= $total ?></p>
            </div>
            <div class="panneau" style="margin-bottom: 0; padding: 20px; border-left: 4px solid #f59e0b;">
                <p style="font-size: 14px; color: #6b7280; margin-bottom: 8px;">En attente</p>
                <p style="font-size: 32px; font-weight: 700; color: #f59e0b; margin: 0;"><?= $enAttente ?></p>
            </div>
            <div class="panneau" style="margin-bottom: 0; padding: 20px; border-left: 4px solid #22c55e;">
                <p style="font-size: 14px; color: #6b7280; margin-bottom: 8px;">Acceptées</p>
                <p style="font-size: 32px; font-weight: 700; color: #22c55e; margin: 0;"><?= $acceptees ?></p>
            </div>
            <div class="panneau" style="margin-bottom: 0; padding: 20px; border-left: 4px solid #ef4444;">
                <p style="font-size: 14px; color: #6b7280; margin-bottom: 8px;">Refusées</p>
                <p style="font-size: 32px; font-weight: 700; color: #ef4444; margin: 0;"><?= $refusees ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Liste des candidatures -->
        <?php if (empty($candidatures)): ?>
            <div class="panneau">
                <p style="color: #9ca3af; text-align: center; padding: 40px 0;">
                    <?php if ($idOffreFiltre !== ''): ?>
                        Aucune candidature pour ce poste.
                    <?php else: ?>
                        Aucune candidature reçue pour le moment.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="panneau" style="padding: 0;">
                <table class="tableau-donnees" style="margin: 0;">
                    <thead style="background: #f9fafb;">
                        <tr>
                            <th style="padding: 16px; text-align: left;">Candidat</th>
                            <th style="padding: 16px; text-align: left;">Poste</th>
                            <th style="padding: 16px; text-align: center;">Score IA</th>
                            <th style="padding: 16px; text-align: left;">Email</th>
                            <th style="padding: 16px; text-align: left;">Téléphone</th>
                            <th style="padding: 16px; text-align: left;">Date</th>
                            <th style="padding: 16px; text-align: center;">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $offreActuelle = '';
                        foreach ($candidatures as $cand): 
                            if ($offreActuelle !== $cand['titre_offre'] && count($offres) > 1):
                                $offreActuelle = $cand['titre_offre'];
                        ?>
                            <tr style="background: #f3f4f6;">
                                <td colspan="7" style="padding: 12px 16px; font-weight: 600; color: #4a5bd4; border-bottom: 2px solid #e5e7eb;">
                                    📌 <?= htmlspecialchars($offreActuelle ?: 'Offre supprimée') ?>
                                </td>
                            </tr>
                        <?php endif; 
                            
                            $couleurStatut = $cand['statut'] === 'En attente' ? '#f59e0b' : ($cand['statut'] === 'Accepté' ? '#22c55e' : '#ef4444');
                            $bgStatut = $cand['statut'] === 'En attente' ? '#fef3c7' : ($cand['statut'] === 'Accepté' ? '#dcfce7' : '#fee2e2');
                            
                            // Affichage du Score IA
                            $score = $cand['score_ia'];
                            if ($score !== null && $score !== '') {
                                $score = (float)$score;
                                if ($score >= 75) { $bgScore = '#dcfce7'; $colorScore = '#166534'; $icon = '🏆'; }
                                elseif ($score >= 50) { $bgScore = '#fef3c7'; $colorScore = '#92400e'; $icon = ''; }
                                else { $bgScore = '#fee2e2'; $colorScore = '#991b1b'; $icon = '👎'; }
                                $affichageScore = "<span style='background: $bgScore; color: $colorScore; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 13px;'>$icon " . round($score) . "/100</span>";
                            } else {
                                $affichageScore = "<span style='color: #9ca3af; font-size: 13px;'>En attente</span>";
                            }
                        ?>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 16px;">
                                    <strong style="color: #1f2937;"><?= htmlspecialchars($cand['prenom'] . ' ' . $cand['nom']) ?></strong>
                                </td>
                                <td style="padding: 16px; color: #6b7280;"><?= htmlspecialchars($cand['titre_offre'] ?: 'Offre supprimée') ?></td>
                                <td style="padding: 16px; text-align: center;">
                                    <?= $affichageScore ?>
                                </td>
                                <td style="padding: 16px; color: #6b7280;"><?= htmlspecialchars($cand['email']) ?></td>
                                <td style="padding: 16px; color: #6b7280;"><?= htmlspecialchars($cand['telephone']) ?></td>
                                <td style="padding: 16px; color: #6b7280;"><?= date('d/m/Y', strtotime($cand['date_soumission'])) ?></td>
                                <td style="padding: 16px; text-align: center;">
                                    <span style="background: <?= $bgStatut ?>; color: <?= $couleurStatut ?>; padding: 6px 12px; border-radius: 12px; font-size: 13px; font-weight: 600;">
                                        <?= $cand['statut'] ?>
                                    </span>
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