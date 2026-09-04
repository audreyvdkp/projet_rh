<?php
session_start();
session_unset();
session_destroy();

// Redirection vers la page de connexion
// Remplace 'projet_rh' par le VRAI nom du dossier où se trouve ton site
header("Location: /projet_rh/login.php");
exit;
?>