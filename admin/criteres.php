<?php
require_once '../includes/auth.php';
verifierRole('Administrateur RH');
require_once '../config/db.php';

$message = "";
$typeMessage = "";

// --- TRAITEMENT DES ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'ajouter') {
        $nom = trim($_POST['nom_critere']);
        $description = trim($_POST['description']);
        $poids = (float)$_POST['poids'];
        $posteConcerne = trim($_POST['poste_concerne']);
        $noteMaximale = (int)$_POST['note_maximale'];
        $actif = isset($_POST['actif']) ? 1 : 0;

        if ($nom === "" || $poids <= 0 || $poids > 100) {
            $message = "Nom requis et poids doit être entre 0.01 et 100.";
            $typeMessage = "erreur";
        } else {
            // Récupérer tous les postes
            $stmtPostes = $pdo->query("SELECT DISTINCT poste FROM Employe WHERE poste IS NOT NULL AND poste != '' ORDER BY poste");
            $postes = $stmtPostes->fetchAll(PDO::FETCH_COLUMN);
            
            // Vérifier la règle des 100% pour chaque poste concerné
            $postesConcernes = ($posteConcerne === '' || $posteConcerne === 'Tous') ? $postes : [$posteConcerne];
            $erreurTotal = false;
            $messageErreur = "";
            
            foreach ($postesConcernes as $poste) {
                // Calculer le total actuel pour ce poste
                $stmtTotal = $pdo->prepare("
                    SELECT COALESCE(SUM(poids), 0) as total 
                    FROM critere_evaluation 
                    WHERE actif = 1 AND (poste_concerne = '' OR poste_concerne = ? OR poste_concerne IS NULL)
                ");
                $stmtTotal->execute([$poste]);
                $totalActuel = (float)$stmtTotal->fetchColumn();
                
                // Ajouter les critères spécifiques à ce poste
                if ($posteConcerne === $poste && $posteConcerne !== '') {
                    // On ajoute le nouveau critère
                    if (($totalActuel + $poids) > 100.01) { // 0.01 de tolérance pour les flottants
                        $erreurTotal = true;
                        $messageErreur = "Impossible d'activer ce critère : le total pour le poste '{$poste}' dépasserait 100% (Actuel: {$totalActuel}%, Nouveau: {$poids}%).";
                        break;
                    }
                } else {
                    // Critère pour "Tous"
                    if (($totalActuel + $poids) > 100.01) {
                        $erreurTotal = true;
                        $messageErreur = "Impossible d'activer ce critère : le total pour le poste '{$poste}' dépasserait 100% (Actuel: {$totalActuel}%, Nouveau: {$poids}%).";
                        break;
                    }
                }
            }
            
            if ($erreurTotal) {
                $message = $messageErreur;
                $typeMessage = "erreur";
            } else {
                $stmt = $pdo->prepare("INSERT INTO critere_evaluation (nom_critere, description, poids, poste_concerne, note_maximale, actif) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nom, $description, $poids, $posteConcerne, $noteMaximale, $actif]);
                $message = "Critère ajouté avec succès.";
                $typeMessage = "succes";
            }
        }
    } 
    elseif ($action === 'modifier') {
        $id = (int)$_POST['id_critere'];
        $nom = trim($_POST['nom_critere']);
        $description = trim($_POST['description']);
        $poids = (float)$_POST['poids'];
        $posteConcerne = trim($_POST['poste_concerne']);
        $noteMaximale = (int)$_POST['note_maximale'];
        $actif = isset($_POST['actif']) ? 1 : 0;
        
        if ($nom === "" || $poids <= 0 || $poids > 100) {
            $message = "Nom requis et poids doit être entre 0.01 et 100.";
            $typeMessage = "erreur";
        } else {
            // Récupérer l'ancien poste concerné
            $stmtOld = $pdo->prepare("SELECT poste_concerne, poids, actif FROM critere_evaluation WHERE id_critere = ?");
            $stmtOld->execute([$id]);
            $oldCritere = $stmtOld->fetch(PDO::FETCH_ASSOC);
            
            // Récupérer tous les postes
            $stmtPostes = $pdo->query("SELECT DISTINCT poste FROM Employe WHERE poste IS NOT NULL AND poste != '' ORDER BY poste");
            $tousLesPostes = $stmtPostes->fetchAll(PDO::FETCH_COLUMN);
            
            // Déterminer quels postes sont affectés par cette modification
            $ancienPoste = $oldCritere['poste_concerne'];
            $postesImpactes = array_unique(array_merge(
                ($ancienPoste === '' || $ancienPoste === 'Tous') ? $tousLesPostes : [$ancienPoste],
                ($posteConcerne === '' || $posteConcerne === 'Tous') ? $tousLesPostes : [$posteConcerne]
            ));
            
            $erreurTotal = false;
            $messageErreur = "";
            
            foreach ($postesImpactes as $poste) {
                // Calculer le total pour ce poste SANS le critère qu'on modifie
                $stmtTotal = $pdo->prepare("
                    SELECT COALESCE(SUM(poids), 0) as total 
                    FROM critere_evaluation 
                    WHERE actif = 1 
                    AND id_critere != ?
                    AND (poste_concerne = '' OR poste_concerne = ? OR poste_concerne IS NULL)
                ");
                $stmtTotal->execute([$id, $poste]);
                $totalSansCritere = (float)$stmtTotal->fetchColumn();
                
                // Vérifier si ce critère s'applique à ce poste
                $critereSapplique = false;
                if ($posteConcerne === '' || $posteConcerne === 'Tous') {
                    $critereSapplique = true;
                } elseif ($posteConcerne === $poste) {
                    $critereSapplique = true;
                }
                
                if ($critereSapplique && $actif) {
                    if (($totalSansCritere + $poids) > 100.01) {
                        $erreurTotal = true;
                        $messageErreur = "Impossible : le total pour le poste '{$poste}' dépasserait 100% (Actuel sans ce critère: {$totalSansCritere}%, Avec: {$poids}%).";
                        break;
                    }
                }
            }
            
            if ($erreurTotal) {
                $message = $messageErreur;
                $typeMessage = "erreur";
            } else {
                $stmt = $pdo->prepare("UPDATE critere_evaluation SET nom_critere = ?, description = ?, poids = ?, poste_concerne = ?, note_maximale = ?, actif = ? WHERE id_critere = ?");
                $stmt->execute([$nom, $description, $poids, $posteConcerne, $noteMaximale, $actif, $id]);
                $message = "Critère modifié avec succès.";
                $typeMessage = "succes";
            }
        }
    }
    elseif ($action === 'toggle') {
        $id = (int)$_POST['id_critere'];
        $stmt = $pdo->prepare("SELECT poids, actif, poste_concerne FROM critere_evaluation WHERE id_critere = ?");
        $stmt->execute([$id]);
        $critere = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($critere) {
            $nouveauStatut = $critere['actif'] ? 0 : 1;
            
            if ($nouveauStatut === 1) {
                // Récupérer tous les postes
                $stmtPostes = $pdo->query("SELECT DISTINCT poste FROM Employe WHERE poste IS NOT NULL AND poste != '' ORDER BY poste");
                $tousLesPostes = $stmtPostes->fetchAll(PDO::FETCH_COLUMN);
                
                $postesConcernes = ($critere['poste_concerne'] === '' || $critere['poste_concerne'] === 'Tous') 
                    ? $tousLesPostes 
                    : [$critere['poste_concerne']];
                
                foreach ($postesConcernes as $poste) {
                    $stmtTotal = $pdo->prepare("
                        SELECT COALESCE(SUM(poids), 0) as total 
                        FROM critere_evaluation 
                        WHERE actif = 1 
                        AND id_critere != ?
                        AND (poste_concerne = '' OR poste_concerne = ? OR poste_concerne IS NULL)
                    ");
                    $stmtTotal->execute([$id, $poste]);
                    $totalActuel = (float)$stmtTotal->fetchColumn();
                    
                    if (($totalActuel + $critere['poids']) > 100.01) {
                        $message = "Activation impossible : le total pour le poste '{$poste}' dépasserait 100%.";
                        $typeMessage = "erreur";
                        break;
                    }
                }
                
                if ($message === "") {
                    $pdo->prepare("UPDATE critere_evaluation SET actif = ? WHERE id_critere = ?")->execute([$nouveauStatut, $id]);
                }
            } else {
                $pdo->prepare("UPDATE critere_evaluation SET actif = ? WHERE id_critere = ?")->execute([$nouveauStatut, $id]);
            }
        }
    }
    elseif ($action === 'supprimer') {
        $id = (int)$_POST['id_critere'];
        $pdo->prepare("DELETE FROM critere_evaluation WHERE id_critere = ?")->execute([$id]);
        $message = "Critère supprimé.";
        $typeMessage = "succes";
    }
}

// --- RÉCUPÉRATION DES DONNÉES ---
$stmt = $pdo->query("SELECT * FROM critere_evaluation ORDER BY poste_concerne ASC, actif DESC, nom_critere ASC");
$criteres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer tous les postes distincts
$stmtPostes = $pdo->query("SELECT DISTINCT poste FROM Employe WHERE poste IS NOT NULL AND poste != '' ORDER BY poste");
$tousLesPostes = $stmtPostes->fetchAll(PDO::FETCH_COLUMN);

// Calculer le total des poids actifs pour CHAQUE poste
$totauxParPoste = [];
foreach ($tousLesPostes as $poste) {
    $stmtTotal = $pdo->prepare("
        SELECT COALESCE(SUM(poids), 0) as total 
        FROM critere_evaluation 
        WHERE actif = 1 AND (poste_concerne = '' OR poste_concerne = ? OR poste_concerne IS NULL)
    ");
    $stmtTotal->execute([$poste]);
    $total = (float)$stmtTotal->fetchColumn();
    $totauxParPoste[$poste] = round($total, 2);
}

// Mode modification ?
$modeModification = false;
$critereAModifier = null;
if (isset($_GET['modifier']) && !empty($_GET['modifier'])) {
    $idAModifier = (int)$_GET['modifier'];
    $stmt = $pdo->prepare("SELECT * FROM critere_evaluation WHERE id_critere = ?");
    $stmt->execute([$idAModifier]);
    $critereAModifier = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($critereAModifier) {
        $modeModification = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des critères — Administrateur RH</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">

    <!-- Menu latéral -->
    <div class="menu-lateral">
        <div class="logo-menu">
            <div class="icone-logo-menu">RH</div>
            <span>Système RH</span>
        </div>
        <a href="dashboard.php" class="lien-menu">Tableau de bord</a>
        <a href="employes.php" class="lien-menu">Employés</a>
        <a href="evaluations.php" class="lien-menu">Évaluations</a>
        <a href="demandes.php" class="lien-menu">Demandes</a>
        <a href="criteres.php" class="lien-menu actif">Critères</a>
        <div class="pied-menu">
            <div class="avatar-mini"><?= strtoupper(substr($_SESSION['prenom'],0,1) . substr($_SESSION['nom'],0,1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['prenom']) ?></span>
            <a href="../logout.php" class="icone-deconnexion" title="Déconnexion">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </a>
        </div>
    </div>

    <!-- Zone de contenu -->
    <div class="zone-contenu">

        <div class="entete-page">
            <div>
                <p class="titre-page">Gestion des critères d'évaluation</p>
                <p class="sous-titre-page">Définissez les critères et leur pondération (Total doit faire 100% par poste)</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message-<?= $typeMessage === 'erreur' ? 'erreur' : 'succes' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- Tableau de bord des totaux par poste -->
        <div class="panneau" style="margin-bottom: 1.5rem;">
            <p class="titre-panneau">📊 Totaux par poste</p>
            <?php if (empty($tousLesPostes)): ?>
                <p class="texte-vide">Aucun poste défini dans la base d'employés.</p>
            <?php else: ?>
                <div class="grille-cartes" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                    <?php foreach ($tousLesPostes as $poste): ?>
                        <div class="carte-stat" style="background: <?= $totauxParPoste[$poste] === 100 ? '#f0fdf4' : ($totauxParPoste[$poste] > 100 ? '#fee2e2' : '#fff7ed') ?>; border: 1px solid <?= $totauxParPoste[$poste] === 100 ? '#bbf7d0' : ($totauxParPoste[$poste] > 100 ? '#fecaca' : '#fed7aa') ?>;">
                            <p class="label-stat"><?= htmlspecialchars($poste) ?></p>
                            <p class="valeur-stat" style="font-size: 20px; color: <?= $totauxParPoste[$poste] === 100 ? '#15803d' : ($totauxParPoste[$poste] > 100 ? '#b91c1c' : '#c2410c') ?>;">
                                <?= $totauxParPoste[$poste] ?>%
                                <?php if ($totauxParPoste[$poste] === 100): ?>
                                    <span style="font-size: 12px;">✅</span>
                                <?php elseif ($totauxParPoste[$poste] > 100): ?>
                                    <span style="font-size: 12px;">⚠️</span>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: #9ca3af;">(manque <?= 100 - $totauxParPoste[$poste] ?>%)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Formulaire d'ajout/modification -->
        <div class="panneau" style="margin-bottom: 1.5rem;">
            <p class="titre-panneau"><?= $modeModification ? 'Modifier le critère' : 'Ajouter un nouveau critère' ?></p>
            <form method="POST" action="" id="form-critere">
                <input type="hidden" name="action" value="<?= $modeModification ? 'modifier' : 'ajouter' ?>">
                <?php if ($modeModification): ?>
                    <input type="hidden" name="id_critere" value="<?= $critereAModifier['id_critere'] ?>">
                <?php endif; ?>
                
                <div class="grille-cartes" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="champ">
                        <label>Nom du critère *</label>
                        <input type="text" name="nom_critere" id="nom_critere" required placeholder="Ex: Qualité du code" value="<?= $modeModification ? htmlspecialchars($critereAModifier['nom_critere']) : '' ?>">
                    </div>
                    <div class="champ">
                        <label>Poste concerné (laisser vide pour tous)</label>
                        <input type="text" name="poste_concerne" id="poste_concerne" placeholder="Ex: Développeur" value="<?= $modeModification ? htmlspecialchars($critereAModifier['poste_concerne']) : '' ?>">
                    </div>
                </div>
                <div class="grille-cartes" style="grid-template-columns: 2fr 1fr 1fr auto; gap: 16px; align-items: end;">
                    <div class="champ">
                        <label>Description (optionnelle)</label>
                        <input type="text" name="description" id="description" placeholder="Ex: Respect des bonnes pratiques" value="<?= $modeModification ? htmlspecialchars($critereAModifier['description']) : '' ?>">
                    </div>
                    <div class="champ">
                        <label>Poids (%) *</label>
                        <input type="number" name="poids" id="input-poids" min="0.01" max="100" step="0.01" required value="<?= $modeModification ? $critereAModifier['poids'] : '10' ?>">
                    </div>
                    <div class="champ">
                        <label>Note maximale</label>
                        <input type="number" name="note_maximale" id="note_maximale" min="1" max="1000" value="<?= $modeModification ? $critereAModifier['note_maximale'] : '100' ?>">
                    </div>
                    <div class="champ" style="display: flex; align-items: center; gap: 8px; padding-bottom: 10px;">
                        <input type="checkbox" name="actif" id="check-actif" <?= (!$modeModification || $critereAModifier['actif']) ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="check-actif" style="margin: 0; cursor: pointer;">Actif</label>
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="bouton-principal" id="btn-soumettre" style="width: auto; margin-top: 0;">
                        <?= $modeModification ? 'Enregistrer les modifications' : 'Ajouter le critère' ?>
                    </button>
                    <?php if ($modeModification): ?>
                        <a href="criteres.php" class="bouton-secondaire" style="padding: 9px 16px;">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
            <p id="message-total" style="margin-top: 12px; font-size: 13px; font-weight: 500;"></p>
        </div>

        <!-- Liste des critères -->
        <div class="panneau">
            <p class="titre-panneau">Liste des critères existants</p>
            <?php if (empty($criteres)): ?>
                <p class="texte-vide">Aucun critère défini pour le moment.</p>
            <?php else: ?>
                <table class="tableau-donnees">
                    <tr>
                        <th>Nom</th>
                        <th>Poste concerné</th>
                        <th>Description</th>
                        <th>Poids</th>
                        <th>Note max</th>
                        <th>Statut</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                    <?php foreach ($criteres as $c): ?>
                        <tr>
                            <td style="font-weight: 500;"><?= htmlspecialchars($c['nom_critere']) ?></td>
                            <td style="color: #6b7280;"><?= $c['poste_concerne'] ? htmlspecialchars($c['poste_concerne']) : '<em>Tous</em>' ?></td>
                            <td style="color: #6b7280;"><?= htmlspecialchars($c['description']) ?></td>
                            <td><strong><?= $c['poids'] ?>%</strong></td>
                            <td><?= $c['note_maximale'] ?></td>
                            <td>
                                <?php if ($c['actif']): ?>
                                    <span class="badge-statut badge-succes">Actif</span>
                                <?php else: ?>
                                    <span class="badge-statut badge-neutre">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="criteres.php?modifier=<?= $c['id_critere'] ?>" class="bouton-secondaire" style="padding: 4px 10px; font-size: 12px; margin-right: 4px;">
                                    Modifier
                                </a>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id_critere" value="<?= $c['id_critere'] ?>">
                                    <button type="submit" class="bouton-secondaire" style="padding: 4px 10px; font-size: 12px;">
                                        <?= $c['actif'] ? 'Désactiver' : 'Activer' ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ce critère ?');">
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="id_critere" value="<?= $c['id_critere'] ?>">
                                    <button type="submit" class="bouton-secondaire" style="padding: 4px 10px; font-size: 12px; color: #b91c1c; border-color: #fee2e2;">
                                        Supprimer
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
    // Données des totaux actuels par poste (depuis PHP)
    const totauxParPoste = <?= json_encode($totauxParPoste) ?>;
    const tousLesPostes = <?= json_encode($tousLesPostes) ?>;
    
    const inputPoids = document.getElementById('input-poids');
    const inputPoste = document.getElementById('poste_concerne');
    const checkActif = document.getElementById('check-actif');
    const messageTotal = document.getElementById('message-total');
    const btnSoumettre = document.getElementById('btn-soumettre');
    const modeModification = <?= $modeModification ? 'true' : 'false' ?>;
    <?php if ($modeModification): ?>
    const ancienPoste = <?= json_encode($critereAModifier['poste_concerne']) ?>;
    const ancienPoids = <?= (float)$critereAModifier['poids'] ?>;
    const ancienActif = <?= (int)$critereAModifier['actif'] ?>;
    <?php else: ?>
    const ancienPoste = null;
    const ancienPoids = 0;
    const ancienActif = 0;
    <?php endif; ?>

    function verifierTotaux() {
        if (modeModification) {
            verifierTotauxModification();
        } else {
            verifierTotauxAjout();
        }
    }

    function verifierTotauxAjout() {
        const poidsSaisi = parseFloat(inputPoids.value) || 0;
        const posteConcerne = inputPoste.value.trim();
        const estActif = checkActif.checked;
        
        if (!estActif) {
            messageTotal.textContent = "ℹ️ Le critère sera inactif (n'affecte pas les totaux).";
            messageTotal.style.color = '#6b7280';
            btnSoumettre.disabled = false;
            btnSoumettre.style.opacity = '1';
            return;
        }
        
        // Déterminer quels postes sont concernés
        const postesConcernes = (posteConcerne === '' || posteConcerne === 'Tous') 
            ? tousLesPostes 
            : [posteConcerne];
        
        let messageDetail = "";
        let peutSoumettre = true;
        let depassement = false;
        
        postesConcernes.forEach(poste => {
            const totalActuel = totauxParPoste[poste] || 0;
            const nouveauTotal = totalActuel + poidsSaisi;
            
            if (nouveauTotal > 100.01) {
                depassement = true;
                peutSoumettre = false;
                messageDetail += `⚠️ ${poste}: ${nouveauTotal.toFixed(2)}% (dépasse)\n`;
            } else if (nouveauTotal === 100 || Math.abs(nouveauTotal - 100) < 0.01) {
                messageDetail += `✅ ${poste}: ${nouveauTotal.toFixed(2)}% (parfait)\n`;
            } else {
                messageDetail += `ℹ️ ${poste}: ${nouveauTotal.toFixed(2)}% (manque ${100 - nouveauTotal.toFixed(2)}%)\n`;
            }
        });
        
        if (depassement) {
            messageTotal.textContent = "⚠️ Le total dépasse 100% pour certains postes.";
            messageTotal.style.color = '#b91c1c';
            messageTotal.title = messageDetail;
            btnSoumettre.disabled = true;
            btnSoumettre.style.opacity = '0.5';
        } else if (peutSoumettre) {
            const tousParfaits = postesConcernes.every(poste => {
                const total = (totauxParPoste[poste] || 0) + poidsSaisi;
                return Math.abs(total - 100) < 0.01;
            });
            
            if (tousParfaits) {
                messageTotal.textContent = "✅ Parfait ! Le total fera 100% pour tous les postes concernés.";
                messageTotal.style.color = '#15803d';
            } else {
                messageTotal.textContent = `ℹ️ ${messageDetail.trim()}`;
                messageTotal.style.color = '#6b7280';
            }
            messageTotal.title = "";
            btnSoumettre.disabled = false;
            btnSoumettre.style.opacity = '1';
        }
    }

    function verifierTotauxModification() {
        const poidsSaisi = parseFloat(inputPoids.value) || 0;
        const posteConcerne = inputPoste.value.trim();
        const estActif = checkActif.checked;
        
        // Déterminer quels postes sont impactés par le changement
        const postesImpactes = new Set();
        
        // Ancien poste concerné
        if (ancienPoste === '' || ancienPoste === 'Tous') {
            tousLesPostes.forEach(p => postesImpactes.add(p));
        } else if (ancienPoste) {
            postesImpactes.add(ancienPoste);
        }
        
        // Nouveau poste concerné
        if (posteConcerne === '' || posteConcerne === 'Tous') {
            tousLesPostes.forEach(p => postesImpactes.add(p));
        } else if (posteConcerne) {
            postesImpactes.add(posteConcerne);
        }
        
        let messageDetail = "";
        let peutSoumettre = true;
        let depassement = false;
        
        postesImpactes.forEach(poste => {
            // Calculer le total SANS ce critère
            let totalSansCritere = 0;
            tousLesPostes.forEach(p => {
                if (p === poste) {
                    // Ce critère s'applique-t-il à ce poste actuellement ?
                    const critereSappliqueAvant = (ancienPoste === '' || ancienPoste === 'Tous' || ancienPoste === poste);
                    if (critereSappliqueAvant && ancienActif) {
                        totalSansCritere = (totauxParPoste[p] || 0) - ancienPoids;
                    } else {
                        totalSansCritere = totauxParPoste[p] || 0;
                    }
                }
            });
            
            // Calculer le nouveau total AVEC ce critère
            let nouveauTotal = totalSansCritere;
            if (estActif) {
                const critereSappliqueApres = (posteConcerne === '' || posteConcerne === 'Tous' || posteConcerne === poste);
                if (critereSappliqueApres) {
                    nouveauTotal += poidsSaisi;
                }
            }
            
            if (nouveauTotal > 100.01) {
                depassement = true;
                peutSoumettre = false;
                messageDetail += `⚠️ ${poste}: ${nouveauTotal.toFixed(2)}% (dépasse)\n`;
            } else if (Math.abs(nouveauTotal - 100) < 0.01) {
                messageDetail += `✅ ${poste}: ${nouveauTotal.toFixed(2)}% (parfait)\n`;
            } else {
                messageDetail += `️ ${poste}: ${nouveauTotal.toFixed(2)}% (manque ${100 - nouveauTotal.toFixed(2)}%)\n`;
            }
        });
        
        if (depassement) {
            messageTotal.textContent = "️ Le total dépasse 100% pour certains postes.";
            messageTotal.style.color = '#b91c1c';
            messageTotal.title = messageDetail;
            btnSoumettre.disabled = true;
            btnSoumettre.style.opacity = '0.5';
        } else {
            messageTotal.textContent = `ℹ️ ${messageDetail.trim()}`;
            messageTotal.style.color = '#6b7280';
            messageTotal.title = "";
            btnSoumettre.disabled = false;
            btnSoumettre.style.opacity = '1';
        }
    }

    // Écouteurs d'événements
    inputPoids.addEventListener('input', verifierTotaux);
    inputPoste.addEventListener('input', verifierTotaux);
    checkActif.addEventListener('change', verifierTotaux);
    
    // Vérification initiale
    verifierTotaux();
</script>

</body>
</html>