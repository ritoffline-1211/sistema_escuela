function mostrarMensaje(texto, esError = false) {
    const mensaje = document.getElementById("mensaje");

    if (!mensaje) {
        window.alert(texto);
        return;
    }

    mensaje.textContent = texto;
    mensaje.className = "mensaje " + (esError ? "error" : "ok");
    mensaje.hidden = false;
}

async function peticionJSON(url, opciones = {}) {
    const respuesta = await fetch(url, opciones);

    if (respuesta.status === 401) {
        window.location.href = "login.php";
        throw new Error("Tu sesión expiró. Inicia sesión de nuevo.");
    }

    let datos;

    try {
        datos = await respuesta.json();
    } catch (error) {
        throw new Error("El servidor devolvió una respuesta no válida.");
    }

    return datos;
}

async function cargarGrupos(select, seleccionado = "") {
    const grupos = await peticionJSON("endpoints.php?tipo=grupos");

    if (!Array.isArray(grupos)) {
        throw new Error(grupos.mensaje || "No se pudieron cargar los grupos.");
    }

    select.innerHTML = '<option value="">Seleccione un grupo</option>';

    grupos.forEach((grupo) => {
        const opcion = document.createElement("option");
        opcion.value = grupo.IdGrupos;
        opcion.textContent = grupo.grupo;

        if (String(grupo.IdGrupos) === String(seleccionado)) {
            opcion.selected = true;
        }

        select.appendChild(opcion);
    });
}

function datosDelFormulario(formulario) {
    return Object.fromEntries(new FormData(formulario).entries());
}
