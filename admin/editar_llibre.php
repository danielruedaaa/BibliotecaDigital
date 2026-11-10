<?php
// Administració: Modificar un llibre
require_once 'auth_admin.php';

$db = getDB();
$user = getCurrentUser();
$error = '';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// Obtenir llibre actual
$stmt = $db->prepare("SELECT * FROM Llibre WHERE id = ? AND eliminat = 0");
$stmt->execute([$id]);
$llibre = $stmt->fetch();
if (!$llibre) { header('Location: index.php'); exit; }

// Comptar préstecs actius
$stmt_pa = $db->prepare("SELECT COUNT(*) FROM Prestec p JOIN Exemplar e ON p.id_exemplar = e.id WHERE e.id_llibre = ? AND p.estat = 'actiu'");
$stmt_pa->execute([$id]);
$prestecs_actius = $stmt_pa->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titol = trim($_POST['titol'] ?? '');
    $autor = trim($_POST['autor'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $descripcio = trim($_POST['descripcio'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');

    if (empty($titol) || empty($autor) || empty($isbn)) {
        $error = "Camps obligatoris: títol, autor i ISBN.";
    } elseif (!preg_match('/^\d{13}$/', str_replace('-', '', $isbn))) {
        $error = "L'ISBN ha de tenir 13 dígits.";
    } else {
        // Comprovar ISBN únic (excloent el propi)
        $clean_isbn = str_replace('-', '', $isbn);
        $stmt_isbn = $db->prepare("SELECT id FROM Llibre WHERE ISBN = ? AND id != ?");
        $stmt_isbn->execute([$clean_isbn, $id]);
        if ($stmt_isbn->fetch()) {
            $error = "Ja existeix un altre llibre amb aquest ISBN.";
        } else {
            // Gestió portada
            $portada_path = $llibre['portada'];
            if (isset($_FILES['portada']) && $_FILES['portada']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['portada']['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mime, $allowed)) {
                    $error = "Format de portada no permès. Usa JPG, PNG o WebP.";
                } elseif ($_FILES['portada']['size'] > 2 * 1024 * 1024) {
                    $error = "La imatge no pot superar els 2 MB.";
                } else {
                    $ext = pathinfo($_FILES['portada']['name'], PATHINFO_EXTENSION);
                    $filename = 'portada_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = __DIR__ . '/../img/' . $filename;
                    if (!is_dir(__DIR__ . '/../img/')) mkdir(__DIR__ . '/../img/', 0755, true);
                    if (move_uploaded_file($_FILES['portada']['tmp_name'], $dest)) {
                        $portada_path = '../img/' . $filename;
                    } else {
                        $error = "Error pujant la imatge.";
                    }
                }
            }

            if (empty($error)) {
                $stmt_update = $db->prepare("UPDATE Llibre SET titol = ?, autor = ?, ISBN = ?, descripcio = ?, categoria = ?, portada = ? WHERE id = ?");
                $stmt_update->execute([$titol, $autor, $clean_isbn, $descripcio, $categoria, $portada_path, $id]);
                header("Location: index.php?msg=Llibre+actualitzat+correctament");
                exit;
            }
        }
    }
    // Actualitzar $llibre amb les dades POST per mostrar al formulari
    $llibre['titol'] = $titol;
    $llibre['autor'] = $autor;
    $llibre['ISBN'] = $isbn;
    $llibre['descripcio'] = $descripcio;
    $llibre['categoria'] = $categoria;
}

$currentPage = 'admin_catalog';
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Llibre | Administració</title>
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
                    <h2 style="font-size: 1.25rem;">Editar Llibre #<?= $id ?></h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem;"><?= htmlspecialchars($llibre['titol']) ?></p>
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
                        <h3 style="margin-bottom: var(--spacing-lg);">Dades del Llibre</h3>

                        <div class="form-group">
                            <label class="form-label">Títol *</label>
                            <input type="text" name="titol" class="form-input" required value="<?= htmlspecialchars($llibre['titol']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Autor *</label>
                            <input type="text" name="autor" class="form-input" required value="<?= htmlspecialchars($llibre['autor']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ISBN-13 *</label>
                            <input type="text" name="isbn" class="form-input" required maxlength="17" value="<?= htmlspecialchars($llibre['ISBN']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Descripció</label>
                            <textarea name="descripcio" class="form-input" rows="3"><?= htmlspecialchars($llibre['descripcio'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Categoria</label>
                            <input type="text" name="categoria" class="form-input" value="<?= htmlspecialchars($llibre['categoria'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Portada</label>
                            <?php if ($llibre['portada']): ?>
                                <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.5rem;">Actual: <?= htmlspecialchars($llibre['portada']) ?></p>
                            <?php endif; ?>
                            <input type="file" name="portada" class="form-input" accept="image/jpeg,image/png,image/webp">
                            <small style="color: var(--text-muted);">Deixa en blanc per mantenir l'actual.</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Guardar Canvis</button>
                    <a href="index.php" class="btn btn-secondary" style="margin-left: 0.5rem;">Cancel·lar</a>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
