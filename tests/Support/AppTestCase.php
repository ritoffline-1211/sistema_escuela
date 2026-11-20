<?php

declare(strict_types=1);

namespace Escuela\Tests\Support;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Base con fixtures, limpieza y helpers de sesión compartidos por los tests de características. */
abstract class AppTestCase extends TestCase
{
    protected static string $prefijo = '';

    /** @var list<int> */
    protected array $gruposCreados = [];

    /** @var list<int> */
    protected array $alumnosCreados = [];

    /** @var list<string> */
    protected array $archivosCookies = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$prefijo = 'TST' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach ($this->alumnosCreados as $id) {
            ApiClient::api('DELETE', 'tipo=alumnos&id=' . $id);
        }

        foreach ($this->gruposCreados as $id) {
            ApiClient::api('DELETE', 'tipo=grupos&id=' . $id);
        }

        foreach ($this->archivosCookies as $archivo) {
            if (is_file($archivo)) {
                unlink($archivo);
            }
        }

        $this->alumnosCreados = [];
        $this->gruposCreados = [];
        $this->archivosCookies = [];

        parent::tearDown();
    }

    protected function crearGrupo(?string $nombre = null): int
    {
        $nombre ??= self::$prefijo . '-G' . bin2hex(random_bytes(3));
        $respuesta = ApiClient::api('POST', 'tipo=grupos', ['grupo' => $nombre]);

        if ($respuesta['status'] !== 201 || !isset($respuesta['data']['id'])) {
            throw new RuntimeException('No se pudo crear el grupo fixture: ' . $respuesta['raw']);
        }

        $id = (int) $respuesta['data']['id'];
        $this->gruposCreados[] = $id;

        return $id;
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array{id: int, nombre: string, matricula: string, grado: string, grupoId: int}
     */
    protected function crearAlumno(?int $grupoId = null, array $extra = []): array
    {
        $grupoId ??= $this->crearGrupo();
        $datos = array_merge([
            'nombre' => 'Alumno ' . self::$prefijo,
            'matricula' => self::$prefijo . bin2hex(random_bytes(2)),
            'grado' => 'Primero',
            'IdGrupos' => $grupoId,
        ], $extra);

        $respuesta = ApiClient::api('POST', 'tipo=alumnos', $datos);

        if ($respuesta['status'] !== 201 || !isset($respuesta['data']['id'])) {
            throw new RuntimeException('No se pudo crear el alumno fixture: ' . $respuesta['raw']);
        }

        $id = (int) $respuesta['data']['id'];
        $this->alumnosCreados[] = $id;

        return [
            'id' => $id,
            'nombre' => (string) $datos['nombre'],
            'matricula' => (string) $datos['matricula'],
            'grado' => (string) $datos['grado'],
            'grupoId' => $grupoId,
        ];
    }

    protected function archivoCookies(): string
    {
        $archivo = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'escuela_phpunit_' . bin2hex(random_bytes(6)) . '.cookies.txt';

        if (is_file($archivo)) {
            unlink($archivo);
        }

        $this->archivosCookies[] = $archivo;

        return $archivo;
    }

    /** @return array{status: int, data: mixed, raw: string, headers: array<string, string>} */
    protected function login(string $usuario, string $password, string $cookies): array
    {
        $pagina = ApiClient::web('login.php', ['cookies' => $cookies]);
        $csrf = $this->csrfDePagina($pagina['raw']);

        return ApiClient::web('login.php', [
            'metodo' => 'POST',
            'cuerpo' => ['csrf' => $csrf, 'usuario' => $usuario, 'password' => $password],
            'cookies' => $cookies,
        ]);
    }

    protected function csrfDePagina(string $html): string
    {
        if (!preg_match('/name="csrf"\s+value="([^"]+)"/', $html, $coincidencias)) {
            throw new RuntimeException('No se encontró el token CSRF en la página.');
        }

        return $coincidencias[1];
    }
}
