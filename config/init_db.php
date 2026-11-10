<?php
// Script d'inicialització de la BD
// Executa aquest fitxer UNA SOLA VEGADA per crear les taules i dades de prova
// Exemple XAMPP: http://localhost/BiblioDigital/config/init_db.php

require_once 'database.php';

$db = getDB();

// Crear taules
$db->exec("
CREATE TABLE IF NOT EXISTS Usuari (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nom VARCHAR(100),
  correu_electronic VARCHAR(100) UNIQUE,
  telefon VARCHAR(20),
  hash_contrasenya VARCHAR(255),
  estat ENUM('actiu','pendent','inactiu') DEFAULT 'pendent',
  rol ENUM('usuari','admin') DEFAULT 'usuari',
  token_activacio VARCHAR(100) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS Llibre (
  id INT PRIMARY KEY AUTO_INCREMENT,
  titol VARCHAR(255),
  autor VARCHAR(100),
  ISBN VARCHAR(13) UNIQUE,
  descripcio TEXT,
  categoria VARCHAR(50),
  portada VARCHAR(255),
  estat ENUM('publicat','ocult','retirat') DEFAULT 'publicat',
  eliminat TINYINT(1) DEFAULT 0
);

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
);

CREATE TABLE IF NOT EXISTS Exemplar (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_llibre INT,
  estat ENUM('disponible','prestat','reservat') DEFAULT 'disponible',
  FOREIGN KEY (id_llibre) REFERENCES Llibre(id)
);

CREATE TABLE IF NOT EXISTS Prestec (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_usuari INT,
  id_exemplar INT,
  data_inici DATE,
  data_fi DATE,
  estat ENUM('actiu','retornat','bloquejat') DEFAULT 'actiu',
  sancio VARCHAR(255),
  FOREIGN KEY (id_usuari) REFERENCES Usuari(id),
  FOREIGN KEY (id_exemplar) REFERENCES Exemplar(id)
);

CREATE TABLE IF NOT EXISTS Reserva (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_usuari INT,
  id_llibre INT,
  data DATE,
  estat ENUM('activa','cancel·lada','atesa') DEFAULT 'activa',
  FOREIGN KEY (id_usuari) REFERENCES Usuari(id),
  FOREIGN KEY (id_llibre) REFERENCES Llibre(id)
);
");

// Crear índexs (ignora error si ja existeixen)
try {
    $db->exec('CREATE INDEX idx_usuari_correu ON Usuari(correu_electronic)');
} catch (Exception $e) {
}
try {
    $db->exec('CREATE INDEX idx_llibre_titol ON Llibre(titol)');
} catch (Exception $e) {
}
try {
    $db->exec('CREATE INDEX idx_prestec_estat ON Prestec(estat)');
} catch (Exception $e) {
}
try {
    $db->exec('CREATE INDEX idx_exemplar_estat ON Exemplar(estat)');
} catch (Exception $e) {
}

// Insertar usuari de prova (admin)
$hash = password_hash('admin123', PASSWORD_DEFAULT);
try {
    $db->exec("INSERT INTO Usuari (nom, correu_electronic, telefon, hash_contrasenya, estat, rol) VALUES 
        ('Admin Demo', 'admin@biblioteca.edu', '600123456', '$hash', 'actiu', 'admin')");
} catch (Exception $e) { /* Ja existeix */
}

// Insertar usuari pendent de prova
$hash2 = password_hash('user123', PASSWORD_DEFAULT);
try {
    $db->exec("INSERT INTO Usuari (nom, correu_electronic, telefon, hash_contrasenya, estat, rol) VALUES 
        ('Estudiant Pendent', 'estudiant@biblioteca.edu', '600654321', '$hash2', 'pendent', 'usuari')");
} catch (Exception $e) { /* Ja existeix */
}

// Insertar llibres de prova
try {
    $db->exec("INSERT INTO Llibre (titol, autor, ISBN, descripcio, categoria, estat) VALUES 
        ('Història de l\\'Art', 'E. Gombrich', '9780714832470', 'Un recorregut per la història de l\\'art des dels inicis fins l\\'actualitat.', 'Art', 'publicat'),
        ('Clean Code', 'Robert C. Martin', '9780132350884', 'Una guia pràctica per escriure codi net i mantenible.', 'Informàtica', 'publicat'),
        ('Disseny d\\'Interfícies', 'Jakob Nielsen', '9780125184069', 'Principis fonamentals del disseny d\\'interfícies d\\'usuari.', 'Informàtica', 'publicat'),
        ('El Quixot', 'Miguel de Cervantes', '9788420412146', 'La gran novel·la de la literatura espanyola.', 'Literatura', 'publicat'),
        ('1984', 'George Orwell', '9780451524935', 'Una distopia que reflexiona sobre el totalitarisme.', 'Literatura', 'publicat')
    ");
} catch (Exception $e) { /* Ja existeixen */
}

// Insertar exemplars (1 per llibre)
try {
    $db->exec("INSERT INTO Exemplar (id_llibre, estat) VALUES 
        (1, 'prestat'), (2, 'prestat'), (3, 'prestat'), (4, 'disponible'), (5, 'disponible')");
} catch (Exception $e) { /* Ja existeixen */
}

// Insertar préstecs de prova per l'admin (id=1)
try {
    $db->exec("INSERT INTO Prestec (id_usuari, id_exemplar, data_inici, data_fi, estat) VALUES 
        (1, 1, '2026-02-15', '2026-03-15', 'actiu'),
        (1, 2, '2026-02-20', '2026-03-20', 'actiu'),
        (1, 3, '2026-01-10', '2026-02-10', 'retornat')
    ");
} catch (Exception $e) { /* Ja existeixen */
}

echo "<h1 style='font-family: sans-serif; color: green;'>✅ Base de dades inicialitzada correctament!</h1>";
echo "<p style='font-family: sans-serif;'>Usuaris de prova creats:</p>";
echo "<ul style='font-family: sans-serif;'>";
echo "<li><strong>Admin:</strong> admin@biblioteca.edu / admin123 (actiu)</li>";
echo "<li><strong>Estudiant:</strong> estudiant@biblioteca.edu / user123 (pendent d'activar)</li>";
echo "</ul>";
echo "<p style='font-family: sans-serif;'><a href='../pages/login.php'>Anar al Login →</a></p>";
