<?php

require_once __DIR__ . '/../auth.php';

$configAuth = require __DIR__ . '/../config/auth.php';
$tokenEsperado = (string) ($configAuth['api_token'] ?? '');
$error = '';

function tokenDocumentacionValido($token, string $esperado): bool
{
    return is_string($token)
        && $token !== ''
        && $esperado !== ''
        && hash_equals($esperado, $token);
}

if (isset($_GET['salir'])) {
    unset($_SESSION['docs_token']);
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenRecibido = trim((string) ($_POST['token'] ?? ''));

    if (tokenDocumentacionValido($tokenRecibido, $tokenEsperado)) {
        session_regenerate_id(true);
        $_SESSION['docs_token'] = $tokenRecibido;
        header('Location: index.php');
        exit;
    }

    $error = 'Token inválido.';
} elseif (isset($_GET['token'])) {
    $tokenRecibido = trim((string) $_GET['token']);

    if (tokenDocumentacionValido($tokenRecibido, $tokenEsperado)) {
        session_regenerate_id(true);
        $_SESSION['docs_token'] = $tokenRecibido;
        header('Location: index.php');
        exit;
    }

    $error = 'Token inválido.';
}

$tokenSesion = $_SESSION['docs_token'] ?? '';
$autorizado = tokenDocumentacionValido($tokenSesion, $tokenEsperado);

if (!$autorizado) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso a la documentación · API Escuela</title>
    <link rel="stylesheet" href="../estilos.css">
    <link rel="icon" type="image/png" href="swagger-ui/favicon-32x32.png">
</head>
<body class="login-page">
    <main class="login-main">
        <section class="card login-card">
            <div class="login-marca">
                <span class="login-icono" aria-hidden="true">📘</span>
                <h1>Documentación de la API</h1>
                <p>Introduce el token de acceso para abrir Swagger.</p>
            </div>

            <?php if ($error !== ''): ?>
                <p class="mensaje error" role="alert"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="post" action="index.php" class="formulario login-formulario">
                <label>
                    Token de acceso
                    <input
                        type="password"
                        name="token"
                        autocomplete="off"
                        placeholder="Token de la API"
                        required
                        autofocus
                    >
                </label>

                <button type="submit" class="btn btn-primario btn-login">Abrir documentación</button>
            </form>

            <p class="login-pie">
                <a href="../lista_alumnos.php">&larr; Volver al sistema escolar</a>
            </p>
        </section>
    </main>
</body>
</html>
<?php
    exit;
}

$tokenJs = json_encode((string) $tokenSesion, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Escuela · Swagger UI</title>
    <link rel="stylesheet" href="swagger-ui/swagger-ui.css">
    <link rel="icon" type="image/png" href="swagger-ui/favicon-32x32.png">
    <style>
        html {
            box-sizing: border-box;
            overflow-y: scroll;
        }

        *,
        *::before,
        *::after {
            box-sizing: inherit;
        }

        body {
            margin: 0;
            background: #fafafa;
        }

        .barra-documentacion {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.75rem 1rem;
            background: #111827;
            color: #e5e7eb;
            font-family: sans-serif;
            font-size: 0.9rem;
        }

        .barra-documentacion a {
            color: #bfdbfe;
            text-decoration: none;
        }

        .barra-documentacion a:hover {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="barra-documentacion">
        <a href="../lista_alumnos.php">&larr; Volver al sistema escolar</a>
        <span>Swagger UI · acceso con token</span>
        <a href="index.php?salir=1">Cerrar acceso</a>
    </div>
    <div id="swagger-ui"></div>

    <script src="swagger-ui/swagger-ui-bundle.js"></script>
    <script src="swagger-ui/swagger-ui-standalone-preset.js"></script>
    <script>
        window.addEventListener("load", () => {
            window.ui = SwaggerUIBundle({
                url: "openapi.php",
                dom_id: "#swagger-ui",
                deepLinking: true,
                displayRequestDuration: true,
                tryItOutEnabled: true,
                requestInterceptor: (request) => {
                    request.headers = request.headers || {};
                    request.headers["Authorization"] = "Bearer " + <?= $tokenJs ?>;
                    request.headers["X-API-Token"] = <?= $tokenJs ?>;
                    return request;
                },
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout"
            });
        });
    </script>
</body>
</html>
