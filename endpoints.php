<?php

header("Content-Type: application/json; charset=utf-8");

$config = require __DIR__ . '/config/database.php';

$conexion = @mysqli_connect(
    $config['host'],
    $config['user'],
    $config['pass'],
    $config['name'],
    $config['port']
);

function responder($datos, $codigo = 200)
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$conexion) {
    responder(["ok" => false, "mensaje" => "No se pudo conectar con la base de datos."], 500);
}

mysqli_set_charset($conexion, "utf8mb4");

function leerCuerpo()
{
    $contenido = file_get_contents("php://input");
    $tipoContenido = $_SERVER["CONTENT_TYPE"] ?? "";

    if (stripos($tipoContenido, "application/json") !== false) {
        $datos = json_decode($contenido, true);
        return is_array($datos) ? $datos : [];
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    $datos = [];
    parse_str($contenido, $datos);
    return is_array($datos) ? $datos : [];
}

function recursoSolicitado()
{
    $recurso = $_GET["tipo"] ?? $_GET["recurso"] ?? "alumnos";

    return is_string($recurso) ? $recurso : "alumnos";
}

function idSolicitado()
{
    return isset($_GET["id"]) ? (int) $_GET["id"] : 0;
}

function accionSolicitada()
{
    $accion = $_GET["accion"] ?? "";

    return is_string($accion) ? strtolower(trim($accion)) : "";
}

function valor($datos, $campo)
{
    if (!isset($datos[$campo]) || !is_scalar($datos[$campo])) {
        return "";
    }

    return trim((string) $datos[$campo]);
}

function metodoSolicitado($cuerpo)
{
    if (isset($_GET["_method"]) && is_string($_GET["_method"])) {
        return strtoupper($_GET["_method"]);
    }

    if (isset($cuerpo["_method"]) && is_string($cuerpo["_method"])) {
        return strtoupper($cuerpo["_method"]);
    }

    return strtoupper($_SERVER["REQUEST_METHOD"]);
}

/**
 * Construye la respuesta JSON a partir del error lanzado por un procedimiento.
 * La regla de negocio viaja como SIGNAL SQLSTATE '45000' (errno 1644).
 */
function errorDeProcedimiento(mysqli_stmt $stmt): array
{
    $codigo = mysqli_stmt_errno($stmt);
    $mensaje = mysqli_stmt_error($stmt);

    if ($codigo === 1644) {
        return [
            "ok" => false,
            "mensaje" => $mensaje,
        ];
    }

    return [
        "ok" => false,
        "mensaje" => "Error al procesar la operación.",
    ];
}

function codigoDeProcedimiento(mysqli_stmt $stmt): int
{
    $mensaje = mysqli_stmt_error($stmt);

    if (mysqli_stmt_errno($stmt) === 1644) {
        return stripos($mensaje, "no encontrado") !== false ? 404 : 400;
    }

    return 500;
}

function ultimoIdInsertado($conexion): int
{
    $resultado = mysqli_query($conexion, "SELECT LAST_INSERT_ID() AS id");

    if (!$resultado) {
        return 0;
    }

    $fila = mysqli_fetch_assoc($resultado);
    mysqli_free_result($resultado);

    return (int) ($fila["id"] ?? 0);
}

function ejecutarProcedimiento($conexion, string $llamada, string $tipos, array $parametros)
{
    $stmt = mysqli_prepare($conexion, $llamada);

    if (!$stmt) {
        responder(["ok" => false, "mensaje" => "Error al procesar la operación."], 500);
    }

    mysqli_stmt_bind_param($stmt, $tipos, ...$parametros);

    if (!mysqli_stmt_execute($stmt)) {
        $respuesta = errorDeProcedimiento($stmt);
        $codigo = codigoDeProcedimiento($stmt);
        mysqli_stmt_close($stmt);
        responder($respuesta, $codigo);
    }

    mysqli_stmt_close($stmt);
}

function grupoExiste($conexion, $idGrupo)
{
    if ($idGrupo <= 0) {
        return false;
    }

    $stmt = mysqli_prepare($conexion, "SELECT IdGrupos FROM grupos WHERE IdGrupos = ?");
    mysqli_stmt_bind_param($stmt, "i", $idGrupo);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $existe = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $existe;
}

/* ----------------------------- Alumnos ----------------------------- */

function buscarAlumno($conexion, $id)
{
    $stmt = mysqli_prepare(
        $conexion,
        "SELECT alumnos.IdAlumnos, alumnos.nombre, alumnos.matricula, alumnos.grado,
                alumnos.IdGrupos, grupos.grupo
         FROM alumnos
         LEFT JOIN grupos ON alumnos.IdGrupos = grupos.IdGrupos
         WHERE alumnos.IdAlumnos = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $alumno = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    return $alumno ?: null;
}

function validarAlumno($conexion, $cuerpo, $actual = null)
{
    $nombre = valor($cuerpo, "nombre");
    $matricula = valor($cuerpo, "matricula");
    $grado = valor($cuerpo, "grado");
    $idGrupo = isset($cuerpo["IdGrupos"]) ? (int) $cuerpo["IdGrupos"] : 0;

    if ($actual !== null) {
        if (!isset($cuerpo["nombre"])) {
            $nombre = $actual["nombre"];
        }
        if (!isset($cuerpo["matricula"])) {
            $matricula = $actual["matricula"];
        }
        if (!isset($cuerpo["grado"])) {
            $grado = $actual["grado"];
        }
        if (!isset($cuerpo["IdGrupos"])) {
            $idGrupo = (int) $actual["IdGrupos"];
        }
    }

    $errores = [];

    if ($nombre === "") {
        $errores[] = "El nombre es obligatorio.";
    } elseif (strlen($nombre) > 100) {
        $errores[] = "El nombre no puede superar los 100 caracteres.";
    }

    if ($matricula === "") {
        $errores[] = "La matrícula es obligatoria.";
    } elseif (strlen($matricula) > 20) {
        $errores[] = "La matrícula no puede superar los 20 caracteres.";
    }

    if ($grado === "") {
        $errores[] = "El grado es obligatorio.";
    } elseif (strlen($grado) > 20) {
        $errores[] = "El grado no puede superar los 20 caracteres.";
    }

    if (!grupoExiste($conexion, $idGrupo)) {
        $errores[] = "Selecciona un grupo válido.";
    }

    if (!empty($errores)) {
        responder([
            "ok" => false,
            "mensaje" => "Revisa los datos del alumno.",
            "errores" => $errores
        ], 400);
    }

    return [
        "nombre" => $nombre,
        "matricula" => $matricula,
        "grado" => $grado,
        "IdGrupos" => $idGrupo
    ];
}

function listarAlumnos($conexion)
{
    $resultado = mysqli_query(
        $conexion,
        "SELECT alumnos.IdAlumnos, alumnos.nombre, alumnos.matricula, alumnos.grado,
                alumnos.IdGrupos, grupos.grupo
         FROM alumnos
         LEFT JOIN grupos ON alumnos.IdGrupos = grupos.IdGrupos
         ORDER BY alumnos.IdAlumnos"
    );

    if (!$resultado) {
        responder(["ok" => false, "mensaje" => "Error al consultar los alumnos."], 500);
    }

    responder(mysqli_fetch_all($resultado, MYSQLI_ASSOC));
}

function obtenerAlumno($conexion, $id)
{
    if ($id <= 0) {
        responder(["ok" => false, "mensaje" => "Debes indicar el alumno a consultar."], 400);
    }

    $alumno = buscarAlumno($conexion, $id);

    if (!$alumno) {
        responder(["ok" => false, "mensaje" => "Alumno no encontrado."], 404);
    }

    responder($alumno);
}

function crearAlumno($conexion, $cuerpo)
{
    $datos = validarAlumno($conexion, $cuerpo);

    $nombre = $datos["nombre"];
    $matricula = $datos["matricula"];
    $grado = $datos["grado"];
    $idGrupo = $datos["IdGrupos"];

    ejecutarProcedimiento(
        $conexion,
        "CALL sp_crear_alumno(?, ?, ?, ?)",
        "sssi",
        [$nombre, $matricula, $grado, $idGrupo]
    );

    $id = ultimoIdInsertado($conexion);

    responder([
        "ok" => true,
        "mensaje" => "Alumno registrado correctamente.",
        "id" => (int) $id
    ], 201);
}

function actualizarAlumno($conexion, $id, $cuerpo)
{
    if ($id <= 0) {
        responder(["ok" => false, "mensaje" => "Debes indicar el alumno a actualizar."], 400);
    }

    $actual = buscarAlumno($conexion, $id);

    if (!$actual) {
        responder(["ok" => false, "mensaje" => "Alumno no encontrado."], 404);
    }

    $datos = validarAlumno($conexion, $cuerpo, $actual);

    $nombre = $datos["nombre"];
    $matricula = $datos["matricula"];
    $grado = $datos["grado"];
    $idGrupo = $datos["IdGrupos"];

    ejecutarProcedimiento(
        $conexion,
        "CALL sp_actualizar_alumno(?, ?, ?, ?, ?)",
        "isssi",
        [$id, $nombre, $matricula, $grado, $idGrupo]
    );

    responder(["ok" => true, "mensaje" => "Alumno actualizado correctamente."]);
}

function duplicarAlumno($conexion, $id)
{
    if ($id <= 0) {
        responder(["ok" => false, "mensaje" => "Debes indicar el alumno a duplicar."], 400);
    }

    if (!buscarAlumno($conexion, $id)) {
        responder(["ok" => false, "mensaje" => "Alumno no encontrado."], 404);
    }

    ejecutarProcedimiento(
        $conexion,
        "CALL sp_duplicar_alumno(?)",
        "i",
        [$id]
    );

    $nuevoId = ultimoIdInsertado($conexion);

    responder([
        "ok" => true,
        "mensaje" => "Alumno duplicado correctamente.",
        "id" => (int) $nuevoId
    ], 201);
}

function eliminarAlumno($conexion, $id)
{
    if ($id <= 0) {
        responder(["ok" => false, "mensaje" => "Debes indicar el alumno a eliminar."], 400);
    }

    if (!buscarAlumno($conexion, $id)) {
        responder(["ok" => false, "mensaje" => "Alumno no encontrado."], 404);
    }

    $stmt = mysqli_prepare($conexion, "DELETE FROM alumnos WHERE IdAlumnos = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        responder(["ok" => false, "mensaje" => "Error al eliminar el alumno."], 500);
    }

    mysqli_stmt_close($stmt);

    responder(["ok" => true, "mensaje" => "Alumno eliminado correctamente."]);
}

/* ------------------------------ Grupos ----------------------------- */

function buscarGrupo($conexion, $id)
{
    $stmt = mysqli_prepare(
        $conexion,
        "SELECT grupos.IdGrupos, grupos.grupo, COUNT(alumnos.IdAlumnos) AS total_alumnos
         FROM grupos
         LEFT JOIN alumnos ON alumnos.IdGrupos = grupos.IdGrupos
         WHERE grupos.IdGrupos = ?
         GROUP BY grupos.IdGrupos, grupos.grupo"
    );
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $grupo = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    return $grupo ?: null;
}

function grupoDuplicado($conexion, $nombre, $excluirId = 0)
{
    $sql = "SELECT IdGrupos FROM grupos WHERE grupo = ?";

    if ($excluirId > 0) {
        $sql .= " AND IdGrupos <> ?";
    }

    $stmt = mysqli_prepare($conexion, $sql);

    if ($excluirId > 0) {
        mysqli_stmt_bind_param($stmt, "si", $nombre, $excluirId);
    } else {
        mysqli_stmt_bind_param($stmt, "s", $nombre);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $existe = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $existe;
}

function validarGrupo($conexion, $cuerpo, $id = 0, $actual = null)
{
    $nombre = valor($cuerpo, "grupo");

    if ($actual !== null && !isset($cuerpo["grupo"])) {
        $nombre = $actual["grupo"];
    }

    $errores = [];

    if ($nombre === "") {
        $errores[] = "El nombre del grupo es obligatorio.";
    } elseif (strlen($nombre) > 50) {
        $errores[] = "El nombre del grupo no puede superar los 50 caracteres.";
    } elseif (grupoDuplicado($conexion, $nombre, $id)) {
        $errores[] = "Ya existe un grupo con ese nombre.";
    }

    if (!empty($errores)) {
        responder([
            "ok" => false,
            "mensaje" => "Revisa los datos del grupo.",
            "errores" => $errores
        ], 400);
    }

    return $nombre;
}

function listarGrupos($conexion)
{
    $resultado = mysqli_query(
        $conexion,
        "SELECT grupos.IdGrupos, grupos.grupo, COUNT(alumnos.IdAlumnos) AS total_alumnos
         FROM grupos
         LEFT JOIN alumnos ON alumnos.IdGrupos = grupos.IdGrupos
         GROUP BY grupos.IdGrupos, grupos.grupo
         ORDER BY grupos.IdGrupos"
    );

    if (!$resultado) {
        responder(["ok" => false, "mensaje" => "Error al consultar los grupos."], 500);
    }

    responder(mysqli_fetch_all($resultado, MYSQLI_ASSOC));
}

function obtenerGrupo($conexion, $id)
{
    if ($id <= 0) {
        responder(["ok" => false, "mensaje" => "Debes indicar el grupo a consultar."], 400);
    }

    $grupo = buscarGrupo($conexion, $id);

    if (!$grupo) {
        responder(["ok" => false, "mensaje" => "Grupo no encontrado."], 404);
    }

    responder($grupo);
}

function crearGrupo($conexion, $cuerpo)
{
    $nombre = validarGrupo($conexion, $cuerpo);

    ejecutarProcedimiento(
        $conexion,
        "CALL sp_crear_grupo(?)",
        "s",
        [$nombre]
    );

    $id = ultimoIdInsertado($conexion);

    responder([
        "ok" => true,
        "mensaje" => "Grupo registrado correctamente.",
        "id" => (int) $id
    ], 201);
}

function actualizarGrupo($conexion, $id, $cuerpo)
{
    if ($id <= 0) {
        responder(["ok" => false, "mensaje" => "Debes indicar el grupo a actualizar."], 400);
    }

    $actual = buscarGrupo($conexion, $id);

    if (!$actual) {
        responder(["ok" => false, "mensaje" => "Grupo no encontrado."], 404);
    }

    $nombre = validarGrupo($conexion, $cuerpo, $id, $actual);

    ejecutarProcedimiento(
        $conexion,
        "CALL sp_actualizar_grupo(?, ?)",
        "is",
        [$id, $nombre]
    );

    responder(["ok" => true, "mensaje" => "Grupo actualizado correctamente."]);
}

function eliminarGrupo($conexion, $id)
{
    if ($id <= 0) {
        responder(["ok" => false, "mensaje" => "Debes indicar el grupo a eliminar."], 400);
    }

    $grupo = buscarGrupo($conexion, $id);

    if (!$grupo) {
        responder(["ok" => false, "mensaje" => "Grupo no encontrado."], 404);
    }

    if ((int) $grupo["total_alumnos"] > 0) {
        responder([
            "ok" => false,
            "mensaje" => "No se puede eliminar el grupo porque tiene alumnos asignados."
        ], 409);
    }

    $stmt = mysqli_prepare($conexion, "DELETE FROM grupos WHERE IdGrupos = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        $codigoError = mysqli_stmt_errno($stmt);
        mysqli_stmt_close($stmt);

        if ($codigoError === 1451) {
            responder([
                "ok" => false,
                "mensaje" => "No se puede eliminar el grupo porque tiene alumnos asignados."
            ], 409);
        }

        responder(["ok" => false, "mensaje" => "Error al eliminar el grupo."], 500);
    }

    mysqli_stmt_close($stmt);

    responder(["ok" => true, "mensaje" => "Grupo eliminado correctamente."]);
}

/* ----------------------------- Enrutador ---------------------------- */

$cuerpo = leerCuerpo();
$metodo = metodoSolicitado($cuerpo);
$recurso = recursoSolicitado();
$id = idSolicitado();
$accion = accionSolicitada();

switch ($recurso) {
    case "alumnos":
        switch ($metodo) {
            case "GET":
                if ($id > 0) {
                    obtenerAlumno($conexion, $id);
                } else {
                    listarAlumnos($conexion);
                }
                break;

            case "POST":
                if ($accion === "duplicar") {
                    duplicarAlumno($conexion, $id);
                }

                if ($accion !== "") {
                    responder(["ok" => false, "mensaje" => "Acción no encontrada."], 404);
                }

                crearAlumno($conexion, $cuerpo);
                break;

            case "PUT":
            case "PATCH":
                actualizarAlumno($conexion, $id, $cuerpo);
                break;

            case "DELETE":
                eliminarAlumno($conexion, $id);
                break;

            default:
                header("Allow: GET, POST, PUT, PATCH, DELETE");
                responder(["ok" => false, "mensaje" => "Método no permitido."], 405);
        }
        break;

    case "grupos":
        switch ($metodo) {
            case "GET":
                if ($id > 0) {
                    obtenerGrupo($conexion, $id);
                } else {
                    listarGrupos($conexion);
                }
                break;

            case "POST":
                if ($accion === "duplicar") {
                    responder([
                        "ok" => false,
                        "mensaje" => "La acción duplicar solo está disponible para alumnos."
                    ], 400);
                }

                if ($accion !== "") {
                    responder(["ok" => false, "mensaje" => "Acción no encontrada."], 404);
                }

                crearGrupo($conexion, $cuerpo);
                break;

            case "PUT":
            case "PATCH":
                actualizarGrupo($conexion, $id, $cuerpo);
                break;

            case "DELETE":
                eliminarGrupo($conexion, $id);
                break;

            default:
                header("Allow: GET, POST, PUT, PATCH, DELETE");
                responder(["ok" => false, "mensaje" => "Método no permitido."], 405);
        }
        break;

    default:
        responder(["ok" => false, "mensaje" => "Recurso no encontrado."], 404);
}
