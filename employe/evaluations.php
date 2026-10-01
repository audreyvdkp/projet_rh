<?php
require_once '../includes/auth.php';
verifierRole('Employé');
require_once '../config/db.php';

// Récupérer l'id_employe connecté
$stmt = $pdo->prepare("SELECT id_employe FROM Employe WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['id_utilisateur']]);
$idEmploye = $stmt->fetchColumn();

// Récupérer les évaluations de l'employé
$stmt = $pdo->prepare("
    SELECT ev.id_evaluation, ev.date_evaluation, ev.periode_debut, ev.periode_fin, 
           ev.score_final, ev.mention, ev.statut,
           u.prenom as prenom_eval, u.nom as nom_eval
    FROM evaluation ev
    INNER JOIN Employe ev_emp ON ev.id_evaluateur = ev_emp.id_employe
    INNER JOIN Utilisateur u ON ev_emp.id_utilisateur = u.id_utilisateur
    WHERE ev.id_employe = ?
    ORDER BY ev.date_evaluation DESC
");
$stmt->execute([$idEmploye]);
$evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes évaluations — Espace Employé</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">
    <div class="menu-lateral">
        <div class="logo-menu"><div class="icone-logo-menu">RH</div><span>Système RH</span></div>
        <a href="dashboard.php" class="lien-menu">Tableau de bord</a>
        <a href="objectifs.php" class="lien-menu">Mes objectifs</a>
        <a href="demandes.php" class="lien-menu">Mes demandes</a>
        <a href="evaluations.php" class="lien-menu actif">Mes évaluations</a>
        <a href="profil.php" class="lien-menu">Mon profil</a>
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
                <p class="titre-page">Mes évaluations</p>
                <p class="sous-titre-page">Consultez l'historique de vos évaluations professionnelles</p>
            </div>
        </div>

        <?php if (empty($evaluations)): ?>
            <div class="panneau">
                <p style="color: #9ca3af; text-align: center; padding: 40px 0;">Aucune évaluation n'a encore été réalisée pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="panneau">
                <p class="titre-panneau">Historique</p>
                <?php foreach ($evaluations as $eval): 
                    $couleurScore = $eval['score_final'] >= 75 ? '#22c55e' : ($eval['score_final'] >= 50 ? '#f59e0b' : '#ef4444');
                    $couleurBg = $eval['score_final'] >= 75 ? '#dcfce7' : ($eval['score_final'] >= 50 ? '#fef9c3' : '#fee2e2');
                ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px; background: #fafafa;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                                <span style="font-size: 24px; font-weight: 700; color: <?= $couleurScore ?>;"><?= number_format($eval['score_final'], 1) ?>%</span>
                                <span style="background: <?= $couleurBg ?>; color: <?= $couleurScore ?>; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                    <?= htmlspecialchars($eval['mention']) ?>
                                </span>
                            </div>
                            <p style="font-size: 13px; color: #6b7280; margin: 0;">
                                Période : <?= date('d/m/Y', strtotime($eval['periode_debut'])) ?> au <?= date('d/m/Y', strtotime($eval['periode_fin'])) ?>
                                <br>Évalué par : <?= htmlspecialchars($eval['prenom_eval'] . ' ' . $eval['nom_eval']) ?> le <?= date('d/m/Y', strtotime($eval['date_evaluation'])) ?>
                            </p>
                        </div>
                        <a href="voir_evaluation.php?id=<?= $eval['id_evaluation'] ?>" class="bouton-secondaire" style="padding: 8px 16px; text-decoration: none; font-size: 13px;">
                            Voir le détail →
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>