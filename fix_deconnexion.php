<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('.'));
$count = 0;

foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && $file->getFilename() !== 'fix_deconnexion.php') {
        $path = $file->getPathname();
        $content = file_get_contents($path);
        
        // Remplace logout.php par ../logout.php pour remonter à la racine
        $newContent = str_replace('href="logout.php"', 'href="../logout.php"', $content);
        $newContent = str_replace("href='logout.php'", "href='../logout.php'", $newContent);
        
        if ($content !== $newContent) {
            file_put_contents($path, $newContent);
            echo "✅ Corrigé : " . $path . "<br>";
            $count++;
        }
    }
}

echo "<br><b>Terminé ! $count fichier(s) modifié(s).</b><br>";
echo "<a href='manager/dashboard.php'>Retour au tableau de bord</a>";
?>