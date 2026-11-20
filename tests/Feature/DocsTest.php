<?php

declare(strict_types=1);

namespace Escuela\Tests\Feature;

use Escuela\Tests\Support\ApiClient;
use Escuela\Tests\Support\AppTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/** Cubre el acceso protegido a Swagger y al contrato OpenAPI. */
final class DocsTest extends AppTestCase
{
    #[Test]
    public function openapi_devuelve_403_sin_la_sesion_de_documentacion(): void
    {
        $respuesta = ApiClient::web('docs/openapi.php');

        $this->assertSame(403, $respuesta['status']);
        $this->assertFalse($respuesta['data']['ok'] ?? null);
        $this->assertStringContainsString('Acceso no autorizado', (string) ($respuesta['data']['mensaje'] ?? ''));
    }

    #[Test]
    public function index_muestra_el_formulario_de_token_cuando_no_hay_acceso(): void
    {
        $respuesta = ApiClient::web('docs/index.php');

        $this->assertSame(200, $respuesta['status']);
        $this->assertStringContainsString('Introduce el token de acceso', $respuesta['raw']);
        $this->assertStringContainsString('name="token"', $respuesta['raw']);
    }

    #[Test]
    #[DataProvider('tokensInvalidos')]
    public function index_rechaza_tokens_invalidos(string $ruta): void
    {
        $cookies = $this->archivoCookies();

        $respuesta = ApiClient::web($ruta, ['cookies' => $cookies]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertStringContainsString('Token inválido.', $respuesta['raw']);
        $this->assertSame(403, ApiClient::web('docs/openapi.php', ['cookies' => $cookies])['status']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function tokensInvalidos(): array
    {
        return [
            'token incorrecto' => ['docs/index.php?token=token-incorrecto'],
            'token vacío' => ['docs/index.php?token='],
        ];
    }

    #[Test]
    public function index_rechaza_un_token_enviado_por_post(): void
    {
        $cookies = $this->archivoCookies();

        $respuesta = ApiClient::web('docs/index.php', [
            'metodo' => 'POST',
            'cuerpo' => ['token' => 'token-incorrecto'],
            'cookies' => $cookies,
        ]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertStringContainsString('Token inválido.', $respuesta['raw']);
    }

    #[Test]
    public function index_acepta_el_token_por_get_y_habilita_swagger(): void
    {
        $cookies = $this->archivoCookies();
        $token = ApiClient::tokenPorDefecto();

        // Act: acceso directo con ?token=... y redirección a la UI.
        $acceso = ApiClient::web('docs/index.php?token=' . urlencode($token), ['cookies' => $cookies]);
        $ui = ApiClient::web('docs/index.php', ['cookies' => $cookies]);
        $contrato = ApiClient::web('docs/openapi.php', ['cookies' => $cookies]);

        // Assert.
        $this->assertSame(302, $acceso['status']);
        $this->assertStringContainsString('index.php', (string) ($acceso['headers']['location'] ?? ''));
        $this->assertSame(200, $ui['status']);
        $this->assertStringContainsString('SwaggerUIBundle', $ui['raw']);
        $this->assertSame(200, $contrato['status']);
        $this->assertIsArray($contrato['data']);
        $this->assertSame('3.0.3', $contrato['data']['openapi'] ?? null);
    }

    #[Test]
    public function index_acepta_el_token_por_post_y_habilita_el_contrato(): void
    {
        $cookies = $this->archivoCookies();

        $acceso = ApiClient::web('docs/index.php', [
            'metodo' => 'POST',
            'cuerpo' => ['token' => ApiClient::tokenPorDefecto()],
            'cookies' => $cookies,
        ]);
        $contrato = ApiClient::web('docs/openapi.php', ['cookies' => $cookies]);

        $this->assertSame(302, $acceso['status']);
        $this->assertSame(200, $contrato['status']);
        $this->assertArrayHasKey('paths', $contrato['data'] ?? []);
    }

    #[Test]
    public function el_contrato_documenta_los_endpoints_de_alumnos_y_grupos(): void
    {
        $cookies = $this->archivoCookies();
        ApiClient::web('docs/index.php?token=' . urlencode(ApiClient::tokenPorDefecto()), ['cookies' => $cookies]);

        $contrato = ApiClient::web('docs/openapi.php', ['cookies' => $cookies]);
        $rutas = array_keys($contrato['data']['paths'] ?? []);
        $metodos = $contrato['data']['paths']['/endpoints.php'] ?? [];

        // El contrato modela el front controller con parámetros `tipo` e `id`.
        $this->assertContains('/endpoints.php', $rutas);
        foreach (['get', 'post', 'put', 'patch', 'delete'] as $metodo) {
            $this->assertArrayHasKey($metodo, $metodos);
        }
        $this->assertStringContainsString(
            'alumnos',
            json_encode($metodos, JSON_UNESCAPED_UNICODE) ?: ''
        );
        $this->assertStringContainsString(
            'grupos',
            json_encode($metodos, JSON_UNESCAPED_UNICODE) ?: ''
        );
    }

    #[Test]
    public function salir_de_la_documentacion_revoca_el_acceso_al_contrato(): void
    {
        $cookies = $this->archivoCookies();
        ApiClient::web('docs/index.php?token=' . urlencode(ApiClient::tokenPorDefecto()), ['cookies' => $cookies]);

        $this->assertSame(200, ApiClient::web('docs/openapi.php', ['cookies' => $cookies])['status']);

        $salida = ApiClient::web('docs/index.php?salir=1', ['cookies' => $cookies]);

        $this->assertSame(302, $salida['status']);
        $this->assertSame(403, ApiClient::web('docs/openapi.php', ['cookies' => $cookies])['status']);
    }
}
