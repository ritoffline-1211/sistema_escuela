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
    <title>Editar Grupo</title>
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
                <h2>Editar Grupo</h2>
            </div>

            <p id="mensaje" class="mensaje" hidden></p>

            <form id="formGrupo" class="formulario" data-id="<?= $id ?>">
                <label>
                    Nombre del grupo
                    <input name="grupo" maxlength="50" required>
                </label>

                <div class="acciones-form">
                    <button type="submit" class="btn btn-primario">Guardar Cambios</button>
                    <a class="btn" href="lista_grupos.php">Cancelar</a>
                </div>
            </form>
        </section>
    </main>

    <script src="app.js"></script>
    <script>
        const form = document.getElementById("formGrupo");
        const id = form.dataset.id;

        async function cargarGrupo() {
            if (!id || id === "0") {
                form.hidden = true;
                mostrarMensaje("No se indicó el grupo a editar.", true);
                return;
            }

            try {
                const grupo = await peticionJSON(
                    "endpoints.php?tipo=grupos&id=" + encodeURIComponent(id)
                );

                if (grupo.ok === false) {
                    throw new Error(grupo.mensaje);
                }

                form.grupo.value = grupo.grupo;
            } catch (error) {
                form.hidden = true;
                mostrarMensaje(error.message, true);
            }
        }

        form.addEventListener("submit", async (evento) => {
            evento.preventDefault();

            try {
                const datos = await peticionJSON(
                    "endpoints.php?tipo=grupos&id=" + encodeURIComponent(id),
                    {
                        method: "PUT",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify(datosDelFormulario(form))
                    }
                );

                mostrarMensaje(datos.mensaje, !datos.ok);

                if (datos.ok) {
                    setTimeout(() => {
                        window.location.href = "lista_grupos.php";
                    }, 700);
                }
            } catch (error) {
                mostrarMensaje(error.message, true);
            }
        });

        document.addEventListener("DOMContentLoaded", cargarGrupo);
    </script>
</body>
</html>
