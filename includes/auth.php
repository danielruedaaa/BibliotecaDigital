<?php
// Funcions d'autenticació
session_start();

require_once __DIR__ . '/../config/database.php';

function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function getCurrentUser()
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM Usuari WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}
