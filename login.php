<?php

require_once __DIR__ . '/auth.php';

if (usuarioActual() !== null) {
    header('Location: lista_alumnos.php');
    exit;
}

$error = '';
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrfValido($_POST['csrf'] ?? '')) {
        $error = 'La sesión expiró. Vuelve a intentarlo.';
    } elseif ($usuario === '' || $password === '') {
        $error = 'Escribe tu usuario y contraseña.';
    } else {
        try {
            if (autenticar($usuario, $password)) {
                header('Location: lista_alumnos.php');
                exit;
            }

            $error = 'Usuario o contraseña incorrectos.';
        } catch (Throwable $excepcion) {
            $error = $excepcion->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · Sistema Escolar</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body class="login-page">
    <main class="login-main">
        <section class="card login-card">
            <div class="login-marca">
                <span class="login-icono" aria-hidden="true">🎓</span>
                <h1>Sistema Escolar</h1>
                <p>Acceso restringido. Inicia sesión para continuar.</p>
            </div>

            <?php if ($error !== ''): ?>
                <p class="mensaje error" role="alert"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="post" action="login.php" class="formulario login-formulario">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">

                <label>
                    Usuario
                    <input
                        name="usuario"
                        maxlength="50"
                        autocomplete="username"
                        value="<?= htmlspecialchars($usuario) ?>"
                        required
                        autofocus
                    >
                </label>

                <label>
                    Contraseña
                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >
                </label>

                <button type="submit" class="btn btn-primario btn-login">Iniciar sesión</button>
            </form>
        </section>
    </main>
</body>
</html>
