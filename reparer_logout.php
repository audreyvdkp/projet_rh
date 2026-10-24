<?php
// Contenu correct pour un fichier de déconnexion
$code = "<?php\n";
$code .= "session_start();\n";
$code .= "session_destroy();\n";
$code .= "header('Location: login.php');\n";
$code .= "exit;\n";
$code .= "?>";

file_put_contents('logout.php', $code);

echo "✅ Fichier logout.php recréé avec succès.<br>";
echo "Taille : " . filesize('logout.php') . " octets<br>";
echo "<a href='login.php'>Aller à la page de connexion</a>";
?>