<?php
// Control d'accés per a l'àrea d'administració
// Inclou aquest fitxer amb require_once a l'inici de cada pàgina admin
require_once __DIR__ . '/../includes/auth.php';

// Primer, requereix que estigui logejat
requireLogin();

// Després, comprova que sigui admin
$currentUserAdmin = getCurrentUser();
if (!$currentUserAdmin || $currentUserAdmin['rol'] !== 'admin') {
    // No és admin: redirigir al dashboard d'usuari normal
    header('Location: ../pages/dashboard.php');
    exit;
}
