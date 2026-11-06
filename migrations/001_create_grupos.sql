-- Migración 001: crea la tabla de grupos.
-- Es idempotente para poder ejecutarse sobre una base que ya tiene la tabla.

CREATE TABLE IF NOT EXISTS grupos (
    IdGrupos INT(11) NOT NULL AUTO_INCREMENT,
    grupo VARCHAR(50) NOT NULL,
    PRIMARY KEY (IdGrupos)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
