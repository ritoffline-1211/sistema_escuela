<?php

require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: lista_alumnos.php');
    exit;
}

if (!csrfValido($_POST['csrf'] ?? '')) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Solicitud inválida.';
    exit;
}

cerrarSesion();

header('Location: login.php');
exit;
