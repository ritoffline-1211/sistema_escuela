<?php

/**
 * Configuración de conexión a MySQL.
 * Se puede sobrescribir con variables de entorno: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS.
 */
return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'name' => getenv('DB_NAME') ?: 'escuela',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
];
