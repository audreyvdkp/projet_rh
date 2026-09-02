<?php
require_once '../includes/auth.php';
verifierRole('Administrateur RH');

require_once '../config/db.php';

// Récupération des filtres envoyés par le formulaire (méthode GET)
$recherche = $_GET['recherche'] ?? '';
$filtreService = $_GET['service'] ?? '';
$filtreStatut = $_GET['statut'] ?? '';

// Construction dynamique de la requête selon les filtres actifs
$sql = "
    SELECT e.id_employe, u.nom, u.prenom, e.poste, e.service, e.type_contrat, e.statut
    FROM Employe e
    INNER JOIN Utilisateur u ON e.id_utilisateur = u.id_utilisateur
    WHERE 1=1
";
$params = [];

if ($recherche !== '') {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ?)";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}
if ($filtreService !== '') {
    $sql .= " AND e.service = ?";
    $params[] = $filtreService;
}
if ($filtreStatut !== '') {
    $sql .= " AND e.statut = ?";
    $params[] = $filtreStatut;
}

$sql .= " ORDER BY u.nom";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Liste des services existants, pour remplir le menu déroulant du filtre
$stmt = $pdo->query("SELECT DISTINCT service FROM Employe WHERE service IS NOT NULL AND service != '' ORDER BY service");
$services = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Nombre total d'employés actifs (pour le sous-titre de la page)
$totalActifs = $pdo->query("SELECT COUNT(*) FROM Employe WHERE statut = 'Actif'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Employés</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="conteneur-app">

    <div class="menu-lateral">
        <div class="logo-menu">
            <div class="icone-logo-menu">RH</div>
            <span>Système RH</span>
        </div>

        <a href="dashboard.php" class="lien-menu">Tableau de bord</a>
        <a href="employes.php" class="lien-menu actif">Employés</a>
        <a href="evaluations.php" class="lien-menu">Évaluations</a>
        <a href="demandes.php" class="lien-menu">Demandes</a>
        <a href="criteres.php" class="lien-menu">Critères</a>

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

    <div class="zone-contenu">

        <div class="entete-page">
            <div>
                <p class="titre-page">Employés</p>
                <p class="sous-titre-page"><?= $totalActifs ?> employés actifs</p>
            </div>
            <a href="ajouter_employe.php" class="bouton-principal-inline">+ Ajouter un employé</a>
        </div>

        <!-- Barre de recherche et filtres -->
        <form method="GET" action="employes.php" class="barre-filtres">
            <input type="text" id="champRecherche" name="recherche" placeholder="Rechercher un employé..." value="<?= htmlspecialchars($recherche) ?>" oninput="filtrerTableau()" autocomplete="off">

            <select name="service" onchange="this.form.submit()">
                <option value="">Tous les services</option>
                <?php foreach ($services as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $filtreService === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="statut" onchange="this.form.submit()">
                <option value="">Tous les statuts</option>
                <option value="Actif" <?= $filtreStatut === 'Actif' ? 'selected' : '' ?>>Actif</option>
                <option value="En congé" <?= $filtreStatut === 'En congé' ? 'selected' : '' ?>>En congé</option>
                <option value="Ancien employé" <?= $filtreStatut === 'Ancien employé' ? 'selected' : '' ?>>Ancien employé</option>
            </select>
        </form>

        <div class="panneau">
            <table class="tableau-donnees" id="tableauEmployes">
                <tr>
                    <th>Employé</th><th>Poste</th><th>Service</th><th>Contrat</th><th>Statut</th><th></th>
                </tr>
                <?php if (empty($employes)): ?>
                    <tr><td colspan="6" class="texte-vide">Aucun employé ne correspond à ces critères</td></tr>
                <?php else: ?>
                    <?php foreach ($employes as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?></td>
                            <td><?= htmlspecialchars($e['poste']) ?></td>
                            <td><?= htmlspecialchars($e['service'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($e['type_contrat']) ?></td>
                            <td>
                                <span class="badge-statut badge-<?= $e['statut'] === 'Actif' ? 'succes' : 'neutre' ?>">
                                    <?= htmlspecialchars($e['statut']) ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <a href="fiche_employe.php?id=<?= $e['id_employe'] ?>" class="lien-action">Voir la fiche</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>

    </div>

</div>

<script>
// Filtre les lignes du tableau en direct, à chaque lettre tapée,
// sans avoir besoin de recharger la page.
function filtrerTableau() {
    const texteRecherche = document.getElementById('champRecherche').value.toLowerCase();
    const table = document.getElementById('tableauEmployes');
    const lignes = table.getElementsByTagName('tr');

    // On commence à 1 pour ne pas toucher la ligne d'en-tête (index 0)
    for (let i = 1; i < lignes.length; i++) {
        const cellNom = lignes[i].getElementsByTagName('td')[0];
        if (!cellNom) continue; // ignore la ligne "Aucun employé..." si elle existe

        const nomEmploye = cellNom.textContent.toLowerCase();
        if (nomEmploye.includes(texteRecherche)) {
            lignes[i].style.display = '';
        } else {
            lignes[i].style.display = 'none';
        }
    }
}
</script>

</body>
</html>