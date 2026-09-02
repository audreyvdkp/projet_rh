<?php
require_once '../includes/auth.php';
verifierRole('Manager'); // Bloque l'accès si pas Manager connecté

require_once '../config/db.php';

// 1. Récupérer l'id_employe du manager connecté
$stmt = $pdo->prepare("SELECT id_employe FROM Employe WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['id_utilisateur']]);
$idEmployeManager = $stmt->fetchColumn();

if (!$idEmployeManager) {
    die("Erreur : Aucun profil employé trouvé pour ce compte manager.");
}

// 2. Nombre d'employés actifs dans l'équipe
$stmt = $pdo->prepare("SELECT COUNT(*) FROM Employe WHERE manager_referent = ? AND statut = 'Actif'");
$stmt->execute([$idEmployeManager]);
$nbEmployesEquipe = $stmt->fetchColumn();

// 3. Score moyen de l'équipe (basé sur la dernière évaluation de chaque employé)
$stmt = $pdo->prepare("
    SELECT AVG(t.score_final) as moyenne
    FROM Evaluation t
    INNER JOIN (
        SELECT id_employe, MAX(date_evaluation) AS derniere_date
        FROM Evaluation
        GROUP BY id_employe
    ) dernier ON t.id_employe = dernier.id_employe AND t.date_evaluation = dernier.derniere_date
    INNER JOIN Employe e ON t.id_employe = e.id_employe
    WHERE e.manager_referent = ?
");
$stmt->execute([$idEmployeManager]);
$scoreMoyenEquipe = $stmt->fetchColumn();
$scoreMoyenEquipe = $scoreMoyenEquipe ? round($scoreMoyenEquipe) : 0;

// 4. Nombre de demandes en attente pour l'équipe
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM Demande d 
    INNER JOIN Employe e ON d.id_employe = e.id_employe 
    WHERE e.manager_referent = ? AND d.statut = 'En attente'
");
$stmt->execute([$idEmployeManager]);
$nbDemandesAttente = $stmt->fetchColumn();

// 5. Derniers scores de l'équipe (les 5 évaluations les plus récentes)
$stmt = $pdo->prepare("
    SELECT u.nom, u.prenom, e.poste, ev.score_final, ev.date_evaluation
    FROM Evaluation ev
    INNER JOIN Employe e ON ev.id_employe = e.id_employe
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    WHERE e.manager_referent = ?
    ORDER BY ev.date_evaluation DESC
    LIMIT 5
");
$stmt->execute([$idEmployeManager]);
$derniersScores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Demandes en attente de validation (les 5 plus récentes)
$stmt = $pdo->prepare("
    SELECT u.nom, u.prenom, d.type, d.date_debut, d.date_fin, d.id_demande
    FROM Demande d
    INNER JOIN Employe e ON d.id_employe = e.id_employe
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    WHERE e.manager_referent = ? AND d.statut = 'En attente'
    ORDER BY d.date_demande DESC
    LIMIT 5
");
$stmt->execute([$idEmployeManager]);
$demandesEnAttente = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de bord — Manager</title>
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

        <a href="dashboard.php" class="lien-menu actif">Tableau de bord</a>
        <a href="equipe.php" class="lien-menu">Mon équipe</a>
        <a href="tout_evaluer.php" class="lien-menu">Tout évaluer</a>
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
                <p class="titre-page">Tableau de bord</p>
                <p class="sous-titre-page">Espace Manager — <?= htmlspecialchars($_SESSION['prenom']) ?></p>
            </div>
            <a href="objectifs.php" class="bouton-principal-inline">+ Fixer un objectif</a>
        </div>

        <!-- Cartes de statistiques -->
        <div class="grille-cartes">
            <div class="carte-stat">
                <p class="label-stat">Employés dans l'équipe</p>
                <p class="valeur-stat"><?= $nbEmployesEquipe ?></p>
            </div>
            <div class="carte-stat">
                <p class="label-stat">Score moyen équipe</p>
                <p class="valeur-stat"><?= $scoreMoyenEquipe ?>%</p>
            </div>
            <div class="carte-stat">
                <p class="label-stat">Demandes à valider</p>
                <p class="valeur-stat"><?= $nbDemandesAttente ?></p>
            </div>
        </div>

        <!-- Panneaux -->
        <div class="grille-panneaux">
            
            <!-- Panneau 1 : Derniers scores -->
            <div class="panneau">
                <p class="titre-panneau">Dernières évaluations de l'équipe</p>
                <table class="tableau-donnees">
                    <tr><th>Employé</th><th>Poste</th><th>Score</th></tr>
                    <?php if (empty($derniersScores)): ?>
                        <tr><td colspan="3" class="texte-vide">Aucune évaluation pour le moment</td></tr>
                    <?php else: ?>
                        <?php foreach ($derniersScores as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['prenom'] . ' ' . $s['nom']) ?></td>
                                <td><?= htmlspecialchars($s['poste']) ?></td>
                                <td class="score-positif"><?= $s['score_final'] ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Panneau 2 : Demandes en attente -->
            <div class="panneau">
                <p class="titre-panneau">Demandes en attente</p>
                <?php if (empty($demandesEnAttente)): ?>
                    <p class="texte-vide">Aucune demande en attente</p>
                <?php else: ?>
                    <?php foreach ($demandesEnAttente as $d): ?>
                        <div class="ligne-demande">
                            <div>
                                <p class="nom-demande"><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></p>
                                <p class="detail-demande"><?= htmlspecialchars($d['type']) ?> · <?= date('d/m', strtotime($d['date_debut'])) ?> - <?= date('d/m', strtotime($d['date_fin'])) ?></p>
                            </div>
                            <span class="icone-alarme">⏰</span>
                        </div>
                    <?php endforeach; ?>
                    <div style="margin-top: 12px; text-align: right;">
                        <a href="demandes.php" class="lien-action">Voir toutes les demandes →</a>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>

</div>

</body>
</html>