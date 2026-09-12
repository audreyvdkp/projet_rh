<?php
require_once '../includes/auth.php';
verifierRole('Manager');
require_once '../config/db.php';

// Récupérer l'id_employe du Manager connecté
$stmt = $pdo->prepare("SELECT id_employe FROM Employe WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['id_utilisateur']]);
$idEmployeManager = $stmt->fetchColumn();

$message = "";
$typeMessage = "";

// Traitement des actions (accepter/refuser)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_demande'])) {
    $idDemande = (int)$_POST['id_demande'];
    $action = $_POST['action_demande'];
    
    if ($action === 'accepter' || $action === 'refuser') {
        $nouveauStatut = $action === 'accepter' ? 'Accepté' : 'Refusé';
        
        // Vérifier que la demande appartient bien à un employé de l'équipe
        $stmt = $pdo->prepare("
            UPDATE Demande d 
            INNER JOIN Employe e ON d.id_employe = e.id_employe 
            SET d.statut = ?, d.id_validateur = ?
            WHERE d.id_demande = ? AND e.manager_referent = ? AND d.statut = 'En attente'
        ");
        $stmt->execute([$nouveauStatut, $_SESSION['id_utilisateur'], $idDemande, $idEmployeManager]);
        
        if ($stmt->rowCount() > 0) {
            $message = "Demande " . ($action === 'accepter' ? 'acceptée' : 'refusée') . " avec succès.";
            $typeMessage = "succes";
        }
    }
}

// Filtres
$statutFiltre = $_GET['statut'] ?? 'En attente'; // Par défaut, on voit les demandes en attente
$recherche = $_GET['recherche'] ?? '';

// Récupération des demandes de l'équipe
$sql = "
    SELECT d.id_demande, d.type, d.date_debut, d.date_fin, d.date_demande, d.statut, d.motif,
           u.nom, u.prenom, e.poste
    FROM Demande d
    INNER JOIN Employe e ON d.id_employe = e.id_employe
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    WHERE e.manager_referent = ?
";
$params = [$idEmployeManager];

if ($statutFiltre !== '') {
    $sql .= " AND d.statut = ?";
    $params[] = $statutFiltre;
}

if ($recherche !== '') {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ?)";
    $rechercheLike = '%' . $recherche . '%';
    $params[] = $rechercheLike;
    $params[] = $rechercheLike;
}

$sql .= " ORDER BY d.date_demande DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compteur par statut
$stmtCompteur = $pdo->prepare("
    SELECT d.statut, COUNT(*) as nb
    FROM Demande d
    INNER JOIN Employe e ON d.id_employe = e.id_employe
    WHERE e.manager_referent = ?
    GROUP BY d.statut
");
$stmtCompteur->execute([$idEmployeManager]);
$compteurs = $stmtCompteur->fetchAll(PDO::FETCH_ASSOC);
$compteursParStatut = [];
foreach ($compteurs as $c) {
    $compteursParStatut[$c['statut']] = $c['nb'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demandes de l'équipe — Manager</title>
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
        <a href="equipe.php" class="lien-menu">Mon équipe</a>
        <a href="objectifs.php" class="lien-menu">Objectifs</a>
        <a href="demandes.php" class="lien-menu actif">Demandes de mon équipe</a>

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
                <p class="titre-page">Demandes de l'équipe</p>
                <p class="sous-titre-page">Gérez les congés et permissions de vos collaborateurs</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message-<?= $typeMessage === 'erreur' ? 'erreur' : 'succes' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- Onglets de filtrage -->
        <div class="filtres-demandes" style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
            <?php 
            $statuts = ['En attente', 'Accepté', 'Refusé'];
            foreach ($statuts as $s): 
                $nb = $compteursParStatut[$s] ?? 0;
                $classeActif = $statutFiltre === $s ? 'filtre-actif' : '';
                $couleurBadge = $s === 'En attente' ? '#fbbf24' : ($s === 'Accepté' ? '#22c55e' : '#ef4444');
            ?>
                <a href="?statut=<?= $s ?>&recherche=<?= htmlspecialchars($recherche) ?>" 
                   class="filtre-badge <?= $classeActif ?>" 
                   style="padding: 8px 16px; border-radius: 20px; background: <?= $statutFiltre === $s ? $couleurBadge : '#f3f4f6' ?>; 
                          color: <?= $statutFiltre === $s ? '#fff' : '#4b5563' ?>; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px;">
                    <?= $s ?>
                    <?php if ($nb > 0): ?>
                        <span style="background: rgba(255,255,255,0.3); padding: 2px 8px; border-radius: 10px; font-size: 12px;"><?= $nb ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Barre de recherche -->
        <div class="barre-recherche-equipe" style="margin-bottom: 20px;">
            <form method="GET" action="" style="display: flex; gap: 12px;">
                <input type="hidden" name="statut" value="<?= htmlspecialchars($statutFiltre) ?>">
                <input type="text" name="recherche" placeholder="Rechercher un employé..." value="<?= htmlspecialchars($recherche) ?>" style="flex: 1; padding: 10px 14px; border: 1px solid #e0e0e0; border-radius: 8px;">
                <button type="submit" class="bouton-principal" style="padding: 10px 20px;">Rechercher</button>
                <?php if ($recherche !== ''): ?>
                    <a href="?statut=<?= htmlspecialchars($statutFiltre) ?>" class="bouton-secondaire" style="padding: 10px 20px;">Réinitialiser</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Liste des demandes -->
        <?php if (empty($demandes)): ?>
            <div class="panneau">
                <p class="texte-vide">
                    <?php if ($statutFiltre === 'En attente'): ?>
                        Aucune demande en attente de validation. 🎉
                    <?php else: ?>
                        Aucune demande <?= strtolower(htmlspecialchars($statutFiltre)) ?>.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="liste-demandes">
                <?php foreach ($demandes as $demande): ?>
                    <div class="demande-item" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 12px;">
                        <div class="demande-info" style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                <div class="avatar-mini" style="width: 36px; height: 36px; background: #4a5bd4; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px;">
                                    <?= strtoupper(substr($demande['prenom'], 0, 1) . substr($demande['nom'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="demande-nom" style="font-weight: 600; margin: 0; font-size: 15px;">
                                        <?= htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']) ?>
                                    </p>
                                    <p style="font-size: 13px; color: #6b7280; margin: 0;"><?= htmlspecialchars($demande['poste']) ?></p>
                                </div>
                            </div>
                            
                            <div style="margin-left: 48px; font-size: 14px;">
                                <p style="margin: 4px 0; color: #374151;">
                                    <strong><?= htmlspecialchars($demande['type']) ?></strong> 
                                    · Du <?= date('d/m/Y', strtotime($demande['date_debut'])) ?> 
                                    au <?= date('d/m/Y', strtotime($demande['date_fin'])) ?>
                                </p>
                                <?php if (!empty($demande['motif'])): ?>
                                    <p style="margin: 4px 0; color: #6b7280; font-size: 13px; font-style: italic;">
                                        "<?= htmlspecialchars($demande['motif']) ?>"
                                    </p>
                                <?php endif; ?>
                                <p style="margin: 4px 0; font-size: 12px; color: #9ca3af;">
                                    Demandé le <?= date('d/m/Y à H:i', strtotime($demande['date_demande'])) ?>
                                </p>
                            </div>
                        </div>

                        <div class="demande-actions" style="display: flex; gap: 8px; align-items: center;">
                            <?php if ($demande['statut'] === 'En attente'): ?>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="id_demande" value="<?= $demande['id_demande'] ?>">
                                    <input type="hidden" name="action_demande" value="accepter">
                                    <button type="submit" class="btn-action btn-accepter" title="Accepter" 
                                            style="background: #22c55e; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                                        ✓ Accepter
                                    </button>
                                </form>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="id_demande" value="<?= $demande['id_demande'] ?>">
                                    <input type="hidden" name="action_demande" value="refuser">
                                    <button type="submit" class="btn-action btn-refuser" title="Refuser" 
                                            style="background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                                        ✗ Refuser
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge-statut" style="padding: 6px 12px; border-radius: 12px; font-size: 13px; font-weight: 500; 
                                       background: <?= $demande['statut'] === 'Accepté' ? '#dcfce7' : ($demande['statut'] === 'Refusé' ? '#fee2e2' : '#f3f4f6') ?>;
                                       color: <?= $demande['statut'] === 'Accepté' ? '#166534' : ($demande['statut'] === 'Refusé' ? '#991b1b' : '#4b5563') ?>;">
                                    <?= htmlspecialchars($demande['statut']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

</div>

<script src="../assets/js/app.js" defer></script>
</body>
</html>