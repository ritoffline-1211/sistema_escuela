<?php
require_once __DIR__ . '/auth.php';

$usuarioActual = requiereSesion();
$esAdmin = esAdmin();
$mensajeAviso = (($_GET["error"] ?? "") === "sin_permiso")
    ? "No tienes permisos para realizar esa acción."
    : "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Alumnos</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
    <header>
        <h1>Sistema Escolar</h1>
        <nav>
            <a class="activo" href="lista_alumnos.php">Alumnos</a>
            <a href="lista_grupos.php">Grupos</a>
            <div class="usuario-nav">
                <span class="usuario-nombre">
                    <?= htmlspecialchars($usuarioActual["nombre"]) ?>
                    <span class="rol"><?= htmlspecialchars($usuarioActual["rol"]) ?></span>
                </span>
                <form method="post" action="logout.php">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <button type="submit" class="btn-salir">Salir</button>
                </form>
            </div>
        </nav>
    </header>

    <main>
        <section class="card">
            <div class="barra">
                <h2>Lista de Alumnos</h2>
                <?php if ($esAdmin): ?>
                    <a class="btn btn-primario" href="crear_alumno.php">Agregar Alumno</a>
                <?php else: ?>
                    <span class="badge-consulta">Modo consulta</span>
                <?php endif; ?>
            </div>

            <?php if ($mensajeAviso !== ""): ?>
                <p class="mensaje error"><?= htmlspecialchars($mensajeAviso) ?></p>
            <?php endif; ?>

            <p id="mensaje" class="mensaje" hidden></p>

            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Matrícula</th>
                        <th>Grado</th>
                        <th>Grupo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla"></tbody>
            </table>

            <p id="vacio" class="vacio" hidden>No hay alumnos registrados.</p>
        </section>
    </main>

    <script src="app.js"></script>
    <script>
        const tabla = document.getElementById("tabla");
        const vacio = document.getElementById("vacio");
        const esAdmin = <?= $esAdmin ? "true" : "false" ?>;

        async function cargarAlumnos() {
            try {
                const alumnos = await peticionJSON("endpoints.php?tipo=alumnos");

                if (!Array.isArray(alumnos)) {
                    throw new Error(alumnos.mensaje || "No se pudo cargar la lista de alumnos.");
                }

                tabla.innerHTML = "";
                vacio.hidden = alumnos.length > 0;

                alumnos.forEach((alumno) => {
                    const fila = document.createElement("tr");

                    [
                        alumno.nombre,
                        alumno.matricula,
                        alumno.grado,
                        alumno.grupo || "Sin grupo"
                    ].forEach((valor) => {
                        const celda = document.createElement("td");
                        celda.textContent = valor;
                        fila.appendChild(celda);
                    });

                    const acciones = document.createElement("td");
                    acciones.className = "acciones";

                    if (!esAdmin) {
                        const soloLectura = document.createElement("span");
                        soloLectura.className = "texto-suave";
                        soloLectura.textContent = "Solo lectura";
                        acciones.appendChild(soloLectura);
                        fila.appendChild(acciones);
                        tabla.appendChild(fila);
                        return;
                    }

                    const duplicar = document.createElement("button");
                    duplicar.type = "button";
                    duplicar.className = "btn btn-duplicar";
                    duplicar.textContent = "Duplicar";
                    duplicar.addEventListener("click", () => duplicarAlumno(alumno));

                    const editar = document.createElement("a");
                    editar.className = "btn btn-editar";
                    editar.href = "editar_alumno.php?id=" + encodeURIComponent(alumno.IdAlumnos);
                    editar.textContent = "Editar";

                    const eliminar = document.createElement("button");
                    eliminar.type = "button";
                    eliminar.className = "btn btn-eliminar";
                    eliminar.textContent = "Eliminar";
                    eliminar.addEventListener("click", () => eliminarAlumno(alumno));

                    acciones.append(duplicar, editar, eliminar);
                    fila.appendChild(acciones);
                    tabla.appendChild(fila);
                });
            } catch (error) {
                mostrarMensaje(error.message, true);
            }
        }

        async function duplicarAlumno(alumno) {
            if (!window.confirm("¿Duplicar a " + alumno.nombre + "?")) {
                return;
            }

            try {
                const datos = await peticionJSON(
                    "endpoints.php?tipo=alumnos&id=" + encodeURIComponent(alumno.IdAlumnos) + "&accion=duplicar",
                    { method: "POST" }
                );

                mostrarMensaje(datos.mensaje, !datos.ok);

                if (datos.ok) {
                    cargarAlumnos();
                }
            } catch (error) {
                mostrarMensaje(error.message, true);
            }
        }

        async function eliminarAlumno(alumno) {
            if (!window.confirm("¿Eliminar a " + alumno.nombre + "?")) {
                return;
            }

            try {
                const datos = await peticionJSON(
                    "endpoints.php?tipo=alumnos&id=" + encodeURIComponent(alumno.IdAlumnos),
                    { method: "DELETE" }
                );

                mostrarMensaje(datos.mensaje, !datos.ok);

                if (datos.ok) {
                    cargarAlumnos();
                }
            } catch (error) {
                mostrarMensaje(error.message, true);
            }
        }

        document.addEventListener("DOMContentLoaded", cargarAlumnos);
    </script>
</body>
</html>
