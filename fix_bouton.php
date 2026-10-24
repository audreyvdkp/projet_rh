<?php
$file = 'admin/candidatures.php';
$content = file_get_contents($file);
$content = str_replace('offres.php', '../index.php', $content);
file_put_contents($file, $content);
echo "✅ Bouton corrigé. <a href='admin/candidatures.php'>Retour</a>";
?>