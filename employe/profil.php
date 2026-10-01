
<?php
require_once '../includes/auth.php';
verifierRole('Employé');
require_once '../config/db.php';

$message = "";
$typeMessage = "";
$peutChangerMdp = true;
$joursRestants = 0;

// Récupérer les infos complètes de l'employé
$stmt = $pdo->prepare("
    SELECT e.poste, e.service, e.date_embauche, e.type_contrat,
           u.nom, u.prenom, u.email, u.telephone, u.date_dernier_changement_mdp
    FROM Employe e
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    WHERE e.id_utilisateur = ?
");
$stmt->execute([$_SESSION['id_utilisateur']]);
$profil = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérifier la règle des 7 jours
if ($profil['date_dernier_changement_mdp']) {
    $timestampDernier = strtotime($profil['date_dernier_changement_mdp']);
    $joursEcoules = (time() - $timestampDernier) / 86400;
    if ($joursEcoules < 7) {
        $peutChangerMdp = false;
        $joursRestants = ceil(7 - $joursEcoules);
    }
}

// Traitement du changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'changer_mdp') {
    if (!$peutChangerMdp) {
        $message = "Vous devez attendre encore $joursRestants jour(s) avant de pouvoir changer votre mot de passe.";
        $typeMessage = "erreur";
    } else {
        $ancienMdp = $_POST['ancien_mdp'] ?? '';
        $nouveauMdp = $_POST['nouveau_mdp'] ?? '';
        $confirmationMdp = $_POST['confirmation_mdp'] ?? '';

        $stmt = $pdo->prepare("SELECT mot_de_passe FROM Utilisateur WHERE id_utilisateur = ?");
        $stmt->execute([$_SESSION['id_utilisateur']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (password_verify($ancienMdp, $user['mot_de_passe'])) {
            if (strlen($nouveauMdp) >= 6 && $nouveauMdp === $confirmationMdp) {
                $mdpHash = password_hash($nouveauMdp, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE Utilisateur SET mot_de_passe = ?, date_dernier_changement_mdp = NOW() WHERE id_utilisateur = ?");
                $update->execute([$mdpHash, $_SESSION['id_utilisateur']]);
                $message = "Votre mot de passe a été modifié avec succès. Prochain changement possible dans 7 jours.";
                $typeMessage = "succes";
                $peutChangerMdp = false; // Met à jour l'état pour la session
                $joursRestants = 7;
            } else {
                $message = "Le mot de passe doit faire au moins 6 caractères et les confirmations doivent correspondre.";
                $typeMessage = "erreur";
            }
        } else {
            $message = "L'ancien mot de passe est incorrect.";
            $typeMessage = "erreur";
        }
    }
}

// Récupérer le nom du manager
$stmtManager = $pdo->prepare("
    SELECT u.prenom, u.nom FROM Employe e 
    LEFT JOIN Utilisateur u ON e.manager_referent = (SELECT id_employe FROM Employe WHERE id_utilisateur = u.id_utilisateur LIMIT 1) 
    WHERE e.id_utilisateur = ?
");
// Simplification pour le manager référent
$stmtManager = $pdo->prepare("
    SELECT u.prenom, u.nom FROM Employe emp
    LEFT JOIN Employe man_emp ON emp.manager_referent = man_emp.id_employe
    LEFT JOIN Utilisateur u ON man_emp.id_utilisateur = u.id_utilisateur
    WHERE emp.id_utilisateur = ?
");
$stmtManager->execute([$_SESSION['id_utilisateur']]);
$manager = $stmtManager->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil — <?= htmlspecialchars($profil['prenom']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">
    <div class="menu-lateral">
        <div class="logo-menu"><div class="icone-logo-menu">RH</div><span>Système RH</span></div>
        <a href="dashboard.php" class="lien-menu">Tableau de bord</a>
        <a href="objectifs.php" class="lien-menu">Mes objectifs</a>
        <a href="demandes.php" class="lien-menu">Mes demandes</a>
        <a href="evaluations.php" class="lien-menu">Mes évaluations</a>
        <a href="profil.php" class="lien-menu actif">Mon profil</a>
        <div class="pied-menu">
            <div class="avatar-mini"><?= strtoupper(substr($_SESSION['prenom'],0,1) . substr($_SESSION['nom'],0,1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['prenom']) ?></span>
            <a href="../logout.php" class="icone-deconnexion" title="Déconnexion">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </a>
        </div>
    </div>

    <div class="zone-contenu">
        
        <?php if ($message): ?>
            <div style="padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; background: <?= $typeMessage === 'erreur' ? '#fee2e2' : '#dcfce7' ?>; color: <?= $typeMessage === 'erreur' ? '#991b1b' : '#166534' ?>; border-left: 4px solid <?= $typeMessage === 'erreur' ? '#dc2626' : '#22c55e' ?>;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Bannière de profil style LinkedIn -->
        <div style="background: linear-gradient(135deg, #4a5bd4 0%, #6c78e8 100%); border-radius: 12px; padding: 30px; color: white; position: relative; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: flex-end; gap: 24px;">
                <div style="width: 100px; height: 100px; border-radius: 50%; background: white; color: #4a5bd4; display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: bold; border: 4px solid rgba(255,255,255,0.3); box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <?= strtoupper(substr($profil['prenom'],0,1) . substr($profil['nom'],0,1)) ?>
                </div>
                <div style="padding-bottom: 10px;">
                    <h1 style="margin: 0; font-size: 28px; font-weight: 700;"><?= htmlspecialchars($profil['prenom'] . ' ' . $profil['nom']) ?></h1>
                    <p style="margin: 5px 0 0 0; font-size: 16px; opacity: 0.9;"><?= htmlspecialchars($profil['poste']) ?> · <?= htmlspecialchars($profil['type_contrat'] ?? 'CDI') ?></p>
                </div>
            </div>
        </div>

        <!-- Grille de contenu -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
            
            <!-- Colonne Gauche : Infos Pro -->
            <div>
                <div class="panneau" style="margin-bottom: 24px;">
                    <h3 style="margin-top: 0; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; font-size: 18px;">📋 Informations professionnelles</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; font-weight: 600;">Service</p>
                            <p style="font-weight: 600; font-size: 15px; color: #1f2937;"><?= htmlspecialchars($profil['service']) ?></p>
                        </div>
                        <div>
                            <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; font-weight: 600;">Type de contrat</p>
                            <p style="font-weight: 600; font-size: 15px; color: #1f2937;"><?= htmlspecialchars($profil['type_contrat'] ?? 'CDI') ?></p>
                        </div>
                        <div>
                            <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; font-weight: 600;">Date d'embauche</p>
                            <p style="font-weight: 600; font-size: 15px; color: #1f2937;"><?= date('d/m/Y', strtotime($profil['date_embauche'])) ?></p>
                        </div>
                        <div>
                            <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; font-weight: 600;">Manager référent</p>
                            <p style="font-weight: 600; font-size: 15px; color: #1f2937;">
                                <?= $manager ? htmlspecialchars($manager['prenom'] . ' ' . $manager['nom']) : 'Non assigné' ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colonne Droite : Contact & Sécurité -->
            <div>
                <div class="panneau" style="margin-bottom: 24px;">
                    <h3 style="margin-top: 0; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; font-size: 18px;">📞 Contact</h3>
                    <div style="display: grid; gap: 16px;">
                        <div>
                            <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; font-weight: 600;">Email</p>
                            <p style="font-weight: 500; font-size: 14px; color: #4a5bd4; word-break: break-all;"><?= htmlspecialchars($profil['email']) ?></p>
                        </div>
                        <div>
                            <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; font-weight: 600;">Téléphone</p>
                            <p style="font-weight: 500; font-size: 14px; color: #1f2937;"><?= htmlspecialchars($profil['telephone'] ?: 'Non renseigné') ?></p>
                        </div>
                    </div>
                </div>

                <!-- Section Sécurité avec bouton toggle -->
                <div class="panneau">
                    <h3 style="margin-top: 0; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; font-size: 18px;">🔒 Sécurité</h3>
                    
                    <?php if (!$peutChangerMdp): ?>
                        <div style="background: #f3f4f6; padding: 12px; border-radius: 6px; font-size: 13px; color: #6b7280; text-align: center;">
                            <p style="margin: 0;">⏳ Vous pourrez changer votre mot de passe dans <strong><?= $joursRestants ?> jour(s)</strong>.</p>
                        </div>
                    <?php else: ?>
                        <button id="btn-afficher-mdp" class="bouton-principal" style="width: 100%; padding: 12px; margin-bottom: 16px; background: #1f2937;">
                            Modifier mon mot de passe
                        </button>

                        <div id="form-mdp" style="display: none; margin-top: 16px; padding-top: 16px; border-top: 1px dashed #e5e7eb;">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="changer_mdp">
                                <div style="margin-bottom: 12px;">
                                    <label style="font-size: 12px; font-weight: 600; color: #6b7280; display: block; margin-bottom: 4px;">Ancien mot de passe</label>
                                    <input type="password" name="ancien_mdp" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; box-sizing: border-box;">
                                </div>
                                <div style="margin-bottom: 12px;">
                                    <label style="font-size: 12px; font-weight: 600; color: #6b7280; display: block; margin-bottom: 4px;">Nouveau mot de passe (min. 6 caractères)</label>
                                    <input type="password" name="nouveau_mdp" required minlength="6" style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; box-sizing: border-box;">
                                </div>
                                <div style="margin-bottom: 16px;">
                                    <label style="font-size: 12px; font-weight: 600; color: #6b7280; display: block; margin-bottom: 4px;">Confirmer le nouveau mot de passe</label>
                                    <input type="password" name="confirmation_mdp" required minlength="6" style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; box-sizing: border-box;">
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <button type="button" id="btn-annuler-mdp" class="bouton-secondaire" style="flex: 1; padding: 10px;">Annuler</button>
                                    <button type="submit" class="bouton-principal" style="flex: 1; padding: 10px;">Enregistrer</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // Script pour afficher/masquer le formulaire de mot de passe
    document.getElementById('btn-afficher-mdp').addEventListener('click', function() {
        document.getElementById('form-mdp').style.display = 'block';
        this.style.display = 'none';
    });
    document.getElementById('btn-annuler-mdp').addEventListener('click', function() {
        document.getElementById('form-mdp').style.display = 'none';
        document.getElementById('btn-afficher-mdp').style.display = 'block';
    });
</script>

</body>
</html>