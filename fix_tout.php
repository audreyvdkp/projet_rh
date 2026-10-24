<?php
// Liste de toutes les tables à remettre en minuscules
$tables = [
    'candidature' => 'candidature',
    'critere_evaluation' => 'critere_evaluation',
    'demande' => 'demande',
    'detail_evaluation' => 'detail_evaluation',
    'employe' => 'employe',
    'evaluation' => 'evaluation',
    'objectif' => 'objectif',
    'offre_emploi' => 'offre_emploi',
    'utilisateur' => 'utilisateur'
];

// Ce script va chercher tous les fichiers .php du site
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('.'));
$count = 0;

foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        $content = file_get_contents($path);
        $newContent = str_replace(array_keys($tables), array_values($tables), $content);
        
        // Si le fichier a été modifié, on le sauvegarde
        if ($content !== $newContent) {
            file_put_contents($path, $newContent);
            echo "✅ Corrigé : " . $path . "<br>";
            $count++;
        }
    }
}

echo "<br><b>🎉 Terminé ! $count fichier(s) modifié(s) avec succès.</b><br>";
echo "<a href='index.php'>Clique ici pour aller sur ton site</a>";
?>