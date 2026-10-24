<?php
$file = 'offre-detail.php';
$content = file_get_contents($file);

// Remplace le lien de retour vers index.php au lieu de offres.php
$newContent = str_replace('href="offres.php"', 'href="index.php"', $content);
$newContent = str_replace("href='offres.php'", "href='index.php'", $newContent);

file_put_contents($file, $newContent);

echo "✅ Lien 'Retour aux offres' corrigé.<br>";
echo "<a href='offre-detail.php?id=5'>Tester le lien</a>";
?>