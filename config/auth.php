<?php

/**
 * Configuración de autenticación.
 *
 * El token de la API se usa para acceder a la documentación Swagger y para
 * clientes externos. Se puede sobrescribir con la variable de entorno API_TOKEN.
 */
return [
    'api_token' => getenv('API_TOKEN') ?: '442e35cdf0b4225362746e3f8e7a17d4a393b25d2495a628',
    'session_name' => 'escuela_sesion',
];
