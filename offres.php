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
    <style>
    .processus-section {
        padding: 60px 20px;
        background: #f8fafc;
    }
    .processus-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    .section-title {
        text-align: center;
        font-size: 28px;
        color: #1e293b;
        margin-bottom: 10px;
        font-weight: 700;
    }
    .section-subtitle {
        text-align: center;
        color: #64748b;
        margin-bottom: 50px;
        font-size: 16px;
    }
    .processus-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 30px;
    }
    .processus-card {
        background: white;
        padding: 35px 20px 25px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        position: relative;
        border-top: 4px solid #4a5bd4;
    }
    .processus-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 10px 25px rgba(74, 91, 212, 0.15);
    }
    .step-number {
        position: absolute;
        top: -18px;
        left: 50%;
        transform: translateX(-50%);
        background: #4a5bd4;
        color: white;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 16px;
        border: 3px solid #f8fafc;
    }
        .step-icon {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 20px;
    }
    .step-icon svg {
        width: 48px;
        height: 48px;
        stroke: #4a5bd4; /* Couleur bleue du thème */
    }
    .processus-card h3 {
        color: #1e293b;
        font-size: 18px;
        margin-bottom: 12px;
        font-weight: 600;
    }
    .processus-card p {
        color: #64748b;
        font-size: 14px;
        line-height: 1.6;
        margin: 0;
    }
</style>
</head>
<body>

    <header>
       <a href="offres.php" class="logo">
    <img src="assets/images/logo-bsm-groupe - Copie.png" alt="BSM groupe" style="height: 40px; width: auto;">
</a>
        <div class="nav-links">
            <a href="#offres">Nos offres</a>
            <a href="#apropos">À propos</a>
            <a href="#contact">Contact</a>
            <a href="login.php" class="btn-connexion">Portail RH</a>
        </div>
    </header>

    <section class="hero">
        <h1>Rejoignez BSM groupe</h1>
<p style="font-size: 18px; color: #e2e8f0; margin-top: 10px;">Développez votre carrière au sein d'une entreprise leader et innovante.</p>
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
<!-- Section Processus de Recrutement -->
<section class="processus-section">
    <div class="processus-container">
        <h2 class="section-title">Notre processus de recrutement</h2>
        <p class="section-subtitle">Simple, transparent et efficace. Voici les 4 étapes pour rejoindre notre équipe.</p>
        
               <div class="processus-grid">
            <!-- Étape 1 -->
            <div class="processus-card">
                <div class="step-number">1</div>
                <div class="step-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><polyline points="9 15 12 12 15 15"></polyline></svg>
                </div>
                <h3>Candidature en ligne</h3>
                <p>Remplissez notre formulaire simple et joignez votre CV (PDF, Word ou image). C'est rapide et gratuit.</p>
            </div>
            
            <!-- Étape 2 -->
            <div class="processus-card">
                <div class="step-number">2</div>
                <div class="step-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                </div>
                <h3>Analyse du profil</h3>
                <p>Notre équipe RH étudie attentivement votre parcours et vos compétences par rapport aux exigences du poste.</p>
            </div>
            
            <!-- Étape 3 -->
            <div class="processus-card">
                <div class="step-number">3</div>
                <div class="step-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
                <h3>Entretien de découverte</h3>
                <p>Si votre profil correspond, nous vous invitons pour un premier échange convivial afin de faire connaissance.</p>
            </div>
            
            <!-- Étape 4 -->
            <div class="processus-card">
                <div class="step-number">4</div>
                <div class="step-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                </div>
                <h3>Offre d'embauche</h3>
                <p>Après validation finale, nous vous envoyons votre contrat. Bienvenue dans l'équipe !</p>
            </div>
        </div>
    </div>
</section>
    <!-- Section Contact -->
<div class="section-contact" id="contact">
    <h2>Nous contacter</h2>
    <p>Une question sur une offre ou sur notre processus de recrutement ? N'hésitez pas à nous joindre !</p>
    <div class="contact-grid">
        <div class="contact-item">
            <h4>📞 Téléphone</h4>
            <p>+229 01 68 45 80 28</p>
        </div>
 <!-- WhatsApp -->
<div class="contact-card" style="background: #f8fafc; padding: 20px; border-radius: 12px; border-top: 3px solid #4a5bd4; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
        <!-- Icône WhatsApp en VERT -->
        <svg width="24" height="24" viewBox="0 0 24 24" fill="#25D366">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
        <h3 style="color: #1e293b; font-size: 18px; font-weight: 600; margin: 0;">WhatsApp</h3>
    </div>
    <p style="color: #64748b; margin: 0; font-size: 16px;">
        <a href="https://wa.me/22968458028?text=Bonjour%2C%20je%20suis%20int%C3%A9ress%C3%A9%20par%20une%20offre%20chez%20BSM%20groupe." 
           target="_blank" 
           style="color: #1e293b; text-decoration: none;">
            +229 68458028
        </a>
    </p>
</div>

        <div class="contact-item">
            <h4>📧 E-mail</h4>
         <a href="mailto:contact@bsmgroupe.com" style="color: #94a3b8; text-decoration: none; transition: color 0.3s;">
        contact@bsmgroupe.com
        </a>
        </div>
        <div class="contact-item">
            <h4>📍 Adresse</h4>
            <p>Bidossessi, Abomey-Calavi, Bénin</p>
        </div>
        <div class="contact-item">
            <h4>🌐 Réseaux sociaux</h4>
            <div class="social-links">
                <!-- tiktok -->
                 <a href="https://www.tiktok.com/@bsm.groupe" target="_blank"> <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
             </a>
        <!-- linkedin -->
      <a href="https://www.linkedin.com/company/bsm-groupe" target="_blank">
       <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
        </a>
       <!-- facebook -->
<a href="https://www.facebook.com/bsmgroupe" target="_blank">            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg></a>
        <!-- site officiel -->
<a href="https://bsmgroupe.com/" target="_blank"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg></a>
            </div>
        </div>
    </div>
</div>

    <footer>
      <footer>&copy; <?= date('Y') ?> BSM groupe - Tous droits réservés.</footer>
    </footer>

</body>
</html>