<?php

require_once __DIR__ . '/../auth.php';

$configAuth = require __DIR__ . '/../config/auth.php';
$tokenEsperado = (string) ($configAuth['api_token'] ?? '');
$tokenSesion = $_SESSION['docs_token'] ?? '';

$autorizado = is_string($tokenSesion)
    && $tokenSesion !== ''
    && $tokenEsperado !== ''
    && hash_equals($tokenEsperado, $tokenSesion);

if (!$autorizado) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Acceso no autorizado a la documentación.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$contrato = require __DIR__ . '/openapi_data.php';

if (!is_string($contrato) || trim($contrato) === '') {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'mensaje' => 'No se pudo cargar el contrato OpenAPI.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo $contrato;
