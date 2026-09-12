<?php
require_once '../includes/auth.php';
verifierRole('Administrateur RH');
require_once '../config/db.php';

$idEvaluation = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$idEvaluation) {
    header("Location: evaluations.php");
    exit;
}

// Récupérer l'évaluation avec les infos de l'employé
$stmt = $pdo->prepare("
    SELECT ev.*, u.nom, u.prenom, u.email, e.poste, e.service,
           evu.nom as nom_evaluateur, evu.prenom as prenom_evaluateur
    FROM evaluation ev
    INNER JOIN Employe e ON ev.id_employe = e.id_employe
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    INNER JOIN Employe ev_emp ON ev.id_evaluateur = ev_emp.id_employe
    INNER JOIN Utilisateur evu ON ev_emp.id_utilisateur = evu.id_utilisateur
    WHERE ev.id_evaluation = ?
");
$stmt->execute([$idEvaluation]);
$evaluation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evaluation) {
    header("Location: evaluations.php");
    exit;
}

// Récupérer le détail par critère
$stmtDetail = $pdo->prepare("
    SELECT dc.note_obtenue, dc.note_maximale, dc.commentaire,
           c.nom_critere, c.description, c.poids
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
    <title>Détail de l'évaluation — <?= htmlspecialchars($evaluation['prenom'] . ' ' . $evaluation['nom']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">
    <div class="menu-lateral">
        <div class="logo-menu"><div class="icone-logo-menu">RH</div><span>Système RH</span></div>
        <a href="dashboard.php" class="lien-menu">Tableau de bord</a>
        <a href="employes.php" class="lien-menu">Employés</a>
        <a href="evaluations.php" class="lien-menu actif">Évaluations</a>
        <a href="demandes.php" class="lien-menu">Demandes</a>
        <a href="criteres.php" class="lien-menu">Critères</a>
        <a href="mon_equipe.php" class="lien-menu">Mon équipe</a>
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
                <p class="titre-page">Détail de l'évaluation</p>
                <p class="sous-titre-page"><?= htmlspecialchars($evaluation['prenom'] . ' ' . $evaluation['nom']) ?> — <?= htmlspecialchars($evaluation['poste']) ?></p>
            </div>
            <a href="evaluations.php" class="bouton-secondaire">← Retour</a>
        </div>

        <!-- En-tête de l'évaluation -->
        <div class="panneau" style="margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div>
                    <p style="font-size: 13px; color: #6b7280; margin-bottom: 4px;">Période évaluée</p>
                    <p style="font-weight: 600;"><?= date('d/m/Y', strtotime($evaluation['periode_debut'])) ?> au <?= date('d/m/Y', strtotime($evaluation['periode_fin'])) ?></p>
                </div>
                <div>
                    <p style="font-size: 13px; color: #6b7280; margin-bottom: 4px;">Date d'évaluation</p>
                    <p style="font-weight: 600;"><?= date('d/m/Y', strtotime($evaluation['date_evaluation'])) ?></p>
                </div>
                <div>
                    <p style="font-size: 13px; color: #6b7280; margin-bottom: 4px;">Évaluateur</p>
                    <p style="font-weight: 600;"><?= htmlspecialchars($evaluation['prenom_evaluateur'] . ' ' . $evaluation['nom_evaluateur']) ?></p>
                </div>
                <div>
                    <p style="font-size: 13px; color: #6b7280; margin-bottom: 4px;">Statut</p>
                    <span class="badge-statut" style="padding: 4px 12px; border-radius: 12px; font-size: 13px; background: <?= $evaluation['statut'] === 'En cours' ? '#dbeafe' : '#dcfce7' ?>; color: <?= $evaluation['statut'] === 'En cours' ? '#1e40af' : '#166534' ?>;">
                        <?= htmlspecialchars($evaluation['statut']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Score final -->
        <div class="panneau" style="margin-bottom: 20px; text-align: center; padding: 30px;">
            <p style="font-size: 14px; color: #6b7280; margin-bottom: 12px;">Score Final</p>
            <div style="font-size: 48px; font-weight: 700; color: <?= $evaluation['score_final'] >= 75 ? '#16a34a' : ($evaluation['score_final'] >= 50 ? '#ca8a04' : '#dc2626') ?>;">
                <?= number_format($evaluation['score_final'], 1) ?>/100
            </div>
            <span class="badge-mention" style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-weight: 600; margin-top: 12px; background: <?= $evaluation['score_final'] >= 75 ? '#dcfce7' : ($evaluation['score_final'] >= 50 ? '#fef9c3' : '#fee2e2') ?>; color: <?= $evaluation['score_final'] >= 75 ? '#166534' : ($evaluation['score_final'] >= 50 ? '#854d0e' : '#991b1b') ?>;">
                <?= htmlspecialchars($evaluation['mention']) ?>
            </span>
        </div>

        <!-- Détail par critère -->
        <div class="panneau" style="margin-bottom: 20px;">
            <p class="titre-panneau">Détail par critère</p>
            <table class="tableau-donnees">
                <tr>
                    <th>Critère</th>
                    <th>Poids</th>
                    <th>Note max</th>
                    <th>Note obtenue</th>
                    <th style="text-align: center;">Performance</th>
                </tr>
                <?php foreach ($details as $detail): 
                    $pourcentage = $detail['note_maximale'] > 0 ? ($detail['note_obtenue'] / $detail['note_maximale']) * 100 : 0;
                ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($detail['nom_critere']) ?></strong>
                            <?php if (!empty($detail['description'])): ?>
                                <br><small style="color: #6b7280; font-size: 12px;"><?= htmlspecialchars($detail['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= number_format($detail['poids'], 0) ?>%</strong></td>
                        <td>/<?= $detail['note_maximale'] ?></td>
                        <td><strong><?= number_format($detail['note_obtenue'], 1) ?></strong></td>
                        <td style="text-align: center;">
                            <span style="color: <?= $pourcentage >= 75 ? '#16a34a' : ($pourcentage >= 50 ? '#ca8a04' : '#dc2626') ?>; font-weight: 600;">
                                <?= number_format($pourcentage, 0) ?>%
                            </span>
                            <div style="width: 100%; height: 6px; background: #e5e7eb; border-radius: 3px; margin-top: 6px;">
                                <div style="width: <?= min($pourcentage, 100) ?>%; height: 100%; background: <?= $pourcentage >= 75 ? '#16a34a' : ($pourcentage >= 50 ? '#ca8a04' : '#dc2626') ?>; border-radius: 3px;"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Commentaire global -->
        <?php if (!empty($evaluation['commentaire'])): ?>
            <div class="panneau">
                <p class="titre-panneau">Commentaire global</p>
                <p style="line-height: 1.6; color: #374151;"><?= nl2br(htmlspecialchars($evaluation['commentaire'])) ?></p>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="../assets/js/app.js" defer></script>
</body>
</html>