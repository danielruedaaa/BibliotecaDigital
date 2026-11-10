<?php
// Administració: Gestió d'exemplars (stock)
require_once 'auth_admin.php';

$db = getDB();
$user = getCurrentUser();
$error = '';
$success = '';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $db->prepare("SELECT * FROM Llibre WHERE id = ? AND eliminat = 0");
$stmt->execute([$id]);
$llibre = $stmt->fetch();
if (!$llibre) { header('Location: index.php'); exit; }

// Comptar exemplars i préstecs
$stmt_total = $db->prepare("SELECT COUNT(*) FROM Exemplar WHERE id_llibre = ?");
$stmt_total->execute([$id]);
$total_exemplars = $stmt_total->fetchColumn();

$stmt_disp = $db->prepare("SELECT COUNT(*) FROM Exemplar WHERE id_llibre = ? AND estat = 'disponible'");
$stmt_disp->execute([$id]);
$disponibles = $stmt_disp->fetchColumn();

$stmt_pa = $db->prepare("SELECT COUNT(*) FROM Prestec p JOIN Exemplar e ON p.id_exemplar = e.id WHERE e.id_llibre = ? AND p.estat = 'actiu'");
$stmt_pa->execute([$id]);
$prestecs_actius = $stmt_pa->fetchColumn();

// Historial d'auditoria d'aquest llibre
$stmt_audit = $db->prepare("
    SELECT a.*, u.nom as admin_nom 
    FROM Audit_Stock a 
    JOIN Usuari u ON a.id_admin = u.id 
    WHERE a.id_llibre = ? 
    ORDER BY a.data_canvi DESC 
    LIMIT 20
");
$stmt_audit->execute([$id]);
$historial_audit = $stmt_audit->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nou_total = intval($_POST['nou_total'] ?? 0);

    if ($nou_total < 0) {
        $error = "El nombre d'exemplars no pot ser negatiu.";
    } elseif ($nou_total < $prestecs_actius) {
        $error = "No pots reduir a $nou_total exemplars perquè hi ha $prestecs_actius exemplar(s) en préstec.";
    } else {
        $diferencia = $nou_total - $total_exemplars;

        $db->beginTransaction();
        try {
            if ($diferencia > 0) {
                // Afegir exemplars
                for ($i = 0; $i < $diferencia; $i++) {
                    $db->prepare("INSERT INTO Exemplar (id_llibre, estat) VALUES (?, 'disponible')")->execute([$id]);
                }
            } elseif ($diferencia < 0) {
                // Eliminar exemplars disponibles (mai prestats)
                // NOTA: LIMIT con placeholder falla con PDO::ATTR_EMULATE_PREPARES=false,
                // por eso se interpola el entero ya validado.
                $a_eliminar = abs((int)$diferencia);
                $stmt_del = $db->prepare("SELECT id FROM Exemplar WHERE id_llibre = ? AND estat = 'disponible' LIMIT $a_eliminar");
                $stmt_del->execute([$id]);
                $exemplars_per_eliminar = $stmt_del->fetchAll(PDO::FETCH_COLUMN);

                foreach ($exemplars_per_eliminar as $ex_id) {
                    $db->prepare("DELETE FROM Exemplar WHERE id = ?")->execute([$ex_id]);
                }
            }

            // Registre d'auditoria
            $stmt_audit_ins = $db->prepare("INSERT INTO Audit_Stock (id_llibre, id_admin, accio, valor_anterior, valor_nou) VALUES (?, ?, 'canvi_stock', ?, ?)");
            $stmt_audit_ins->execute([$id, $user['id'], $total_exemplars, $nou_total]);

            $db->commit();

            // Refrescar comptadors
            $total_exemplars = $nou_total;
            $stmt_disp->execute([$id]);
            $disponibles = $stmt_disp->fetchColumn();

            // Refrescar auditoria
            $stmt_audit->execute([$id]);
            $historial_audit = $stmt_audit->fetchAll();

            $success = "Stock actualitzat de $total_exemplars a $nou_total exemplars.";
            // Corregir el valor mostrat
            $stmt_total->execute([$id]);
            $total_exemplars = $stmt_total->fetchColumn();
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error actualitzant l'stock: " . $e->getMessage();
        }
    }
}

$currentPage = 'admin_catalog';
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestió d'Exemplars | Administració</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/layout.css">
    <link rel="stylesheet" href="../css/components.css">
    <link rel="stylesheet" href="../css/dashboard.css">

</head>
<body>
    <div class="dashboard-layout">
        <?php include 'header_admin.php'; ?>

        <main class="main-content">
            <header class="top-bar">
                <div>
                    <h2 style="font-size: 1.25rem;">Gestió d'Exemplars</h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem;"><?= htmlspecialchars($llibre['titol']) ?> — <?= htmlspecialchars($llibre['autor']) ?></p>
                </div>
                <div class="user-profile">
                    <a href="index.php" class="btn btn-secondary" style="padding: 0.5rem 1rem;">← Tornar</a>
                </div>
            </header>

            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <section style="max-width: 650px;">
                <div class="card mb-4">
                    <h3 style="margin-bottom: var(--spacing-lg);">Estat Actual de l'Stock</h3>
                    <div class="stock-info">
                        <div class="stock-box">
                            <div class="number"><?= $total_exemplars ?></div>
                            <div class="label">Total Exemplars</div>
                        </div>
                        <div class="stock-box">
                            <div class="number" style="color: var(--success-color);"><?= $disponibles ?></div>
                            <div class="label">Disponibles</div>
                        </div>
                        <div class="stock-box">
                            <div class="number" style="color: var(--error-color);"><?= $prestecs_actius ?></div>
                            <div class="label">En Préstec</div>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Nou nombre total d'exemplars</label>
                            <input type="number" name="nou_total" class="form-input" min="<?= $prestecs_actius ?>" value="<?= $total_exemplars ?>">
                            <small style="color: var(--text-muted);">Mínim: <?= $prestecs_actius ?> (exemplars en préstec). Es crearan o eliminaran exemplars automàticament.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Actualitzar Stock</button>
                    </form>
                </div>

                <?php if (!empty($historial_audit)): ?>
                <div class="card">
                    <h3 style="margin-bottom: var(--spacing-lg);">Registre d'Auditoria</h3>
                    <div class="table-container">
                        <table class="audit-table">
                            <thead>
                                <tr><th>Data</th><th>Admin</th><th>Acció</th><th>Anterior</th><th>Nou</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historial_audit as $audit): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($audit['data_canvi'])) ?></td>
                                        <td><?= htmlspecialchars($audit['admin_nom']) ?></td>
                                        <td><?= htmlspecialchars($audit['accio']) ?></td>
                                        <td><?= $audit['valor_anterior'] ?? '-' ?></td>
                                        <td><?= $audit['valor_nou'] ?? '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
