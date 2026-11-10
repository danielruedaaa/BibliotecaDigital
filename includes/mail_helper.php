<?php
// Helper per a l'enviament de correus electrònics amb PHPMailer

require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envia un correu electrònic d'activació d'usuari utilitzant PHPMailer (SMTP).
 * Si falla l'enviament SMTP, es guarda al fitxer de log per a poder fer proves de totes maneres.
 *
 * @param string $email Correu electrònic de destinació.
 * @param string $nom Nom de l'usuari.
 * @param string $token Token d'activació.
 * @param bool $isResend Indica si és un re-enviament.
 * @return bool Retorna true si s'ha enviat correctament per SMTP, o false en cas contrari.
 */
function sendActivationEmail($email, $nom, $token, $isResend = false)
{
    $base = defined('APP_URL') ? APP_URL : 'http://localhost/BiblioDigital';
    $activationLink = $base . "/pages/activate.php?token=" . urlencode($token);
    $subject = $isResend ? "Re-enviament: Activa el teu compte - Biblioteca Digital" : "Activa el teu compte - Biblioteca Digital";
    
    $bodyHtml = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #0F3C6D; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #E2E8F0; border-radius: 8px;'>
            <h2 style='color: #004AAD; margin-bottom: 20px;'>Hola " . htmlspecialchars($nom) . ",</h2>
            <p>Gràcies per registrar-te a la <strong>Biblioteca Digital</strong>.</p>
            <p>Per poder accedir al teu compte, cal que l'activis fent clic al següent botó:</p>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='" . $activationLink . "' style='background-color: #004AAD; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Activar el meu compte</a>
            </p>
            <p style='font-size: 0.85rem; color: #576F86;'>Si el botó no funciona, pots copiar i enganxar la següent adreça al teu navegador:<br>
            <a href='" . $activationLink . "'>" . $activationLink . "</a></p>
            <hr style='border: none; border-top: 1px solid #E2E8F0; margin: 20px 0;'>
            <p style='font-size: 0.8rem; color: #576F86;'>Si tu no has creat aquest compte, pots ignorar aquest correu amb total seguretat.</p>
        </div>
    ";
    
    $bodyText = "Hola " . $nom . ",\n\n" .
                "Gràcies per registrar-te a la Biblioteca Digital.\n" .
                "Per activar el teu compte, si us plau accedeix a la següent adreça:\n" .
                $activationLink . "\n\n" .
                "Si no has demanat aquest registre, pots ignorar aquest missatge.";

    // 1. Intentar l'enviament real amb PHPMailer SMTP (solo si hay credenciales)
    $mail = new PHPMailer(true);
    $smtpSuccess = false;
    $smtpErrorMsg = '';

    $smtpConfigured = (MAIL_USERNAME !== '' && MAIL_PASSWORD !== '' && MAIL_FROM_ADDRESS !== '');

    if (!$smtpConfigured) {
        $smtpErrorMsg = 'SMTP no configurado (.env vacío): se usa solo logs/emails.log';
    } else
    try {
        // Configuració del servidor SMTP
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->Port       = MAIL_PORT;
        $mail->SMTPAuth   = MAIL_SMTP_AUTH;
        
        if (MAIL_SMTP_AUTH) {
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
        }
        
        // Encriptació si està definida
        if (!empty(MAIL_ENCRYPTION)) {
            $mail->SMTPSecure = MAIL_ENCRYPTION;
        } else {
            // Desactiva validacions SSL en local si no s'utilitza encriptació
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
        }

        // Destinataris
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($email, $nom);

        // Contingut
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->AltBody = $bodyText;
        $mail->CharSet = 'UTF-8';

        $mail->send();
        $smtpSuccess = true;
    } catch (Exception $e) {
        $smtpSuccess = false;
        $smtpErrorMsg = $mail->ErrorInfo;
    }

    // 2. Sempre guardem un registre simulat a logs/emails.log (per desenvolupament i fallback)
    $logsDir = __DIR__ . '/../logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }
    
    $logMessage = sprintf(
        "========================================\nDate: %s\nTo: %s\nSubject: %s\nSMTP Sending Status: %s%s\nBody:\n%s\n========================================\n\n",
        date('Y-m-d H:i:s'),
        $email,
        $subject,
        $smtpSuccess ? "SUCCESS" : "FAILED",
        !$smtpSuccess ? " (Error: " . $smtpErrorMsg . ")" : "",
        $bodyText
    );
    file_put_contents($logsDir . '/emails.log', $logMessage, FILE_APPEND);

    return $smtpSuccess;
}
