<?php
// Migració per afegir el camp 'token_activacio' per l'activació per email
// Executa una sola vegada (només si tu BD es antiga): http://localhost/BiblioDigital/config/migrate_activation.php

require_once 'database.php';
$db = getDB();

try {
    $db->exec("ALTER TABLE Usuari ADD COLUMN token_activacio VARCHAR(100) DEFAULT NULL");
    echo "<p>✅ Columna 'token_activacio' afegida a Usuari correctament.</p>";
} catch (Exception $e) {
    echo "<p>ℹ️ La columna 'token_activacio' ja existeix o ha hagut un error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2 style='font-family:sans-serif; color:green;'>Migració d'activació completada!</h2>";
echo "<p style='font-family:sans-serif;'><a href='../pages/login.php'>Anar al Login →</a></p>";
