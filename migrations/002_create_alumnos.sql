-- Migración 002: crea la tabla de alumnos y su relación con grupos.
-- Es idempotente para poder ejecutarse sobre una base que ya tiene la tabla.

CREATE TABLE IF NOT EXISTS alumnos (
    IdAlumnos INT(11) NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    matricula VARCHAR(20) NOT NULL,
    grado VARCHAR(20) NOT NULL,
    IdGrupos INT(11) DEFAULT NULL,
    PRIMARY KEY (IdAlumnos),
    KEY IdGrupos (IdGrupos),
    CONSTRAINT alumnos_ibfk_1
        FOREIGN KEY (IdGrupos) REFERENCES grupos (IdGrupos)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
