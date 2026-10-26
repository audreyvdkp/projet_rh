<?php
require_once '../includes/auth.php';
verifierRole('Employé');
require_once '../config/db.php';

// Récupérer les infos de l'employé connecté
$stmt = $pdo->prepare("
    SELECT e.*, u.nom, u.prenom, u.email, 
           m.nom as nom_manager, m.prenom as prenom_manager
    FROM employe e
    INNER JOIN utilisateur u ON e.id_utilisateur = u.id_utilisateur
    LEFT JOIN employe m_emp ON e.manager_referent = m_emp.id_employe
    LEFT JOIN utilisateur m ON m_emp.id_utilisateur = m.id_utilisateur
    WHERE e.id_utilisateur = ?
");
$stmt->execute([$_SESSION['id_utilisateur']]);
$employe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employe) {
    die("Profil employé introuvable.");
}

// 1. Statistiques des objectifs
$stmtObj = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'Atteint' THEN 1 ELSE 0 END) as atteints,
        SUM(CASE WHEN statut = 'En cours' THEN 1 ELSE 0 END) as en_cours,
        SUM(CASE WHEN statut = 'Non atteint' THEN 1 ELSE 0 END) as non_atteints
    FROM objectif
    WHERE id_employe = ?
");
$stmtObj->execute([$employe['id_employe']]);
$statsobjectifs = $stmtObj->fetch(PDO::FETCH_ASSOC);

// 2. Statistiques des demandes
$stmtDem = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'En attente' THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN statut = 'Accepté' THEN 1 ELSE 0 END) as acceptees,
        SUM(CASE WHEN statut = 'Refusé' THEN 1 ELSE 0 END) as refusees
    FROM demande
    WHERE id_employe = ?
");
$stmtDem->execute([$employe['id_employe']]);
$statsdemandes = $stmtDem->fetch(PDO::FETCH_ASSOC);

// 3. Dernière évaluation
$stmtEval = $pdo->prepare("
    SELECT score_final, mention, date_evaluation, periode_debut, periode_fin
    FROM evaluation
    WHERE id_employe = ?
    ORDER BY date_evaluation DESC
    LIMIT 1
");
$stmtEval->execute([$employe['id_employe']]);
$derniereEval = $stmtEval->fetch(PDO::FETCH_ASSOC);

// 4. objectifs en cours (les 3 plus récents)
$stmtObjCours = $pdo->prepare("
    SELECT titre, date_debut, date_fin, statut
    FROM objectif
    WHERE id_employe = ? AND statut IN ('En cours', 'Atteint')
    ORDER BY date_fin DESC
    LIMIT 3
");
$stmtObjCours->execute([$employe['id_employe']]);
$objectifsRecents = $stmtObjCours->fetchAll(PDO::FETCH_ASSOC);

// 5. demandes récentes (les 3 dernières)
$stmtDemRecent = $pdo->prepare("
    SELECT type, date_debut, date_fin, statut, date_demande
    FROM demande
    WHERE id_employe = ?
    ORDER BY date_demande DESC
    LIMIT 3
");
$stmtDemRecent->execute([$employe['id_employe']]);
$demandesRecents = $stmtDemRecent->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace — <?= htmlspecialchars($employe['prenom']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">

    <!-- Menu latéral Employé -->
    <div class="menu-lateral">
        
        <div class="logo-menu" style="padding: 20px 15px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
    <div style="background: white; border-radius: 10px; padding: 8px 12px; display: inline-block; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
        <img src="../assets/images/logo-bsm-groupe - Copie.png" alt="BSM groupe" style="height: 35px; width: auto; display: block;">
    </div>
    <!-- <p style="color: white; font-size: 13px; font-weight: 600; margin: 0; letter-spacing: 1px;">BSM groupe</p> -->
</div>

    

        <a href="dashboard.php" class="lien-menu actif">Tableau de bord</a>
        <a href="objectifs.php" class="lien-menu">Mes objectifs</a>
        <a href="demandes.php" class="lien-menu">Mes demandes</a>
        <a href="evaluations.php" class="lien-menu">Mes évaluations</a>
        <a href="profil.php" class="lien-menu">Mon profil</a>

        <div class="pied-menu">
            <div class="avatar-mini"><?= strtoupper(substr($employe['prenom'],0,1) . substr($employe['nom'],0,1)) ?></div>
            <span><?= htmlspecialchars($employe['prenom']) ?></span>
            <a href="../logout.php" class="icone-deconnexion" style="text-decoration: none;" title="Déconnexion">
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
                <p class="titre-page">Bonjour, <?= htmlspecialchars($employe['prenom']) ?> 👋</p>
                <p class="sous-titre-page"><?= htmlspecialchars($employe['poste']) ?> · <?= htmlspecialchars($employe['service']) ?></p>
            </div>
           <div style="text-align: right;">
    <a href="demandes.php?action=nouvelle" class="bouton-principal" style="padding: 10px 20px; background: #4a5bd4; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Nouvelle demande
    </a>
</div>
        </div>

        <!-- Cartes de statistiques -->
      <div class="grille-stats" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
            <!-- Dernière évaluation -->
            <div class="carte-stat" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
                <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">Dernière évaluation</p>
                <?php if ($derniereEval): ?>
                    <p style="font-size: 32px; font-weight: 700; color: <?= $derniereEval['score_final'] >= 75 ? '#16a34a' : ($derniereEval['score_final'] >= 50 ? '#ca8a04' : '#dc2626') ?>; margin: 0;">
                        <?= number_format($derniereEval['score_final'], 1) ?>%
                    </p>
                    <span style="font-size: 12px; color: #6b7280;"><?= htmlspecialchars($derniereEval['mention']) ?></span>
                <?php else: ?>
                    <p style="font-size: 18px; color: #9ca3af; margin: 0;">Aucune évaluation</p>
                <?php endif; ?>
            </div>

            <!-- objectifs atteints -->
            <div class="carte-stat" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
                <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">objectifs atteints</p>
                <p style="font-size: 32px; font-weight: 700; color: #4a5bd4; margin: 0;">
                    <?= $statsobjectifs['atteints'] ?? 0 ?> / <?= $statsobjectifs['total'] ?? 0 ?>
                </p>
                <span style="font-size: 12px; color: #6b7280;">
                    <?= $statsobjectifs['en_cours'] ?? 0 ?> en cours
                </span>
            </div>

            <!-- demandes en attente -->
            <div class="carte-stat" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
                <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">demandes en attente</p>
                <p style="font-size: 32px; font-weight: 700; color: #f59e0b; margin: 0;">
                    <?= $statsdemandes['en_attente'] ?? 0 ?>
                </p>
                <span style="font-size: 12px; color: #6b7280;">
                    <?= $statsdemandes['acceptees'] ?? 0 ?> acceptée(s)
                </span>
            </div>

            <!-- Manager référent -->
            <div class="carte-stat" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
                <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">Manager référent</p>
                <?php if (!empty($employe['prenom_manager'])): ?>
                    <p style="font-size: 18px; font-weight: 600; color: #1f2937; margin: 0;">
                        <?= htmlspecialchars($employe['prenom_manager'] . ' ' . $employe['nom_manager']) ?>
                    </p>
                <?php else: ?>
                    <p style="font-size: 14px; color: #9ca3af; margin: 0;">Non assigné</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Deux colonnes : objectifs et demandes -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

            <!-- objectifs récents -->
            <div class="panneau">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <p class="titre-panneau" style="margin: 0;">Mes objectifs récents</p>
                    <a href="objectifs.php" style="font-size: 13px; color: #4a5bd4; text-decoration: none;">Voir tout →</a>
                </div>

                <?php if (empty($objectifsRecents)): ?>
                    <p style="color: #9ca3af; font-size: 14px; text-align: center; padding: 20px 0;">Aucun objectif fixé pour le moment.</p>
                <?php else: ?>
                    <?php foreach ($objectifsRecents as $obj): 
                        $couleurStatut = $obj['statut'] === 'Atteint' ? '#22c55e' : '#3b82f6';
                    ?>
                        <div style="padding: 12px; border-left: 3px solid <?= $couleurStatut ?>; background: #f9fafb; border-radius: 6px; margin-bottom: 10px;">
                            <p style="font-weight: 600; margin: 0 0 4px 0; font-size: 14px;"><?= htmlspecialchars($obj['titre']) ?></p>
                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #6b7280;">
                                <span>Fin : <?= date('d/m/Y', strtotime($obj['date_fin'])) ?></span>
                                <span style="color: <?= $couleurStatut ?>; font-weight: 500;"><?= htmlspecialchars($obj['statut']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- demandes récentes -->
            <div class="panneau">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <p class="titre-panneau" style="margin: 0;">Mes demandes récentes</p>
                    <a href="demandes.php" style="font-size: 13px; color: #4a5bd4; text-decoration: none;">Voir tout →</a>
                </div>

                <?php if (empty($demandesRecents)): ?>
                    <p style="color: #9ca3af; font-size: 14px; text-align: center; padding: 20px 0;">Aucune demande effectuée.</p>
                <?php else: ?>
                    <?php foreach ($demandesRecents as $dem): 
                        $couleur = $dem['statut'] === 'Accepté' ? '#22c55e' : ($dem['statut'] === 'Refusé' ? '#ef4444' : '#f59e0b');
                    ?>
                        <div style="padding: 12px; border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <p style="font-weight: 600; margin: 0; font-size: 14px;"><?= htmlspecialchars($dem['type']) ?></p>
                                <span style="background: <?= $couleur ?>; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 500;">
                                    <?= htmlspecialchars($dem['statut']) ?>
                                </span>
                            </div>
                            <p style="font-size: 12px; color: #6b7280; margin: 4px 0 0 0;">
                                Du <?= date('d/m/Y', strtotime($dem['date_debut'])) ?> au <?= date('d/m/Y', strtotime($dem['date_fin'])) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

    </div>

</div>

</body>
</html>