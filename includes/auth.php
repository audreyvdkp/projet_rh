<?php
// Vérifie que l'utilisateur est connecté ET qu'il a le bon rôle

function verifierRole($roleAttendu) {
    session_start();

    // Pas connecté du tout → retour à la connexion
    if (!isset($_SESSION['id_utilisateur'])) {
        header("Location: /projet_rh/login.php");
        exit;
    }

    // Connecté, mais mauvais rôle → retour à la connexion aussi
    if ($_SESSION['role'] !== $roleAttendu) {
        header("Location: /projet_rh/login.php");
        exit;
    }
}
?>