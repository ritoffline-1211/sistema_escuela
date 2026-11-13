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
    <title>Lista de Grupos</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
    <header>
        <h1>Sistema Escolar</h1>
        <nav>
            <a href="lista_alumnos.php">Alumnos</a>
            <a class="activo" href="lista_grupos.php">Grupos</a>
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
                <h2>Lista de Grupos</h2>
                <?php if ($esAdmin): ?>
                    <a class="btn btn-primario" href="crear_grupo.php">Agregar Grupo</a>
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
                        <th>Grupo</th>
                        <th>Alumnos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla"></tbody>
            </table>

            <p id="vacio" class="vacio" hidden>No hay grupos registrados.</p>
        </section>
    </main>

    <script src="app.js"></script>
    <script>
        const tabla = document.getElementById("tabla");
        const vacio = document.getElementById("vacio");
        const esAdmin = <?= $esAdmin ? "true" : "false" ?>;

        async function cargarGruposLista() {
            try {
                const grupos = await peticionJSON("endpoints.php?tipo=grupos");

                if (!Array.isArray(grupos)) {
                    throw new Error(grupos.mensaje || "No se pudo cargar la lista de grupos.");
                }

                tabla.innerHTML = "";
                vacio.hidden = grupos.length > 0;

                grupos.forEach((grupo) => {
                    const fila = document.createElement("tr");
                    const total = Number(grupo.total_alumnos) || 0;

                    const celdaGrupo = document.createElement("td");
                    celdaGrupo.textContent = grupo.grupo;

                    const celdaTotal = document.createElement("td");
                    celdaTotal.textContent = total;

                    const acciones = document.createElement("td");
                    acciones.className = "acciones";

                    if (!esAdmin) {
                        const soloLectura = document.createElement("span");
                        soloLectura.className = "texto-suave";
                        soloLectura.textContent = "Solo lectura";
                        acciones.appendChild(soloLectura);
                        fila.append(celdaGrupo, celdaTotal, acciones);
                        tabla.appendChild(fila);
                        return;
                    }

                    const editar = document.createElement("a");
                    editar.className = "btn btn-editar";
                    editar.href = "editar_grupo.php?id=" + encodeURIComponent(grupo.IdGrupos);
                    editar.textContent = "Editar";

                    const eliminar = document.createElement("button");
                    eliminar.type = "button";
                    eliminar.className = "btn btn-eliminar";
                    eliminar.textContent = "Eliminar";
                    eliminar.disabled = total > 0;
                    eliminar.title = total > 0
                        ? "No se puede eliminar: tiene alumnos asignados"
                        : "Eliminar grupo";
                    eliminar.addEventListener("click", () => eliminarGrupo(grupo));

                    acciones.append(editar, eliminar);
                    fila.append(celdaGrupo, celdaTotal, acciones);
                    tabla.appendChild(fila);
                });
            } catch (error) {
                mostrarMensaje(error.message, true);
            }
        }

        async function eliminarGrupo(grupo) {
            if (!window.confirm("¿Eliminar el grupo " + grupo.grupo + "?")) {
                return;
            }

            try {
                const datos = await peticionJSON(
                    "endpoints.php?tipo=grupos&id=" + encodeURIComponent(grupo.IdGrupos),
                    { method: "DELETE" }
                );

                mostrarMensaje(datos.mensaje, !datos.ok);

                if (datos.ok) {
                    cargarGruposLista();
                }
            } catch (error) {
                mostrarMensaje(error.message, true);
            }
        }

        document.addEventListener("DOMContentLoaded", cargarGruposLista);
    </script>
</body>
</html>
