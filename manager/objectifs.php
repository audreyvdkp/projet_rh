<?php
require_once '../includes/auth.php';
verifierRole('Manager');
require_once '../config/db.php';

// Récupérer l'id_employe du manager connecté
$stmt = $pdo->prepare("SELECT id_employe FROM Employe WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['id_utilisateur']]);
$idEmployeManager = $stmt->fetchColumn();

$managerNom = htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']);
$message = "";
$typeMessage = "";

// Traitement du formulaire d'ajout d'objectif
// Mode modification ?
$modeModification = false;
$objectifAModifier = null;
if (isset($_GET['modifier']) && !empty($_GET['modifier'])) {
    $idAModifier = (int)$_GET['modifier'];
    $stmt = $pdo->prepare("
        SELECT o.* FROM Objectif o 
        INNER JOIN Employe e ON o.id_employe = e.id_employe 
        WHERE o.id_objectif = ? AND e.manager_referent = ?
    ");
    $stmt->execute([$idAModifier, $idEmployeManager]);
    $objectifAModifier = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($objectifAModifier) {
        $modeModification = true;
    }
}

// Traitement du formulaire (Ajout OU Modification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enregistrer') {
    $idObjectif = !empty($_POST['id_objectif']) ? (int)$_POST['id_objectif'] : null;
    $idEmploye = (int)$_POST['id_employe'];
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']); 
    $dateDebut = $_POST['date_debut'];
    $dateFin = $_POST['date_fin'];

    // Vérifier que l'employé appartient bien à l'équipe
    $stmt = $pdo->prepare("SELECT id_employe FROM Employe WHERE id_employe = ? AND manager_referent = ? AND statut = 'Actif'");
    $stmt->execute([$idEmploye, $idEmployeManager]);
    
    if (!$stmt->fetch() || $titre === "" || $dateDebut === "" || $dateFin === "") {
        $message = "Veuillez remplir tous les champs obligatoires.";
        $typeMessage = "erreur";
    } elseif (strtotime($dateFin) < strtotime($dateDebut)) {
        $message = "La date de fin doit être postérieure ou égale à la date de début.";
        $typeMessage = "erreur";
    } else {
        if ($idObjectif) {
            // MODIFICATION
            $stmt = $pdo->prepare("
                UPDATE Objectif o 
                INNER JOIN Employe e ON o.id_employe = e.id_employe 
                SET o.titre = ?, o.description = ?, o.date_debut = ?, o.date_fin = ?, o.id_employe = ?
                WHERE o.id_objectif = ? AND e.manager_referent = ?
            ");
            $stmt->execute([$titre, $description, $dateDebut, $dateFin, $idEmploye, $idObjectif, $idEmployeManager]);
            $message = "Objectif modifié avec succès.";
        } else {
            // AJOUT
            $stmt = $pdo->prepare("INSERT INTO Objectif (id_employe, id_manager, titre, description, date_debut, date_fin, statut) VALUES (?, ?, ?, ?, ?, ?, 'En cours')");
            $stmt->execute([$idEmploye, $idEmployeManager, $titre, $description, $dateDebut, $dateFin]);
            $message = "Objectif fixé avec succès.";
        }
        $typeMessage = "succes";
        // Recharger la page sans le paramètre modifier
        header("Location: objectifs.php");
        exit;
    }
}

// Traitement de la validation/refus/suppression d'un objectif
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_objectif'])) {
    $idObjectif = (int)$_POST['id_objectif'];
    $action = $_POST['action_objectif'];
    
    if ($action === 'atteint' || $action === 'non_atteint') {
        $nouveauStatut = $action === 'atteint' ? 'Atteint' : 'Non atteint';
        $stmt = $pdo->prepare("
            UPDATE Objectif o 
            INNER JOIN Employe e ON o.id_employe = e.id_employe 
            SET o.statut = ?
            WHERE o.id_objectif = ? AND e.manager_referent = ?
        ");
        $stmt->execute([$nouveauStatut, $idObjectif, $idEmployeManager]);
        $message = "Objectif marqué comme " . ($action === 'atteint' ? "atteint" : "non atteint") . ".";
        $typeMessage = "succes";
    } 
    elseif ($action === 'supprimer') {
        $stmt = $pdo->prepare("
            DELETE o FROM Objectif o 
            INNER JOIN Employe e ON o.id_employe = e.id_employe 
            WHERE o.id_objectif = ? AND e.manager_referent = ?
        ");
        $stmt->execute([$idObjectif, $idEmployeManager]);
        $message = "Objectif supprimé.";
        $typeMessage = "succes";
    }
}


// Récupération de l'équipe pour le dropdown
$stmt = $pdo->prepare("
    SELECT e.id_employe, u.nom, u.prenom, e.poste 
    FROM Employe e 
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur 
    WHERE e.manager_referent = ? AND e.statut = 'Actif'
    ORDER BY u.nom, u.prenom
");
$stmt->execute([$idEmployeManager]);
$equipe = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération de tous les objectifs des employés de l'équipe
$stmt = $pdo->prepare("
    SELECT o.*, u.nom, u.prenom, e.poste 
    FROM Objectif o
    INNER JOIN Employe e ON o.id_employe = e.id_employe
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    WHERE e.manager_referent = ?
    ORDER BY 
        CASE o.statut 
            WHEN 'En cours' THEN 1 
            WHEN 'Partiellement atteint' THEN 2 
            WHEN 'Atteint' THEN 3 
            WHEN 'Non atteint' THEN 4 
        END,
        o.date_fin ASC
");
$stmt->execute([$idEmployeManager]);
$objectifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Objectifs — Manager</title>
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
        <!-- <a href="tout_evaluer.php" class="lien-menu">Tout évaluer</a> -->
        <a href="objectifs.php" class="lien-menu actif">Objectifs</a>
        <a href="demandes.php" class="lien-menu">Demandes de mon équipe</a>

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
                <p class="titre-page">Objectifs</p>
                <p class="sous-titre-page">Fixez et suivez les objectifs de votre équipe</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message-<?= $typeMessage === 'erreur' ? 'erreur' : 'succes' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- Formulaire : Fixer un objectif -->
        <div class="panneau formulaire-objectif">
            <p class="titre-panneau">Fixer un objectif</p>
            <p class="sous-titre-form"><?= $managerNom ?></p>

            <form method="POST" action="">
    <input type="hidden" name="action" value="enregistrer">
    <?php if ($modeModification): ?>
        <input type="hidden" name="id_objectif" value="<?= $objectifAModifier['id_objectif'] ?>">
    <?php endif; ?>

    <div class="champ">
        <label>Employé concerné</label>
        <select name="id_employe" required>
            <option value="">-- Sélectionner un employé --</option>
            <?php foreach ($equipe as $emp): ?>
                <option value="<?= $emp['id_employe'] ?>" 
                    <?= ($modeModification && $objectifAModifier['id_employe'] == $emp['id_employe']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']) ?> — <?= htmlspecialchars($emp['poste']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="champ">
        <label>Titre de l'objectif</label>
        <input type="text" name="titre" required 
            placeholder="Ex : Améliorer l'engagement sur les réseaux"
            value="<?= $modeModification ? htmlspecialchars($objectifAModifier['titre']) : '' ?>">
    </div>

    <div class="champ">
        <label>Description</label>
        <textarea name="description" rows="3" 
            placeholder="Détaillez ce qui est attendu..."><?= $modeModification ? htmlspecialchars($objectifAModifier['description']) : '' ?></textarea>
    </div>

    <div class="grille-dates">
        <div class="champ">
            <label>Date de début</label>
            <input type="date" name="date_debut" required 
                value="<?= $modeModification ? $objectifAModifier['date_debut'] : '' ?>">
        </div>
        <div class="champ">
            <label>Date de fin</label>
            <input type="date" name="date_fin" required 
                value="<?= $modeModification ? $objectifAModifier['date_fin'] : '' ?>">
        </div>
    </div>

    <div class="astuce-smart">
        💡 Astuce : un bon objectif est spécifique, mesurable et limité dans le temps (méthode SMART)
    </div>

    <div class="actions-formulaire">
        <a href="objectifs.php" class="bouton-secondaire">Annuler</a>
        <button type="submit" class="bouton-principal">
            <?= $modeModification ? 'Enregistrer les modifications' : 'Enregistrer l\'objectif' ?>
        </button>
    </div>
</form>
        </div>

        <!-- Liste des objectifs -->
        <div class="panneau" style="margin-top: 24px;">
            <p class="titre-panneau">Objectifs de l'équipe</p>
            <!-- Filtre par employé -->
<div style="margin-bottom: 16px;">
    <select id="filtre-employe" style="padding: 8px 12px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 14px; min-width: 200px;">
        <option value="">Tous les employés</option>
        <?php foreach ($equipe as $emp): ?>
            <option value="<?= $emp['id_employe'] ?>">
                <?= htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
            <?php if (empty($objectifs)): ?>
                <p class="texte-vide">Aucun objectif fixé pour le moment.</p>
            <?php else: ?>
                <table class="tableau-donnees">
                    <tr>
                        <th>Employé</th>
                        <th>Objectif</th>
                        <th>Période</th>
                        <th>Statut</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                    <?php foreach ($objectifs as $obj): ?>
                        <tr data-employe="<?= $obj['id_employe'] ?>">
                            <td>
                                <strong><?= htmlspecialchars($obj['prenom'] . ' ' . $obj['nom']) ?></strong>
                                <br><span style="font-size: 12px; color: #9ca3af;"><?= htmlspecialchars($obj['poste']) ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($obj['titre']) ?></strong>
                                <?php if ($obj['description']): ?>
                                    <br><span style="font-size: 12px; color: #6b7280;"><?= htmlspecialchars($obj['description']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 13px; color: #6b7280;">
                                <?= date('d/m/Y', strtotime($obj['date_debut'])) ?>
                                <br>au <?= date('d/m/Y', strtotime($obj['date_fin'])) ?>
                            </td>
                            <td>
                                <?php
                                $classeBadge = match($obj['statut']) {
                                    'En cours' => 'badge-info',
                                    'Atteint' => 'badge-succes',
                                    'Non atteint' => 'badge-danger',
                                    'Partiellement atteint' => 'badge-attention',
                                    default => 'badge-neutre'
                                };
                                ?>
                                <span class="badge-statut <?= $classeBadge ?>"><?= htmlspecialchars($obj['statut']) ?></span>
                            </td>
                        <td style="text-align: right;">
    <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
        <?php if ($obj['statut'] === 'En cours' || $obj['statut'] === 'Partiellement atteint'): ?>
            <!-- Bouton Modifier -->
            <a href="objectifs.php?modifier=<?= $obj['id_objectif'] ?>" 
               class="btn-action btn-modifier" 
               title="Modifier">
                ✏️
            </a>
            
            <!-- Bouton Atteint -->
            <form method="POST" style="display: inline;">
                <input type="hidden" name="id_objectif" value="<?= $obj['id_objectif'] ?>">
                <input type="hidden" name="action_objectif" value="atteint">
                <button type="submit" class="btn-action btn-accepter" title="Marquer comme atteint">✓</button>
            </form>
            
            <!-- Bouton Non atteint -->
            <form method="POST" style="display: inline;">
                <input type="hidden" name="id_objectif" value="<?= $obj['id_objectif'] ?>">
                <input type="hidden" name="action_objectif" value="non_atteint">
                <button type="submit" class="btn-action btn-refuser" title="Marquer comme non atteint">✗</button>
            </form>
            
            <!-- Bouton Supprimer -->
            <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cet objectif ?');">
                <input type="hidden" name="id_objectif" value="<?= $obj['id_objectif'] ?>">
                <input type="hidden" name="action_objectif" value="supprimer">
                <button type="submit" class="btn-action btn-supprimer" title="Supprimer">🗑</button>
            </form>
        <?php else: ?>
            <span style="color: #9ca3af; font-size: 12px;">Clôturé</span>
        <?php endif; ?>
    </div>
</td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

    </div>

</div>
<script>
document.getElementById('filtre-employe').addEventListener('change', function() {
    const employeSelectionne = this.value;
    // On cible toutes les lignes SAUF la première (les en-têtes)
    const lignes = document.querySelectorAll('.tableau-donnees tr:not(:first-child)');
    
    lignes.forEach(function(ligne) {
        const employeLigne = ligne.getAttribute('data-employe');
        
        if (employeSelectionne === '' || employeLigne === employeSelectionne) {
            ligne.style.display = '';
        } else {
            ligne.style.display = 'none';
        }
    });
});
</script>

<script src="../assets/js/app.js" defer></script>
</body>
</html>