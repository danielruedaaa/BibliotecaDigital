<?php
require_once __DIR__ . '/../includes/auth.php';

// Si ja està logejat, anar al dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Processar formulari de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Si us plau, omple tots els camps.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM Usuari WHERE correu_electronic = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['hash_contrasenya'])) {
            // Comprovar si l'usuari està pendent d'activació
            if ($user['estat'] === 'pendent') {
                $error = 'El teu compte encara no està activat. <a href="activate.php">Activa\'l aquí</a>.';
            } elseif ($user['estat'] === 'inactiu') {
                $error = 'El teu compte ha estat desactivat. Contacta amb l\'administrador.';
            } else {
                // Login correcte
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nom'] = $user['nom'];
                $_SESSION['user_rol'] = $user['rol'];
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = 'Correu o contrasenya incorrectes.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accés | Biblioteca Digital</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/layout.css">
    <link rel="stylesheet" href="../css/components.css">
    <link rel="stylesheet" href="../css/auth.css">
</head>

<body>
    <div class="split-screen">
        <div class="auth-side">
            <div class="auth-content">
                <div class="auth-header">
                    <h1>Biblioteca Digital</h1>
                    <p style="color: var(--text-muted);">Accedeix al fons bibliogràfic en línia.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert-error">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="email">Correu Electrònic</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="nom@exemple.com"
                            required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Contrasenya</label>
                        <input type="password" id="password" name="password" class="form-input" placeholder="••••••••"
                            required>
                        <div style="text-align: right; margin-top: 0.5rem;">
                            <a href="forgot-password.html" style="font-size: var(--font-small);">Has oblidat la
                                contrasenya?</a>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Iniciar Sessió</button>
                </form>

                <div class="auth-footer">
                    <p>No tens un compte? <a href="register.php">Registra't</a></p>
                </div>
            </div>
        </div>

        <div class="hero-side login-hero">
            <div class="text-center">
                <h2 style="font-size: 3rem; margin-bottom: 1rem; color: white;">Coneixement Obert</h2>
                <p>Milers de llibres, articles i revistes al teu abast.</p>
            </div>
        </div>
    </div>
</body>

</html>