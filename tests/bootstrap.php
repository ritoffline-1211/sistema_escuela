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
 * 1b. Comprobación de humo: los stored procedures deben poder ejecutarse.
 *     Falla aquí con el error real de MySQL en lugar de dejar 500 en los tests.
 * ------------------------------------------------------------------------ */
$config = require $baseDir . '/config/database.php';
$conexion = @mysqli_connect(
    (string) $config['host'],
    (string) $config['user'],
    (string) $config['pass'],
    (string) $config['name'],
    (int) $config['port']
);

if (!$conexion) {
    throw new RuntimeException('No se pudo conectar con la base de prueba para verificarla.');
}

mysqli_set_charset($conexion, 'utf8mb4');
$smoke = 'PHPUNIT_SMOKE_' . bin2hex(random_bytes(4));
$stmt = mysqli_prepare($conexion, 'CALL sp_crear_grupo(?)');

if (!$stmt) {
    throw new RuntimeException('No se pudo preparar sp_crear_grupo: ' . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmt, 's', $smoke);

if (!mysqli_stmt_execute($stmt)) {
    throw new RuntimeException('No se pudo ejecutar sp_crear_grupo: ' . mysqli_stmt_error($stmt));
}

mysqli_stmt_close($stmt);

while (mysqli_more_results($conexion) && mysqli_next_result($conexion)) {
    // Se descartan resultados adicionales del CALL.
}

mysqli_query(
    $conexion,
    "DELETE FROM grupos WHERE grupo = '" . mysqli_real_escape_string($conexion, $smoke) . "'"
);
mysqli_close($conexion);

/* --------------------------------------------------------------------------
 * 2. Servidor HTTP local. Si TEST_BASE_URL ya responde (p. ej. Apache), se usa.
 * ------------------------------------------------------------------------ */
$baseUrl = (string) (getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8000');
ServerManager::ensureRunning($baseUrl);
register_shutdown_function(static fn () => ServerManager::stop());
