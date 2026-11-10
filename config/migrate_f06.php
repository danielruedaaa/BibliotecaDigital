<?php
// Migració F06: Afegir suport per soft delete i auditoria d'stock
// Executa UNA SOLA VEGADA (només si tu BD es antiga): http://localhost/BiblioDigital/config/migrate_f06.php

require_once 'database.php';
$db = getDB();

// 1. Afegir columna 'eliminat' a Llibre per soft delete
try {
    $db->exec("ALTER TABLE Llibre ADD COLUMN eliminat TINYINT(1) DEFAULT 0");
    echo "<p>✅ Columna 'eliminat' afegida a Llibre.</p>";
} catch (Exception $e) {
    echo "<p>ℹ️ Columna 'eliminat' ja existeix o error: " . $e->getMessage() . "</p>";
}

// 2. Crear taula d'auditoria de stock
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS Audit_Stock (
            id INT PRIMARY KEY AUTO_INCREMENT,
            id_llibre INT NOT NULL,
            id_admin INT NOT NULL,
            accio VARCHAR(50) NOT NULL,
            valor_anterior INT,
            valor_nou INT,
            data_canvi DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_llibre) REFERENCES Llibre(id),
            FOREIGN KEY (id_admin) REFERENCES Usuari(id)
        )
    ");
    echo "<p>✅ Taula 'Audit_Stock' creada.</p>";
} catch (Exception $e) {
    echo "<p>ℹ️ Taula 'Audit_Stock' ja existeix o error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2 style='font-family:sans-serif; color:green;'>Migració F06 completada!</h2>";
echo "<p style='font-family:sans-serif;'><a href='../admin/index.php'>Anar al panell d'administració →</a></p>";
