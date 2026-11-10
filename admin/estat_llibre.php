<?php
// Administració: Canviar visibilitat i estat del llibre
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

// Comptar préstecs actius i reserves
$stmt_pa = $db->prepare("SELECT COUNT(*) FROM Prestec p JOIN Exemplar e ON p.id_exemplar = e.id WHERE e.id_llibre = ? AND p.estat = 'actiu'");
$stmt_pa->execute([$id]);
$prestecs_actius = $stmt_pa->fetchColumn();

$stmt_ra = $db->prepare("SELECT COUNT(*) FROM Reserva WHERE id_llibre = ? AND estat = 'activa'");
$stmt_ra->execute([$id]);
$reserves_actives = $stmt_ra->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nou_estat = $_POST['estat'] ?? '';
    $estats_permesos = ['publicat', 'ocult', 'retirat'];

    if (!in_array($nou_estat, $estats_permesos)) {
        $error = "Estat no vàlid. Ha de ser: publicat, ocult o retirat.";
    } elseif ($nou_estat === 'retirat' && ($prestecs_actius > 0 || $reserves_actives > 0)) {
        $error = "No es pot retirar el llibre perquè té $prestecs_actius préstec(s) actiu(s) i $reserves_actives reserva(es) activa(es).";
    } else {
        $estat_anterior = $llibre['estat'];
        $stmt_update = $db->prepare("UPDATE Llibre SET estat = ? WHERE id = ?");
        $stmt_update->execute([$nou_estat, $id]);

        // Registre d'auditoria
        $stmt_audit = $db->prepare("INSERT INTO Audit_Stock (id_llibre, id_admin, accio, valor_anterior, valor_nou) VALUES (?, ?, ?, NULL, NULL)");
        $stmt_audit->execute([$id, $user['id'], "estat: $estat_anterior -> $nou_estat"]);

        $success = "Estat del llibre canviat de '$estat_anterior' a '$nou_estat'.";
        $llibre['estat'] = $nou_estat;
    }
}

$currentPage = 'admin_catalog';
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visibilitat del Llibre | Administració</title>
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
                    <h2 style="font-size: 1.25rem;">Visibilitat i Estat</h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem;"><?= htmlspecialchars($llibre['titol']) ?> — Estat actual: <strong><?= ucfirst($llibre['estat']) ?></strong></p>
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

            <section style="max-width: 600px;">
                <form method="POST" action="">
                    <div class="card mb-4">
                        <h3 style="margin-bottom: var(--spacing-lg);">Selecciona l'estat</h3>

                        <label class="estat-option <?= $llibre['estat'] === 'publicat' ? 'selected' : '' ?>">
                            <input type="radio" name="estat" value="publicat" <?= $llibre['estat'] === 'publicat' ? 'checked' : '' ?>>
                            <div>
                                <div class="estat-title" style="color: #166534;">📗 Publicat</div>
                                <div class="estat-desc">El llibre apareix al catàleg públic i es pot prestar o reservar.</div>
                            </div>
                        </label>

                        <label class="estat-option <?= $llibre['estat'] === 'ocult' ? 'selected' : '' ?>">
                            <input type="radio" name="estat" value="ocult" <?= $llibre['estat'] === 'ocult' ? 'checked' : '' ?>>
                            <div>
                                <div class="estat-title" style="color: #92400E;">📙 Ocult</div>
                                <div class="estat-desc">El llibre no apareix al catàleg públic, però els préstecs actius continuen vigents.</div>
                            </div>
                        </label>

                        <label class="estat-option <?= $llibre['estat'] === 'retirat' ? 'selected' : '' ?>">
                            <input type="radio" name="estat" value="retirat" <?= $llibre['estat'] === 'retirat' ? 'checked' : '' ?>>
                            <div>
                                <div class="estat-title" style="color: #991B1B;">📕 Retirat</div>
                                <div class="estat-desc">El llibre es retira del catàleg. Requereix 0 préstecs actius i 0 reserves.</div>
                                <?php if ($prestecs_actius > 0 || $reserves_actives > 0): ?>
                                    <div class="estat-desc" style="color: var(--error-color); margin-top:0.3rem;">⚠️ No disponible: <?= $prestecs_actius ?> préstec(s), <?= $reserves_actives ?> reserva(es)</div>
                                <?php endif; ?>
                            </div>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">Canviar Estat</button>
                    <a href="index.php" class="btn btn-secondary" style="margin-left: 0.5rem;">Cancel·lar</a>
                </form>
            </section>
        </main>
    </div>

    <script>
        // Highlight selected option
        document.querySelectorAll('.estat-option input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', () => {
                document.querySelectorAll('.estat-option').forEach(opt => opt.classList.remove('selected'));
                radio.closest('.estat-option').classList.add('selected');
            });
        });
    </script>
</body>
</html>
