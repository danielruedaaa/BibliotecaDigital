<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $passwordActual = $_POST['password_actual'] ?? '';
    $passwordNova = $_POST['password_nova'] ?? '';
    $passwordNova2 = $_POST['password_nova2'] ?? '';

    if (empty($nom)) {
        $error = 'El nom és obligatori.';
    } else {
        $db = getDB();

        // Actualitzar dades bàsiques (correu exclòs perquè és readonly)
        $stmt = $db->prepare("UPDATE Usuari SET nom = ?, telefon = ? WHERE id = ?");
        $stmt->execute([$nom, $telefon, $user['id']]);

        // Canviar contrasenya (si s'ha omplert)
        if (!empty($passwordActual) || !empty($passwordNova)) {
            if (empty($passwordActual)) {
                $error = 'Has d\'introduir la contrasenya actual per canviar-la.';
            } elseif (!password_verify($passwordActual, $user['hash_contrasenya'])) {
                $error = 'La contrasenya actual no és correcta.';
            } elseif (strlen($passwordNova) < 6) {
                $error = 'La nova contrasenya ha de tenir almenys 6 caràcters.';
            } elseif ($passwordNova !== $passwordNova2) {
                $error = 'Les noves contrasenyes no coincideixen.';
            } else {
                $hash = password_hash($passwordNova, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE Usuari SET hash_contrasenya = ? WHERE id = ?");
                $stmt->execute([$hash, $user['id']]);
            }
        }

        if (empty($error)) {
            $success = 'Perfil actualitzat correctament!';
            // Refrescar dades
            $user = getCurrentUser();
            $_SESSION['user_nom'] = $user['nom'];
        }
    }
}

$currentPage = 'profile';
?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El meu Perfil | Biblioteca Digital</title>
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
                    <h2 style="font-size: 1.25rem;">El meu Perfil</h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem;">Actualitza les teves dades personals.</p>
                </div>
                <div class="user-profile">
                    <div class="avatar">
                        <?= htmlspecialchars($userInitial) ?>
                    </div>
                </div>
            </header>

            <?php if ($error): ?>
                <div class="alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <section style="max-width: 600px;">
                <form method="POST" action="">
                    <div class="card mb-4">
                        <h3 style="margin-bottom: var(--spacing-lg);">Dades Personals</h3>
                        <div class="form-group">
                            <label class="form-label">Nom Complet</label>
                            <input type="text" name="nom" class="form-input"
                                value="<?= htmlspecialchars($user['nom']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Correu Electrònic</label>
                            <input type="email" name="email" class="form-input"
                                value="<?= htmlspecialchars($user['correu_electronic']) ?>" readonly required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Telèfon</label>
                            <input type="tel" name="telefon" class="form-input"
                                value="<?= htmlspecialchars($user['telefon'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="card mb-4">
                        <h3 style="margin-bottom: var(--spacing-lg);">Canviar Contrasenya</h3>
                        <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: var(--spacing-lg);">
                            Deixa en blanc si no vols canviar-la.</p>
                        <div class="form-group">
                            <label class="form-label">Contrasenya Actual</label>
                            <input type="password" name="password_actual" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nova Contrasenya</label>
                            <input type="password" name="password_nova" class="form-input"
                                placeholder="Mínim 6 caràcters">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Repetir Nova Contrasenya</label>
                            <input type="password" name="password_nova2" class="form-input">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Guardar Canvis</button>
                </form>
            </section>
        </main>
    </div>
</body>

</html>