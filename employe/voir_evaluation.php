<?php
require_once '../includes/auth.php';
verifierRole('Employé');
require_once '../config/db.php';

$stmt = $pdo->prepare("SELECT id_employe FROM Employe WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['id_utilisateur']]);
$idEmploye = $stmt->fetchColumn();

$idEvaluation = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$idEvaluation) { header("Location: evaluations.php"); exit; }

// l'employé ne peut voir que ses propres évaluations
$stmt = $pdo->prepare("
    SELECT ev.*, u.nom, u.prenom, e.poste, e.service,
           evu.nom as nom_eval, evu.prenom as prenom_eval
    FROM evaluation ev
    INNER JOIN Employe e ON ev.id_employe = e.id_employe
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    INNER JOIN Employe ev_emp ON ev.id_evaluateur = ev_emp.id_employe
    INNER JOIN Utilisateur evu ON ev_emp.id_utilisateur = evu.id_utilisateur
    WHERE ev.id_evaluation = ? AND ev.id_employe = ?
");
$stmt->execute([$idEvaluation, $idEmploye]);
$evaluation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evaluation) { header("Location: evaluations.php"); exit; }

$stmtDetail = $pdo->prepare("
    SELECT dc.note_obtenue, dc.note_maximale, dc.commentaire, c.nom_critere, c.poids
    FROM detail_evaluation dc
    INNER JOIN critere_evaluation c ON dc.id_critere = c.id_critere
    WHERE dc.id_evaluation = ?
    ORDER BY c.nom_critere
");
$stmtDetail->execute([$idEvaluation]);
$details = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail de l'évaluation</title>
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
            <a href="../logout.php" class="icone-deconnexion"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg></a>
        </div>
    </div>
    <div class="zone-contenu">
        <div class="entete-page">
            <div>
                <p class="titre-page">Détail de mon évaluation</p>
                <p class="sous-titre-page"><?= htmlspecialchars($evaluation['prenom'] . ' ' . $evaluation['nom']) ?> — <?= htmlspecialchars($evaluation['poste']) ?></p>
            </div>
            <a href="evaluations.php" class="bouton-secondaire">← Retour</a>
        </div>

        <div class="panneau" style="margin-bottom: 20px; text-align: center; padding: 30px;">
            <p style="font-size: 14px; color: #6b7280; margin-bottom: 12px;">Score Final</p>
            <div style="font-size: 48px; font-weight: 700; color: <?= $evaluation['score_final'] >= 75 ? '#16a34a' : ($evaluation['score_final'] >= 50 ? '#ca8a04' : '#dc2626') ?>;">
                <?= number_format($evaluation['score_final'], 1) ?>/100
            </div>
            <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-weight: 600; margin-top: 12px; background: <?= $evaluation['score_final'] >= 75 ? '#dcfce7' : ($evaluation['score_final'] >= 50 ? '#fef9c3' : '#fee2e2') ?>; color: <?= $evaluation['score_final'] >= 75 ? '#166534' : ($evaluation['score_final'] >= 50 ? '#854d0e' : '#991b1b') ?>;">
                <?= htmlspecialchars($evaluation['mention']) ?>
            </span>
            <p style="margin-top: 16px; font-size: 14px; color: #6b7280;">
                Évalué par <?= htmlspecialchars($evaluation['prenom_eval'] . ' ' . $evaluation['nom_eval']) ?> le <?= date('d/m/Y', strtotime($evaluation['date_evaluation'])) ?>
            </p>
        </div>

        <div class="panneau">
            <p class="titre-panneau">Détail par critère</p>
            <table class="tableau-donnees">
                <tr><th>Critère</th><th>Poids</th><th>Note max</th><th>Note obtenue</th><th style="text-align: center;">Performance</th></tr>
                <?php foreach ($details as $detail): 
                    $pourcentage = $detail['note_maximale'] > 0 ? ($detail['note_obtenue'] / $detail['note_maximale']) * 100 : 0;
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($detail['nom_critere']) ?></strong></td>
                        <td><strong><?= number_format($detail['poids'], 0) ?>%</strong></td>
                        <td>/<?= $detail['note_maximale'] ?></td>
                        <td><strong><?= number_format($detail['note_obtenue'], 1) ?></strong></td>
                        <td style="text-align: center;">
                            <span style="color: <?= $pourcentage >= 75 ? '#16a34a' : ($pourcentage >= 50 ? '#ca8a04' : '#dc2626') ?>; font-weight: 600;"><?= number_format($pourcentage, 0) ?>%</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>