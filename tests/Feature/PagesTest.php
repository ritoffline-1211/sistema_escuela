<?php

declare(strict_types=1);

namespace Escuela\Tests\Feature;

use Escuela\Tests\Support\ApiClient;
use Escuela\Tests\Support\AppTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/** Cubre las páginas HTML: acceso por sesión, permisos por rol y logout. */
final class PagesTest extends AppTestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function paginasProtegidas(): array
    {
        return [
            'índice' => ['index.php'],
            'lista de alumnos' => ['lista_alumnos.php'],
            'lista de grupos' => ['lista_grupos.php'],
            'crear alumno' => ['crear_alumno.php'],
            'editar alumno' => ['editar_alumno.php?id=1'],
            'crear grupo' => ['crear_grupo.php'],
            'editar grupo' => ['editar_grupo.php?id=1'],
        ];
    }

    #[Test]
    #[DataProvider('paginasProtegidas')]
    public function pagina_redirige_a_login_sin_sesion(string $ruta): void
    {
        $respuesta = ApiClient::web($ruta);

        $this->assertSame(302, $respuesta['status']);
        $this->assertStringContainsString('login.php', (string) ($respuesta['headers']['location'] ?? ''));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function paginasSoloAdmin(): array
    {
        return [
            'crear alumno' => ['crear_alumno.php'],
            'editar alumno' => ['editar_alumno.php?id=1'],
            'crear grupo' => ['crear_grupo.php'],
            'editar grupo' => ['editar_grupo.php?id=1'],
        ];
    }

    #[Test]
    #[DataProvider('paginasSoloAdmin')]
    public function pagina_de_escritura_redirige_al_sistema_para_el_rol_consulta(string $ruta): void
    {
        $cookies = $this->archivoCookies();
        $this->login('profesor', '1212', $cookies);

        $respuesta = ApiClient::web($ruta, ['cookies' => $cookies]);

        $this->assertSame(302, $respuesta['status']);
        $this->assertStringContainsString(
            'lista_alumnos.php?error=sin_permiso',
            (string) ($respuesta['headers']['location'] ?? '')
        );
    }

    #[Test]
    #[DataProvider('paginasSoloAdmin')]
    public function pagina_de_escritura_esta_disponible_para_el_admin(string $ruta): void
    {
        $cookies = $this->archivoCookies();
        $this->login('ricardo', '1211', $cookies);

        $respuesta = ApiClient::web($ruta, ['cookies' => $cookies]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertStringContainsString('<form', $respuesta['raw']);
    }

    #[Test]
    public function indice_redirige_a_la_lista_de_alumnos_con_sesion(): void
    {
        $cookies = $this->archivoCookies();
        $this->login('ricardo', '1211', $cookies);

        $respuesta = ApiClient::web('index.php', ['cookies' => $cookies]);

        $this->assertSame(302, $respuesta['status']);
        $this->assertStringContainsString('lista_alumnos.php', (string) ($respuesta['headers']['location'] ?? ''));
    }

    #[Test]
    public function listas_renderizan_la_estructura_y_llaman_a_los_endpoints(): void
    {
        // Arrange: datos en la base de prueba y sesión de administrador.
        $this->crearAlumno();
        $cookies = $this->archivoCookies();
        $this->login('ricardo', '1211', $cookies);

        // Act.
        $alumnos = ApiClient::web('lista_alumnos.php', ['cookies' => $cookies]);
        $grupos = ApiClient::web('lista_grupos.php', ['cookies' => $cookies]);

        // Assert: las filas las carga el navegador vía JS desde la API.
        $this->assertSame(200, $alumnos['status']);
        $this->assertStringContainsString('endpoints.php?tipo=alumnos', $alumnos['raw']);
        $this->assertStringContainsString('Agregar Alumno', $alumnos['raw']);
        $this->assertSame(200, $grupos['status']);
        $this->assertStringContainsString('endpoints.php?tipo=grupos', $grupos['raw']);
        $this->assertStringContainsString('Agregar Grupo', $grupos['raw']);
    }

    #[Test]
    public function lista_de_alumnos_muestra_el_aviso_de_permisos(): void
    {
        $cookies = $this->archivoCookies();
        $this->login('ricardo', '1211', $cookies);

        $respuesta = ApiClient::web('lista_alumnos.php?error=sin_permiso', ['cookies' => $cookies]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertStringContainsString('No tienes permisos', $respuesta['raw']);
    }

    #[Test]
    public function logout_por_get_no_cierra_la_sesion(): void
    {
        $cookies = $this->archivoCookies();
        $this->login('ricardo', '1211', $cookies);

        $respuesta = ApiClient::web('logout.php', ['cookies' => $cookies]);

        $this->assertSame(302, $respuesta['status']);
        $this->assertStringContainsString('lista_alumnos.php', (string) ($respuesta['headers']['location'] ?? ''));
        $this->assertSame(200, ApiClient::web('lista_alumnos.php', ['cookies' => $cookies])['status']);
    }
}
