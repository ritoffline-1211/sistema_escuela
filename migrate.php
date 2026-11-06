<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Las migraciones solo se pueden ejecutar desde la línea de comandos.\n");
}

$config = require __DIR__ . '/config/database.php';

function finalizar(string $mensaje, int $codigo = 1): void
{
    $salida = $codigo === 0 ? STDOUT : STDERR;
    fwrite($salida, $mensaje . "\n");
    exit($codigo);
}

function mostrarAyuda(): void
{
    echo <<<TEXTO
Uso:
  php migrate.php            Aplica las migraciones pendientes
  php migrate.php status     Muestra el estado de las migraciones
  php migrate.php help       Muestra esta ayuda

Variables de entorno opcionales:
  DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS

TEXTO;
}

$nombreBase = (string) $config['name'];

if (!preg_match('/^[A-Za-z0-9_]+$/', $nombreBase)) {
    finalizar('El nombre de la base de datos solo puede contener letras, números y guiones bajos.');
}

$conexion = @mysqli_connect(
    (string) $config['host'],
    (string) $config['user'],
    (string) $config['pass'],
    null,
    (int) $config['port']
);

if (!$conexion) {
    finalizar('No se pudo conectar con MySQL: ' . mysqli_connect_error());
}

mysqli_set_charset($conexion, 'utf8mb4');

$sqlBase = sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    $nombreBase
);

if (!mysqli_query($conexion, $sqlBase)) {
    finalizar('No se pudo crear la base de datos: ' . mysqli_error($conexion));
}

if (!mysqli_select_db($conexion, $nombreBase)) {
    finalizar('No se pudo seleccionar la base de datos: ' . mysqli_error($conexion));
}

$sqlHistorial = "CREATE TABLE IF NOT EXISTS migraciones (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    aplicada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if (!mysqli_query($conexion, $sqlHistorial)) {
    finalizar('No se pudo crear la tabla de migraciones: ' . mysqli_error($conexion));
}

function archivosDeMigracion(): array
{
    $archivos = glob(__DIR__ . '/migrations/*.sql') ?: [];
    sort($archivos, SORT_STRING);

    return $archivos;
}

function migracionesAplicadas(mysqli $conexion): array
{
    $aplicadas = [];
    $resultado = mysqli_query($conexion, 'SELECT nombre FROM migraciones ORDER BY id');

    if (!$resultado) {
        finalizar('No se pudo consultar la tabla de migraciones: ' . mysqli_error($conexion));
    }

    while ($fila = mysqli_fetch_assoc($resultado)) {
        $aplicadas[] = (string) $fila['nombre'];
    }

    mysqli_free_result($resultado);

    return $aplicadas;
}

function ejecutarSql(mysqli $conexion, string $sql, string $archivo): void
{
    if (!mysqli_multi_query($conexion, $sql)) {
        throw new RuntimeException('Error en ' . basename($archivo) . ': ' . mysqli_error($conexion));
    }

    do {
        $resultado = mysqli_store_result($conexion);

        if ($resultado !== false) {
            mysqli_free_result($resultado);
        }

        if (!mysqli_more_results($conexion)) {
            break;
        }

        if (!mysqli_next_result($conexion)) {
            throw new RuntimeException('Error en ' . basename($archivo) . ': ' . mysqli_error($conexion));
        }
    } while (true);
}

function aplicarMigracion(mysqli $conexion, string $archivo): void
{
    $sql = file_get_contents($archivo);

    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('El archivo de migración está vacío o no se puede leer: ' . basename($archivo));
    }

    ejecutarSql($conexion, $sql, $archivo);

    $nombre = basename($archivo);
    $stmt = mysqli_prepare($conexion, 'INSERT INTO migraciones (nombre) VALUES (?)');
    mysqli_stmt_bind_param($stmt, 's', $nombre);

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('La migración se ejecutó pero no se pudo registrar: ' . $error);
    }

    mysqli_stmt_close($stmt);
}

function comandoEstado(mysqli $conexion, string $nombreBase): void
{
    $archivos = archivosDeMigracion();
    $aplicadas = migracionesAplicadas($conexion);

    echo "Base de datos: {$nombreBase}\n";
    echo 'Migraciones encontradas: ' . count($archivos) . "\n\n";

    if (count($archivos) === 0) {
        echo "No hay archivos en migrations/.\n";
        return;
    }

    $pendientes = 0;

    foreach ($archivos as $archivo) {
        $nombre = basename($archivo);
        $aplicada = in_array($nombre, $aplicadas, true);

        if (!$aplicada) {
            $pendientes++;
        }

        echo ($aplicada ? '[APLICADA]   ' : '[PENDIENTE]  ') . $nombre . "\n";
    }

    $sinArchivo = array_diff($aplicadas, array_map('basename', $archivos));

    if (count($sinArchivo) > 0) {
        echo "\nRegistradas en la base pero sin archivo:\n";

        foreach ($sinArchivo as $nombre) {
            echo "  - {$nombre}\n";
        }
    }

    echo "\nPendientes: {$pendientes}\n";
}

function comandoMigrar(mysqli $conexion, string $nombreBase): void
{
    $archivos = archivosDeMigracion();
    $aplicadas = migracionesAplicadas($conexion);
    $pendientes = [];

    foreach ($archivos as $archivo) {
        $nombre = basename($archivo);

        if (!in_array($nombre, $aplicadas, true)) {
            $pendientes[] = $archivo;
        }
    }

    echo "Base de datos: {$nombreBase}\n";

    if (count($pendientes) === 0) {
        echo "No hay migraciones pendientes.\n";
        return;
    }

    echo 'Migraciones pendientes: ' . count($pendientes) . "\n\n";
    $aplicadasAhora = 0;

    foreach ($pendientes as $archivo) {
        $nombre = basename($archivo);
        echo "Aplicando {$nombre}... ";

        try {
            aplicarMigracion($conexion, $archivo);
            $aplicadasAhora++;
            echo "OK\n";
        } catch (Throwable $error) {
            echo "ERROR\n";
            finalizar($error->getMessage());
        }
    }

    echo "\nMigraciones aplicadas: {$aplicadasAhora}\n";
}

$comando = strtolower($argv[1] ?? 'up');

switch ($comando) {
    case 'up':
    case 'migrate':
        comandoMigrar($conexion, $nombreBase);
        break;

    case 'status':
        comandoEstado($conexion, $nombreBase);
        break;

    case 'help':
    case '--help':
    case '-h':
        mostrarAyuda();
        break;

    default:
        finalizar("Comando no reconocido: {$comando}\nUsa 'php migrate.php help' para ver las opciones.");
}

mysqli_close($conexion);
