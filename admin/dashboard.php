<?php
require_once '../includes/auth.php';
verifierRole('Administrateur RH'); // bloque l'accès si pas admin connecté

require_once '../config/db.php';

// 1. Nombre d'employés actifs
$stmt = $pdo->query("SELECT COUNT(*) FROM Employe WHERE statut = 'Actif'");
$nbEmployes = $stmt->fetchColumn();

// 2. Score moyen de l'entreprise (basé sur la dernière évaluation de chaque employé)
$stmt = $pdo->query("
    SELECT AVG(t.score_final) as moyenne
    FROM Evaluation t
    INNER JOIN (
        SELECT id_employe, MAX(date_evaluation) AS derniere_date
        FROM Evaluation
        GROUP BY id_employe
    ) dernier ON t.id_employe = dernier.id_employe AND t.date_evaluation = dernier.derniere_date
");
$scoreMoyen = $stmt->fetchColumn();
$scoreMoyen = $scoreMoyen ? round($scoreMoyen) : 0;

// 3. Nombre de demandes en attente
$stmt = $pdo->query("SELECT COUNT(*) FROM Demande WHERE statut = 'En attente'");
$nbDemandes = $stmt->fetchColumn();

// 4. Derniers scores (les 5 évaluations les plus récentes)
$stmt = $pdo->query("
    SELECT u.nom, u.prenom, e.poste, ev.score_final, ev.date_evaluation
    FROM Evaluation ev
    INNER JOIN Employe e ON ev.id_employe = e.id_employe
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    ORDER BY ev.date_evaluation DESC
    LIMIT 5
");
$derniersScores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Demandes en attente (les 5 plus récentes)
$stmt = $pdo->query("
    SELECT u.nom, u.prenom, d.type, d.date_debut, d.date_fin
    FROM Demande d
    INNER JOIN Employe e ON d.id_employe = e.id_employe
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    WHERE d.statut = 'En attente'
    ORDER BY d.date_demande DESC
    LIMIT 5
");
$demandesEnAttente = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de bord — Administrateur RH</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">

    <!-- Menu latéral -->
    <div class="menu-lateral">
        <div class="logo-menu">
            <div class="icone-logo-menu">RH</div>
            <span>Système RH</span>
        </div>

        <a href="dashboard.php" class="lien-menu actif">Tableau de bord</a>
        <a href="employes.php" class="lien-menu">Employés</a>
        <a href="mon_equipe.php" class="lien-menu">Mon équipe</a>
        <a href="evaluations.php" class="lien-menu">Évaluations</a>
        <a href="demandes.php" class="lien-menu">Demandes</a>
        <a href="criteres.php" class="lien-menu">Critères</a>

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
                <p class="sous-titre-page">Espace Administrateur RH — <?= htmlspecialchars($_SESSION['prenom']) ?></p>
            </div>
            <a href="ajouter_employe.php" class="bouton-principal-inline">+ Ajouter un employé</a>
        </div>

        <div class="grille-cartes">
            <div class="carte-stat">
                <p class="label-stat">Employés actifs</p>
                <p class="valeur-stat"><?= $nbEmployes ?></p>
            </div>
            <div class="carte-stat">
                <p class="label-stat">Score moyen</p>
                <p class="valeur-stat"><?= $scoreMoyen ?>%</p>
            </div>
            <div class="carte-stat">
                <p class="label-stat">Demandes en attente</p>
                <p class="valeur-stat"><?= $nbDemandes ?></p>
            </div>
        </div>

        <div class="grille-panneaux">
            <div class="panneau">
                <p class="titre-panneau">Derniers scores de performance</p>
                <table class="tableau-donnees">
                    <tr><th>Employé</th><th>Poste</th><th>Score</th></tr>
                    <?php if (empty($derniersScores)): ?>
                        <tr><td colspan="3" class="texte-vide">Aucune évaluation enregistrée pour le moment</td></tr>
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
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

</body>
</html>