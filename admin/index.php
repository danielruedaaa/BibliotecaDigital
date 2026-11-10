<?php
// Panell d'administració: Llistat del catàleg
require_once 'auth_admin.php';

$db = getDB();
$user = getCurrentUser();

// Obtenir tots els llibres (incloent eliminats per a l'admin)
$stmtLlibres = $db->query("
    SELECT l.*, 
           (SELECT COUNT(*) FROM Exemplar e WHERE e.id_llibre = l.id) as total_exemplars,
           (SELECT COUNT(*) FROM Exemplar e WHERE e.id_llibre = l.id AND e.estat = 'disponible') as disponibles,
           (SELECT COUNT(*) FROM Prestec p JOIN Exemplar e2 ON p.id_exemplar = e2.id WHERE e2.id_llibre = l.id AND p.estat = 'actiu') as prestecs_actius,
           (SELECT COUNT(*) FROM Reserva r WHERE r.id_llibre = l.id AND r.estat = 'activa') as reserves_actives
    FROM Llibre l
    ORDER BY l.eliminat ASC, l.titol ASC
");
$llibres = $stmtLlibres->fetchAll();

$currentPage = 'admin_catalog';
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administració del Catàleg | BiblioDigital</title>
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
                    <h2 style="font-size: 1.25rem;">Administració del Catàleg</h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem;"><?= count($llibres) ?> títols registrats al sistema.</p>
                </div>
                <div class="user-profile">
                    <a href="alta_llibre.php" class="btn btn-primary" style="padding: 0.5rem 1rem;">+ Nou Llibre</a>
                    <div class="avatar"><?= mb_substr(htmlspecialchars($user['nom']), 0, 1) ?></div>
                </div>
            </header>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert-success"><?= htmlspecialchars($_GET['msg']) ?></div>
            <?php endif; ?>

            <section>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Títol / Autor</th>
                                <th>ISBN</th>
                                <th>Categoria</th>
                                <th>Estat</th>
                                <th>Exemplars</th>
                                <th style="text-align:center;">Accions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($llibres)): ?>
                                <tr><td colspan="7" style="text-align:center; padding:2rem; color:var(--text-muted);">No hi ha cap llibre al catàleg.</td></tr>
                            <?php else: ?>
                                <?php foreach ($llibres as $ll): ?>
                                    <tr class="<?= $ll['eliminat'] ? 'row-eliminat' : '' ?>">
                                        <td><?= $ll['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($ll['titol']) ?></strong><br>
                                            <span style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($ll['autor']) ?></span>
                                        </td>
                                        <td style="font-size:0.8rem;"><?= htmlspecialchars($ll['ISBN']) ?></td>
                                        <td style="font-size:0.8rem;"><?= htmlspecialchars($ll['categoria'] ?? '-') ?></td>
                                        <td>
                                            <?php if ($ll['eliminat']): ?>
                                                <span class="badge badge-eliminat">Eliminat</span>
                                            <?php else: ?>
                                                <span class="badge badge-<?= $ll['estat'] ?>"><?= ucfirst($ll['estat']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:0.8rem;">
                                            <?= $ll['disponibles'] ?> disp / <?= $ll['total_exemplars'] ?> total
                                            <?php if ($ll['prestecs_actius'] > 0): ?>
                                                <br><span style="color:var(--error-color); font-size:0.7rem;"><?= $ll['prestecs_actius'] ?> prestat(s)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$ll['eliminat']): ?>
                                                <div class="actions-cell">
                                                    <a href="editar_llibre.php?id=<?= $ll['id'] ?>" class="btn btn-secondary btn-xs">Editar</a>
                                                    <a href="stock_llibre.php?id=<?= $ll['id'] ?>" class="btn btn-secondary btn-xs">Stock</a>
                                                    <a href="estat_llibre.php?id=<?= $ll['id'] ?>" class="btn btn-secondary btn-xs">Estat</a>
                                                    <a href="eliminar_llibre.php?id=<?= $ll['id'] ?>" class="btn btn-secondary btn-xs" style="color:var(--error-color);">Eliminar</a>
                                                </div>
                                            <?php else: ?>
                                                <span style="font-size:0.75rem; color:var(--text-muted);">Arxivat</span>
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
