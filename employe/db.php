<?php

// Configuration globale du site BSM groupe
define('ADMIN_EMAIL', 'lead@bsmgroupe.com');
// Tu pourras ajouter d'autres infos ici plus tard (ex: TELEPHONE, ADRESSE)

// Connexion à la base de données

$host = "localhost";
$dbname = "systeme_rh";       
$user = "root";            
$password = "";            

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>