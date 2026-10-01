<?php
require_once 'config/db.php';

$id_offre = (int)($_GET['id'] ?? 0);
$message = "";
$typeMessage = "";

// Vérifier que l'offre existe et n'est pas expirée
if ($id_offre > 0) {
    $stmt = $pdo->prepare("SELECT titre, date_limite, description, poids_competences, poids_experience, poids_diplome, poids_langues FROM offre_emploi WHERE id_offre = ? AND statut = 'Publiée'");
    $stmt->execute([$id_offre]);
    $offre = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$offre || $offre['date_limite'] < date('Y-m-d')) {
        header('Location: offres.php');
        exit;
    }
} else {
    header('Location: offres.php');
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $lettre = trim($_POST['lettre'] ?? '');
    
    // Gestion du fichier CV
    $cv_nom = null;
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
        $dossier_upload = 'uploads/cv/';
        if (!is_dir($dossier_upload)) {
            mkdir($dossier_upload, 0777, true);
        }
        
        $extension = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
        $cv_nom = uniqid('cv_') . '.' . $extension;
        $chemin_final = $dossier_upload . $cv_nom;
        
        if (!move_uploaded_file($_FILES['cv']['tmp_name'], $chemin_final)) {
            $message = "⚠️ Erreur lors de l'envoi du CV.";
            $typeMessage = "erreur";
        }
    }

    if (empty($prenom) || empty($nom) || empty($email) || empty($telephone)) {
        $message = "⚠️ Veuillez remplir tous les champs obligatoires (*).";
        $typeMessage = "erreur";
    } elseif (!$cv_nom) {
        $message = "️ Veuillez joindre votre CV (PDF ou Image).";
        $typeMessage = "erreur";
    } else {
        try {
            // Calcul du score IA en PHP (simple et fiable)
            $score_ia = 0;
            $description = strtolower($offre['description'] ?? '');
            
            // Liste de mots-clés à chercher dans la description de l'offre
            $mots_cles = [
                'python', 'java', 'javascript', 'php', 'html', 'css', 'sql', 'react', 'angular',
                'photoshop', 'illustrator', 'indesign', 'figma', 'canva', 'django', 'flask',
                'machine learning', 'deep learning', 'data science', 'git', 'docker',
                'communication', 'leadership', 'gestion', 'créativité', 'design', 'marketing',
                'vente', 'comptabilité', 'excel', 'word', 'powerpoint', 'fullstack', 'full stack',
                'frontend', 'backend', 'node.js', 'vue.js', 'mysql', 'postgresql', 'mongodb'
            ];
            
            // Compter combien de mots-clés sont dans la description
            $mots_trouves = 0;
            foreach ($mots_cles as $mot) {
                if (strpos($description, $mot) !== false) {
                    $mots_trouves++;
                }
            }
            
            // Score basé sur le nombre de mots-clés (max 100)
            $score_ia = min(100, $mots_trouves * 5);
            
           // --- DÉBUT CALCUL SCORE IA (VERSION CORRIGÉE) ---
$score_ia = 0;

// 1. Créer le fichier de l'offre dans le dossier module_ia (plus fiable sous Windows)
$dossierModule = __DIR__ . '/module_ia/';
$fichierOffre = $dossierModule . 'offre_temp.txt';

// On écrit la description dans ce fichier
file_put_contents($fichierOffre, $offre['description'] ?? '', LOCK_EX);

// 2. Préparer les chemins
$cheminCV_absolu = realpath($chemin_final);
$cheminScript_absolu = realpath($dossierModule . 'analyser_cv.py');

// 3. Lancer Python
$commande = sprintf('python "%s" "%s" "%s" 2>&1', 
    escapeshellarg($cheminScript_absolu), 
    escapeshellarg($cheminCV_absolu), 
    escapeshellarg($fichierOffre)
);

$sortie = shell_exec($commande);

// 4. Récupérer le score
if (is_numeric(trim($sortie))) {
    $score_ia = (float)trim($sortie);
} else {
    // Si ça plante, on affiche l'erreur pour déboguer (tu pourras l'enlever plus tard)
    $score_ia = 0; 
}

// 5. Nettoyer le fichier temporaire
@unlink($fichierOffre);
// --- FIN CALCUL SCORE IA ---

// Vérifier si cette personne a déjà postulé à cette offre
$stmtVerif = $pdo->prepare("SELECT id_candidature FROM candidature WHERE email = ? AND id_offre = ?");
$stmtVerif->execute([$email, $id_offre]);

if ($stmtVerif->fetch()) {
    $message = "⚠️ Vous avez déjà postulé à cette offre. Une seule candidature par personne est autorisée.";
    $typeMessage = "erreur";
} else {
    // Ici on met l'INSERT et le calcul du score IA
    // ... (ton code d'insertion actuel)
}
// Insérer la candidature (Assure-toi que score_ia est bien dans ta requête INSERT)
$stmt = $pdo->prepare("
    INSERT INTO candidature (id_offre, nom, prenom, email, telephone, cv, lettre_motivation, date_soumission, statut, score_ia)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'En attente', ?)
");
$stmt->execute([$id_offre, $nom, $prenom, $email, $telephone, $cv_nom, $lettre, $score_ia]);
            $stmt = $pdo->prepare("
                INSERT INTO candidature (id_offre, nom, prenom, email, telephone, cv, lettre_motivation, date_soumission, statut, score_ia)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'En attente', ?)
            ");
            $stmt->execute([$id_offre, $nom, $prenom, $email, $telephone, $cv_nom, $lettre, $score_ia]);
            
            $message = "✅ Votre candidature a été envoyée avec succès ! Nous vous contacterons par email si votre profil retient notre attention.";
            $typeMessage = "succes";
            
            $prenom = $nom = $email = $telephone = $lettre = '';
            
        } catch (PDOException $e) {
            $message = "⚠️ Erreur : " . $e->getMessage();
            $typeMessage = "erreur";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Postuler à <?= htmlspecialchars($offre['titre']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background: #f8fafc; color: #1e293b; line-height: 1.6; }
        header { background: white; padding: 20px 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 22px; font-weight: bold; color: #4a5bd4; display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .logo span { background: #4a5bd4; color: white; padding: 5px 10px; border-radius: 8px; }
        .btn-retour { color: #64748b; text-decoration: none; font-weight: 500; }
        .btn-retour:hover { color: #4a5bd4; }
        .container { max-width: 700px; margin: 40px auto; padding: 0 20px; }
        .form-header { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 24px; border-top: 5px solid #4a5bd4; text-align: center; }
        .form-header h1 { font-size: 24px; color: #1e293b; margin-bottom: 8px; }
        .form-container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .champ { margin-bottom: 20px; }
        .champ label { display: block; margin-bottom: 8px; font-weight: 500; color: #1e293b; font-size: 14px; }
        .champ input, .champ textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 15px; font-family: inherit; transition: border 0.2s; }
        .champ input:focus, .champ textarea:focus { outline: none; border-color: #4a5bd4; }
        .champ textarea { resize: vertical; min-height: 120px; }
        .btn-submit { width: 100%; background: #4a5bd4; color: white; border: none; padding: 14px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; margin-top: 10px; }
        .btn-submit:hover { background: #3d4bb8; }
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; }
        .alert-succes { background: #dcfce7; color: #166534; border-left: 4px solid #22c55e; }
        .alert-erreur { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        footer { text-align: center; padding: 30px; color: #94a3b8; font-size: 14px; border-top: 1px solid #e2e8f0; background: white; margin-top: 40px; }
        @media (max-width: 768px) { .form-container { padding: 24px; } }
    </style>
</head>
<body>
    <header>
        <a href="offres.php" class="logo"><span>RH</span> Système RH</a>
        <a href="offre-detail.php?id=<?= $id_offre ?>" class="btn-retour">← Retour à l'offre</a>
    </header>

    <div class="container">
        <div class="form-header">
            <h1>Postuler pour le poste de</h1>
            <p style="font-size: 18px; color: #4a5bd4; font-weight: 600;"><?= htmlspecialchars($offre['titre']) ?></p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $typeMessage ?>"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($typeMessage !== 'succes'): ?>
            <form method="POST" action="" enctype="multipart/form-data" class="form-container">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="champ">
                        <label>Prénom *</label>
                        <input type="text" name="prenom" value="<?= htmlspecialchars($prenom ?? '') ?>" required>
                    </div>
                    <div class="champ">
                        <label>Nom *</label>
                        <input type="text" name="nom" value="<?= htmlspecialchars($nom ?? '') ?>" required>
                    </div>
                </div>
                <div class="champ">
                    <label>Adresse Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required placeholder="exemple@email.com">
                </div>
                <div class="champ">
                    <label>Numéro de téléphone *</label>
                    <input type="tel" name="telephone" value="<?= htmlspecialchars($telephone ?? '') ?>" required placeholder="+229 XX XX XX XX">
                </div>
                <div class="champ">
                    <label>Votre CV (PDF, Word ou Image) *</label>
                    <input type="file" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                    <small style="color: #94a3b8; font-size: 12px; margin-top: 4px; display: block;">Taille maximale recommandée : 5 Mo</small>
                </div>
                <div class="champ">
                    <label>Lettre de motivation (Optionnel)</label>
                    <textarea name="lettre" placeholder="Parlez-nous de vous, de votre parcours et de votre motivation..."><?= htmlspecialchars($lettre ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn-submit">Envoyer ma candidature</button>
            </form>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <p style="font-size: 18px; color: #1e293b; margin-bottom: 20px;">Merci pour votre candidature !</p>
                <a href="offres.php" style="color: #4a5bd4; text-decoration: none; font-weight: 500;">← Voir les autres offres</a>
            </div>
        <?php endif; ?>
    </div>

    <footer>&copy; <?= date('Y') ?> Système RH - Tous droits réservés.</footer>
</body>
</html>