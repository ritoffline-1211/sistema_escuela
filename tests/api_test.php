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

$baseUrl = getenv('API_BASE_URL') ?: 'http://localhost/escuela/endpoints.php';
$estado = [];
$pasadas = 0;
$falladas = 0;
$detalleFallos = [];

/* --------------------------- Cliente HTTP --------------------------- */

function api(string $metodo, string $consulta = '', ?array $cuerpo = null, string $tipoContenido = 'application/json'): array
{
    global $baseUrl;

    $url = $baseUrl . ($consulta !== '' ? '?' . $consulta : '');
    $ch = curl_init($url);
    $cabeceras = ['Accept: application/json'];

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
