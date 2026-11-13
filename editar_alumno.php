<?php
require_once __DIR__ . '/auth.php';

$usuarioActual = requiereAdmin();
$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Alumno</title>
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
                <h2>Editar Alumno</h2>
            </div>

            <p id="mensaje" class="mensaje" hidden></p>

            <form id="formAlumno" class="formulario" data-id="<?= $id ?>">
                <label>
                    Nombre
                    <input name="nombre" maxlength="100" required>
                </label>

                <label>
                    Matrícula
                    <input name="matricula" maxlength="20" required>
                </label>

                <label>
                    Grado
                    <input name="grado" maxlength="20" required>
                </label>

                <label>
                    Grupo
                    <select name="IdGrupos" id="grupos" required>
                        <option value="">Cargando grupos...</option>
                    </select>
                </label>

                <div class="acciones-form">
                    <button type="submit" class="btn btn-primario">Guardar Cambios</button>
                    <a class="btn" href="lista_alumnos.php">Cancelar</a>
                </div>
            </form>
        </section>
    </main>

    <script src="app.js"></script>
    <script>
        const form = document.getElementById("formAlumno");
        const id = form.dataset.id;

        async function cargarAlumno() {
            if (!id || id === "0") {
                form.hidden = true;
                mostrarMensaje("No se indicó el alumno a editar.", true);
                return;
            }

            try {
                const alumno = await peticionJSON(
                    "endpoints.php?tipo=alumnos&id=" + encodeURIComponent(id)
                );

                if (alumno.ok === false) {
                    throw new Error(alumno.mensaje);
                }

                form.nombre.value = alumno.nombre;
                form.matricula.value = alumno.matricula;
                form.grado.value = alumno.grado;

                await cargarGrupos(document.getElementById("grupos"), alumno.IdGrupos);
            } catch (error) {
                form.hidden = true;
                mostrarMensaje(error.message, true);
            }
        }

        form.addEventListener("submit", async (evento) => {
            evento.preventDefault();

            try {
                const datos = await peticionJSON(
                    "endpoints.php?tipo=alumnos&id=" + encodeURIComponent(id),
                    {
                        method: "PUT",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify(datosDelFormulario(form))
                    }
                );

                mostrarMensaje(datos.mensaje, !datos.ok);

                if (datos.ok) {
                    setTimeout(() => {
                        window.location.href = "lista_alumnos.php";
                    }, 700);
                }
            } catch (error) {
                mostrarMensaje(error.message, true);
            }
        });

        document.addEventListener("DOMContentLoaded", cargarAlumno);
    </script>
</body>
</html>
