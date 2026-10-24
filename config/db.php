
<?php
$host = 'mysql-bsm-rh.alwaysdata.net';
$dbname = 'bsm-rh_rh';
$username = 'bsm-rh';
$password = 'audy0000';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>