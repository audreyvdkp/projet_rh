<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/auth.php';
verifierRole('Administrateur RH');
require_once '../config/db.php';

// Récupérer l'id_employe de l'Admin connecté
$stmt = $pdo->prepare("SELECT id_employe FROM Employe WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['id_utilisateur']]);
$idEmployeAdmin = $stmt->fetchColumn();

// Récupérer l'ID de l'employé à évaluer
$idEmployeCible = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$idEmployeCible) {
    header("Location: mon_equipe.php");
    exit;
}

// Récupérer les infos de l'employé cible
$stmt = $pdo->prepare("
    SELECT e.id_employe, e.poste, e.service, u.nom, u.prenom, u.email
    FROM Employe e 
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur 
    WHERE e.id_employe = ? AND e.manager_referent = ? AND e.statut = 'Actif'
");
$stmt->execute([$idEmployeCible, $idEmployeAdmin]);
$employeCible = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employeCible) {
    header("Location: mon_equipe.php");
    exit;
}

// Récupérer les critères ACTIFS pour ce poste + ceux pour "Tous"
$stmt = $pdo->prepare("
    SELECT id_critere, nom_critere, description, poids, note_maximale 
    FROM critere_evaluation 
    WHERE actif = 1 
    AND (poste_concerne = ? OR poste_concerne = 'Tous')
    ORDER BY nom_critere
");
$stmt->execute([$employeCible['poste']]);
$criteres = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = "";
$typeMessage = "";

// Traitement du formulaire d'évaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commentaireGlobal = trim($_POST['commentaire'] ?? '');
    $dateEvaluation = date('Y-m-d');
    $periodeDebut = $_POST['periode_debut'] ?? date('Y-m-d');
    $periodeFin = $_POST['periode_fin'] ?? date('Y-m-d');
    
 // Calcul du score final pondéré
$scoreFinal = 0;
$totalPoids = 0;
$notesObtenues = [];

foreach ($criteres as $critere) {
    $nomCritereLower = strtolower($critere['nom_critere']);
    
    // Algorithme automatique pour les objectifs
    if (strpos($nomCritereLower, 'objectif') !== false || strpos($nomCritereLower, 'atteinte') !== false) {
        $stmtObj = $pdo->prepare("SELECT statut FROM Objectif WHERE id_employe = ?");
        $stmtObj->execute([$idEmployeCible]);
        $objectifs = $stmtObj->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($objectifs) > 0) {
            $points = 0;
            foreach ($objectifs as $obj) {
                if ($obj['statut'] === 'Atteint') $points += 1;
                elseif ($obj['statut'] === 'Partiellement atteint') $points += 0.5;
            }
            $noteObtenue = ($points / count($objectifs)) * $critere['note_maximale'];
        } else {
            $noteObtenue = 0;
        }
    } else {
        // Saisie manuelle
        $noteObtenue = isset($_POST['note_' . $critere['id_critere']]) 
            ? (float)$_POST['note_' . $critere['id_critere']] 
            : 0;
    }

    $poids = (float)$critere['poids']; // ex: 15 (pour 15%)
    $noteMaximale = (float)$critere['note_maximale']; // ex: 20
    
    //  CALCUL CORRECT : Note sur 100 × poids
    $noteSur100 = ($noteMaximale > 0) ? ($noteObtenue / $noteMaximale) * 100 : 0;
    $contribution = $noteSur100 * ($poids / 100); // ex: 80/100 × 0.15 = 12 points
    
    $scoreFinal += $contribution;
    $totalPoids += $poids;
    
    $notesObtenues[$critere['id_critere']] = $noteObtenue;
}

// NORMALISATION : Si les poids ne font pas 100%, on ramène à 100
if ($totalPoids > 0) {
    $scoreFinal = ($scoreFinal / $totalPoids) * 100;
}

// Limite à 100 max (sécurité)
$scoreFinal = min($scoreFinal, 100);
    
    // Déterminer la mention
    $mention = '';
    if ($scoreFinal >= 90) $mention = 'Excellent';
    elseif ($scoreFinal >= 75) $mention = 'Très bien';
    elseif ($scoreFinal >= 60) $mention = 'Bien';
    elseif ($scoreFinal >= 50) $mention = 'Passable';
    else $mention = 'Insuffisant';
    

    $stmt = $pdo->prepare("
        INSERT INTO evaluation (id_employe, id_evaluateur, date_evaluation, periode_debut, periode_fin, score_final, mention, statut) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'En cours')
    ");
    $stmt->execute([$idEmployeCible, $idEmployeAdmin, $dateEvaluation, $periodeDebut, $periodeFin, $scoreFinal, $mention]);
    $idEvaluation = $pdo->lastInsertId();
    
    // Insérer les détails par critère
    $stmtDetail = $pdo->prepare("
        INSERT INTO detail_evaluation (id_evaluation, id_critere, note_obtenue, note_maximale, commentaire) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($criteres as $critere) {
    // On récupère la note depuis le tableau qu'on a créé plus haut
    $noteObtenue = $notesObtenues[$critere['id_critere']] ?? 0;
    $commentaireCritere = ($critere === reset($criteres)) ? $commentaireGlobal : '';
    $stmtDetail->execute([$idEvaluation, $critere['id_critere'], $noteObtenue, $critere['note_maximale'], $commentaireCritere]);
}
    $message = "Évaluation enregistrée avec succès. Score final : " . number_format($scoreFinal, 1) . "/100 (" . $mention . ")";
    $typeMessage = "succes";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Évaluer <?= htmlspecialchars($employeCible['prenom']) ?> — Administrateur RH</title>
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
        <a href="evaluations.php" class="lien-menu">Évaluations</a>
        <a href="demandes.php" class="lien-menu">Demandes</a>
        <a href="criteres.php" class="lien-menu">Critères</a>
        <a href="mon_equipe.php" class="lien-menu actif">Mon équipe</a>

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
                <p class="titre-page">Évaluation de <?= htmlspecialchars($employeCible['prenom'] . ' ' . $employeCible['nom']) ?></p>
                <p class="sous-titre-page"><?= htmlspecialchars($employeCible['poste']) ?> · <?= htmlspecialchars($employeCible['service']) ?></p>
            </div>
            <a href="mon_equipe.php" class="bouton-secondaire">← Retour à l'équipe</a>
        </div>

        <?php if ($message): ?>
            <div class="message-<?= $typeMessage === 'erreur' ? 'erreur' : 'succes' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (empty($criteres)): ?>
            <div class="panneau">
                <p class="texte-vide">Aucun critère actif n'est défini pour le poste "<?= htmlspecialchars($employeCible['poste']) ?>". 
                Veuillez d'abord créer des critères dans la page Critères.</p>
            </div>
        <?php else: ?>
            <div class="panneau">
                <p class="titre-panneau">Critères d'évaluation</p>
                <p class="sous-titre-page" style="margin-bottom: 20px;">
                    <?= count($criteres) ?> critère(s) applicable(s) au poste de <?= htmlspecialchars($employeCible['poste']) ?>
                </p>

                <form method="POST" action="">
                    <!-- Période d'évaluation -->
                    <div class="grille-dates" style="margin-bottom: 20px;">
                        <div class="champ">
                            <label>Période du</label>
                            <input type="date" name="periode_debut" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="champ">
                            <label>au</label>
                            <input type="date" name="periode_fin" required value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <table class="tableau-donnees">
                        <tr>
                            <th>Critère</th>
                            <th>Description</th>
                            <th>Poids</th>
                            <th>Note max</th>
                            <th style="text-align: center;">Note obtenue</th>
                        </tr>
                        <?php foreach ($criteres as $critere): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($critere['nom_critere']) ?></strong></td>
                                <td style="font-size: 13px; color: #6b7280;">
                                    <?= htmlspecialchars($critere['description'] ?? '-') ?>
                                </td>
                                <td><strong><?= number_format($critere['poids'], 0) ?>%</strong></td>
                                <td>/<?= $critere['note_maximale'] ?></td>
                                <td style="text-align: center;">
                                    <input 
                                        type="number" 
                                        name="note_<?= $critere['id_critere'] ?>" 
                                        min="0" 
                                        max="<?= $critere['note_maximale'] ?>" 
                                        step="0.5"
                                        required
                                        style="width: 70px; padding: 6px; border: 1px solid #e0e0e0; border-radius: 6px; text-align: center;"
                                        placeholder="0"
                                    >
                                </td>
                            </tr>
                            <?php if (!empty($critere['description'])): ?>
                            <tr>
                                <td colspan="5" style="background: #f9fafb; font-size: 12px; color: #6b7280; padding: 8px;">
                                    <em><?= htmlspecialchars($critere['description']) ?></em>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </table>

                    <div class="champ" style="margin-top: 24px;">
                        <label>Commentaire global</label>
                        <textarea name="commentaire" rows="4" placeholder="Points forts, axes d'amélioration, observations..."></textarea>
                    </div>

                    <div class="actions-formulaire">
                        <a href="mon_equipe.php" class="bouton-secondaire">Annuler</a>
                        <button type="submit" class="bouton-principal">Enregistrer l'évaluation</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </div>

</div>

</body>
</html>