<?php
require_once '../includes/auth.php';
verifierRole('Employé');
require_once '../config/db.php';

// Récupérer l'id_employe connecté
$stmt = $pdo->prepare("SELECT id_employe FROM Employe WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['id_utilisateur']]);
$idEmploye = $stmt->fetchColumn();

$message = "";
$typeMessage = "";

// Traitement du formulaire de nouvelle demande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'creer_demande') {
    $type = $_POST['type'] ?? '';
    $dateDebut = $_POST['date_debut'] ?? '';
    $dateFin = $_POST['date_fin'] ?? '';
    $motif = trim($_POST['motif'] ?? '');
    
    if (!empty($type) && !empty($dateDebut) && !empty($dateFin)) {
        $stmt = $pdo->prepare("
            INSERT INTO Demande (id_employe, type, date_debut, date_fin, motif, date_demande, statut)
            VALUES (?, ?, ?, ?, ?, NOW(), 'En attente')
        ");
        $stmt->execute([$idEmploye, $type, $dateDebut, $dateFin, $motif]);
        
        $message = "Votre demande a été envoyée avec succès. Elle sera traitée par votre manager.";
        $typeMessage = "succes";
    } else {
        $message = "Veuillez remplir tous les champs obligatoires.";
        $typeMessage = "erreur";
    }
}

// Récupérer toutes les demandes de l'employé
$stmt = $pdo->prepare("
    SELECT id_demande, type, date_debut, date_fin, motif, date_demande, statut
    FROM Demande
    WHERE id_employe = ?
    ORDER BY date_demande DESC
");
$stmt->execute([$idEmploye]);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$total = count($demandes);
$enAttente = count(array_filter($demandes, fn($d) => $d['statut'] === 'En attente'));
$acceptees = count(array_filter($demandes, fn($d) => $d['statut'] === 'Accepté'));
$refusees = count(array_filter($demandes, fn($d) => $d['statut'] === 'Refusé'));

// Mode "nouvelle demande"
$modeNouvelle = isset($_GET['action']) && $_GET['action'] === 'nouvelle';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes demandes — Espace Employé</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">
    <div class="menu-lateral">
        <div class="logo-menu" style="padding: 20px 15px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
    <div style="background: white; border-radius: 10px; padding: 8px 12px; display: inline-block; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
        <img src="../assets/images/logo-bsm-groupe - Copie.png" alt="BSM groupe" style="height: 35px; width: auto; display: block;">
    </div>
    <!-- <p style="color: white; font-size: 13px; font-weight: 600; margin: 0; letter-spacing: 1px;">BSM groupe</p> -->
</div>
        <a href="dashboard.php" class="lien-menu">Tableau de bord</a>
        <a href="objectifs.php" class="lien-menu">Mes objectifs</a>
        <a href="demandes.php" class="lien-menu actif">Mes demandes</a>
        <a href="evaluations.php" class="lien-menu">Mes évaluations</a>
        <a href="profil.php" class="lien-menu">Mon profil</a>
        <div class="pied-menu">
            <div class="avatar-mini"><?= strtoupper(substr($_SESSION['prenom'],0,1) . substr($_SESSION['nom'],0,1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['prenom']) ?></span>
            <a href="../logout.php" class="icone-deconnexion" title="Déconnexion">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </a>
        </div>
    </div>

    <div class="zone-contenu">
      <div class="entete-page" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <p class="titre-page">Mes demandes</p>
        <p class="sous-titre-page">Gérez vos demandes de congés et permissions</p>
    </div>
    <?php if (!$modeNouvelle): ?>
    <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
        <a href="demandes.php?action=nouvelle" class="bouton-principal" style="padding: 10px 20px; background: #4a5bd4; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; width: auto;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Nouvelle demande
        </a>
    </div>
<?php endif; ?>
</div>

        <?php if ($message): ?>
            <div class="message-<?= $typeMessage === 'erreur' ? 'erreur' : 'succes' ?>" style="padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; background: <?= $typeMessage === 'erreur' ? '#fee2e2' : '#dcfce7' ?>; color: <?= $typeMessage === 'erreur' ? '#991b1b' : '#166534' ?>; border-left: 4px solid <?= $typeMessage === 'erreur' ? '#dc2626' : '#22c55e' ?>;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($modeNouvelle): ?>
            <!-- Formulaire de nouvelle demande -->
            <div class="panneau">
                <p class="titre-panneau">Créer une nouvelle demande</p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="creer_demande">
                    
                    <div class="champ" style="margin-bottom: 16px;">
                        <label>Type de demande *</label>
                        <select name="type" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                            <option value="">-- Sélectionner --</option>
                            <option value="Congé annuel">Congé annuel</option>
                            <option value="Congé maladie">Congé maladie</option>
                            <option value="Permission">Permission</option>
                            <option value="Télétravail">Télétravail</option>
                            <option value="Formation">Formation</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div class="champ">
                            <label>Date de début *</label>
                            <input type="date" name="date_debut" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                        </div>
                        <div class="champ">
                            <label>Date de fin *</label>
                            <input type="date" name="date_fin" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                        </div>
                    </div>

                    <div class="champ" style="margin-bottom: 20px;">
                        <label>Motif (optionnel)</label>
                        <textarea name="motif" rows="4" placeholder="Expliquez brièvement la raison de votre demande..." style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px;"></textarea>
                    </div>

                    <div style="display: flex; gap: 12px;">
                        <a href="demandes.php" class="bouton-secondaire" style="padding: 10px 20px; text-decoration: none;">Annuler</a>
                        <button type="submit" class="bouton-principal" style="padding: 10px 20px;">Envoyer la demande</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Statistiques -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <div class="carte-stat" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
                    <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">Total</p>
                    <p style="font-size: 32px; font-weight: 700; color: #4a5bd4; margin: 0;"><?= $total ?></p>
                </div>
                <div class="carte-stat" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
                    <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">En attente</p>
                    <p style="font-size: 32px; font-weight: 700; color: #f59e0b; margin: 0;"><?= $enAttente ?></p>
                </div>
                <div class="carte-stat" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
                    <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">Acceptées</p>
                    <p style="font-size: 32px; font-weight: 700; color: #22c55e; margin: 0;"><?= $acceptees ?></p>
                </div>
                <div class="carte-stat" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
                    <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">Refusées</p>
                    <p style="font-size: 32px; font-weight: 700; color: #ef4444; margin: 0;"><?= $refusees ?></p>
                </div>
            </div>

            <!-- Liste des demandes -->
            <div class="panneau">
                <p class="titre-panneau">Historique de mes demandes</p>
                
                <?php if (empty($demandes)): ?>
                    <p style="color: #9ca3af; text-align: center; padding: 40px 0;">Aucune demande effectuée.</p>
                <?php else: ?>
                    <?php foreach ($demandes as $dem): 
                        $couleur = match($dem['statut']) {
                            'Accepté' => '#22c55e',
                            'Refusé' => '#ef4444',
                            'En attente' => '#f59e0b',
                            default => '#6b7280'
                        };
                    ?>
                        <div style="padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                <h3 style="margin: 0; font-size: 16px; font-weight: 600;"><?= htmlspecialchars($dem['type']) ?></h3>
                                <span style="background: <?= $couleur ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">
                                    <?= htmlspecialchars($dem['statut']) ?>
                                </span>
                            </div>
                            <div style="display: flex; gap: 20px; margin-bottom: 8px; font-size: 13px; color: #6b7280;">
                                <span>📅 Du <?= date('d/m/Y', strtotime($dem['date_debut'])) ?></span>
                                <span>au <?= date('d/m/Y', strtotime($dem['date_fin'])) ?></span>
                            </div>
                            <?php if (!empty($dem['motif'])): ?>
                                <p style="margin: 8px 0 0 0; color: #6b7280; font-size: 14px; font-style: italic;">
                                    "<?= htmlspecialchars($dem['motif']) ?>"
                                </p>
                            <?php endif; ?>
                            <p style="margin: 8px 0 0 0; font-size: 12px; color: #9ca3af;">
                                Demandé le <?= date('d/m/Y à H:i', strtotime($dem['date_demande'])) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>