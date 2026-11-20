<?php

declare(strict_types=1);

namespace Escuela\Tests\Feature;

use Escuela\Tests\Support\ApiClient;
use Escuela\Tests\Support\AppTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/** Cubre login, CSRF, roles, logout y las formas de autenticar la API. */
final class AuthTest extends AppTestCase
{
    #[Test]
    public function login_con_credenciales_de_admin_crea_la_sesion(): void
    {
        // Arrange.
        $cookies = $this->archivoCookies();

        // Act.
        $respuesta = $this->login('ricardo', '1211', $cookies);

        // Assert.
        $this->assertSame(302, $respuesta['status']);
        $this->assertStringContainsString('lista_alumnos.php', (string) ($respuesta['headers']['location'] ?? ''));

        $pagina = ApiClient::web('lista_alumnos.php', ['cookies' => $cookies]);
        $this->assertSame(200, $pagina['status']);
        $this->assertStringContainsString('Agregar Alumno', $pagina['raw']);
    }

    #[Test]
    public function login_con_credenciales_de_consulta_muestra_modo_solo_lectura(): void
    {
        $cookies = $this->archivoCookies();

        $respuesta = $this->login('profesor', '1212', $cookies);

        $this->assertSame(302, $respuesta['status']);

        $pagina = ApiClient::web('lista_alumnos.php', ['cookies' => $cookies]);
        $this->assertSame(200, $pagina['status']);
        $this->assertStringContainsString('Modo consulta', $pagina['raw']);
        $this->assertStringNotContainsString('Agregar Alumno', $pagina['raw']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function credencialesInvalidas(): array
    {
        return [
            'usuario inexistente' => ['no_existe', '1211', 'Usuario o contraseña incorrectos.'],
            'password incorrecta' => ['ricardo', 'password-incorrecta', 'Usuario o contraseña incorrectos.'],
            'usuario vacío' => ['', '1211', 'Escribe tu usuario y contraseña.'],
            'password vacía' => ['ricardo', '', 'Escribe tu usuario y contraseña.'],
        ];
    }

    #[Test]
    #[DataProvider('credencialesInvalidas')]
    public function login_rechaza_credenciales_invalidas(
        string $usuario,
        string $password,
        string $mensajeEsperado
    ): void {
        $cookies = $this->archivoCookies();

        $respuesta = $this->login($usuario, $password, $cookies);

        $this->assertSame(200, $respuesta['status']);
        $this->assertStringContainsString($mensajeEsperado, $respuesta['raw']);
        $this->assertSame(302, ApiClient::web('lista_alumnos.php', ['cookies' => $cookies])['status']);
    }

    #[Test]
    public function login_rechaza_un_token_csrf_invalido(): void
    {
        $cookies = $this->archivoCookies();
        ApiClient::web('login.php', ['cookies' => $cookies]);

        $respuesta = ApiClient::web('login.php', [
            'metodo' => 'POST',
            'cuerpo' => ['csrf' => 'token-falso', 'usuario' => 'ricardo', 'password' => '1211'],
            'cookies' => $cookies,
        ]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertStringContainsString('La sesión expiró. Vuelve a intentarlo.', $respuesta['raw']);
    }

    #[Test]
    public function login_con_sesion_activa_redirige_a_la_lista(): void
    {
        $cookies = $this->archivoCookies();
        $this->login('ricardo', '1211', $cookies);

        $respuesta = ApiClient::web('login.php', ['cookies' => $cookies]);

        $this->assertSame(302, $respuesta['status']);
        $this->assertStringContainsString('lista_alumnos.php', (string) ($respuesta['headers']['location'] ?? ''));
    }

    #[Test]
    public function api_rechaza_peticiones_sin_token_ni_sesion(): void
    {
        $respuesta = ApiClient::api('GET', 'tipo=alumnos', null, ['token' => false]);

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['data']['ok'] ?? null);
        $this->assertStringContainsString('No autorizado', (string) ($respuesta['data']['mensaje'] ?? ''));
    }

    #[Test]
    public function api_rechaza_un_token_incorrecto(): void
    {
        $respuesta = ApiClient::api('GET', 'tipo=alumnos', null, ['token' => 'token-incorrecto']);

        $this->assertSame(401, $respuesta['status']);
    }

    #[Test]
    public function api_acepta_el_token_en_la_cabecera_bearer(): void
    {
        $respuesta = ApiClient::api('GET', 'tipo=alumnos');

        $this->assertSame(200, $respuesta['status']);
        $this->assertIsArray($respuesta['data']);
    }

    #[Test]
    public function api_acepta_el_token_como_parametro_de_consulta(): void
    {
        $respuesta = ApiClient::api(
            'GET',
            'tipo=grupos&token=' . urlencode(ApiClient::tokenPorDefecto()),
            null,
            ['token' => false]
        );

        $this->assertSame(200, $respuesta['status']);
        $this->assertIsArray($respuesta['data']);
    }

    #[Test]
    public function api_acepta_la_sesion_de_un_administrador_para_escribir(): void
    {
        $cookies = $this->archivoCookies();
        $this->login('ricardo', '1211', $cookies);

        $respuesta = ApiClient::api(
            'POST',
            'tipo=grupos',
            ['grupo' => self::$prefijo . '-ADMIN'],
            ['token' => false, 'cookies' => $cookies]
        );
        $this->gruposCreados[] = (int) ($respuesta['data']['id'] ?? 0);

        $this->assertSame(201, $respuesta['status']);
    }

    #[Test]
    public function api_permite_leer_a_la_sesion_de_consulta_pero_no_escribir(): void
    {
        // Arrange: alumno fixture e inicio de sesión con rol "consulta".
        $alumno = $this->crearAlumno();
        $cookies = $this->archivoCookies();
        $this->login('profesor', '1212', $cookies);

        // Act.
        $lectura = ApiClient::api('GET', 'tipo=alumnos&id=' . $alumno['id'], null, [
            'token' => false,
            'cookies' => $cookies,
        ]);
        $escritura = ApiClient::api('POST', 'tipo=grupos', ['grupo' => self::$prefijo . '-NO'], [
            'token' => false,
            'cookies' => $cookies,
        ]);

        // Assert.
        $this->assertSame(200, $lectura['status']);
        $this->assertSame(403, $escritura['status']);
        $this->assertStringContainsString('No tienes permisos', (string) ($escritura['data']['mensaje'] ?? ''));
    }

    #[Test]
    public function logout_cierra_la_sesion_y_la_api_deja_de_autorizarla(): void
    {
        $cookies = $this->archivoCookies();
        $this->login('ricardo', '1211', $cookies);

        $pagina = ApiClient::web('lista_alumnos.php', ['cookies' => $cookies]);
        $respuesta = ApiClient::web('logout.php', [
            'metodo' => 'POST',
            'cuerpo' => ['csrf' => $this->csrfDePagina($pagina['raw'])],
            'cookies' => $cookies,
        ]);

        $this->assertSame(302, $respuesta['status']);
        $this->assertStringContainsString('login.php', (string) ($respuesta['headers']['location'] ?? ''));
        $this->assertSame(401, ApiClient::api('GET', 'tipo=alumnos', null, [
            'token' => false,
            'cookies' => $cookies,
        ])['status']);
    }

    #[Test]
    public function logout_rechaza_peticiones_sin_csrf(): void
    {
        $cookies = $this->archivoCookies();
        $this->login('ricardo', '1211', $cookies);

        $respuesta = ApiClient::web('logout.php', [
            'metodo' => 'POST',
            'cuerpo' => ['csrf' => 'invalido'],
            'cookies' => $cookies,
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertStringContainsString('Solicitud inválida.', $respuesta['raw']);
    }
}
