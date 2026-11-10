<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail_helper.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

$db = getDB();

// 1. Processar activació per enllaç directe (GET ?token=...)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = trim($_GET['token']);
    if (empty($token)) {
        $error = "El token d'activació no pot estar buit.";
    } else {
        // Cercar usuari amb aquest token d'activació i estat pendent
        $stmt = $db->prepare("SELECT id, nom FROM Usuari WHERE token_activacio = ? AND estat = 'pendent'");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Activar l'usuari i netejar el token
            $stmt_act = $db->prepare("UPDATE Usuari SET estat = 'actiu', token_activacio = NULL WHERE id = ?");
            $stmt_act->execute([$user['id']]);
            $success = 'Compte activat correctament! Benvingut/da, ' . htmlspecialchars($user['nom']) . '. Ya pots <a href="login.php">iniciar sessió</a>.';
        } else {
            $error = "Enllaç d'activació no vàlid o el compte ja ha estat activat.";
        }
    }
}

// 2. Processar sol·licitud manual / reenviament (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Si us plau, introdueix el teu correu electrònic.';
    } else {
        $stmt = $db->prepare("SELECT id, nom, estat FROM Usuari WHERE correu_electronic = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'No s\'ha trobat cap compte amb aquest correu electrònic.';
        } elseif ($user['estat'] === 'actiu') {
            $error = 'Aquest compte ja està activat. <a href="login.php">Inicia sessió</a>.';
        } elseif ($user['estat'] === 'inactiu') {
            $error = 'Aquest compte ha estat desactivat. Contacta amb l\'administrador.';
        } else {
            // Generar nou token d'activació i actualitzar-lo
            $token = bin2hex(random_bytes(16));
            $stmt_tok = $db->prepare("UPDATE Usuari SET token_activacio = ? WHERE id = ?");
            $stmt_tok->execute([$token, $user['id']]);

            // Enviar correu amb PHPMailer i desat al log
            $sent = sendActivationEmail($email, $user['nom'], $token, true);

            $success = "T'hem enviat un nou correu d'activació a <strong>" . htmlspecialchars($email) . "</strong>.<br><br><strong>Drecera de proves:</strong> pots activar-lo fent clic directament a <a href='activate.php?token=$token'>aquest enllaç d'activació</a> (també desat a <code>logs/emails.log</code>).";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activar Compte | Biblioteca Digital</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/layout.css">
    <link rel="stylesheet" href="../css/components.css">
    <link rel="stylesheet" href="../css/auth.css">
</head>

<body>
    <div class="split-screen">
        <div class="auth-side">
            <div class="auth-content">
                <div style="margin-bottom: var(--spacing-lg);">
                    <a href="login.php"
                        style="font-size: var(--font-small); display: flex; align-items: center; gap: 0.5rem;">
                        &larr; Tornar al login
                    </a>
                </div>

                <div class="auth-header">
                    <h1>Activar Compte</h1>
                    <p style="color: var(--text-muted);">Introdueix el correu amb el qual et vas registrar per activar
                        el teu compte.</p>
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
                            <label class="form-label" for="email">Correu Electrònic</label>
                            <input type="email" id="email" name="email" class="form-input" placeholder="nom@exemple.com"
                                required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Activar Compte</button>
                    </form>
                <?php endif; ?>

                <div class="auth-footer">
                    <p>Ja tens el compte activat? <a href="login.php">Inicia sessió</a></p>
                </div>
            </div>
        </div>

        <div class="hero-side activate-hero">
            <div class="text-center">
                <h2 style="font-size: 3rem; margin-bottom: 1rem; color: white;">Activació</h2>
                <p>Un pas més per accedir al coneixement.</p>
            </div>
        </div>
    </div>
</body>

</html>