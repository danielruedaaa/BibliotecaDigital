<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail_helper.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (empty($nom) || empty($email) || empty($password) || empty($password2)) {
        $error = 'Si us plau, omple tots els camps obligatoris.';
    } elseif ($password !== $password2) {
        $error = 'Les contrasenyes no coincideixen.';
    } elseif (strlen($password) < 6) {
        $error = 'La contrasenya ha de tenir almenys 6 caràcters.';
    } else {
        $db = getDB();
        // Comprovar si el correu ja existeix
        $stmt = $db->prepare("SELECT id FROM Usuari WHERE correu_electronic = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Ja existeix un compte amb aquest correu electrònic.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(16));
            
            $stmt = $db->prepare("INSERT INTO Usuari (nom, correu_electronic, telefon, hash_contrasenya, estat, rol, token_activacio) VALUES (?, ?, ?, ?, 'pendent', 'usuari', ?)");
            $stmt->execute([$nom, $email, $telefon, $hash, $token]);
            
            // Enviar correu real amb PHPMailer i desat al log
            $sent = sendActivationEmail($email, $nom, $token);
            
            $success = "Compte creat correctament! S'ha enviat un correu d'activació a <strong>" . htmlspecialchars($email) . "</strong>.<br><br><strong>Drecera de proves:</strong> pots activar-lo fent clic directament a <a href='activate.php?token=$token'>aquest enllaç d'activació</a> (també desat a <code>logs/emails.log</code>).";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registre | Biblioteca Digital</title>
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
                    <h1>Crea el teu compte</h1>
                    <p style="color: var(--text-muted);">Registra't per accedir a la biblioteca digital.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert-error">
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert-success">
                        <?= $success ?>
                    </div>
                <?php endif; ?>

                <?php if (!$success): ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label" for="nom">Nom Complet *</label>
                            <input type="text" id="nom" name="nom" class="form-input" placeholder="El teu nom complet"
                                required value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Correu Electrònic *</label>
                            <input type="email" id="email" name="email" class="form-input" placeholder="nom@exemple.com"
                                required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="telefon">Telèfon</label>
                            <input type="tel" id="telefon" name="telefon" class="form-input" placeholder="600 123 456"
                                value="<?= htmlspecialchars($_POST['telefon'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">Contrasenya *</label>
                            <input type="password" id="password" name="password" class="form-input"
                                placeholder="Mínim 6 caràcters" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password2">Repetir Contrasenya *</label>
                            <input type="password" id="password2" name="password2" class="form-input"
                                placeholder="Repeteix la contrasenya" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Crear Compte</button>
                    </form>
                <?php endif; ?>

                <div class="auth-footer">
                    <p>Ja tens un compte? <a href="login.php">Inicia sessió</a></p>
                </div>
            </div>
        </div>

        <div class="hero-side register-hero">
            <div class="text-center">
                <h2 style="font-size: 3rem; margin-bottom: 1rem; color: white;">Uneix-te</h2>
                <p>Accedeix a milers de recursos bibliogràfics.</p>
            </div>
        </div>
    </div>
</body>

</html>