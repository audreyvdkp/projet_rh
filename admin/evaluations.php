<?php
require_once '../includes/auth.php';
verifierRole('Administrateur RH');
require_once '../config/db.php';

// Récupérer l'id_employe de l'Admin connecté
$stmt = $pdo->prepare("SELECT id_employe FROM Employe WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['id_utilisateur']]);
$idEmployeAdmin = $stmt->fetchColumn();

// Filtres de recherche
$recherche = $_GET['recherche'] ?? '';
$statutFiltre = $_GET['statut'] ?? '';

$sql = "
    SELECT ev.id_evaluation, u.nom, u.prenom, e.poste, 
           ev.date_evaluation, ev.periode_debut, ev.periode_fin, 
           ev.score_final, ev.mention, ev.statut
    FROM evaluation ev
    INNER JOIN Employe e ON ev.id_employe = e.id_employe
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    WHERE ev.id_evaluateur = ?
";
$params = [$idEmployeAdmin];

if ($recherche !== '') {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ?)";
    $rechercheLike = '%' . $recherche . '%';
    $params[] = $rechercheLike;
    $params[] = $rechercheLike;
}

if ($statutFiltre !== '') {
    $sql .= " AND ev.statut = ?";
    $params[] = $statutFiltre;
}

$sql .= " ORDER BY ev.date_evaluation DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Évaluations — Administrateur RH</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">

    <!-- Menu latéral Admin -->
    <div class="menu-lateral">
        <div class="logo-menu">
            <div class="icone-logo-menu">RH</div>
            <span>Système RH</span>
        </div>
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
                <p class="titre-page">Historique des Évaluations</p>
                <p class="sous-titre-page">Consultez les évaluations que vous avez réalisées</p>
            </div>
        </div>

        <!-- Barre de filtres -->
        <div class="barre-recherche-equipe" style="display: flex; gap: 12px; margin-bottom: 20px;">
            <form method="GET" action="" style="flex: 1; display: flex; gap: 12px;">
                <input type="text" name="recherche" placeholder="Rechercher un employé..." value="<?= htmlspecialchars($recherche) ?>" style="flex: 1;">
                <select name="statut" style="padding: 8px 12px; border: 1px solid #e0e0e0; border-radius: 6px;">
                    <option value="">Tous les statuts</option>
                    <option value="En cours" <?= $statutFiltre === 'En cours' ? 'selected' : '' ?>>En cours</option>
                    <option value="Validé" <?= $statutFiltre === 'Validé' ? 'selected' : '' ?>>Validé</option>
                </select>
                <button type="submit" class="bouton-principal" style="padding: 8px 16px;">Filtrer</button>
                <?php if ($recherche !== '' || $statutFiltre !== ''): ?>
                    <a href="evaluations.php" class="bouton-secondaire" style="padding: 8px 16px;">Réinitialiser</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tableau des évaluations -->
        <?php if (empty($evaluations)): ?>
            <div class="panneau">
                <p class="texte-vide">Aucune évaluation trouvée.</p>
            </div>
        <?php else: ?>
            <div class="panneau">
                <table class="tableau-donnees">
                    <tr>
                        <th>Employé</th>
                        <th>Période évaluée</th>
                        <th>Date d'éval.</th>
                        <th>Score</th>
                        <th>Mention</th>
                        <th>Statut</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                    <?php foreach ($evaluations as $ev): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($ev['prenom'] . ' ' . $ev['nom']) ?></strong>
                                <br><span style="font-size: 12px; color: #6b7280;"><?= htmlspecialchars($ev['poste']) ?></span>
                            </td>
                            <td style="font-size: 13px;">
                                <?= date('d/m/Y', strtotime($ev['periode_debut'])) ?>
                                <br>au <?= date('d/m/Y', strtotime($ev['periode_fin'])) ?>
                            </td>
                            <td style="font-size: 13px;"><?= date('d/m/Y', strtotime($ev['date_evaluation'])) ?></td>
                            <td>
                                <strong style="color: <?= $ev['score_final'] >= 75 ? '#16a34a' : ($ev['score_final'] >= 50 ? '#ca8a04' : '#dc2626') ?>">
                                    <?= number_format($ev['score_final'], 1) ?>/100
                                </strong>
                            </td>
                            <td>
                                <span class="badge-mention" style="background: <?= $ev['score_final'] >= 75 ? '#dcfce7' : ($ev['score_final'] >= 50 ? '#fef9c3' : '#fee2e2') ?>; color: <?= $ev['score_final'] >= 75 ? '#166534' : ($ev['score_final'] >= 50 ? '#854d0e' : '#991b1b') ?>; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                    <?= htmlspecialchars($ev['mention']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-statut badge-<?= $ev['statut'] === 'En cours' ? 'info' : 'succes' ?>">
                                    <?= htmlspecialchars($ev['statut']) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="voir_evaluation.php?id=<?= $ev['id_evaluation'] ?>" class="btn-secondaire btn-mini">Voir détail</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>

    </div>

</div>

</body>
</html>