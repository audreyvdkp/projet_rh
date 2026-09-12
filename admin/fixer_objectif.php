<?php
require_once '../includes/auth.php';
verifierRole('Administrateur RH');
require_once '../config/db.php';

// Récupérer l'id_employe de l'Admin connecté
$stmt = $pdo->prepare("SELECT id_employe FROM Employe WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['id_utilisateur']]);
$idEmployeAdmin = $stmt->fetchColumn();

// Récupérer l'ID de l'employé cible (depuis l'URL)
$idEmployeCible = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$idEmployeCible) {
    header("Location: mon_equipe.php");
    exit;
}

// Vérifier que cet employé est bien sous la responsabilité de l'Admin
$stmt = $pdo->prepare("
    SELECT e.id_employe, u.nom, u.prenom, e.poste 
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

$message = "";
$typeMessage = "";

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $dateDebut = $_POST['date_debut'];
    $dateFin = $_POST['date_fin'];

    if ($titre === "" || $dateDebut === "" || $dateFin === "") {
        $message = "Veuillez remplir tous les champs obligatoires.";
        $typeMessage = "erreur";
    } elseif (strtotime($dateFin) < strtotime($dateDebut)) {
        $message = "La date de fin doit être postérieure ou égale à la date de début.";
        $typeMessage = "erreur";
    } else {
        $stmt = $pdo->prepare("INSERT INTO Objectif (id_employe, id_manager, titre, description, date_debut, date_fin, statut) VALUES (?, ?, ?, ?, ?, ?, 'En cours')");
        $stmt->execute([$idEmployeCible, $idEmployeAdmin, $titre, $description, $dateDebut, $dateFin]);
        $message = "Objectif fixé avec succès pour " . htmlspecialchars($employeCible['prenom'] . ' ' . $employeCible['nom']) . ".";
        $typeMessage = "succes";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fixer un objectif — Administrateur RH</title>
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
                <p class="titre-page">Fixer un objectif</p>
                <p class="sous-titre-page">Pour <?= htmlspecialchars($employeCible['prenom'] . ' ' . $employeCible['nom']) ?> — <?= htmlspecialchars($employeCible['poste']) ?></p>
            </div>
            <a href="mon_equipe.php" class="bouton-secondaire">← Retour à l'équipe</a>
        </div>

        <?php if ($message): ?>
            <div class="message-<?= $typeMessage === 'erreur' ? 'erreur' : 'succes' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="panneau formulaire-objectif">
            <form method="POST" action="">
                <div class="champ">
                    <label>Titre de l'objectif</label>
                    <input type="text" name="titre" required placeholder="Ex : Améliorer l'engagement sur les réseaux">
                </div>

                <div class="champ">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Détaillez ce qui est attendu, de façon claire et mesurable si possible..."></textarea>
                </div>

                <div class="grille-dates">
                    <div class="champ">
                        <label>Date de début</label>
                        <input type="date" name="date_debut" required>
                    </div>
                    <div class="champ">
                        <label>Date de fin</label>
                        <input type="date" name="date_fin" required>
                    </div>
                </div>

                <div class="astuce-smart">
                     Astuce : un bon objectif est spécifique, mesurable et limité dans le temps (méthode SMART)
                </div>

                <div class="actions-formulaire">
                    <a href="mon_equipe.php" class="bouton-secondaire">Annuler</a>
                    <button type="submit" class="bouton-principal">Enregistrer l'objectif</button>
                </div>
            </form>
        </div>

    </div>

</div>

<script src="../assets/js/app.js" defer></script>
</body>
</html>