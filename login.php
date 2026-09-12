<?php
session_start();
require_once 'config/db.php';

$erreur = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $mot_de_passe = $_POST['mot_de_passe'];

    // cherche l'utilisateur par son email
    $stmt = $pdo->prepare("SELECT * FROM Utilisateur WHERE email = ? AND statut_compte = 'Actif'");
    $stmt->execute([$email]);
    $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

    // On vérifie que l'utilisateur existe 
    if ($utilisateur && password_verify($mot_de_passe, $utilisateur['mot_de_passe'])) {
        //connexion réussie
        $_SESSION['id_utilisateur'] = $utilisateur['id_utilisateur'];
        $_SESSION['nom'] = $utilisateur['nom'];
        $_SESSION['prenom'] = $utilisateur['prenom'];
        $_SESSION['role'] = $utilisateur['role'];

        // Redirection selon le rôle
        if ($utilisateur['role'] === 'Administrateur RH') {
            header("Location: admin/dashboard.php");
        } elseif ($utilisateur['role'] === 'Manager') {
            header("Location: manager/dashboard.php");
        } else {
            header("Location: employe/dashboard.php");
        }
        exit;
    } else {
        $erreur = "Email ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Système RH</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="page-centree">

    <div class="carte-connexion">
        <div class="logo-connexion">
            <div class="icone-logo">RH</div>
            <p class="titre-connexion">Connexion</p>
            <p class="sous-titre-connexion">Système RH</p>
        </div>

        <?php if ($erreur): ?>
            <p class="message-erreur"><?= htmlspecialchars($erreur) ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="champ">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="nom@entreprise.com" required>
            </div>
           <div class="champ">
    <label>Mot de passe</label>
    <div style="position: relative;">
        <!-- On ajoute un padding à droite pour que le texte ne passe pas sous l'œil -->
        <input type="password" name="mot_de_passe" id="mot_de_passe" required style="padding-right: 40px;">
        
        <!-- Le bouton avec l'icône œil -->
        <button type="button" id="toggle-password" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #6b7280; display: flex; align-items: center; padding: 0;">
    <!-- Icône œil ouvert (caché par défaut car le mot de passe est masqué) -->
    <svg id="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
        <circle cx="12" cy="12" r="3"></circle>
    </svg>
    <!-- Icône œil barré (visible par défaut) -->
    <svg id="eye-off-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
        <line x1="1" y1="1" x2="23" y2="23"></line>
    </svg>
</button>
    </div>
</div>
            <button type="submit" class="bouton-principal">Se connecter</button>
        </form>
    </div>
<script>
    const toggleBtn = document.getElementById('toggle-password');
    const passwordInput = document.getElementById('mot_de_passe');
    const eyeIcon = document.getElementById('eye-icon');
    const eyeOffIcon = document.getElementById('eye-off-icon');

    toggleBtn.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        // Si le mot de passe est visible (text), on montre l'œil ouvert
        if (type === 'text') {
            eyeIcon.style.display = 'block';
            eyeOffIcon.style.display = 'none';
        } 
        // Sinon (password), on montre l'œil barré
        else {
            eyeIcon.style.display = 'none';
            eyeOffIcon.style.display = 'block';
        }
    });
</script>
<script src="assets/js/app.js" defer></script>
</body>
</html>