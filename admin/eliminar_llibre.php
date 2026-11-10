<?php
// Administració: Eliminar un llibre (soft delete)
require_once 'auth_admin.php';

$db = getDB();
$user = getCurrentUser();
$error = '';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// Obtenir llibre
$stmt = $db->prepare("SELECT * FROM Llibre WHERE id = ? AND eliminat = 0");
$stmt->execute([$id]);
$llibre = $stmt->fetch();
if (!$llibre) { header('Location: index.php'); exit; }

// Comptar préstecs actius i reserves
$stmt_pa = $db->prepare("SELECT COUNT(*) FROM Prestec p JOIN Exemplar e ON p.id_exemplar = e.id WHERE e.id_llibre = ? AND p.estat = 'actiu'");
$stmt_pa->execute([$id]);
$prestecs_actius = $stmt_pa->fetchColumn();

$stmt_ra = $db->prepare("SELECT COUNT(*) FROM Reserva WHERE id_llibre = ? AND estat = 'activa'");
$stmt_ra->execute([$id]);
$reserves_actives = $stmt_ra->fetchColumn();

// Comprovar si té historial de préstecs (per decidir soft vs hard delete)
$stmt_hist = $db->prepare("SELECT COUNT(*) FROM Prestec p JOIN Exemplar e ON p.id_exemplar = e.id WHERE e.id_llibre = ?");
$stmt_hist->execute([$id]);
$te_historial = $stmt_hist->fetchColumn() > 0;

$pot_eliminar = ($prestecs_actius == 0 && $reserves_actives == 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    if (!$pot_eliminar) {
        $error = "No es pot eliminar: el llibre té préstecs actius o reserves pendents.";
    } else {
        if ($te_historial) {
            // Soft delete: marcar com a eliminat
            $stmt_del = $db->prepare("UPDATE Llibre SET eliminat = 1, estat = 'retirat' WHERE id = ?");
            $stmt_del->execute([$id]);

            // Registre d'auditoria
            $stmt_audit = $db->prepare("INSERT INTO Audit_Stock (id_llibre, id_admin, accio, valor_anterior, valor_nou) VALUES (?, ?, 'soft_delete', NULL, NULL)");
            $stmt_audit->execute([$id, $user['id']]);

            header("Location: index.php?msg=Llibre+retirat+(soft+delete)+correctament");
            exit;
        } else {
            // Hard delete: eliminar registre completament (sense dependències)
            // NOTA: no se inserta en Audit_Stock aquí porque su FK a Llibre(id)
            // impediría borrar el libro (o insertar después de borrarlo).
            // El soft delete sí audita porque el libro sigue existiendo.
            $db->beginTransaction();
            try {
                $db->prepare("DELETE FROM Exemplar WHERE id_llibre = ?")->execute([$id]);
                // Borrar auditoría previa de este libro para no bloquear el DELETE por FK
                try { $db->prepare("DELETE FROM Audit_Stock WHERE id_llibre = ?")->execute([$id]); } catch (Exception $e2) {}
                $db->prepare("DELETE FROM Llibre WHERE id = ?")->execute([$id]);

                $db->commit();
                header("Location: index.php?msg=Llibre+eliminat+permanentment");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Error eliminant el llibre: " . $e->getMessage();
            }
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
    <title>Eliminar Llibre | Administració</title>
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
                    <h2 style="font-size: 1.25rem;">Eliminar Llibre #<?= $id ?></h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem;">Confirmació d'eliminació</p>
                </div>
                <div class="user-profile">
                    <a href="index.php" class="btn btn-secondary" style="padding: 0.5rem 1rem;">← Tornar</a>
                </div>
            </header>

            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <section style="max-width: 600px;">
                <div class="card">
                    <h3 style="margin-bottom: var(--spacing-lg); color: var(--error-color);">⚠️ Confirmació d'eliminació</h3>

                    <div class="delete-summary">
                        <p><strong>Títol:</strong> <?= htmlspecialchars($llibre['titol']) ?></p>
                        <p><strong>Autor:</strong> <?= htmlspecialchars($llibre['autor']) ?></p>
                        <p><strong>ISBN:</strong> <?= htmlspecialchars($llibre['ISBN']) ?></p>
                        <p><strong>Préstecs actius:</strong>
                            <?php if ($prestecs_actius > 0): ?>
                                <span style="color: var(--error-color);"><?= $prestecs_actius ?> (no es pot eliminar)</span>
                            <?php else: ?>
                                <span style="color: var(--success-color);">0</span>
                            <?php endif; ?>
                        </p>
                        <p><strong>Reserves actives:</strong>
                            <?php if ($reserves_actives > 0): ?>
                                <span style="color: var(--error-color);"><?= $reserves_actives ?> (no es pot eliminar)</span>
                            <?php else: ?>
                                <span style="color: var(--success-color);">0</span>
                            <?php endif; ?>
                        </p>
                        <p><strong>Té historial:</strong> <?= $te_historial ? 'Sí → Soft delete (es marcarà com eliminat)' : 'No → Hard delete (s\'esborrarà completament)' ?></p>
                    </div>

                    <?php if (!$pot_eliminar): ?>
                        <div class="alert-warning">
                            No es pot eliminar aquest llibre perquè té préstecs actius o reserves pendents. 
                            Primer cal retornar tots els exemplars i cancel·lar les reserves.
                        </div>
                        <a href="index.php" class="btn btn-secondary">Tornar al catàleg</a>
                    <?php else: ?>
                        <form method="POST" action="" onsubmit="return confirm('Estàs segur que vols eliminar aquest llibre? Aquesta acció no es pot desfer fàcilment.');">
                            <input type="hidden" name="confirmar" value="1">
                            <button type="submit" class="btn btn-primary" style="background: var(--error-color); border-color: var(--error-color);">
                                Confirmar Eliminació
                            </button>
                            <a href="index.php" class="btn btn-secondary" style="margin-left: 0.5rem;">Cancel·lar</a>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
