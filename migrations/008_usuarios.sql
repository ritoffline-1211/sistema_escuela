-- Migración 008: usuarios de inicio de sesión.
-- Las contraseñas están guardadas con password_hash() de PHP (bcrypt).
-- Usuarios iniciales:
--   ricardo  / 1211  (admin)
--   profesor / 1212  (consulta, solo lectura)

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'consulta') NOT NULL DEFAULT 'consulta',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO usuarios (usuario, nombre, password_hash, rol, activo)
VALUES
    (
        'ricardo',
        'Ricardo',
        '$2y$10$AR2JgSCJEpWY0izrVd9nt.I2.WjmCF0D4A9Q59J11zhynNlMnPaHS',
        'admin',
        1
    ),
    (
        'profesor',
        'Profesor',
        '$2y$10$RAnh6o1IV0fvWGwOCH6aYOG.nf2Wa/dTm2bDq8o0qX69PLvDOkN32',
        'consulta',
        1
    )
ON DUPLICATE KEY UPDATE usuario = usuario;
