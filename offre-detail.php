<?php
require_once 'config/db.php';

$idOffre = (int)($_GET['id'] ?? 0);

if ($idOffre === 0) {
    header('Location: offres.php');
    exit;
}

// Récupérer l'offre
$stmt = $pdo->prepare("
    SELECT o.*, COUNT(c.id_candidature) as nb_candidatures 
    FROM offre_emploi o 
    LEFT JOIN candidature c ON o.id_offre = c.id_offre 
    WHERE o.id_offre = ? AND o.statut = 'Publiée'
");
$stmt->execute([$idOffre]);
$offre = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$offre) {
    header('Location: offres.php');
    exit;
}

$estExpiree = $offre['date_limite'] < date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($offre['titre']) ?> - Carrières</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background: #f8fafc; color: #1e293b; line-height: 1.6; }
        
        header { background: white; padding: 20px 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 22px; font-weight: bold; color: #4a5bd4; display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .logo span { background: #4a5bd4; color: white; padding: 5px 10px; border-radius: 8px; }
        .btn-retour { color: #64748b; text-decoration: none; font-weight: 500; }
        .btn-retour:hover { color: #4a5bd4; }

        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }

        .offre-header { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 24px; border-top: 5px solid #4a5bd4; }
        .offre-header h1 { font-size: 32px; color: #1e293b; margin-bottom: 16px; }
        .offre-meta { display: flex; gap: 20px; flex-wrap: wrap; font-size: 15px; color: #64748b; margin-bottom: 20px; }
        .offre-meta span { display: flex; align-items: center; gap: 6px; }
        .badge { background: #e0e7ff; color: #4338ca; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600; }
        .candidatures-count { color: #f59e0b; font-weight: 500; }

        .offre-body { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .offre-body h2 { color: #1e293b; margin-bottom: 16px; font-size: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        .offre-body p { color: #475569; margin-bottom: 16px; white-space: pre-wrap; }
        .offre-body ul { margin-left: 20px; color: #475569; margin-bottom: 16px; }
        .offre-body ul li { margin-bottom: 8px; }

        .info-box { background: #f8fafc; padding: 16px 20px; border-radius: 8px; border-left: 4px solid #4a5bd4; margin-bottom: 24px; }
        .info-box p { margin: 4px 0; font-size: 14px; color: #64748b; }
        .info-box strong { color: #1e293b; }

        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-postuler { background: #4a5bd4; color: white; padding: 14px 28px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-block; transition: background 0.2s; }
        .btn-postuler:hover { background: #3d4bb8; }
        .btn-postuler.disabled { background: #94a3b8; cursor: not-allowed; }
        .btn-retour-liste { background: white; color: #4a5bd4; border: 1px solid #4a5bd4; padding: 14px 24px; border-radius: 8px; font-weight: 500; text-decoration: none; }
        .btn-retour-liste:hover { background: #f8fafc; }

        footer { text-align: center; padding: 30px; color: #94a3b8; font-size: 14px; border-top: 1px solid #e2e8f0; background: white; margin-top: 40px; }

        @media (max-width: 768px) {
            .offre-header, .offre-body { padding: 24px; }
            .offre-header h1 { font-size: 24px; }
            .actions { flex-direction: column; }
            .btn-postuler, .btn-retour-liste { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

    <header>
        <a href="offres.php" class="logo"><span>RH</span> Système RH</a>
        <a href="offres.php" class="btn-retour">← Retour aux offres</a>
    </header>

    <div class="container">

        <?php if ($estExpiree): ?>
            <div class="info-box" style="border-left-color: #ef4444; background: #fee2e2;">
                <p style="color: #991b1b; font-weight: 600;">⚠️ Cette offre a expiré le <?= date('d/m/Y', strtotime($offre['date_limite'])) ?>. Les candidatures ne sont plus acceptées.</p>
            </div>
        <?php endif; ?>

        <div class="offre-header">
            <h1><?= htmlspecialchars($offre['titre']) ?></h1>
            <div class="offre-meta">
                <span>📍 <?= htmlspecialchars($offre['lieu'] ?: 'Non précisé') ?></span>
                <span class="badge"><?= htmlspecialchars($offre['type_contrat']) ?></span>
                <span> Date limite : <?= date('d/m/Y', strtotime($offre['date_limite'])) ?></span>
                <?php if ($offre['nb_candidatures'] > 0): ?>
                    <span class="candidatures-count">👥 <?= $offre['nb_candidatures'] ?> candidat(s) ont déjà postulé</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="offre-body">
            <h2>Description du poste</h2>
            <p><?= nl2br(htmlspecialchars($offre['description'])) ?></p>

            <?php if (!empty($offre['exigences'])): ?>
                <h2>Exigences et profil recherché</h2>
                <p><?= nl2br(htmlspecialchars($offre['exigences'])) ?></p>
            <?php endif; ?>

            <?php if ($offre['nombre_postes'] > 0): ?>
                <div class="info-box">

                    <p><strong>Date de publication :</strong> <?= date('d/m/Y', strtotime($offre['date_publication'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="actions">
            <?php if (!$estExpiree): ?>
                <a href="postuler.php?id=<?= $offre['id_offre'] ?>" class="btn-postuler">Postuler à cette offre →</a>
            <?php else: ?>
                <span class="btn-postuler disabled">Offre expirée</span>
            <?php endif; ?>
            <a href="offres.php" class="btn-retour-liste">Voir les autres offres</a>
        </div>

    </div>

    <footer>
        &copy; <?= date('Y') ?> Système RH - Tous droits réservés.
    </footer>

</body>
</html>