<?php
// Administració: Donar d'alta un llibre
require_once 'auth_admin.php';

$db = getDB();
$user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titol = trim($_POST['titol'] ?? '');
    $autor = trim($_POST['autor'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $descripcio = trim($_POST['descripcio'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $stock = intval($_POST['stock'] ?? 0);

    // Validacions
    if (empty($titol) || empty($autor) || empty($isbn)) {
        $error = "Camps obligatoris: títol, autor i ISBN.";
    } elseif (!preg_match('/^\d{13}$/', str_replace('-', '', $isbn))) {
        $error = "L'ISBN ha de tenir 13 dígits (format ISBN-13).";
    } elseif ($stock < 0) {
        $error = "El nombre d'exemplars ha de ser 0 o superior.";
    } else {
        // Comprovar ISBN únic
        $stmt_isbn = $db->prepare("SELECT id FROM Llibre WHERE ISBN = ?");
        $stmt_isbn->execute([str_replace('-', '', $isbn)]);
        if ($stmt_isbn->fetch()) {
            $error = "Ja existeix un llibre amb aquest ISBN al sistema.";
        } else {
            // Gestió de la portada
            $portada_path = null;
            if (isset($_FILES['portada']) && $_FILES['portada']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['portada']['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mime, $allowed)) {
                    $error = "Format de portada no permès. Usa JPG, PNG o WebP.";
                } elseif ($_FILES['portada']['size'] > 2 * 1024 * 1024) {
                    $error = "La imatge de portada no pot superar els 2 MB.";
                } else {
                    $ext = pathinfo($_FILES['portada']['name'], PATHINFO_EXTENSION);
                    $filename = 'portada_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = __DIR__ . '/../img/' . $filename;

                    if (!is_dir(__DIR__ . '/../img/')) {
                        mkdir(__DIR__ . '/../img/', 0755, true);
                    }

                    if (move_uploaded_file($_FILES['portada']['tmp_name'], $dest)) {
                        $portada_path = '../img/' . $filename;
                    } else {
                        $error = "Error pujant la imatge de portada.";
                    }
                }
            }

            if (empty($error)) {
                $db->beginTransaction();
                try {
                    // Inserir llibre
                    $stmt = $db->prepare("INSERT INTO Llibre (titol, autor, ISBN, descripcio, categoria, portada, estat, eliminat) VALUES (?, ?, ?, ?, ?, ?, 'publicat', 0)");
                    $stmt->execute([$titol, $autor, str_replace('-', '', $isbn), $descripcio, $categoria, $portada_path]);
                    $llibre_id = $db->lastInsertId();

                    // Crear exemplars
                    for ($i = 0; $i < $stock; $i++) {
                        $db->exec("INSERT INTO Exemplar (id_llibre, estat) VALUES ($llibre_id, 'disponible')");
                    }

                    // Registre d'auditoria
                    $stmt_audit = $db->prepare("INSERT INTO Audit_Stock (id_llibre, id_admin, accio, valor_anterior, valor_nou) VALUES (?, ?, 'alta_llibre', 0, ?)");
                    $stmt_audit->execute([$llibre_id, $user['id'], $stock]);

                    $db->commit();
                    header("Location: index.php?msg=Llibre+creat+correctament+amb+$stock+exemplars");
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Error creant el llibre: " . $e->getMessage();
                }
            }
        }
    }
}

$currentPage = 'admin_alta';
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alta de Llibre | Administració</title>
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
                    <h2 style="font-size: 1.25rem;">Donar d'Alta un Llibre</h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem;">Afegeix un nou títol al catàleg.</p>
                </div>
                <div class="user-profile">
                    <a href="index.php" class="btn btn-secondary" style="padding: 0.5rem 1rem;">← Tornar</a>
                </div>
            </header>

            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <section style="max-width: 650px;">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="card mb-4">
                        <h3 style="margin-bottom: var(--spacing-lg);">Informació del Llibre</h3>

                        <div class="form-group">
                            <label class="form-label">Títol *</label>
                            <input type="text" name="titol" class="form-input" required value="<?= htmlspecialchars($_POST['titol'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Autor *</label>
                            <input type="text" name="autor" class="form-input" required value="<?= htmlspecialchars($_POST['autor'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ISBN-13 *</label>
                            <input type="text" name="isbn" class="form-input" required placeholder="9781234567890" maxlength="17" value="<?= htmlspecialchars($_POST['isbn'] ?? '') ?>">
                            <small style="color: var(--text-muted);">13 dígits. Pots escriure'l amb o sense guions.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Descripció</label>
                            <textarea name="descripcio" class="form-input" rows="3"><?= htmlspecialchars($_POST['descripcio'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Categoria</label>
                            <input type="text" name="categoria" class="form-input" placeholder="p. ex. Informàtica, Literatura..." value="<?= htmlspecialchars($_POST['categoria'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Portada (imatge)</label>
                            <input type="file" name="portada" class="form-input" accept="image/jpeg,image/png,image/webp">
                            <small style="color: var(--text-muted);">JPG, PNG o WebP. Màxim 2 MB.</small>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <h3 style="margin-bottom: var(--spacing-lg);">Exemplars</h3>
                        <div class="form-group">
                            <label class="form-label">Nombre d'exemplars inicials</label>
                            <input type="number" name="stock" class="form-input" min="0" value="<?= htmlspecialchars($_POST['stock'] ?? '1') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Crear Llibre</button>
                    <a href="index.php" class="btn btn-secondary" style="margin-left: 0.5rem;">Cancel·lar</a>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
