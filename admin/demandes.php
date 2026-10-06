<?php
require_once '../includes/auth.php';
verifierRole('Administrateur RH');
require_once '../config/db.php';

$message = "";
$typeMessage = "";

// Récupérer l'id_employe de l'Admin connecté
$stmt = $pdo->prepare("SELECT id_employe FROM Employe WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['id_utilisateur']]);
$idEmployeAdmin = $stmt->fetchColumn();

// Traitement des actions (accepter/refuser) par l'Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_demande'])) {
    $idDemande = (int)$_POST['id_demande'];
    $action = $_POST['action_demande'];
    
    if ($action === 'accepter' || $action === 'refuser') {
        $nouveauStatut = $action === 'accepter' ? 'Accepté' : 'Refusé';
        
        // L'admin ne peut valider QUE les demandes de SES employés (dont il est le manager référent)
        $stmt = $pdo->prepare("
            UPDATE Demande d
            INNER JOIN Employe e ON d.id_employe = e.id_employe
            SET d.statut = ?
            WHERE d.id_demande = ? 
            AND d.statut = 'En attente'
            AND e.manager_referent = ?
        ");
        $stmt->execute([$nouveauStatut, $idDemande, $idEmployeAdmin]);
        
        if ($stmt->rowCount() > 0) {
            $message = "Demande " . ($action === 'accepter' ? 'acceptée' : 'refusée') . " avec succès.";
            $typeMessage = "succes";
        } else {
            $message = "Action non autorisée ou demande déjà traitée.";
            $typeMessage = "erreur";
        }
    }
}

// Filtres
$statutFiltre = $_GET['statut'] ?? ''; 
$recherche = $_GET['recherche'] ?? '';

// Récupération de TOUTES les demandes de l'entreprise
// On ajoute une colonne "est_mon_employe" pour savoir si l'admin peut agir
$sql = "
    SELECT d.id_demande, d.type, d.date_debut, d.date_fin, d.date_demande, d.statut, d.motif,
           u.nom, u.prenom, e.poste, e.service, e.manager_referent,
           CASE WHEN e.manager_referent = ? THEN 1 ELSE 0 END as est_mon_employe,
           mgr_u.prenom as prenom_manager, mgr_u.nom as nom_manager
    FROM Demande d
    INNER JOIN Employe e ON d.id_employe = e.id_employe
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    LEFT JOIN Employe mgr_e ON e.manager_referent = mgr_e.id_employe
    LEFT JOIN Utilisateur mgr_u ON mgr_e.id_utilisateur = mgr_u.id_utilisateur
    WHERE 1=1
";
$params = [$idEmployeAdmin];

if ($statutFiltre !== '') {
    $sql .= " AND d.statut = ?";
    $params[] = $statutFiltre;
}

if ($recherche !== '') {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR e.service LIKE ?)";
    $rechercheLike = '%' . $recherche . '%';
    $params[] = $rechercheLike;
    $params[] = $rechercheLike;
    $params[] = $rechercheLike;
}

$sql .= " ORDER BY d.date_demande DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compteur par statut (Global)
$stmtCompteur = $pdo->prepare("
    SELECT statut, COUNT(*) as nb
    FROM Demande
    GROUP BY statut
");
$stmtCompteur->execute();
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
    <title>Gestion des Demandes — Admin RH</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">

    <!-- Menu latéral Admin -->
   <div class="menu-lateral">
     <div class="logo-menu" style="padding: 20px 15px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
    <div style="background: white; border-radius: 10px; padding: 8px 12px; display: inline-block; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
        <img src="../assets/images/logo-bsm-groupe - Copie.png" alt="BSM groupe" style="height: 35px; width: auto; display: block;">
    </div>
    <!-- <p style="color: white; font-size: 13px; font-weight: 600; margin: 0; letter-spacing: 1px;">BSM groupe</p> -->
</div>

    <!-- GROUPE 1 : RECRUTEMENT -->
    <p style="color: #cbd5e1; font-size: 11px; font-weight: bold; text-transform: uppercase; margin: 20px 0 8px 15px; letter-spacing: 1px; opacity: 0.7;">Recrutement</p>
    
    <a href="ajouter_offre.php" class="lien-menu">Offres d'emploi</a>
    <a href="candidatures.php" class="lien-menu">Candidatures</a>

    <!-- GROUPE 2 : GESTION -->
    <p style="color: #cbd5e1; font-size: 11px; font-weight: bold; text-transform: uppercase; margin: 20px 0 8px 15px; letter-spacing: 1px; opacity: 0.7;">Gestion</p>
    
    <a href="dashboard.php" class="lien-menu">Tableau de bord</a>
    <a href="employes.php" class="lien-menu">Employés</a>
    <a href="criteres.php" class="lien-menu">Critères</a>
    <a href="mon_equipe.php" class="lien-menu">Mon équipe</a>
    <a href="evaluations.php" class="lien-menu">Évaluations</a>
    <a href="demandes.php" class="lien-menu actif">Demandes</a>
    

    <!-- PIED DE MENU (Profil et Déconnexion) -->
    <div class="pied-menu">
        <div class="avatar-mini"><?= strtoupper(substr($_SESSION['prenom'],0,1) . substr($_SESSION['nom'],0,1)) ?></div>
        <span><?= htmlspecialchars($_SESSION['prenom']) ?></span>
        <a href="../logout.php" class="icone-deconnexion" title="Déconnexion">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        </a>
    </div>
</div>

    <!-- Zone de contenu -->
    <div class="zone-contenu">

        <div class="entete-page">
            <div>
                <p class="titre-page">Gestion des demandes</p>
                <p class="sous-titre-page">Vue d'ensemble de toutes les demandes de l'entreprise</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div style="padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; background: <?= $typeMessage === 'erreur' ? '#fee2e2' : '#dcfce7' ?>; color: <?= $typeMessage === 'erreur' ? '#991b1b' : '#166534' ?>; border-left: 4px solid <?= $typeMessage === 'erreur' ? '#dc2626' : '#22c55e' ?>;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Onglets de filtrage -->
        <div style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
            <?php 
            $statuts = ['En attente', 'Accepté', 'Refusé'];
            foreach ($statuts as $s): 
                $nb = $compteursParStatut[$s] ?? 0;
                $couleurBadge = $s === 'En attente' ? '#f59e0b' : ($s === 'Accepté' ? '#22c55e' : '#ef4444');
            ?>
                <a href="?statut=<?= $s ?>&recherche=<?= htmlspecialchars($recherche) ?>" 
                   style="padding: 8px 16px; border-radius: 20px; background: <?= $statutFiltre === $s ? $couleurBadge : '#f3f4f6' ?>; 
                          color: <?= $statutFiltre === $s ? '#fff' : '#4b5563' ?>; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px;">
                    <?= $s ?>
                    <?php if ($nb > 0): ?>
                        <span style="background: rgba(255,255,255,0.3); padding: 2px 8px; border-radius: 10px; font-size: 12px;"><?= $nb ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
            <?php if ($statutFiltre !== ''): ?>
                <a href="?recherche=<?= htmlspecialchars($recherche) ?>" style="padding: 8px 16px; border-radius: 20px; background: #e5e7eb; color: #4b5563; text-decoration: none; font-weight: 500;">
                    Voir tout
                </a>
            <?php endif; ?>
        </div>

        <!-- Barre de recherche -->
      <div style="margin-bottom: 20px;">
    <form method="GET" action="" style="display: flex; gap: 12px; align-items: center;">
        <input type="hidden" name="statut" value="<?= htmlspecialchars($statutFiltre) ?>">
        <input type="text" name="recherche" placeholder="Rechercher par nom, prénom ou service..." value="<?= htmlspecialchars($recherche) ?>" style="flex: 1; max-width: 400px; padding: 10px 14px; border: 1px solid #e0e0e0; border-radius: 8px;">
        <button type="submit" class="bouton-principal" style="padding: 10px 20px;">Rechercher</button>
        <?php if ($recherche !== ''): ?>
            <a href="?statut=<?= htmlspecialchars($statutFiltre) ?>" class="bouton-secondaire" style="padding: 10px 20px; text-decoration: none;">×</a>
        <?php endif; ?>
    </form>
</div>
        <!-- Liste des demandes -->
        <?php if (empty($demandes)): ?>
            <div class="panneau">
                <p style="color: #9ca3af; text-align: center; padding: 40px 0;">
                    <?php if ($statutFiltre === 'En attente'): ?>
                        Aucune demande en attente de validation dans l'entreprise. 🎉
                    <?php else: ?>
                        Aucune demande trouvée.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div>
                <?php foreach ($demandes as $demande): 
                    $peutAgir = $demande['est_mon_employe'] == 1 && $demande['statut'] === 'En attente';
                ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 12px; <?= $peutAgir ? 'border-left: 4px solid #4a5bd4;' : '' ?>">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                <div style="width: 40px; height: 40px; background: #4a5bd4; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px;">
                                    <?= strtoupper(substr($demande['prenom'], 0, 1) . substr($demande['nom'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p style="font-weight: 600; margin: 0; font-size: 15px;">
                                        <?= htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']) ?>
                                    </p>
                                    <p style="font-size: 13px; color: #6b7280; margin: 0;"><?= htmlspecialchars($demande['poste']) ?> · <?= htmlspecialchars($demande['service']) ?></p>
                                </div>
                            </div>
                            
                            <div style="margin-left: 52px; font-size: 14px;">
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

                        <div style="display: flex; gap: 8px; align-items: center;">
                            <?php if ($peutAgir): ?>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="id_demande" value="<?= $demande['id_demande'] ?>">
                                    <input type="hidden" name="action_demande" value="accepter">
                                    <button type="submit" style="background: #22c55e; color: white; border: none; padding: 10px 18px; border-radius: 6px; cursor: pointer; font-weight: 500; min-width: 110px;">
                                        ✓ Accepter
                                    </button>
                                </form>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="id_demande" value="<?= $demande['id_demande'] ?>">
                                    <input type="hidden" name="action_demande" value="refuser">
                                    <button type="submit" style="background: #ef4444; color: white; border: none; padding: 10px 18px; border-radius: 6px; cursor: pointer; font-weight: 500; min-width: 110px;">
                                        ✗ Refuser
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="padding: 8px 16px; border-radius: 12px; font-size: 13px; font-weight: 500; 
                                       background: <?= $demande['statut'] === 'Accepté' ? '#dcfce7' : ($demande['statut'] === 'Refusé' ? '#fee2e2' : '#fef3c7') ?>;
                                       color: <?= $demande['statut'] === 'Accepté' ? '#166534' : ($demande['statut'] === 'Refusé' ? '#991b1b' : '#92400e') ?>;">
                                    <?= htmlspecialchars($demande['statut']) ?>
                                </span>
                                <?php if ($demande['statut'] === 'En attente' && !empty($demande['prenom_manager'])): ?>
    <span style="font-size: 11px; color: #9ca3af; margin-left: 8px;">(<?= htmlspecialchars($demande['prenom_manager'] . ' ' . $demande['nom_manager']) ?>)</span>
<?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

</div>

</body>
</html>