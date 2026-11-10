<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

$success = '';
$error = '';

/** Helper per comprovar si l'usuari té sancions actives (retards) **/
function teSancionsActives($db, $user_id) {
    // Es considera sancionat si té algun préstec vigent on data_fi és anterior a avui (retard)
    // O si en l'historial recent s'havia bloquejat. Simplificarem comprovant retards actuals.
    $stmt = $db->prepare("SELECT id FROM Prestec WHERE id_usuari = ? AND estat = 'actiu' AND data_fi < CURDATE()");
    $stmt->execute([$user_id]);
    if ($stmt->fetch()) return true;
    
    return false;
}

/** Helper per comptar quants préstecs actius té **/
function getPrestecsActiusCount($db, $user_id) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM Prestec WHERE id_usuari = ? AND estat = 'actiu'");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $llibre_id = intval($_POST['llibre_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($llibre_id > 0) {
        $te_sancions = teSancionsActives($db, $user['id']);
        
        if ($action === 'reservar') {
            if ($te_sancions) {
                $error = "No pots reservar perquè tens devolucions pendents fora de termini.";
            } else {
                // Comprova si ja té reserva activa d'aquest llibre
                $stmt_check = $db->prepare("SELECT id FROM Reserva WHERE id_usuari = ? AND id_llibre = ? AND estat = 'activa'");
                $stmt_check->execute([$user['id'], $llibre_id]);
                if ($stmt_check->fetch()) {
                    $error = "Ja tens una reserva activa per aquest llibre.";
                } else {
                    $stmt = $db->prepare("INSERT INTO Reserva (id_usuari, id_llibre, data, estat) VALUES (?, ?, CURDATE(), 'activa')");
                    $stmt->execute([$user['id'], $llibre_id]);
                    $success = "La reserva s'ha fet correctament!";
                }
            }
        } elseif ($action === 'prestar') {
            if ($te_sancions) {
                $error = "No pots treure llibres en préstec perquè tens devolucions pendents fora de termini.";
            } elseif (getPrestecsActiusCount($db, $user['id']) >= 3) {
                $error = "Has superat el límit màxim de préstecs actius alhora (3).";
            } else {
                // Comprovar si ja té aquest llibre en préstec
                $stmt_dup = $db->prepare("
                    SELECT p.id FROM Prestec p 
                    JOIN Exemplar e ON p.id_exemplar = e.id 
                    WHERE e.id_llibre = ? AND p.id_usuari = ? AND p.estat = 'actiu'
                ");
                $stmt_dup->execute([$llibre_id, $user['id']]);
                if ($stmt_dup->fetch()) {
                    $error = "Ja tens aquest llibre en préstec. No pots treure'l dues vegades.";
                } else {
                // Obtenir un exemplar disponible d'aquest llibre
                $db->beginTransaction();
                try {
                    $stmt_ex = $db->prepare("SELECT id FROM Exemplar WHERE id_llibre = ? AND estat = 'disponible' LIMIT 1 FOR UPDATE");
                    $stmt_ex->execute([$llibre_id]);
                    $exemplar = $stmt_ex->fetch();
                    
                    if ($exemplar) {
                        // Creem el préstec a 15 dies
                        $stmt_prestec = $db->prepare("INSERT INTO Prestec (id_usuari, id_exemplar, data_inici, data_fi, estat) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'actiu')");
                        $stmt_prestec->execute([$user['id'], $exemplar['id']]);
                        
                        // Actualitzem l'estat de l'exemplar
                        $stmt_update_ex = $db->prepare("UPDATE Exemplar SET estat = 'prestat' WHERE id = ?");
                        $stmt_update_ex->execute([$exemplar['id']]);
                        
                        $db->commit();
                        $success = "El llibre se t'ha prestat amb èxit! El pots passar a recollir.";
                    } else {
                        $db->rollBack();
                        $error = "Justament algú altre ha agafat l'últim exemplar d'aquest llibre.";
                    }
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Error intern gestionant el préstec.";
                }
                } // fi else (no duplicat)
            }
        }
    }
}

// Cerca amb filtres combinats (Títol/Autor, Categoria, Disponibilitat)
$search = $_GET['search'] ?? '';
$categoriaFiltre = $_GET['categoria'] ?? '';
$disponibilitatFiltre = $_GET['disponibilitat'] ?? '';

// Construir la consulta principal
$query = "
    SELECT l.*, 
           (SELECT COUNT(*) FROM Exemplar e WHERE e.id_llibre = l.id AND e.estat = 'disponible') as disponibles,
           (SELECT COUNT(*) FROM Exemplar e WHERE e.id_llibre = l.id) as total_exemplars
    FROM Llibre l
    WHERE 1=1 AND l.estat = 'publicat' AND (l.eliminat = 0 OR l.eliminat IS NULL)
";

$params = [];

if ($search) {
    $query .= " AND (l.titol LIKE ? OR l.autor LIKE ? OR l.ISBN LIKE ?)";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm);
}
if ($categoriaFiltre) {
    $query .= " AND l.categoria = ?";
    array_push($params, $categoriaFiltre);
}

// Per aconseguir la disponibilitat hem de fer la selecció post-query o amb HAVING si fos un subselect al WHERE.
// Hem triat HAVING perquè és més net.
if ($disponibilitatFiltre === 'disponible') {
    $query .= " HAVING disponibles > 0";
} elseif ($disponibilitatFiltre === 'prestat') {
    $query .= " HAVING disponibles = 0";
}

$query .= " ORDER BY l.titol ASC";

$stmtLlibres = $db->prepare($query);
$stmtLlibres->execute($params);
$llibres = $stmtLlibres->fetchAll();

// Obtenir categories úniques per al desplegable
$stmtCat = $db->query("SELECT DISTINCT categoria FROM Llibre WHERE categoria IS NOT NULL");
$categories = $stmtCat->fetchAll(PDO::FETCH_COLUMN);

$currentPage = 'catalog';
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catàleg | Biblioteca Digital</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/layout.css">
    <link rel="stylesheet" href="../css/components.css">
    <link rel="stylesheet" href="../css/dashboard.css">

</head>
<body>
    <div class="dashboard-layout">
        <?php include __DIR__ . '/../includes/header.php'; ?>

        <main class="main-content">
            <header class="top-bar">
                <div>
                    <h2 style="font-size: 1.25rem;">Catàleg de Llibres</h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem;">Consulta, reserva o pren en préstec.</p>
                </div>
                <div class="user-profile">
                    <a href="profile.php" class="btn btn-secondary" style="padding: 0.5rem 1rem;">El meu perfil</a>
                    <div class="avatar"><?= mb_substr(htmlspecialchars($user['nom']), 0, 1) ?></div>
                </div>
            </header>

            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <section>
                <div class="card">
                    <!-- Fomulari de Filtres Avançat -->
                    <form method="GET" action="catalog.php" class="filter-form">
                        <input type="text" name="search" class="form-input" style="flex: 2; min-width: 250px;" 
                               placeholder="Cerca per títol, autor o ISBN..." 
                               value="<?= htmlspecialchars($search) ?>">
                               
                        <select name="categoria" class="form-input">
                            <option value="">Totes les categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= ($categoriaFiltre === $cat) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="disponibilitat" class="form-input">
                            <option value="">Tota disponibilitat</option>
                            <option value="disponible" <?= ($disponibilitatFiltre === 'disponible') ? 'selected' : '' ?>>Disponibles</option>
                            <option value="prestat" <?= ($disponibilitatFiltre === 'prestat') ? 'selected' : '' ?>>Prestats / No disponibles</option>
                        </select>

                        <button type="submit" class="btn btn-primary">Aplicar Filtres</button>
                    </form>

                    <table>
                        <thead>
                            <tr>
                                <th>Títol</th>
                                <th>Autor/Cat</th>
                                <th>Disponibilitat</th>
                                <th style="text-align: center;">Accions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($llibres)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">No s'ha trobat cap llibre.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($llibres as $llibre): ?>
                                    <?php 
                                        $disponible = $llibre['disponibles'] > 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($llibre['titol']) ?></strong><br>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);">ISBN: <?= htmlspecialchars($llibre['ISBN']) ?></span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($llibre['autor']) ?><br>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($llibre['categoria']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($disponible): ?>
                                                <span style="color: var(--success-color);">Disponible (<?= $llibre['disponibles'] ?> ex.)</span>
                                            <?php else: ?>
                                                <span style="color: var(--error-color);">Sense exemplars</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <form method="POST" action="catalog.php" style="margin:0;">
                                                <input type="hidden" name="llibre_id" value="<?= $llibre['id'] ?>">
                                                
                                                <?php if ($disponible): ?>
                                                    <input type="hidden" name="action" value="prestar">
                                                    <button type="submit" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                        Prestar
                                                    </button>
                                                <?php else: ?>
                                                    <input type="hidden" name="action" value="reservar">
                                                    <button type="submit" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                        Reservar
                                                    </button>
                                                <?php endif; ?>
                                                
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
