<?php

/**
 * Autenticación por sesión y por token de API.
 *
 * - Las páginas del sistema usan sesión (login.php).
 * - La API acepta la sesión del navegador o un token especial.
 * - La documentación Swagger requiere el token de acceso.
 */

function iniciarSesionAuth(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $config = require __DIR__ . '/config/auth.php';
    $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    session_name((string) $config['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

iniciarSesionAuth();

function usuarioActual(): ?array
{
    $usuario = $_SESSION['usuario'] ?? null;

    return is_array($usuario) ? $usuario : null;
}

function esAdmin(): bool
{
    $usuario = usuarioActual();

    return $usuario !== null && ($usuario['rol'] ?? '') === 'admin';
}

function requiereSesion(): array
{
    $usuario = usuarioActual();

    if ($usuario === null) {
        header('Location: login.php');
        exit;
    }

    return $usuario;
}

function requiereAdmin(): array
{
    $usuario = requiereSesion();

    if (($usuario['rol'] ?? '') !== 'admin') {
        header('Location: lista_alumnos.php?error=sin_permiso');
        exit;
    }

    return $usuario;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrfValido($token): bool
{
    return is_string($token)
        && $token !== ''
        && isset($_SESSION['csrf'])
        && is_string($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

/**
 * Valida las credenciales y crea la sesión.
 *
 * @throws RuntimeException si no se puede conectar con la base de datos.
 */
function autenticar(string $usuario, string $password): bool
{
    $config = require __DIR__ . '/config/database.php';

    $conexion = @mysqli_connect(
        (string) $config['host'],
        (string) $config['user'],
        (string) $config['pass'],
        (string) $config['name'],
        (int) $config['port']
    );

    if (!$conexion) {
        throw new RuntimeException('No se pudo conectar con la base de datos.');
    }

    mysqli_set_charset($conexion, 'utf8mb4');

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT id, usuario, nombre, rol, password_hash
           FROM usuarios
          WHERE usuario = ? AND activo = 1
          LIMIT 1"
    );

    if (!$stmt) {
        mysqli_close($conexion);
        throw new RuntimeException('No se pudo validar el inicio de sesión.');
    }

    mysqli_stmt_bind_param($stmt, 's', $usuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $fila = $resultado ? mysqli_fetch_assoc($resultado) : null;

    mysqli_stmt_close($stmt);
    mysqli_close($conexion);

    if (!$fila || !password_verify($password, (string) $fila['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['usuario'] = [
        'id' => (int) $fila['id'],
        'usuario' => (string) $fila['usuario'],
        'nombre' => (string) $fila['nombre'],
        'rol' => (string) $fila['rol'],
    ];
    $_SESSION['login_en'] = time();

    return true;
}

function cerrarSesion(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parametros['path'],
            $parametros['domain'],
            $parametros['secure'],
            $parametros['httponly']
        );
    }

    session_destroy();
}

function tokenDeSolicitud(): string
{
    $cabecera = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if ($cabecera === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $nombre => $valor) {
            if (strtolower((string) $nombre) === 'authorization') {
                $cabecera = (string) $valor;
                break;
            }
        }
    }

    if (preg_match('/^Bearer\s+(.+)$/i', trim((string) $cabecera), $coincidencias)) {
        return trim($coincidencias[1]);
    }

    $cabeceraToken = $_SERVER['HTTP_X_API_TOKEN'] ?? '';

    if (is_string($cabeceraToken) && trim($cabeceraToken) !== '') {
        return trim($cabeceraToken);
    }

    $tokenQuery = $_GET['token'] ?? '';

    return is_string($tokenQuery) ? trim($tokenQuery) : '';
}

function tokenApiValido(string $token): bool
{
    if ($token === '') {
        return false;
    }

    $config = require __DIR__ . '/config/auth.php';
    $esperado = $config['api_token'] ?? '';

    return is_string($esperado)
        && $esperado !== ''
        && hash_equals($esperado, $token);
}

function responderApiAuth(array $datos, int $codigo): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Devuelve el usuario autenticado por token o por sesión.
 * Responde 401 si no hay ninguna autenticación válida.
 */
function autorizarApi(): array
{
    if (tokenApiValido(tokenDeSolicitud())) {
        return [
            'id' => 0,
            'usuario' => 'token',
            'nombre' => 'Token API',
            'rol' => 'admin',
            'via' => 'token',
        ];
    }

    $usuario = usuarioActual();

    if ($usuario !== null) {
        $usuario['via'] = 'sesion';

        return $usuario;
    }

    responderApiAuth([
        'ok' => false,
        'mensaje' => 'No autorizado. Inicia sesión o envía un token válido.',
    ], 401);
}

function requiereAdminApi(array $autenticacion): void
{
    if (($autenticacion['via'] ?? '') === 'token') {
        return;
    }

    if (($autenticacion['rol'] ?? '') !== 'admin') {
        responderApiAuth([
            'ok' => false,
            'mensaje' => 'No tienes permisos para modificar datos.',
        ], 403);
    }
}
