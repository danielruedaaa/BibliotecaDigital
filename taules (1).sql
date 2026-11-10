CREATE TABLE Usuari (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nom VARCHAR(100),
  correu_electronic VARCHAR(100) UNIQUE,
  telefon VARCHAR(20),
  hash_contrasenya VARCHAR(255),
  estat ENUM('actiu','pendent','inactiu'),
  rol ENUM('usuari','admin')
);

CREATE TABLE Llibre (
  id INT PRIMARY KEY AUTO_INCREMENT,
  titol VARCHAR(255),
  autor VARCHAR(100),
  ISBN VARCHAR(13) UNIQUE,
  descripcio TEXT,
  categoria VARCHAR(50),
  portada VARCHAR(255),
  estat ENUM('publicat','ocult','retirat')
);

CREATE TABLE Exemplar (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_llibre INT,
  estat ENUM('disponible','prestat','reservat'),
  FOREIGN KEY (id_llibre) REFERENCES Llibre(id)
);

CREATE TABLE Prestec (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_usuari INT,
  id_exemplar INT,
  data_inici DATE,
  data_fi DATE,
  estat ENUM('actiu','retornat','bloquejat'),
  sancio VARCHAR(255),
  FOREIGN KEY (id_usuari) REFERENCES Usuari(id),
  FOREIGN KEY (id_exemplar) REFERENCES Exemplar(id)
);

CREATE TABLE Reserva (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_usuari INT,
  id_llibre INT,
  data DATE,
  estat ENUM('activa','cancel·lada','atesa'),
  FOREIGN KEY (id_usuari) REFERENCES Usuari(id),
  FOREIGN KEY (id_llibre) REFERENCES Llibre(id)
);

CREATE INDEX idx_usuari_correu ON Usuari(correu_electronic);
CREATE INDEX idx_llibre_titol ON Llibre(titol);
CREATE INDEX idx_prestec_estat ON Prestec(estat);
CREATE INDEX idx_exemplar_estat ON Exemplar(estat);
