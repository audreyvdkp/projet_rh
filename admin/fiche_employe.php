<?php
require_once '../includes/auth.php';
verifierRole('Administrateur RH');
require_once '../config/db.php';

$idEmploye = $_GET['id'] ?? null;
if (!$idEmploye) {
    header("Location: employes.php");
    exit;
}

// 1. Infos employé + manager référent
$stmt = $pdo->prepare("
    SELECT e.*, u.nom, u.prenom, u.email,
           um.nom AS manager_nom, um.prenom AS manager_prenom
    FROM Employe e
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    LEFT JOIN Employe em ON e.manager_referent = em.id_employe
    LEFT JOIN Utilisateur um ON em.id_utilisateur = um.id_utilisateur
    WHERE e.id_employe = ?
");
$stmt->execute([$idEmploye]);
$employe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employe) {
    header("Location: employes.php");
    exit;
}

// 2. Historique des évaluations
$stmtEval = $pdo->prepare("SELECT date_evaluation, score_final, mention FROM Evaluation WHERE id_employe = ? ORDER BY date_evaluation DESC");
$stmtEval->execute([$idEmploye]);
$evaluations = $stmtEval->fetchAll(PDO::FETCH_ASSOC);

// 3. Objectifs en cours
$stmtObj = $pdo->prepare("SELECT titre, date_fin, statut FROM Objectif WHERE id_employe = ? AND statut = 'En cours' ORDER BY date_fin ASC");
$stmtObj->execute([$idEmploye]);
$objectifs = $stmtObj->fetchAll(PDO::FETCH_ASSOC);

// 4. Dernières demandes
$stmtDem = $pdo->prepare("SELECT type, date_debut, date_fin, statut, date_demande FROM Demande WHERE id_employe = ? ORDER BY date_demande DESC LIMIT 5");
$stmtDem->execute([$idEmploye]);
$demandes = $stmtDem->fetchAll(PDO::FETCH_ASSOC);

$nomComplet = htmlspecialchars($employe['prenom'] . ' ' . $employe['nom']);
$nomManager = $employe['manager_nom'] ? htmlspecialchars($employe['manager_prenom'] . ' ' . $employe['manager_nom']) : 'Non assigné';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche Employé — <?= $nomComplet ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">

    <!-- Menu latéral (EXACTEMENT comme dashboard.php) -->
    <div class="menu-lateral">
        <div class="logo-menu">
            <div class="icone-logo-menu">RH</div>
            <span>Système RH</span>
        </div>

        <a href="dashboard.php" class="lien-menu">Tableau de bord</a>
        <a href="employes.php" class="lien-menu actif">Employés</a>
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

    <!-- Zone de contenu (Utilise ta classe .zone-contenu existante) -->
    <div class="zone-contenu">

        <!-- En-tête de page -->
        <div class="entete-page">
            <div>
                <p class="titre-page"><?= $nomComplet ?></p>
                <p class="sous-titre-page"><?= htmlspecialchars($employe['poste']) ?> · <?= htmlspecialchars($employe['service']) ?></p>
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="employes.php" class="bouton-secondaire">← Retour</a>
                <a href="ajouter_employe.php?id=<?= $employe['id_employe'] ?>" class="bouton-secondaire">Modifier</a>
                <a href="evaluer.php?id=<?= $employe['id_employe'] ?>" class="bouton-principal-inline">Évaluer</a>
            </div>
        </div>

        <!-- 4 Cartes d'information (Réutilise .grille-cartes et .carte-stat) -->
        <div class="grille-cartes" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 1.5rem;">
            <div class="carte-stat">
                <p class="label-stat">Type de contrat</p>
                <p class="valeur-stat" style="font-size: 16px;"><?= htmlspecialchars($employe['type_contrat']) ?></p>
            </div>
            <div class="carte-stat">
                <p class="label-stat">Date d'embauche</p>
                <p class="valeur-stat" style="font-size: 16px;"><?= date('d/m/Y', strtotime($employe['date_embauche'])) ?></p>
            </div>
            <div class="carte-stat">
                <p class="label-stat">Manager référent</p>
                <p class="valeur-stat" style="font-size: 16px;"><?= $nomManager ?></p>
            </div>
            <div class="carte-stat">
                <p class="label-stat">Statut du compte</p>
                <p class="valeur-stat" style="font-size: 16px;">
                    <?php if ($employe['statut'] === 'Actif'): ?>
                        <span class="badge-statut badge-succes">Actif</span>
                    <?php else: ?>
                        <span class="badge-statut badge-neutre">Inactif</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Section 1 : Historique des évaluations -->
        <div class="panneau" style="margin-bottom: 1.5rem;">
            <p class="titre-panneau">Historique des évaluations</p>
            <?php if (empty($evaluations)): ?>
                <p class="texte-vide">Aucune évaluation enregistrée pour le moment.</p>
            <?php else: ?>
                <table class="tableau-donnees">
                    <tr><th>Date</th><th>Score final</th><th>Mention</th></tr>
                    <?php foreach ($evaluations as $eval): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($eval['date_evaluation'])) ?></td>
                            <td class="score-positif"><?= number_format($eval['score_final'], 1) ?>%</td>
                            <td><?= htmlspecialchars($eval['mention']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <!-- Section 2 : Objectifs en cours -->
        <div class="panneau" style="margin-bottom: 1.5rem;">
            <p class="titre-panneau">Objectifs en cours</p>
            <?php if (empty($objectifs)): ?>
                <p class="texte-vide">Aucun objectif en cours pour cet employé.</p>
            <?php else: ?>
                <?php foreach ($objectifs as $obj): ?>
                    <div class="ligne-demande">
                        <div>
                            <p class="nom-demande"><?= htmlspecialchars($obj['titre']) ?></p>
                            <p class="detail-demande">Échéance : <?= date('d/m/Y', strtotime($obj['date_fin'])) ?></p>
                        </div>
                        <span class="badge-statut badge-succes">En cours</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Section 3 : Dernières demandes -->
        <div class="panneau">
            <p class="titre-panneau">Dernières demandes</p>
            <?php if (empty($demandes)): ?>
                <p class="texte-vide">Aucune demande récente.</p>
            <?php else: ?>
                <table class="tableau-donnees">
                    <tr><th>Type</th><th>Période</th><th>Date</th><th>Statut</th></tr>
                    <?php foreach ($demandes as $dem): ?>
                        <tr>
                            <td><?= htmlspecialchars($dem['type']) ?></td>
                            <td><?= date('d/m', strtotime($dem['date_debut'])) ?> - <?= date('d/m', strtotime($dem['date_fin'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($dem['date_demande'])) ?></td>
                            <td>
                                <?php
                                $classeBadge = match($dem['statut']) {
                                    'Accepté' => 'badge-succes',
                                    'Refusé' => 'badge-neutre', // Tu peux changer si tu ajoutes badge-danger
                                    'En attente' => 'badge-attention',
                                    default => 'badge-neutre'
                                };
                                ?>
                                <span class="badge-statut <?= $classeBadge ?>"><?= htmlspecialchars($dem['statut']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>