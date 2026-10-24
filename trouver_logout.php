<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('.'));

foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && $file->getFilename() !== 'trouver_logout.php') {
        $content = file_get_contents($file->getPathname());
        
        // Si le fichier contient "logout.php", on l'affiche
        if (strpos($content, 'logout.php') !== false) {
            echo "📁 Fichier : <b>" . $file->getPathname() . "</b><br>";
            $lines = explode("\n", $content);
            foreach($lines as $num => $line) {
                if (strpos($line, 'logout.php') !== false) {
                    echo "➡️ Ligne " . ($num+1) . " : " . htmlspecialchars(trim($line)) . "<br><br>";
                }
            }
        }
    }
}
?>