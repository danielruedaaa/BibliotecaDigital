<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

$success = '';
$error = '';

/** Handle Actions (Retornar / Prorrogar) **/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $prestec_id = $_POST['prestec_id'] ?? null;
    
    if ($prestec_id) {
        // Obtenir dades del préstec incloent data inical
        $stmt_check = $db->prepare("SELECT id, id_exemplar, data_inici, data_fi, sancio FROM Prestec WHERE id = ? AND id_usuari = ? AND estat = 'actiu'");
        $stmt_check->execute([$prestec_id, $user['id']]);
        $prestec = $stmt_check->fetch();
        
        if ($prestec) {
            $data_fi = new DateTime($prestec['data_fi']);
            $avui = new DateTime();
            
            if ($_POST['action'] === 'retornar') {
                $db->beginTransaction();
                try {
                    $sancio_msg = null;
                    // Calcular sanció si hi ha retard
                    if ($data_fi < $avui) {
                        $diff = $avui->diff($data_fi);
                        $dies_retard = abs((int)$diff->format('%r%a'));
                        $sancio_msg = "Retard de $dies_retard dies.";
                    }
                    
                    // Update Prestec
                    $stmt_retornar = $db->prepare("UPDATE Prestec SET estat = 'retornat', sancio = ? WHERE id = ?");
                    $stmt_retornar->execute([$sancio_msg, $prestec_id]);
                    
                    // Update Exemplar
                    $stmt_exemplar = $db->prepare("UPDATE Exemplar SET estat = 'disponible' WHERE id = ?");
                    $stmt_exemplar->execute([$prestec['id_exemplar']]);
                    
                    $db->commit();
                    $success = "El llibre s'ha retornat amb èxit." . ($sancio_msg ? " Notificació: $sancio_msg" : "");
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Hi ha hagut un error intentant retornar el llibre.";
                }

            } elseif ($_POST['action'] === 'prorrogar') {
                // Obtenir informació si hi ha reserves
                $stmt_reserva = $db->prepare("SELECT id FROM Reserva WHERE id_llibre = (SELECT id_llibre FROM Exemplar WHERE id = ?) AND estat = 'activa'");
                $stmt_reserva->execute([$prestec['id_exemplar']]);
                $te_reserves = $stmt_reserva->fetch();
                
                $data_inici = new DateTime($prestec['data_inici']);
                $diff_total = $data_inici->diff($data_fi)->days;
                
                if ($te_reserves) {
                    $error = "No es pot prorrogar perquè hi ha altres usuaris esperant aquest llibre.";
                } elseif ($prestec['sancio']) {
                    $error = "No et podem prorrogar el préstec perquè tens sancions.";
                } elseif ($diff_total >= 30) { 
                    // Limitem a 1 pròrroga de 15 dies extra (assumint préstec original de 15 dies -> total max 30)
                    $error = "Ja has esgotat el límit màxim de renovacions per a aquest llibre.";
                } elseif ($data_fi < $avui) {
                    $error = "No es pot prorrogar un préstec que ja ha caducat.";
                } else {
                     // Extensió de 15 dies respecte a la data de finalització actula
                     $nova_data = (clone $data_fi)->modify('+15 days')->format('Y-m-d');
                     $stmt_prorrogar = $db->prepare("UPDATE Prestec SET data_fi = ? WHERE id = ?");
                     $stmt_prorrogar->execute([$nova_data, $prestec_id]);
                     $success = "Termini de devolució ampliat fins al " . date('d/m/Y', strtotime($nova_data)) . ".";
                }
            }
        } else {
            $error = "Préstec invàlid o ja tancat.";
        }
    }
}

// Obtenir préstecs actius
$stmtActius = $db->prepare("
    SELECT p.id, p.data_inici, p.data_fi, p.estat AS estat_prestec, p.sancio, p.id_exemplar,
           l.titol, l.autor, l.ISBN
    FROM Prestec p
    JOIN Exemplar e ON p.id_exemplar = e.id
    JOIN Llibre l ON e.id_llibre = l.id
    WHERE p.id_usuari = ? AND p.estat = 'actiu'
    ORDER BY p.data_fi ASC
");
$stmtActius->execute([$user['id']]);
$prestecsActius = $stmtActius->fetchAll();

// Obtenir historial de retornats
$stmtHistoric = $db->prepare("
    SELECT p.id, p.data_inici, p.data_fi, p.sancio,
           l.titol, l.autor
    FROM Prestec p
    JOIN Exemplar e ON p.id_exemplar = e.id
    JOIN Llibre l ON e.id_llibre = l.id
    WHERE p.id_usuari = ? AND p.estat = 'retornat'
    ORDER BY p.data_fi DESC
");
$stmtHistoric->execute([$user['id']]);
$historial = $stmtHistoric->fetchAll();

// Comptar estadístiques
$totalActius = count($prestecsActius);
$totalHistorial = count($historial);

$stmtReserves = $db->prepare("SELECT COUNT(*) FROM Reserva WHERE id_usuari = ? AND estat = 'activa'");
$stmtReserves->execute([$user['id']]);
$totalReserves = $stmtReserves->fetchColumn();

$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El meu Espai | Biblioteca Digital</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/layout.css">
    <link rel="stylesheet" href="../css/components.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .badge-warning {
            color: var(--error-color);
            font-weight: 500;
        }

        .badge-ok {
            color: var(--success-color);
            font-weight: 500;
        }

        .alert-error {
            background-color: #FEE2E2;
            color: var(--error-color);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            font-size: 0.875rem;
            margin-top: 1rem;
        }

        .alert-success {
            background-color: #DCFCE7;
            color: #166534;
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            font-size: 0.875rem;
            margin-top: 1rem;
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include __DIR__ . '/../includes/header.php'; ?>

        <main class="main-content">
            <header class="top-bar">
                <div>
                    <h2 style="font-size: 1.25rem;">Hola, <?= htmlspecialchars($user['nom']) ?></h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem;">
                        <?php if ($totalActius > 0): ?>
                            Tens <?= $totalActius ?> préstec(s) actiu(s).
                        <?php else: ?>
                            No tens cap préstec actiu en aquest moment.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="user-profile">
                    <a href="profile.php" class="btn btn-secondary" style="padding: 0.5rem 1rem;">El meu perfil</a>
                    <div class="avatar"><?= mb_substr(htmlspecialchars($user['nom']), 0, 1) ?></div>
                </div>
            </header>

            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Stats Widgets -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Préstecs Actius</div>
                    <div class="stat-value"><?= $totalActius ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Reserves Actives</div>
                    <div class="stat-value"><?= $totalReserves ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Historial (Retornats)</div>
                    <div class="stat-value"><?= $totalHistorial ?></div>
                </div>
            </section>

            <!-- Llistat de Préstecs Actius -->
            <section style="margin-bottom: 2rem;">
                <div class="flex-center" style="justify-content: space-between; margin-bottom: 1rem;">
                    <h3 class="section-title" style="margin-bottom: 0;">Els Meus Préstecs Actuals</h3>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Títol</th>
                                <th>Data Inici</th>
                                <th>Data Límit Retorn</th>
                                <th>Estat</th>
                                <th>Sancions</th>
                                <th style="text-align:center;">Accions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($prestecsActius)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                        No tens cap llibre prestat actualment. Pots anar al <a href="catalog.php">catàleg</a> per treure'n un.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($prestecsActius as $prestec): ?>
                                    <?php
                                    $dataFi = new DateTime($prestec['data_fi']);
                                    $avui = new DateTime();
                                    $diff = $avui->diff($dataFi);
                                    $diesRestants = (int) $diff->format('%r%a');

                                    if ($diesRestants < 0) {
                                        $estatText = 'Vencut! (' . abs($diesRestants) . ' dies)';
                                        $estatClass = 'badge-warning';
                                    } elseif ($diesRestants <= 3) {
                                        $estatText = 'Acaba properament (' . $diesRestants . ' dies)';
                                        $estatClass = 'badge-warning';
                                    } else {
                                        $estatText = 'En termini (' . $diesRestants . ' dies)';
                                        $estatClass = 'badge-ok';
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($prestec['titol']) ?></strong><br>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($prestec['autor']) ?></span>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($prestec['data_inici'])) ?></td>
                                        <td><?= date('d/m/Y', strtotime($prestec['data_fi'])) ?></td>
                                        <td><span class="<?= $estatClass ?>"><?= $estatText ?></span></td>
                                        <td><span style="color: var(--error-color);font-size:0.875rem;"><?= htmlspecialchars($prestec['sancio'] ?? '') ?></span></td>
                                        <td style="display: flex; gap: 0.5rem; justify-content: center;">
                                            <form method="POST" action="dashboard.php" style="margin: 0;" onsubmit="return confirm('Segur que vols retornar aquest llibre?');">
                                                <input type="hidden" name="action" value="retornar">
                                                <input type="hidden" name="prestec_id" value="<?= $prestec['id'] ?>">
                                                <button type="submit" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">Retornar</button>
                                            </form>
                                            <form method="POST" action="dashboard.php" style="margin: 0;">
                                                <input type="hidden" name="action" value="prorrogar">
                                                <input type="hidden" name="prestec_id" value="<?= $prestec['id'] ?>">
                                                <button type="submit" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" <?= ($diesRestants < 0) ? 'disabled title="No pots prorrogar fora de termini"' : '' ?>>Prorrogar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <!-- Historial de lectures -->
            <section>
                <div class="flex-center" style="justify-content: space-between; margin-bottom: 1rem;">
                    <h3 class="section-title" style="margin-bottom: 0;">El meu Historial de Lectures</h3>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Títol</th>
                                <th>Autor</th>
                                <th>Data Inici</th>
                                <th>Data Retornat</th>
                                <th>Observacions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($historial)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                        Encara no tens llibres en l'historial.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($historial as $item): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($item['titol']) ?></strong></td>
                                        <td><?= htmlspecialchars($item['autor']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($item['data_inici'])) ?></td>
                                        <td><?= date('d/m/Y', strtotime($item['data_fi'])) ?></td>
                                        <td>
                                            <?php if($item['sancio']): ?>
                                                <span style="color: var(--error-color);font-size:0.875rem;"><?= htmlspecialchars($item['sancio']) ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);font-size:0.875rem;">Cap incident</span>
                                            <?php endif; ?>
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