<?php

$ruta = dirname(__DIR__) . '/docs/openapi_data.php';
$contrato = require $ruta;
$spec = json_decode($contrato, true);

if (!is_array($spec)) {
    fwrite(STDERR, "No se pudo decodificar el contrato: " . json_last_error_msg() . "\n");
    exit(1);
}

$spec['components']['responses']['NoAutorizado'] = [
    'description' => 'Falta la autenticación o el token no es válido.',
    'content' => [
        'application/json' => [
            'schema' => ['$ref' => '#/components/schemas/ErrorSimple'],
            'example' => [
                'ok' => false,
                'mensaje' => 'No autorizado. Inicia sesión o envía un token válido.',
            ],
        ],
    ],
];

$spec['components']['responses']['SinPermiso'] = [
    'description' => 'La sesión es de solo lectura (`consulta`) y la operación modifica datos.',
    'content' => [
        'application/json' => [
            'schema' => ['$ref' => '#/components/schemas/ErrorSimple'],
            'example' => [
                'ok' => false,
                'mensaje' => 'No tienes permisos para modificar datos.',
            ],
        ],
    ],
];

foreach ($spec['paths'] as $rutaApi => $operaciones) {
    foreach ($operaciones as $metodo => $operacion) {
        if (!is_array($operacion) || !isset($operacion['responses'])) {
            continue;
        }

        $respuestas = $operacion['responses'];
        $operacion['responses'] = [
            '401' => ['$ref' => '#/components/responses/NoAutorizado'],
            '403' => ['$ref' => '#/components/responses/SinPermiso'],
        ];
        $operacion['responses'] += $respuestas;

        $spec['paths'][$rutaApi][$metodo] = $operacion;
    }
}

$json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$contenido = "<?php\n\n// Datos del contrato OpenAPI. Si se solicita directamente no imprime nada.\nreturn <<<'OPENAPI_JSON'\n"
    . $json . "\nOPENAPI_JSON;\n";

file_put_contents($ruta, $contenido);

$verificado = json_decode(require $ruta, true);

if (!is_array($verificado)) {
    fwrite(STDERR, "El contrato generado no es JSON válido.\n");
    exit(1);
}

echo "Contrato actualizado: " . count($verificado['paths']['/endpoints.php']) . " operaciones.\n";
echo "Respuestas GET: " . implode(', ', array_keys($verificado['paths']['/endpoints.php']['get']['responses'])) . "\n";
