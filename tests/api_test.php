<?php

/**
 * Pruebas de la API CRUD de la escuela.
 *
 * Uso:
 *   C:\xampp\php\php.exe tests\api_test.php
 *
 * Requiere que Apache y MySQL de XAMPP estén activos.
 * Se puede cambiar la URL con la variable de entorno API_BASE_URL.
 */

const PREFIJO_GRUPO = 'TEST-API-';
const PREFIJO_MATRICULA = 'TST-';

$configAuth = require __DIR__ . '/../config/auth.php';
$apiToken = (string) ($configAuth['api_token'] ?? '');
$baseUrl = getenv('API_BASE_URL') ?: 'http://localhost/escuela/endpoints.php';
$webBase = rtrim(getenv('WEB_BASE_URL') ?: preg_replace('#/endpoints\.php$#', '', $baseUrl), '/');
$estado = [];
$pasadas = 0;
$falladas = 0;
$detalleFallos = [];

if ($apiToken === '') {
    fwrite(STDERR, "No se encontró el token de la API en config/auth.php.\n");
    exit(1);
}

/* --------------------------- Cliente HTTP --------------------------- */

/**
 * Llama a endpoints.php.
 *
 * Opciones:
 *   token     => true (Bearer por defecto), false (sin token) o string (token concreto)
 *   cabeceras => cabeceras HTTP adicionales
 */
function api(string $metodo, string $consulta = '', ?array $cuerpo = null, string $tipoContenido = 'application/json', array $opciones = []): array
{
    global $baseUrl, $apiToken;

    $url = $baseUrl . ($consulta !== '' ? '?' . $consulta : '');
    $ch = curl_init($url);
    $cabeceras = array_merge(['Accept: application/json'], $opciones['cabeceras'] ?? []);

    if (($opciones['token'] ?? true) !== false) {
        $token = is_string($opciones['token'] ?? true) ? $opciones['token'] : $apiToken;
        $cabeceras[] = 'Authorization: Bearer ' . $token;
    }

    if ($cuerpo !== null) {
        if ($tipoContenido === 'application/json') {
            $payload = json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
        } else {
            $payload = http_build_query($cuerpo);
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $cabeceras[] = 'Content-Type: ' . $tipoContenido;
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $cabeceras,
        CURLOPT_TIMEOUT => 15,
    ]);

    $respuesta = curl_exec($ch);
    $error = curl_error($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($respuesta === false) {
        throw new RuntimeException(
            "No se pudo conectar con la API ({$baseUrl}): {$error}. " .
            'Verifica que Apache y MySQL estén activos.'
        );
    }

    return [
        'status' => $codigo,
        'data' => json_decode($respuesta, true),
        'raw' => $respuesta,
    ];
}

/**
 * Llama a una página del sistema (login, listas, documentación).
 *
 * Opciones:
 *   metodo      => GET (por defecto) o POST
 *   cuerpo      => arreglo para enviar como formulario
 *   cookies     => ruta del archivo de cookies (para conservar la sesión)
 *   seguir      => seguir redirecciones (por defecto false)
 *   contenido   => Content-Type del cuerpo
 */
function web(string $ruta, array $opciones = []): array
{
    global $webBase;

    $url = $webBase . '/' . ltrim($ruta, '/');
    $ch = curl_init($url);
    $cabeceras = ['Accept: text/html,application/json'];
    $cabecerasRespuesta = [];

    if (isset($opciones['cuerpo'])) {
        $cuerpo = $opciones['cuerpo'];
        $contenido = $opciones['contenido'] ?? 'application/x-www-form-urlencoded';
        $payload = $contenido === 'application/json'
            ? json_encode($cuerpo, JSON_UNESCAPED_UNICODE)
            : http_build_query($cuerpo);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $cabeceras[] = 'Content-Type: ' . $contenido;
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $opciones['metodo'] ?? 'GET',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $cabeceras,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => (bool) ($opciones['seguir'] ?? false),
        CURLOPT_HEADERFUNCTION => function ($ch, $linea) use (&$cabecerasRespuesta) {
            $partes = explode(':', $linea, 2);

            if (count($partes) === 2) {
                $cabecerasRespuesta[strtolower(trim($partes[0]))] = trim($partes[1]);
            }

            return strlen($linea);
        },
    ]);

    if (!empty($opciones['cookies'])) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $opciones['cookies']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $opciones['cookies']);
    }

    $respuesta = curl_exec($ch);
    $error = curl_error($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($respuesta === false) {
        throw new RuntimeException(
            "No se pudo conectar con {$url}: {$error}. " .
            'Verifica que Apache y MySQL estén activos.'
        );
    }

    return [
        'status' => $codigo,
        'data' => json_decode($respuesta, true),
        'raw' => $respuesta,
        'headers' => $cabecerasRespuesta,
    ];
}

function csrfDePagina(string $html): string
{
    if (!preg_match('/name="csrf"\s+value="([^"]+)"/', $html, $coincidencias)) {
        throw new RuntimeException('No se encontró el token CSRF en la página.');
    }

    return $coincidencias[1];
}

function archivoCookies(string $nombre): string
{
    $ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'escuela_test_' . $nombre . '_' . getmypid() . '.txt';

    if (file_exists($ruta)) {
        unlink($ruta);
    }

    return $ruta;
}

/* --------------------------- Aserciones ----------------------------- */

function afirmar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function afirmarEstado(array $respuesta, int $esperado): void
{
    afirmar(
        $respuesta['status'] === $esperado,
        'HTTP esperado ' . $esperado . ', recibido ' . $respuesta['status'] .
        '. Respuesta: ' . $respuesta['raw']
    );
}

function afirmarDatos(array $respuesta): array
{
    afirmar(
        is_array($respuesta['data']),
        'La respuesta no es un JSON válido: ' . $respuesta['raw']
    );

    return $respuesta['data'];
}

function afirmarClave(array $datos, string $clave, string $contexto = ''): void
{
    afirmar(
        array_key_exists($clave, $datos),
        "Falta la clave '{$clave}' {$contexto}. Datos: " . json_encode($datos, JSON_UNESCAPED_UNICODE)
    );
}

function afirmarIgual($esperado, $actual, string $mensaje = ''): void
{
    if ($esperado != $actual) {
        throw new RuntimeException(
            $mensaje .
            ' Esperado: ' . var_export($esperado, true) .
            ' | Actual: ' . var_export($actual, true)
        );
    }
}

function afirmarContiene(string $aguja, string $pajar, string $mensaje = ''): void
{
    afirmar(
        strpos($pajar, $aguja) !== false,
        $mensaje . " No se encontró '{$aguja}' en: {$pajar}"
    );
}

/* ----------------------------- Runner ------------------------------- */

function prueba(string $nombre, callable $funcion): void
{
    global $pasadas, $falladas, $detalleFallos;

    try {
        $funcion();
        $pasadas++;
        echo "[OK]    {$nombre}\n";
    } catch (Throwable $error) {
        $falladas++;
        $detalleFallos[] = [$nombre, $error->getMessage()];
        echo "[FALLO] {$nombre}\n";
        echo "        {$error->getMessage()}\n";
    }
}

function limpiarDatosDePrueba(): void
{
    try {
        $alumnos = api('GET', 'tipo=alumnos');

        if ($alumnos['status'] === 200 && is_array($alumnos['data'])) {
            foreach ($alumnos['data'] as $alumno) {
                if (strpos((string) ($alumno['matricula'] ?? ''), PREFIJO_MATRICULA) === 0) {
                    api('DELETE', 'tipo=alumnos&id=' . (int) $alumno['IdAlumnos']);
                }
            }
        }

        $grupos = api('GET', 'tipo=grupos');

        if ($grupos['status'] === 200 && is_array($grupos['data'])) {
            foreach ($grupos['data'] as $grupo) {
                if (strpos((string) ($grupo['grupo'] ?? ''), PREFIJO_GRUPO) === 0) {
                    api('DELETE', 'tipo=grupos&id=' . (int) $grupo['IdGrupos']);
                }
            }
        }
    } catch (Throwable $error) {
        // La limpieza es best-effort; no debe ocultar el resultado de las pruebas.
    }
}

/* ------------------------- Preparación ------------------------------ */

try {
    $ping = api('GET', 'tipo=alumnos');

    if ($ping['status'] !== 200) {
        throw new RuntimeException('La API respondió HTTP ' . $ping['status'] . ': ' . $ping['raw']);
    }
} catch (Throwable $error) {
    fwrite(STDERR, "No se pudo iniciar las pruebas.\n{$error->getMessage()}\n");
    exit(1);
}

limpiarDatosDePrueba();
register_shutdown_function('limpiarDatosDePrueba');

$sufijo = date('ymdHis') . substr(md5(uniqid('', true)), 0, 4);
$estado = [
    'sufijo' => $sufijo,
    'nombreGrupo' => PREFIJO_GRUPO . $sufijo,
    'matricula' => PREFIJO_MATRICULA . $sufijo,
    'nombreAlumno' => 'Alumno de Prueba',
    'grado' => 'Primero',
    'grupoId' => 0,
    'alumnoId' => 0,
];

/* ---------------------- Autenticación de la API --------------------- */

prueba('API sin token ni sesión devuelve 401', function () {
    $respuesta = api('GET', 'tipo=alumnos', null, 'application/json', ['token' => false]);
    afirmarEstado($respuesta, 401);
    afirmarIgual(false, afirmarDatos($respuesta)['ok'] ?? null, 'Debe indicar ok=false.');
});

prueba('API rechaza escrituras sin token', function () {
    $respuesta = api('POST', 'tipo=grupos', ['grupo' => 'TEST-API-SIN-TOKEN'], 'application/json', ['token' => false]);
    afirmarEstado($respuesta, 401);
});

prueba('API rechaza un token inválido', function () {
    $respuesta = api('GET', 'tipo=alumnos', null, 'application/json', ['token' => 'token-invalido']);
    afirmarEstado($respuesta, 401);
});

prueba('API acepta el token en la cabecera X-API-Token', function () {
    global $apiToken;

    $respuesta = api('GET', 'tipo=alumnos', null, 'application/json', [
        'token' => false,
        'cabeceras' => ['X-API-Token: ' . $apiToken],
    ]);
    afirmarEstado($respuesta, 200);
    afirmar(is_array(afirmarDatos($respuesta)), 'Debe devolver la lista de alumnos.');
});

prueba('API acepta el token en la URL', function () {
    global $apiToken;

    $respuesta = api('GET', 'tipo=alumnos&token=' . urlencode($apiToken), null, 'application/json', ['token' => false]);
    afirmarEstado($respuesta, 200);
    afirmar(is_array(afirmarDatos($respuesta)), 'Debe devolver la lista de alumnos.');
});

prueba('API acepta Authorization: Bearer', function () {
    global $apiToken;

    $respuesta = api('GET', 'tipo=grupos', null, 'application/json', [
        'token' => false,
        'cabeceras' => ['Authorization: Bearer ' . $apiToken],
    ]);
    afirmarEstado($respuesta, 200);
});

/* ------------------------ Pruebas de lectura ------------------------ */

prueba('GET /alumnos devuelve una lista JSON', function () {
    $respuesta = api('GET', 'tipo=alumnos');
    afirmarEstado($respuesta, 200);
    afirmar(is_array(afirmarDatos($respuesta)), 'La respuesta debe ser un arreglo.');
});

prueba('GET /grupos devuelve una lista con total_alumnos', function () {
    $respuesta = api('GET', 'tipo=grupos');
    afirmarEstado($respuesta, 200);
    $grupos = afirmarDatos($respuesta);
    afirmar(is_array($grupos), 'La respuesta debe ser un arreglo.');

    if (count($grupos) > 0) {
        afirmarClave($grupos[0], 'total_alumnos', 'en el primer grupo');
    }
});

prueba('GET sin tipo usa alumnos por defecto', function () {
    $explicito = api('GET', 'tipo=alumnos');
    $porDefecto = api('GET', '');

    afirmarEstado($porDefecto, 200);
    afirmarIgual(
        count(afirmarDatos($explicito)),
        count(afirmarDatos($porDefecto)),
        'El recurso por defecto debe devolver los mismos alumnos.'
    );
});

prueba('GET con recurso inexistente devuelve 404', function () {
    $respuesta = api('GET', 'tipo=profesores');
    afirmarEstado($respuesta, 404);
    afirmarIgual(false, afirmarDatos($respuesta)['ok'] ?? null, 'Debe indicar ok=false.');
});

/* --------------------------- Grupos --------------------------------- */

prueba('POST /grupos crea un grupo', function () {
    global $estado;

    $respuesta = api('POST', 'tipo=grupos', ['grupo' => $estado['nombreGrupo']]);
    afirmarEstado($respuesta, 201);

    $datos = afirmarDatos($respuesta);
    afirmarIgual(true, $datos['ok'] ?? null, 'Debe indicar ok=true.');
    afirmar(isset($datos['id']) && (int) $datos['id'] > 0, 'Debe devolver el id del grupo creado.');

    $estado['grupoId'] = (int) $datos['id'];
});

prueba('GET /grupos&id= devuelve el grupo creado', function () {
    global $estado;

    afirmar($estado['grupoId'] > 0, 'No se creó el grupo de prueba.');
    $respuesta = api('GET', 'tipo=grupos&id=' . $estado['grupoId']);
    afirmarEstado($respuesta, 200);

    $grupo = afirmarDatos($respuesta);
    afirmarIgual($estado['nombreGrupo'], $grupo['grupo'] ?? null, 'El nombre no coincide.');
    afirmarClave($grupo, 'total_alumnos', 'en el grupo');
});

prueba('PUT /grupos actualiza el nombre', function () {
    global $estado;

    $nuevoNombre = $estado['nombreGrupo'] . '-EDIT';
    $respuesta = api('PUT', 'tipo=grupos&id=' . $estado['grupoId'], ['grupo' => $nuevoNombre]);
    afirmarEstado($respuesta, 200);
    afirmarIgual(true, afirmarDatos($respuesta)['ok'] ?? null, 'Debe indicar ok=true.');

    $verificacion = api('GET', 'tipo=grupos&id=' . $estado['grupoId']);
    afirmarIgual($nuevoNombre, afirmarDatos($verificacion)['grupo'] ?? null, 'No se guardó el nuevo nombre.');

    $estado['nombreGrupo'] = $nuevoNombre;
});

prueba('PATCH /grupos actualiza parcialmente', function () {
    global $estado;

    $nuevoNombre = $estado['nombreGrupo'] . '-PATCH';
    $respuesta = api('PATCH', 'tipo=grupos&id=' . $estado['grupoId'], ['grupo' => $nuevoNombre]);
    afirmarEstado($respuesta, 200);

    $verificacion = api('GET', 'tipo=grupos&id=' . $estado['grupoId']);
    afirmarIgual($nuevoNombre, afirmarDatos($verificacion)['grupo'] ?? null, 'No se guardó el nombre con PATCH.');

    $estado['nombreGrupo'] = $nuevoNombre;
});

prueba('POST /grupos duplicado devuelve 400', function () {
    global $estado;

    $respuesta = api('POST', 'tipo=grupos', ['grupo' => $estado['nombreGrupo']]);
    afirmarEstado($respuesta, 400);

    $datos = afirmarDatos($respuesta);
    afirmarIgual(false, $datos['ok'] ?? null, 'Debe indicar ok=false.');
    afirmar(isset($datos['errores']) && is_array($datos['errores']), 'Debe devolver errores.');
    afirmarContiene('Ya existe', implode(' ', $datos['errores']), 'Debe reportar el nombre duplicado.');
});

prueba('POST /grupos vacío devuelve 400', function () {
    $respuesta = api('POST', 'tipo=grupos', ['grupo' => '']);
    afirmarEstado($respuesta, 400);

    $datos = afirmarDatos($respuesta);
    afirmar(isset($datos['errores']) && is_array($datos['errores']), 'Debe devolver errores.');
});

prueba('POST /grupos con accion=duplicar devuelve 400', function () {
    global $estado;

    $respuesta = api('POST', 'tipo=grupos&id=' . $estado['grupoId'] . '&accion=duplicar');
    afirmarEstado($respuesta, 400);

    $datos = afirmarDatos($respuesta);
    afirmarIgual(false, $datos['ok'] ?? null, 'Debe indicar ok=false.');
    afirmarContiene('solo está disponible para alumnos', $datos['mensaje'] ?? '', 'Debe explicar la restricción.');
});

prueba('GET /grupos inexistente devuelve 404', function () {
    $respuesta = api('GET', 'tipo=grupos&id=999999');
    afirmarEstado($respuesta, 404);
});

/* -------------------------- Alumnos --------------------------------- */

prueba('POST /alumnos crea un alumno', function () {
    global $estado;

    $respuesta = api('POST', 'tipo=alumnos', [
        'nombre' => $estado['nombreAlumno'],
        'matricula' => $estado['matricula'],
        'grado' => $estado['grado'],
        'IdGrupos' => $estado['grupoId'],
    ]);
    afirmarEstado($respuesta, 201);

    $datos = afirmarDatos($respuesta);
    afirmarIgual(true, $datos['ok'] ?? null, 'Debe indicar ok=true.');
    afirmar(isset($datos['id']) && (int) $datos['id'] > 0, 'Debe devolver el id del alumno creado.');

    $estado['alumnoId'] = (int) $datos['id'];
});

prueba('GET /alumnos&id= devuelve el alumno con su grupo', function () {
    global $estado;

    afirmar($estado['alumnoId'] > 0, 'No se creó el alumno de prueba.');
    $respuesta = api('GET', 'tipo=alumnos&id=' . $estado['alumnoId']);
    afirmarEstado($respuesta, 200);

    $alumno = afirmarDatos($respuesta);
    afirmarIgual($estado['nombreAlumno'], $alumno['nombre'] ?? null, 'El nombre no coincide.');
    afirmarIgual($estado['matricula'], $alumno['matricula'] ?? null, 'La matrícula no coincide.');
    afirmarIgual($estado['grado'], $alumno['grado'] ?? null, 'El grado no coincide.');
    afirmarIgual($estado['grupoId'], (int) ($alumno['IdGrupos'] ?? 0), 'El grupo no coincide.');
    afirmarIgual($estado['nombreGrupo'], $alumno['grupo'] ?? null, 'El nombre del grupo no coincide.');
});

prueba('PUT /alumnos actualiza todos los datos', function () {
    global $estado;

    $estado['nombreAlumno'] = 'Alumno Editado';
    $estado['grado'] = 'Segundo';

    $respuesta = api('PUT', 'tipo=alumnos&id=' . $estado['alumnoId'], [
        'nombre' => $estado['nombreAlumno'],
        'matricula' => $estado['matricula'],
        'grado' => $estado['grado'],
        'IdGrupos' => $estado['grupoId'],
    ]);
    afirmarEstado($respuesta, 200);

    $verificacion = api('GET', 'tipo=alumnos&id=' . $estado['alumnoId']);
    $alumno = afirmarDatos($verificacion);
    afirmarIgual($estado['nombreAlumno'], $alumno['nombre'] ?? null, 'No se guardó el nombre.');
    afirmarIgual($estado['grado'], $alumno['grado'] ?? null, 'No se guardó el grado.');
});

prueba('PATCH /alumnos actualiza solo los campos enviados', function () {
    global $estado;

    $respuesta = api('PATCH', 'tipo=alumnos&id=' . $estado['alumnoId'], ['grado' => 'Tercero']);
    afirmarEstado($respuesta, 200);

    $verificacion = api('GET', 'tipo=alumnos&id=' . $estado['alumnoId']);
    $alumno = afirmarDatos($verificacion);

    afirmarIgual('Tercero', $alumno['grado'] ?? null, 'No se actualizó el grado.');
    afirmarIgual($estado['nombreAlumno'], $alumno['nombre'] ?? null, 'El nombre no debía cambiar.');
    afirmarIgual($estado['matricula'], $alumno['matricula'] ?? null, 'La matrícula no debía cambiar.');

    $estado['grado'] = 'Tercero';
});

prueba('POST /alumnos&accion=duplicar duplica el alumno', function () {
    global $estado;

    $copiaId = 0;

    try {
        $respuesta = api('POST', 'tipo=alumnos&id=' . $estado['alumnoId'] . '&accion=duplicar');
        afirmarEstado($respuesta, 201);

        $datos = afirmarDatos($respuesta);
        afirmarIgual(true, $datos['ok'] ?? null, 'Debe indicar ok=true.');
        $copiaId = (int) ($datos['id'] ?? 0);
        afirmar($copiaId > 0, 'Debe devolver el id del duplicado.');
        afirmar($copiaId !== $estado['alumnoId'], 'El duplicado debe ser un registro nuevo.');

        $verificacion = api('GET', 'tipo=alumnos&id=' . $copiaId);
        afirmarEstado($verificacion, 200);
        $copia = afirmarDatos($verificacion);

        afirmarIgual(
            $estado['nombreAlumno'] . ' (copia)',
            $copia['nombre'] ?? null,
            'El nombre del duplicado no coincide.'
        );
        afirmarContiene('COPIA-', (string) ($copia['matricula'] ?? ''), 'La matrícula debe generarse con el prefijo COPIA-.');
        afirmarIgual($estado['grado'], $copia['grado'] ?? null, 'El grado debe copiarse.');
        afirmarIgual($estado['grupoId'], (int) ($copia['IdGrupos'] ?? 0), 'El grupo debe copiarse.');
    } finally {
        if ($copiaId > 0) {
            api('DELETE', 'tipo=alumnos&id=' . $copiaId);
        }
    }
});

prueba('POST /alumnos&accion=duplicar inexistente devuelve 404', function () {
    $respuesta = api('POST', 'tipo=alumnos&id=999999&accion=duplicar');
    afirmarEstado($respuesta, 404);

    $datos = afirmarDatos($respuesta);
    afirmarIgual(false, $datos['ok'] ?? null, 'Debe indicar ok=false.');
});

prueba('POST /alumnos vacío devuelve 400 con errores', function () {
    $respuesta = api('POST', 'tipo=alumnos', []);
    afirmarEstado($respuesta, 400);

    $datos = afirmarDatos($respuesta);
    afirmarIgual(false, $datos['ok'] ?? null, 'Debe indicar ok=false.');
    afirmar(isset($datos['errores']) && count($datos['errores']) >= 3, 'Debe devolver los errores de validación.');
});

prueba('POST /alumnos con grupo inexistente devuelve 400', function () {
    global $estado;

    $respuesta = api('POST', 'tipo=alumnos', [
        'nombre' => 'Alumno Sin Grupo',
        'matricula' => 'TST-999',
        'grado' => 'Primero',
        'IdGrupos' => 999999,
    ]);
    afirmarEstado($respuesta, 400);

    $datos = afirmarDatos($respuesta);
    afirmarContiene('grupo válido', implode(' ', $datos['errores'] ?? []), 'Debe reportar el grupo inválido.');
});

prueba('GET /alumnos inexistente devuelve 404', function () {
    $respuesta = api('GET', 'tipo=alumnos&id=999999');
    afirmarEstado($respuesta, 404);
});

prueba('DELETE /alumnos elimina el alumno', function () {
    global $estado;

    $respuesta = api('DELETE', 'tipo=alumnos&id=' . $estado['alumnoId']);
    afirmarEstado($respuesta, 200);
    afirmarIgual(true, afirmarDatos($respuesta)['ok'] ?? null, 'Debe indicar ok=true.');

    $verificacion = api('GET', 'tipo=alumnos&id=' . $estado['alumnoId']);
    afirmarEstado($verificacion, 404);

    $estado['alumnoId'] = 0;
});

prueba('DELETE /alumnos inexistente devuelve 404', function () {
    $respuesta = api('DELETE', 'tipo=alumnos&id=999999');
    afirmarEstado($respuesta, 404);
});

prueba('POST de formulario y _method=DELETE funcionan', function () {
    global $estado;

    $creacion = api('POST', 'tipo=alumnos', [
        'nombre' => 'Alumno Formulario',
        'matricula' => $estado['matricula'],
        'grado' => 'Primero',
        'IdGrupos' => $estado['grupoId'],
    ], 'application/x-www-form-urlencoded');

    afirmarEstado($creacion, 201);
    $id = (int) (afirmarDatos($creacion)['id'] ?? 0);
    afirmar($id > 0, 'No se creó el alumno mediante formulario.');

    $eliminacion = api('POST', 'tipo=alumnos&id=' . $id . '&_method=DELETE');
    afirmarEstado($eliminacion, 200);

    $verificacion = api('GET', 'tipo=alumnos&id=' . $id);
    afirmarEstado($verificacion, 404);
});

/* ---------------------- Eliminación y conflictos --------------------- */

prueba('DELETE /grupos con alumnos devuelve 409', function () {
    global $estado;

    $grupoId = 0;
    $alumnoId = 0;

    try {
        $grupo = api('POST', 'tipo=grupos', ['grupo' => $estado['nombreGrupo'] . '-B']);
        afirmarEstado($grupo, 201);
        $grupoId = (int) (afirmarDatos($grupo)['id'] ?? 0);

        $alumno = api('POST', 'tipo=alumnos', [
            'nombre' => 'Alumno del Grupo B',
            'matricula' => $estado['matricula'],
            'grado' => 'Primero',
            'IdGrupos' => $grupoId,
        ]);
        afirmarEstado($alumno, 201);
        $alumnoId = (int) (afirmarDatos($alumno)['id'] ?? 0);

        $respuesta = api('DELETE', 'tipo=grupos&id=' . $grupoId);
        afirmarEstado($respuesta, 409);

        $datos = afirmarDatos($respuesta);
        afirmarIgual(false, $datos['ok'] ?? null, 'Debe indicar ok=false.');
        afirmarContiene('alumnos asignados', $datos['mensaje'] ?? '', 'Debe explicar el conflicto.');
    } finally {
        if ($alumnoId > 0) {
            api('DELETE', 'tipo=alumnos&id=' . $alumnoId);
        }
        if ($grupoId > 0) {
            api('DELETE', 'tipo=grupos&id=' . $grupoId);
        }
    }
});

prueba('DELETE /grupos elimina el grupo', function () {
    global $estado;

    $respuesta = api('DELETE', 'tipo=grupos&id=' . $estado['grupoId']);
    afirmarEstado($respuesta, 200);
    afirmarIgual(true, afirmarDatos($respuesta)['ok'] ?? null, 'Debe indicar ok=true.');

    $verificacion = api('GET', 'tipo=grupos&id=' . $estado['grupoId']);
    afirmarEstado($verificacion, 404);

    $estado['grupoId'] = 0;
});

prueba('DELETE /grupos inexistente devuelve 404', function () {
    $respuesta = api('DELETE', 'tipo=grupos&id=999999');
    afirmarEstado($respuesta, 404);
});

prueba('Método no permitido devuelve 405', function () {
    $respuesta = api('POST', 'tipo=alumnos&_method=INVALIDO');
    afirmarEstado($respuesta, 405);
});

/* --------------------- Sesión y documentación ----------------------- */

prueba('La página de login está disponible', function () {
    $respuesta = web('login.php');
    afirmarEstado($respuesta, 200);
    afirmarContiene('Iniciar sesión', $respuesta['raw'], 'Debe mostrarse el formulario.');
    afirmarContiene('name="csrf"', $respuesta['raw'], 'El formulario debe incluir CSRF.');
});

prueba('Las páginas protegidas redirigen al login', function () {
    foreach (['lista_alumnos.php', 'lista_grupos.php', 'crear_alumno.php'] as $pagina) {
        $respuesta = web($pagina);
        afirmarEstado($respuesta, 302);
        afirmarContiene('login.php', $respuesta['headers']['location'] ?? '', "{$pagina} debe redirigir al login.");
    }
});

prueba('El login rechaza una contraseña incorrecta', function () {
    $cookies = archivoCookies('login_malo');

    try {
        $pagina = web('login.php', ['cookies' => $cookies]);
        $csrf = csrfDePagina($pagina['raw']);

        $respuesta = web('login.php', [
            'metodo' => 'POST',
            'cuerpo' => ['csrf' => $csrf, 'usuario' => 'ricardo', 'password' => 'incorrecta'],
            'cookies' => $cookies,
        ]);

        afirmarEstado($respuesta, 200);
        afirmarContiene('Usuario o contraseña incorrectos', $respuesta['raw'], 'Debe mostrar el error.');
    } finally {
        @unlink($cookies);
    }
});

prueba('El administrador inicia sesión y conserva los permisos de escritura', function () {
    $cookies = archivoCookies('login_admin');
    $grupoId = 0;

    try {
        $pagina = web('login.php', ['cookies' => $cookies]);
        $csrf = csrfDePagina($pagina['raw']);

        $login = web('login.php', [
            'metodo' => 'POST',
            'cuerpo' => ['csrf' => $csrf, 'usuario' => 'ricardo', 'password' => '1211'],
            'cookies' => $cookies,
        ]);

        afirmarEstado($login, 302);
        afirmarContiene('lista_alumnos.php', $login['headers']['location'] ?? '', 'Debe entrar al sistema.');

        $lista = web('lista_alumnos.php', ['cookies' => $cookies]);
        afirmarEstado($lista, 200);
        afirmarContiene('Ricardo', $lista['raw'], 'Debe mostrar el usuario autenticado.');
        afirmarContiene('Salir', $lista['raw'], 'Debe ofrecer cerrar sesión.');

        $creacion = web('endpoints.php?tipo=grupos', [
            'metodo' => 'POST',
            'cuerpo' => ['grupo' => 'TEST-API-SESION-' . substr(md5(uniqid('', true)), 0, 6)],
            'contenido' => 'application/json',
            'cookies' => $cookies,
        ]);

        afirmarEstado($creacion, 201);
        $grupoId = (int) (($creacion['data']['id'] ?? 0));

        $eliminacion = web('endpoints.php?tipo=grupos&id=' . $grupoId, [
            'metodo' => 'DELETE',
            'cookies' => $cookies,
        ]);
        afirmarEstado($eliminacion, 200);
        $grupoId = 0;
    } finally {
        if ($grupoId > 0) {
            web('endpoints.php?tipo=grupos&id=' . $grupoId, [
                'metodo' => 'DELETE',
                'cookies' => $cookies,
            ]);
        }

        @unlink($cookies);
    }
});

prueba('La sesión de consulta solo puede leer', function () {
    $cookies = archivoCookies('login_consulta');

    try {
        $pagina = web('login.php', ['cookies' => $cookies]);
        $csrf = csrfDePagina($pagina['raw']);

        $login = web('login.php', [
            'metodo' => 'POST',
            'cuerpo' => ['csrf' => $csrf, 'usuario' => 'profesor', 'password' => '1212'],
            'cookies' => $cookies,
        ]);
        afirmarEstado($login, 302);

        $lista = web('lista_alumnos.php', ['cookies' => $cookies]);
        afirmarEstado($lista, 200);
        afirmarContiene('Modo consulta', $lista['raw'], 'Debe indicar el modo de solo lectura.');

        $lectura = web('endpoints.php?tipo=alumnos', ['cookies' => $cookies]);
        afirmarEstado($lectura, 200);

        $escritura = web('endpoints.php?tipo=grupos', [
            'metodo' => 'POST',
            'cuerpo' => ['grupo' => 'TEST-API-CONSULTA'],
            'contenido' => 'application/json',
            'cookies' => $cookies,
        ]);
        afirmarEstado($escritura, 403);
        afirmarIgual(false, $escritura['data']['ok'] ?? null, 'Debe indicar ok=false.');

        $formulario = web('crear_grupo.php', ['cookies' => $cookies]);
        afirmarEstado($formulario, 302);
        afirmarContiene('sin_permiso', $formulario['headers']['location'] ?? '', 'Debe bloquear el formulario.');
    } finally {
        @unlink($cookies);
    }
});

prueba('Cerrar sesión vuelve al login', function () {
    $cookies = archivoCookies('login_logout');

    try {
        $pagina = web('login.php', ['cookies' => $cookies]);
        $csrf = csrfDePagina($pagina['raw']);

        web('login.php', [
            'metodo' => 'POST',
            'cuerpo' => ['csrf' => $csrf, 'usuario' => 'ricardo', 'password' => '1211'],
            'cookies' => $cookies,
        ]);

        $lista = web('lista_alumnos.php', ['cookies' => $cookies]);
        $csrfSesion = csrfDePagina($lista['raw']);

        $salida = web('logout.php', [
            'metodo' => 'POST',
            'cuerpo' => ['csrf' => $csrfSesion],
            'cookies' => $cookies,
        ]);
        afirmarEstado($salida, 302);
        afirmarContiene('login.php', $salida['headers']['location'] ?? '', 'Debe volver al login.');

        $despues = web('lista_alumnos.php', ['cookies' => $cookies]);
        afirmarEstado($despues, 302);
        afirmarContiene('login.php', $despues['headers']['location'] ?? '', 'La sesión debe estar cerrada.');
    } finally {
        @unlink($cookies);
    }
});

prueba('El contrato OpenAPI exige token', function () {
    $respuesta = web('docs/openapi.php');
    afirmarEstado($respuesta, 403);
    afirmarIgual(false, $respuesta['data']['ok'] ?? null, 'Debe indicar ok=false.');
});

prueba('La documentación pide un token y rechaza los inválidos', function () {
    $pagina = web('docs/index.php');
    afirmarEstado($pagina, 200);
    afirmarContiene('Token de acceso', $pagina['raw'], 'Debe pedir el token.');

    $invalido = web('docs/index.php?token=token-invalido');
    afirmarEstado($invalido, 200);
    afirmarContiene('Token inválido', $invalido['raw'], 'Debe rechazar el token incorrecto.');
});

prueba('El token válido abre Swagger y el contrato', function () {
    global $apiToken;

    $cookies = archivoCookies('docs_token');

    try {
        $acceso = web('docs/index.php?token=' . urlencode($apiToken), ['cookies' => $cookies]);
        afirmarEstado($acceso, 302);

        $pagina = web('docs/index.php', ['cookies' => $cookies]);
        afirmarEstado($pagina, 200);
        afirmarContiene('swagger-ui-bundle.js', $pagina['raw'], 'Debe cargar Swagger UI.');
        afirmarContiene('openapi.php', $pagina['raw'], 'Debe cargar el contrato protegido.');

        $contrato = web('docs/openapi.php', ['cookies' => $cookies]);
        afirmarEstado($contrato, 200);
        afirmarIgual('3.0.3', $contrato['data']['openapi'] ?? null, 'Debe entregar el contrato OpenAPI.');
        afirmarIgual(
            5,
            count($contrato['data']['paths']['/endpoints.php'] ?? []),
            'El contrato debe documentar los 5 métodos.'
        );

        $salida = web('docs/index.php?salir=1', ['cookies' => $cookies]);
        afirmarEstado($salida, 302);

        $cerrado = web('docs/openapi.php', ['cookies' => $cookies]);
        afirmarEstado($cerrado, 403);
    } finally {
        @unlink($cookies);
    }
});

prueba('El archivo de datos del contrato no se imprime directamente', function () {
    $respuesta = web('docs/openapi_data.php');
    afirmarEstado($respuesta, 200);
    afirmarIgual('', trim($respuesta['raw']), 'No debe exponer el contrato sin autorización.');
});

/* ----------------------------- Resumen ------------------------------ */

echo "\n----------------------------------------\n";
echo 'Pruebas ejecutadas: ' . ($pasadas + $falladas) . "\n";
echo "Correctas: {$pasadas}\n";
echo "Fallidas:  {$falladas}\n";

if ($falladas > 0) {
    echo "\nDetalle de fallos:\n";

    foreach ($detalleFallos as [$nombre, $mensaje]) {
        echo "- {$nombre}: {$mensaje}\n";
    }

    exit(1);
}

echo "Todas las pruebas pasaron.\n";
exit(0);
