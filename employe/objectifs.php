<?php
require_once '../includes/auth.php';
verifierRole('Employé');
require_once '../config/db.php';

// Récupérer l'id_employe connecté
$stmt = $pdo->prepare("SELECT id_employe FROM Employe WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['id_utilisateur']]);
$idEmploye = $stmt->fetchColumn();

// Récupérer tous les objectifs de l'employé
$stmt = $pdo->prepare("
    SELECT titre, description, date_debut, date_fin, statut
    FROM Objectif
    WHERE id_employe = ?
    ORDER BY date_fin DESC
");
$stmt->execute([$idEmploye]);
$objectifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$total = count($objectifs);
$atteints = count(array_filter($objectifs, fn($o) => $o['statut'] === 'Atteint'));
$enCours = count(array_filter($objectifs, fn($o) => $o['statut'] === 'En cours'));
$nonAtteints = count(array_filter($objectifs, fn($o) => $o['statut'] === 'Non atteint'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes objectifs — Espace Employé</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">
    <div class="menu-lateral">
        <div class="logo-menu"><div class="icone-logo-menu">RH</div><span>Système RH</span></div>
        <a href="dashboard.php" class="lien-menu">Tableau de bord</a>
        <a href="objectifs.php" class="lien-menu actif">Mes objectifs</a>
        <a href="demandes.php" class="lien-menu">Mes demandes</a>
        <a href="evaluations.php" class="lien-menu">Mes évaluations</a>
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
                <p class="titre-page">Mes objectifs</p>
                <p class="sous-titre-page">Suivez vos objectifs et leur avancement</p>
            </div>
        </div>

        <!-- Statistiques -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div class="carte-stat" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
                <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">Total</p>
                <p style="font-size: 32px; font-weight: 700; color: #4a5bd4; margin: 0;"><?= $total ?></p>
            </div>
            <div class="carte-stat" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
                <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">Atteints</p>
                <p style="font-size: 32px; font-weight: 700; color: #22c55e; margin: 0;"><?= $atteints ?></p>
            </div>
            <div class="carte-stat" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
                <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">En cours</p>
                <p style="font-size: 32px; font-weight: 700; color: #3b82f6; margin: 0;"><?= $enCours ?></p>
            </div>
            <div class="carte-stat" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
                <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">Non atteints</p>
                <p style="font-size: 32px; font-weight: 700; color: #ef4444; margin: 0;"><?= $nonAtteints ?></p>
            </div>
        </div>

        <!-- Liste des objectifs -->
        <div class="panneau">
            <p class="titre-panneau">Liste de mes objectifs</p>
            
            <?php if (empty($objectifs)): ?>
                <p style="color: #9ca3af; text-align: center; padding: 40px 0;">Aucun objectif fixé pour le moment.</p>
            <?php else: ?>
                <?php foreach ($objectifs as $obj): 
                    $couleur = match($obj['statut']) {
                        'Atteint' => '#22c55e',
                        'En cours' => '#3b82f6',
                        'Non atteint' => '#ef4444',
                        default => '#6b7280'
                    };
                ?>
                    <div style="padding: 16px; border-left: 4px solid <?= $couleur ?>; background: #f9fafb; border-radius: 8px; margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <h3 style="margin: 0; font-size: 16px; font-weight: 600;"><?= htmlspecialchars($obj['titre']) ?></h3>
                            <span style="background: <?= $couleur ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">
                                <?= htmlspecialchars($obj['statut']) ?>
                            </span>
                        </div>
                        <?php if (!empty($obj['description'])): ?>
                            <p style="margin: 8px 0; color: #6b7280; font-size: 14px;"><?= htmlspecialchars($obj['description']) ?></p>
                        <?php endif; ?>
                        <div style="display: flex; gap: 20px; margin-top: 12px; font-size: 13px; color: #6b7280;">
                            <span>📅 Début : <?= date('d/m/Y', strtotime($obj['date_debut'])) ?></span>
                            <span> Fin : <?= date('d/m/Y', strtotime($obj['date_fin'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>