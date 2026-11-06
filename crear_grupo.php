<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Grupo</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
    <header>
        <h1>Sistema Escolar</h1>
        <nav>
            <a href="lista_alumnos.php">Alumnos</a>
            <a class="activo" href="lista_grupos.php">Grupos</a>
        </nav>
    </header>

    <main>
        <section class="card">
            <div class="barra">
                <h2>Agregar Grupo</h2>
            </div>

            <p id="mensaje" class="mensaje" hidden></p>

            <form id="formGrupo" class="formulario">
                <label>
                    Nombre del grupo
                    <input name="grupo" maxlength="50" placeholder="Ej. Grupo A" required>
                </label>

                <div class="acciones-form">
                    <button type="submit" class="btn btn-primario">Guardar Grupo</button>
                    <a class="btn" href="lista_grupos.php">Cancelar</a>
                </div>
            </form>
        </section>
    </main>

    <script src="app.js"></script>
    <script>
        const form = document.getElementById("formGrupo");

        form.addEventListener("submit", async (evento) => {
            evento.preventDefault();

            try {
                const datos = await peticionJSON("endpoints.php?tipo=grupos", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(datosDelFormulario(form))
                });

                mostrarMensaje(datos.mensaje, !datos.ok);

                if (datos.ok) {
                    form.reset();
                    setTimeout(() => {
                        window.location.href = "lista_grupos.php";
                    }, 700);
                }
            } catch (error) {
                mostrarMensaje(error.message, true);
            }
        });
    </script>
</body>
</html>
