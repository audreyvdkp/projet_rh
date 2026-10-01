<?php
require_once 'config/db.php';

// Filtres
$filtreContrat = $_GET['contrat'] ?? '';
$filtreLieu = $_GET['lieu'] ?? '';
$recherche = $_GET['q'] ?? '';

// Récupérer les offres avec filtres
$sql = "SELECT o.*, COUNT(c.id_candidature) as nb_candidatures 
        FROM offre_emploi o 
        LEFT JOIN candidature c ON o.id_offre = c.id_offre 
        WHERE o.statut = 'Publiée' AND o.date_limite >= CURDATE()";
$params = [];

if ($filtreContrat !== '') {
    $sql .= " AND o.type_contrat = ?";
    $params[] = $filtreContrat;
}
if ($filtreLieu !== '') {
    $sql .= " AND o.lieu LIKE ?";
    $params[] = '%' . $filtreLieu . '%';
}
if ($recherche !== '') {
    $sql .= " AND (o.titre LIKE ? OR o.description LIKE ?)";
    $params[] = '%' . $recherche . '%';
    $params[] = '%' . $recherche . '%';
}

$sql .= " GROUP BY o.id_offre ORDER BY o.date_publication DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$offres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les lieux uniques pour le filtre
$stmtLieux = $pdo->query("SELECT DISTINCT lieu FROM offre_emploi WHERE lieu IS NOT NULL AND lieu != '' AND statut = 'Publiée'");
$lieux = $stmtLieux->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrières - Rejoignez notre équipe</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background: #f8fafc; color: #1e293b; line-height: 1.6; }
        
        /* Header */
        header { background: white; padding: 20px 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .logo { font-size: 22px; font-weight: bold; color: #4a5bd4; display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .logo span { background: #4a5bd4; color: white; padding: 5px 10px; border-radius: 8px; }
        .nav-links { display: flex; gap: 20px; align-items: center; }
        .nav-links a { text-decoration: none; color: #64748b; font-weight: 500; transition: color 0.2s; }
        .nav-links a:hover { color: #4a5bd4; }
        .btn-connexion { color: #4a5bd4 !important; border: 1px solid #4a5bd4; padding: 8px 16px; border-radius: 6px; }
        .btn-connexion:hover { background: #4a5bd4; color: white !important; }

        /* Hero */
        .hero { background: linear-gradient(135deg, #4a5bd4 0%, #6c78e8 100%); color: white; text-align: center; padding: 70px 20px; }
        .hero h1 { font-size: 38px; margin-bottom: 12px; font-weight: 700; }
        .hero p { font-size: 17px; opacity: 0.95; max-width: 600px; margin: 0 auto; }

        /* Container */
        .container { max-width: 1100px; margin: 0 auto; padding: 0 20px; }

        /* Section À propos */
        .section-apropos { background: white; padding: 50px 30px; margin: -30px auto 40px auto; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 1100px; }
        .section-apropos h2 { color: #1e293b; margin-bottom: 16px; font-size: 24px; }
        .section-apropos p { color: #64748b; margin-bottom: 12px; }
        .valeurs { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 24px; }
        .valeur { padding: 20px; background: #f8fafc; border-radius: 8px; border-left: 3px solid #4a5bd4; }
        .valeur h4 { color: #4a5bd4; margin-bottom: 8px; font-size: 15px; }
        .valeur p { color: #64748b; font-size: 14px; margin: 0; }

        /* Filtres */
        .filtres { background: white; padding: 20px; border-radius: 10px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .filtres input, .filtres select { padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; }
        .filtres input { flex: 1; min-width: 200px; }
        .filtres select { min-width: 150px; }
        .btn-filtrer { background: #4a5bd4; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; }
        .btn-filtrer:hover { background: #3d4bb8; }
        .btn-reset { background: transparent; color: #64748b; border: 1px solid #e2e8f0; padding: 10px 16px; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 14px; }

        /* Offres */
        .offres-grid { display: grid; gap: 16px; margin-bottom: 50px; }
        .offre-card { background: white; padding: 24px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border-left: 4px solid #4a5bd4; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; text-decoration: none; color: inherit; }
        .offre-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
        .offre-info h3 { font-size: 18px; color: #1e293b; margin-bottom: 8px; }
        .offre-meta { display: flex; gap: 14px; flex-wrap: wrap; font-size: 13px; color: #64748b; align-items: center; }
        .offre-meta span { display: flex; align-items: center; gap: 4px; }
        .badge { background: #e0e7ff; color: #4338ca; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .candidatures-count { color: #f59e0b; font-size: 12px; font-weight: 500; }
        .btn-voir { background: #4a5bd4; color: white; padding: 10px 20px; border-radius: 6px; font-weight: 500; text-decoration: none; white-space: nowrap; }
        .btn-voir:hover { background: #3d4bb8; }

        .empty-state { text-align: center; padding: 50px 20px; color: #64748b; background: white; border-radius: 10px; }

        /* Footer */
        footer { text-align: center; padding: 30px; color: #94a3b8; font-size: 14px; border-top: 1px solid #e2e8f0; background: white; }

        @media (max-width: 768px) {
            .hero h1 { font-size: 28px; }
            header { padding: 15px 20px; }
            .offre-card { flex-direction: column; align-items: flex-start; }
            .btn-voir { width: 100%; text-align: center; }
        }
.section-contact { background: white; padding: 50px 30px; margin: 40px auto; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 1100px; text-align: center; }
.section-contact h2 { color: #1e293b; margin-bottom: 16px; font-size: 24px; }
.section-contact > p { color: #64748b; margin-bottom: 30px; }
.contact-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; text-align: left; }
.contact-item { padding: 20px; background: #f8fafc; border-radius: 8px; border-top: 3px solid #4a5bd4; }
.contact-item h4 { color: #1e293b; margin-bottom: 8px; font-size: 15px; margin-top: 0;}
.contact-item p { color: #64748b; font-size: 14px; margin: 0; }
.social-links { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 5px; }
.social-links a { color: #4a5bd4; text-decoration: none; font-weight: 500; font-size: 14px; }
.social-links a:hover { text-decoration: underline; }

    </style>
</head>
<body>

    <header>
        <a href="offres.php" class="logo"><span>RH</span> Système RH</a>
        <div class="nav-links">
            <a href="#offres">Nos offres</a>
            <a href="#apropos">À propos</a>
            <a href="#contact">Contact</a>
            <a href="login.php" class="btn-connexion">Espace Employé</a>
        </div>
    </header>

    <section class="hero">
        <h1>Rejoignez notre équipe</h1>
        <p>Découvrez nos opportunités de carrière et donnez un nouvel élan à votre parcours professionnel.</p>
    </section>

    <div class="container">
        
        <!-- Section À propos -->
        <div class="section-apropos" id="apropos">
            <h2>Pourquoi nous rejoindre ?</h2>
            <p>Nous sommes une entreprise dynamique qui place l'humain au cœur de ses préoccupations. Rejoindre notre équipe, c'est intégrer un environnement bienveillant où chaque talent compte.</p>
            <div class="valeurs">
                <div class="valeur">
                    <h4> Ambition</h4>
                    <p>Des projets stimulants qui vous poussent à grandir.</p>
                </div>
                <div class="valeur">
                    <h4>🤝 Collaboration</h4>
                    <p>Une équipe soudée qui avance ensemble vers l'excellence.</p>
                </div>
                <div class="valeur">
                    <h4>🌱 Évolution</h4>
                    <p>Des opportunités de formation et d'évolution continues.</p>
                </div>
                <div class="valeur">
                    <h4>️ Équilibre</h4>
                    <p>Le respect de votre temps et de votre bien-être.</p>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div id="offres">
            <h2 style="margin-bottom: 16px; color: #1e293b;">Nos offres disponibles</h2>
            <form method="GET" action="" class="filtres">
                <input type="text" name="q" placeholder="🔍 Rechercher un poste..." value="<?= htmlspecialchars($recherche) ?>">
                <select name="contrat">
                    <option value="">Tous les contrats</option>
                    <option value="CDI" <?= $filtreContrat === 'CDI' ? 'selected' : '' ?>>CDI</option>
                    <option value="CDD" <?= $filtreContrat === 'CDD' ? 'selected' : '' ?>>CDD</option>
                    <option value="Stage" <?= $filtreContrat === 'Stage' ? 'selected' : '' ?>>Stage</option>
                    <option value="Alternance" <?= $filtreContrat === 'Alternance' ? 'selected' : '' ?>>Alternance</option>
                    <option value="Freelance" <?= $filtreContrat === 'Freelance' ? 'selected' : '' ?>>Freelance</option>
                </select>
                <select name="lieu">
                    <option value="">Tous les lieux</option>
                    <?php foreach ($lieux as $lieu): ?>
                        <option value="<?= htmlspecialchars($lieu) ?>" <?= $filtreLieu === $lieu ? 'selected' : '' ?>><?= htmlspecialchars($lieu) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filtrer">Filtrer</button>
                <?php if ($recherche !== '' || $filtreContrat !== '' || $filtreLieu !== ''): ?>
                    <a href="offres.php" class="btn-reset">Réinitialiser</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Liste des offres -->
        <?php if (empty($offres)): ?>
            <div class="empty-state">
                <h2>Aucune offre disponible pour le moment</h2>
                <p>N'hésitez pas à revenir plus tard.</p>
            </div>
        <?php else: ?>
            <div class="offres-grid">
                <?php foreach ($offres as $offre): ?>
                    <a href="offre-detail.php?id=<?= $offre['id_offre'] ?>" class="offre-card">
                        <div class="offre-info">
                            <h3><?= htmlspecialchars($offre['titre']) ?></h3>
                            <div class="offre-meta">
                                <span>📍 <?= htmlspecialchars($offre['lieu'] ?: 'Non précisé') ?></span>
                                <span class="badge"><?= htmlspecialchars($offre['type_contrat']) ?></span>
                                <span> <?= date('d/m/Y', strtotime($offre['date_limite'])) ?></span>
                                <?php if ($offre['nb_candidatures'] > 0): ?>
                                    <span class="candidatures-count">👥 <?= $offre['nb_candidatures'] ?> candidat(s)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="btn-voir">Voir l'offre →</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- Section Contact -->
<div class="section-contact" id="contact">
    <h2>Nous contacter</h2>
    <p>Une question sur une offre ou sur notre processus de recrutement ? N'hésitez pas à nous joindre !</p>
    <div class="contact-grid">
        <div class="contact-item">
            <h4>📞 Téléphone</h4>
            <p>+229 01 68 45 80 28</p>
        </div>
        <div class="contact-item">
            <h4>📧 contact@bsm.com</h4>
            <p>recrutement@bsmgroupe.com</p>
        </div>
        <div class="contact-item">
            <h4>📍 Adresse</h4>
            <p>Abomey-Calavi, Bénin</p>
        </div>
        <div class="contact-item">
            <h4>🌐 Réseaux sociaux</h4>
            <div class="social-links">
                <a href="#">Facebook</a>
                <a href="#">TikTok</a>
                <a href="#">WhatsApp</a>
            </div>
        </div>
    </div>
</div>

    <footer>
        &copy; <?= date('Y') ?> Système RH - Tous droits réservés.
    </footer>

</body>
</html>