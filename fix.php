<?php
// Ce script va réécrire le fichier db.php avec le bon port pour forcer la connexion réseau
$code = "<?php\n";
$code .= "\$host = 'mysql-bsm-rh.alwaysdata.net';\n";
$code .= "\$port = '3306';\n"; // C'est cette ligne qui force le réseau et évite le bug
$code .= "\$dbname = 'bsm-rh_rh';\n";
$code .= "\$username = 'bsm-rh';\n";
$code .= "\$password = 'audy0000';\n\n";
$code .= "try {\n";
$code .= "    \$pdo = new PDO(\"mysql:host=\$host;port=\$port;dbname=\$dbname;charset=utf8\", \$username, \$password);\n";
$code .= "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
$code .= "} catch (PDOException \$e) {\n";
$code .= "    die(\"Erreur de connexion : \" . \$e->getMessage());\n";
$code .= "}\n";
$code .= "?>";

file_put_contents('db.php', $code);
echo "✅ Le fichier db.php a été corrigé avec le port 3306. <br><a href='index.php'>Clique ici pour tester le site</a>";
?>