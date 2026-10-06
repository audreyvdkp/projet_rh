<?php
require_once '../includes/auth.php';
verifierRole('Administrateur RH');
require_once '../config/db.php';

$message = "";
$typeMessage = "";
$mdp_defaut = "123456"; // Mot de passe par défaut généré par le système

// On récupère les données du POST pour les réafficher en cas d'erreur
$nom = $_POST['nom'] ?? '';
$prenom = $_POST['prenom'] ?? '';
$email = $_POST['email'] ?? '';
$telephone = $_POST['telephone'] ?? '';
$poste = $_POST['poste'] ?? '';
$service = $_POST['service'] ?? '';
$type_contrat = $_POST['type_contrat'] ?? 'CDI';
$date_embauche = $_POST['date_embauche'] ?? date('Y-m-d');
$manager_referent = $_POST['manager_referent'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($nom) && !empty($prenom) && !empty($email) && !empty($poste)) {
        try {
            $pdo->beginTransaction();

            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM Utilisateur WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception("Cet email est déjà utilisé par un autre compte.");
            }

            // Créer l'utilisateur avec le mot de passe par défaut
            $mdpHash = password_hash($mdp_defaut, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO Utilisateur (nom, prenom, email, telephone, mot_de_passe, role, statut_compte)
                VALUES (?, ?, ?, ?, ?, 'Employé', 'Actif')
            ");
            $stmt->execute([$nom, $prenom, $email, $telephone, $mdpHash]);
            $idUtilisateur = $pdo->lastInsertId();

            // Créer l'employé
            $stmt = $pdo->prepare("
                INSERT INTO Employe (id_utilisateur, poste, service, type_contrat, date_embauche, manager_referent, statut)
                VALUES (?, ?, ?, ?, ?, ?, 'Actif')
            ");
            $stmt->execute([$idUtilisateur, $poste, $service, $type_contrat, $date_embauche, $manager_referent]);

            $pdo->commit();
            $message = "✅ Employé ajouté avec succès ! <br>🔑 Mot de passe temporaire à communiquer : <strong>$mdp_defaut</strong>";
            $typeMessage = "succes";
            
            // On vide les variables pour le prochain ajout
            $nom = $prenom = $email = $telephone = $poste = $service = '';
            $manager_referent = '';

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "⚠️ Erreur : " . $e->getMessage();
            $typeMessage = "erreur";
        }
    } else {
        $message = "⚠️ Veuillez remplir tous les champs obligatoires (*).";
        $typeMessage = "erreur";
    }
}

// Récupérer la liste des managers
$stmtManagers = $pdo->prepare("
    SELECT u.id_utilisateur, u.nom, u.prenom, e.poste
    FROM Utilisateur u
    INNER JOIN Employe e ON u.id_utilisateur = e.id_utilisateur
    WHERE u.role = 'Manager' AND u.statut_compte = 'Actif'
    ORDER BY u.nom, u.prenom
");
$stmtManagers->execute();
$managers = $stmtManagers->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titrePage ?> — Administrateur RH</title>
    <title>Ajouter un employé — Admin RH</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">
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
    <a href="mon_equipe.php" class="lien-menu">Mon équipe</a>
    <a href="evaluations.php" class="lien-menu">Évaluations</a>
    <a href="demandes.php" class="lien-menu">Demandes</a>
    <a href="criteres.php" class="lien-menu">Critères</a>

    <!-- PIED DE MENU (Profil et Déconnexion) -->
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
                <p class="titre-page">Ajouter un employé</p>
                <p class="sous-titre-page">Créez un nouveau compte employé dans le système</p>
            </div>
            <a href="employes.php" class="bouton-secondaire">← Retour</a>
        </div>

        <?php if ($message): ?>
            <div style="padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; background: <?= $typeMessage === 'erreur' ? '#fee2e2' : '#dcfce7' ?>; color: <?= $typeMessage === 'erreur' ? '#991b1b' : '#166534' ?>; border-left: 4px solid <?= $typeMessage === 'erreur' ? '#dc2626' : '#22c55e' ?>;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="panneau">
            <h3 style="margin-top: 0; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; margin-bottom: 20px;">Informations personnelles</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div class="champ">
                    <label>Nom *</label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($nom) ?>" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                </div>
                <div class="champ">
                    <label>Prénom *</label>
                    <input type="text" name="prenom" value="<?= htmlspecialchars($prenom) ?>" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div class="champ">
                    <label>Email professionnel *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                </div>
                <div class="champ">
                    <label>Téléphone</label>
                    <input type="tel" name="telephone" value="<?= htmlspecialchars($telephone) ?>" style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                </div>
            </div>


            <h3 style="color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; margin: 30px 0 20px 0;">Informations professionnelles</h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div class="champ">
                    <label>Poste *</label>
                    <input type="text" name="poste" value="<?= htmlspecialchars($poste) ?>" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                </div>
                <div class="champ">
                    <label>Service *</label>
                    <input type="text" name="service" value="<?= htmlspecialchars($service) ?>" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div class="champ">
                    <label>Type de contrat *</label>
                    <select name="type_contrat" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                        <option value="CDI" <?= $type_contrat === 'CDI' ? 'selected' : '' ?>>CDI</option>
                        <option value="CDD" <?= $type_contrat === 'CDD' ? 'selected' : '' ?>>CDD</option>
                        <option value="Stage" <?= $type_contrat === 'Stage' ? 'selected' : '' ?>>Stage</option>
                        <option value="Alternance" <?= $type_contrat === 'Alternance' ? 'selected' : '' ?>>Alternance</option>
                        <option value="Interim" <?= $type_contrat === 'Interim' ? 'selected' : '' ?>>Intérim</option>
                        <option value="Freelance" <?= $type_contrat === 'Freelance' ? 'selected' : '' ?>>Freelance</option>
                    </select>
                </div>
                <div class="champ">
                    <label>Date d'embauche</label>
                    <input type="date" name="date_embauche" value="<?= htmlspecialchars($date_embauche) ?>" style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                </div>
            </div>

            <div class="champ" style="margin-bottom: 24px;">
                <label>Manager référent</label>
                <select name="manager_referent" style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                    <option value="">-- Aucun manager --</option>
                    <?php foreach ($managers as $mgr): ?>
                        <option value="<?= $mgr['id_utilisateur'] ?>" <?= $manager_referent == $mgr['id_utilisateur'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mgr['prenom'] . ' ' . $mgr['nom']) ?> - <?= htmlspecialchars($mgr['poste']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="employes.php" class="bouton-secondaire" style="padding: 12px 24px; text-decoration: none;">Annuler</a>
                <button type="submit" class="bouton-principal" style="padding: 12px 24px;">Créer l'employé</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/app.js" defer></script>
</body>
</html>