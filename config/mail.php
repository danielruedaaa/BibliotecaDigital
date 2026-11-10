<?php
// Configuració del Servidor de Correu (PHPMailer SMTP)
// Lee desde .env. No hardcodear credenciales aquí.
require_once __DIR__ . '/env.php';

define('MAIL_HOST', env('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT', (int)env('MAIL_PORT', '587'));
define('MAIL_SMTP_AUTH', filter_var(env('MAIL_SMTP_AUTH', 'true'), FILTER_VALIDATE_BOOLEAN));
define('MAIL_USERNAME', env('MAIL_USERNAME', ''));
define('MAIL_PASSWORD', env('MAIL_PASSWORD', ''));
define('MAIL_ENCRYPTION', env('MAIL_ENCRYPTION', 'tls'));

define('MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME', '')));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Biblioteca Digital'));

define('APP_URL', rtrim(env('APP_URL', 'http://localhost/BiblioDigital'), '/'));
