<?php
$file = 'assets/css/style.css';

// Lire le contenu actuel
$content = file_get_contents($file);

// Le CSS à ajouter pour centrer sur les grands écrans
$ajout = "

/* === CENTRAGE SUR GRANDS ÉCRANS === */
body {
    max-width: 1400px;
    margin: 0 auto !important;
    padding: 0 20px;
}

.container, .main-content, .dashboard, .page-content {
    max-width: 1200px;
    margin: 0 auto;
}

/* Centrer les cartes et tableaux */
.carte, .tableau, .section {
    max-width: 100%;
    margin: 0 auto;
}
";

// Ajouter à la fin du fichier
$content .= $ajout;

// Sauvegarder
file_put_contents($file, $content);

echo "✅ CSS modifié avec succès !<br>";
echo "Taille du fichier : " . filesize($file) . " octets<br>";
echo "<a href='login.php'>Voir le résultat</a>";
?>