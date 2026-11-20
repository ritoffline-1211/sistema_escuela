<?php

declare(strict_types=1);

use Escuela\Tests\Support\ServerManager;

require dirname(__DIR__) . '/vendor/autoload.php';

$baseDir = dirname(__DIR__);

if (getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "Los tests solo pueden ejecutarse con APP_ENV=testing.\n");
    exit(1);
}

if (getenv('DB_NAME') !== 'testing' || getenv('DB_USER') !== 'test') {
    fwrite(STDERR, "La configuración de DB debe apuntar a la base desechable 'testing'.\n");
    exit(1);
}

/* --------------------------------------------------------------------------
 * 1. Aplicar las migraciones a la base de pruebas (nunca a la de desarrollo).
 * ------------------------------------------------------------------------ */
$comandoMigracion = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($baseDir . '/migrate.php');
$descriptores = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$proceso = proc_open($comandoMigracion, $descriptores, $pipes, $baseDir);

if (!is_resource($proceso)) {
    throw new RuntimeException('No se pudo ejecutar migrate.php para la base de pruebas.');
}

fclose($pipes[0]);
$salida = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$codigo = proc_close($proceso);

if ($codigo !== 0) {
    throw new RuntimeException("Fallaron las migraciones de prueba: {$salida} {$error}");
}

/* --------------------------------------------------------------------------
 * 2. Servidor HTTP local. Si TEST_BASE_URL ya responde (p. ej. Apache), se usa.
 * ------------------------------------------------------------------------ */
$baseUrl = (string) (getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8000');
ServerManager::ensureRunning($baseUrl);
register_shutdown_function(static fn () => ServerManager::stop());
