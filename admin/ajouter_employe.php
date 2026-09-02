<?php
require_once '../includes/auth.php';
verifierRole('Administrateur RH');
require_once '../config/db.php';

// Vérifier si on est en mode modification
$modeModification = isset($_GET['id']) && !empty($_GET['id']);
$idEmploye = $modeModification ? (int)$_GET['id'] : null;

$employe = null;
$erreur = "";
$succes = "";

// Si mode modification, on récupère les données existantes
if ($modeModification) {
    $stmt = $pdo->prepare("
        SELECT e.*, u.nom, u.prenom, u.email, u.role 
        FROM Employe e 
        INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur 
        WHERE e.id_employe = ?
    ");
    $stmt->execute([$idEmploye]);
    $employe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employe) {
        header("Location: employes.php");
        exit;
    }
}

// Traitement du formulaire (Ajout OU Modification)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $poste = trim($_POST['poste']);
    $service = trim($_POST['service']);
    $role = $_POST['role'];
    $contrat = $_POST['contrat'];
    $dateEmbauche = $_POST['date_embauche'];
    $managerReferent = !empty($_POST['manager_referent']) ? $_POST['manager_referent'] : null;

    // Vérifier si l'email existe déjà (sauf si c'est le même employé qu'on modifie)
    $stmt = $pdo->prepare("SELECT id_utilisateur FROM Utilisateur WHERE email = ?");
    $stmt->execute([$email]);
    $userExistant = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userExistant && (!$modeModification || $userExistant['id_utilisateur'] != $employe['id_utilisateur'])) {
        $erreur = "Cet email est déjà utilisé par un autre compte.";
    } else {
        if ($modeModification) {
            // --- MODE MODIFICATION ---
            $idUtilisateur = $employe['id_utilisateur'];
            
            // Mise à jour de la table Utilisateur
            $stmt = $pdo->prepare("UPDATE Utilisateur SET nom = ?, prenom = ?, email = ?, role = ? WHERE id_utilisateur = ?");
            $stmt->execute([$nom, $prenom, $email, $role, $idUtilisateur]);

            // Mise à jour de la table Employe
            $stmt = $pdo->prepare("UPDATE Employe SET poste = ?, service = ?, date_embauche = ?, type_contrat = ?, manager_referent = ? WHERE id_employe = ?");
            $stmt->execute([$poste, $service, $dateEmbauche, $contrat, $managerReferent, $idEmploye]);

            $succes = "Employé modifié avec succès.";
            
            // Recharger les données pour mettre à jour l'affichage du formulaire
            $stmt = $pdo->prepare("SELECT e.*, u.nom, u.prenom, u.email, u.role FROM Employe e INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur WHERE e.id_employe = ?");
            $stmt->execute([$idEmploye]);
            $employe = $stmt->fetch(PDO::FETCH_ASSOC);

        } else {
            // --- MODE AJOUT ---
            $motDePasseTemporaire = substr(bin2hex(random_bytes(4)), 0, 8);
            $motDePasseHache = password_hash($motDePasseTemporaire, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO Utilisateur (nom, prenom, email, mot_de_passe, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $prenom, $email, $motDePasseHache, $role]);
            $idUtilisateur = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO Employe (id_utilisateur, poste, service, date_embauche, type_contrat, manager_referent) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$idUtilisateur, $poste, $service, $dateEmbauche, $contrat, $managerReferent]);

            $succes = "Employé créé avec succès. Mot de passe temporaire : " . $motDePasseTemporaire;
        }
    }
}

// Liste des employés existants pour le menu déroulant "manager référent"
$stmt = $pdo->query("
    SELECT e.id_employe, u.nom, u.prenom, e.poste
    FROM Employe e 
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    WHERE e.statut = 'Actif' 
    ORDER BY u.nom
");
$employesExistants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Variables pour pré-remplir le formulaire (mode modif) ou vides (mode ajout)
$valNom = $employe['nom'] ?? '';
$valPrenom = $employe['prenom'] ?? '';
$valEmail = $employe['email'] ?? '';
$valPoste = $employe['poste'] ?? '';
$valService = $employe['service'] ?? '';
$valRole = $employe['role'] ?? 'Employé';
$valContrat = $employe['type_contrat'] ?? 'CDI';
$valDate = $employe['date_embauche'] ?? '';
$valManager = $employe['manager_referent'] ?? '';

$titrePage = $modeModification ? "Modifier un employé" : "Ajouter un employé";
$texteBouton = $modeModification ? "Enregistrer les modifications" : "Créer l'employé";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $titrePage ?> — Administrateur RH</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">

    <!-- Menu latéral -->
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

    <!-- Zone de contenu -->
    <div class="zone-contenu">

        <div class="entete-page">
            <div>
                <p class="titre-page"><?= $titrePage ?></p>
                <p class="sous-titre-page">Espace Administrateur RH</p>
            </div>
            <a href="employes.php" class="bouton-secondaire">← Retour à la liste</a>
        </div>

        <?php if ($erreur): ?>
            <div class="message-erreur"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>
        
        <?php if ($succes): ?>
            <div class="message-succes"><?= htmlspecialchars($succes) ?></div>
        <?php endif; ?>

        <div class="panneau">
            <form method="POST" action="">
                <div class="grille-cartes" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="champ">
                        <label>Nom</label>
                        <input type="text" name="nom" value="<?= htmlspecialchars($valNom) ?>" required>
                    </div>
                    <div class="champ">
                        <label>Prénom</label>
                        <input type="text" name="prenom" value="<?= htmlspecialchars($valPrenom) ?>" required>
                    </div>
                </div>

                <div class="grille-cartes" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="champ">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($valEmail) ?>" required>
                    </div>
                    <div class="champ">
                        <label>Rôle dans le système</label>
                        <select name="role" required>
                            <option value="Employé" <?= $valRole === 'Employé' ? 'selected' : '' ?>>Employé</option>
                            <option value="Manager" <?= $valRole === 'Manager' ? 'selected' : '' ?>>Manager</option>
                            <option value="Administrateur RH" <?= $valRole === 'Administrateur RH' ? 'selected' : '' ?>>Administrateur RH</option>
                        </select>
                    </div>
                </div>

                <div class="grille-cartes" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="champ">
                        <label>Poste</label>
                        <input type="text" name="poste" value="<?= htmlspecialchars($valPoste) ?>" required>
                    </div>
                    <div class="champ">
                        <label>Service</label>
                        <input type="text" name="service" value="<?= htmlspecialchars($valService) ?>" required>
                    </div>
                </div>

                <div class="grille-cartes" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="champ">
                        <label>Type de contrat</label>
                        <select name="contrat" required>
                            <option value="CDI" <?= $valContrat === 'CDI' ? 'selected' : '' ?>>CDI</option>
                            <option value="CDD" <?= $valContrat === 'CDD' ? 'selected' : '' ?>>CDD</option>
                            <option value="Stage" <?= $valContrat === 'Stage' ? 'selected' : '' ?>>Stage</option>
                            <option value="Alternance" <?= $valContrat === 'Alternance' ? 'selected' : '' ?>>Alternance</option>
                        </select>
                    </div>
                    <div class="champ">
                        <label>Date d'embauche</label>
                        <input type="date" name="date_embauche" value="<?= htmlspecialchars($valDate) ?>" required>
                    </div>
                </div>

                <div class="champ" style="margin-bottom: 24px;">
                    <label>Manager référent (optionnel)</label>
                    <select name="manager_referent">
                        <option value="">-- Aucun --</option>
                        <?php foreach ($employesExistants as $emp): ?>
                            <!-- On exclut l'employé lui-même de la liste si on est en mode modification -->
                            <?php if (!$modeModification || $emp['id_employe'] != $idEmploye): ?>
                                <option value="<?= $emp['id_employe'] ?>" <?= $valManager == $emp['id_employe'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['prenom'] . ' ' . $emp['nom'] . ' (' . $emp['poste'] . ')') ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="bouton-principal"><?= $texteBouton ?></button>
            </form>
        </div>

    </div>
</div>

</body>
</html>