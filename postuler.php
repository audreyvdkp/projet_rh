<?php
require_once 'config/db.php';
session_start();

$id_offre = (int)($_GET['id'] ?? 0);

// Vérifier que l'offre existe
if ($id_offre > 0) {
    $stmt = $pdo->prepare("SELECT titre, date_limite, description FROM offre_emploi WHERE id_offre = ? AND statut = 'Publiée'");
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
    // Token anti-doublon
    if (!isset($_POST['token']) || $_POST['token'] !== $_SESSION['form_token']) {
        header('Location: postuler.php?id=' . $id_offre . '&erreur=doublon');
        exit;
    }
    unset($_SESSION['form_token']);
    
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $lettre = trim($_POST['lettre'] ?? '');
    
    $cv_nom = null;
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
        $dossier_upload = 'uploads/cv/';
        if (!is_dir($dossier_upload)) {
            mkdir($dossier_upload, 0777, true);
        }
        $extension = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
        $cv_nom = uniqid('cv_') . '.' . $extension;
        $chemin_final = $dossier_upload . $cv_nom;
        move_uploaded_file($_FILES['cv']['tmp_name'], $chemin_final);
    }

    if (empty($prenom) || empty($nom) || empty($email) || empty($telephone) || !$cv_nom) {
        header('Location: postuler.php?id=' . $id_offre . '&erreur=incomplet');
        exit;
    }

    // Vérification anti-doublon par email
    $stmtVerif = $pdo->prepare("SELECT id_candidature FROM candidature WHERE email = ? AND id_offre = ?");
    $stmtVerif->execute([$email, $id_offre]);
    
    if ($stmtVerif->fetch()) {
        @unlink($chemin_final);
        header('Location: postuler.php?id=' . $id_offre . '&erreur=deja_postule');
        exit;
    }

    // CALCUL DU SCORE IA via fichier (100% fiable)
    $score_ia = 0;
    $dossierModule = __DIR__ . '/module_ia/';
    $fichierOffre = $dossierModule . 'offre_temp.txt';
    $fichierScore = $dossierModule . 'score_resultat.txt';
    
    file_put_contents($fichierOffre, $offre['description'] ?? '', LOCK_EX);
    @unlink($fichierScore); // Supprimer l'ancien résultat
    
    $cheminCV_absolu = realpath($chemin_final);
    $cheminScript_absolu = realpath($dossierModule . 'analyser_cv.py');
    
    $commande = sprintf('python "%s" "%s" "%s" "%s" 2>&1', 
        escapeshellarg($cheminScript_absolu), 
        escapeshellarg($cheminCV_absolu), 
        escapeshellarg($fichierOffre),
        escapeshellarg($fichierScore)
    );
    
    shell_exec($commande);
    
    // Lire le score depuis le fichier
    if (file_exists($fichierScore)) {
        $contenu = trim(file_get_contents($fichierScore));
        if (is_numeric($contenu)) {
            $score_ia = (float)$contenu;
        }
    }
    
    @unlink($fichierOffre);
    @unlink($fichierScore);
    
    // Insertion
    $stmt = $pdo->prepare("
        INSERT INTO candidature (id_offre, nom, prenom, email, telephone, cv, lettre_motivation, date_soumission, statut, score_ia)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'En attente', ?)
    ");
    $stmt->execute([$id_offre, $nom, $prenom, $email, $telephone, $cv_nom, $lettre, $score_ia]);
    
    // Redirection pour éviter les doublons (pattern PRG)
    header('Location: postuler.php?id=' . $id_offre . '&succes=1');
    exit;
}

// Générer un token unique pour ce formulaire
$_SESSION['form_token'] = md5(uniqid(rand(), true));

// Messages d'erreur/succès via URL
$message = "";
$typeMessage = "";
if (isset($_GET['succes'])) {
    $message = "✅ Votre candidature a été envoyée avec succès !";
    $typeMessage = "succes";
} elseif (isset($_GET['erreur'])) {
    switch ($_GET['erreur']) {
        case 'deja_postule':
            $message = "⚠️ Vous avez déjà postulé à cette offre avec cet email.";
            break;
        case 'incomplet':
            $message = "⚠️ Veuillez remplir tous les champs obligatoires.";
            break;
        case 'doublon':
            $message = "️ Soumission en double détectée.";
            break;
        default:
            $message = "⚠️ Une erreur est survenue.";
    }
    $typeMessage = "erreur";
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
        .container { max-width: 700px; margin: 40px auto; padding: 0 20px; }
        .form-header { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 24px; border-top: 5px solid #4a5bd4; text-align: center; }
        .form-header h1 { font-size: 24px; color: #1e293b; margin-bottom: 8px; }
        .form-container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .champ { margin-bottom: 20px; }
        .champ label { display: block; margin-bottom: 8px; font-weight: 500; color: #1e293b; font-size: 14px; }
        .champ input, .champ textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 15px; font-family: inherit; }
        .champ input:focus, .champ textarea:focus { outline: none; border-color: #4a5bd4; }
        .champ textarea { resize: vertical; min-height: 120px; }
        .btn-submit { width: 100%; background: #4a5bd4; color: white; border: none; padding: 14px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background: #3d4bb8; }
        .btn-submit:disabled { background: #94a3b8; cursor: not-allowed; }
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; }
        .alert-succes { background: #dcfce7; color: #166534; border-left: 4px solid #22c55e; }
        .alert-erreur { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .info-ia { background: #eff6ff; padding: 12px 16px; border-radius: 8px; border-left: 4px solid #3b82f6; margin-bottom: 20px; font-size: 13px; color: #1e40af; }
        footer { text-align: center; padding: 30px; color: #94a3b8; font-size: 14px; border-top: 1px solid #e2e8f0; background: white; margin-top: 40px; }
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
            <form method="POST" action="" enctype="multipart/form-data" class="form-container" id="formCandidature">
                <input type="hidden" name="token" value="<?= $_SESSION['form_token'] ?>">
                
                <div class="info-ia">
     <strong>Traitement rapide :</strong> Votre candidature sera analysée automatiquement par notre système pour garantir une réponse dans les plus brefs délais.
</div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="champ">
                        <label>Prénom *</label>
                        <input type="text" name="prenom" required>
                    </div>
                    <div class="champ">
                        <label>Nom *</label>
                        <input type="text" name="nom" required>
                    </div>
                </div>
                <div class="champ">
                    <label>Adresse Email *</label>
                    <input type="email" name="email" required placeholder="exemple@email.com">
                </div>
                <div class="champ">
                    <label>Numéro de téléphone *</label>
                    <input type="tel" name="telephone" required placeholder="+229 XX XX XX XX">
                </div>
                <div class="champ">
                    <label>Votre CV (PDF) *</label>
                    <input type="file" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                </div>
                <div class="champ">
                    <label>Lettre de motivation (Optionnel)</label>
                    <textarea name="lettre" rows="6" placeholder="Parlez-nous de vous..."></textarea>
                </div>
                <button type="submit" class="btn-submit" id="btnSubmit">Envoyer ma candidature</button>
            </form>
            <script>
                document.getElementById('formCandidature').addEventListener('submit', function() {
                    var btn = document.getElementById('btnSubmit');
                    btn.disabled = true;
                    btn.textContent = ' Analyse en cours... (patientez)';
                });
            </script>
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