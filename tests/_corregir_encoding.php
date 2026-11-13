<?php

$ruta = dirname(__DIR__) . '/docs/openapi_data.php';
$contenido = file_get_contents($ruta);
$corregido = mb_convert_encoding($contenido, 'ISO-8859-1', 'UTF-8');

if (!mb_check_encoding($corregido, 'UTF-8')) {
    fwrite(STDERR, "El contenido corregido no es UTF-8 válido.\n");
    exit(1);
}

file_put_contents($ruta, $corregido);
echo "Codificación corregida.\n";
