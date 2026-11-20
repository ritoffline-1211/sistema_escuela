<?php

declare(strict_types=1);

namespace Escuela\Tests\Feature;

use Escuela\Tests\Support\ApiClient;
use Escuela\Tests\Support\AppTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class GruposApiTest extends AppTestCase
{
    #[Test]
    public function listar_grupos_incluye_total_de_alumnos(): void
    {
        $grupoId = $this->crearGrupo();
        $this->crearAlumno($grupoId);

        $respuesta = ApiClient::api('GET', 'tipo=grupos');

        $this->assertSame(200, $respuesta['status']);
        $this->assertIsArray($respuesta['data']);

        $grupo = null;

        foreach ($respuesta['data'] as $fila) {
            if ((int) ($fila['IdGrupos'] ?? 0) === $grupoId) {
                $grupo = $fila;
                break;
            }
        }

        $this->assertNotNull($grupo, 'El grupo creado debe aparecer en la lista.');
        $this->assertArrayHasKey('total_alumnos', $grupo);
        $this->assertGreaterThanOrEqual(1, (int) $grupo['total_alumnos']);
    }

    #[Test]
    public function obtener_grupo_por_id_devuelve_el_registro(): void
    {
        $grupoId = $this->crearGrupo('Grupo PHPUnit ' . bin2hex(random_bytes(3)));

        $respuesta = ApiClient::api('GET', 'tipo=grupos&id=' . $grupoId);

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame((string) $grupoId, (string) ($respuesta['data']['IdGrupos'] ?? null));
        $this->assertArrayHasKey('total_alumnos', $respuesta['data'] ?? []);
    }

    #[Test]
    public function obtener_grupo_con_id_cero_devuelve_la_lista(): void
    {
        $grupoId = $this->crearGrupo();

        // Un id no positivo se trata como "sin id" según el enrutador.
        $respuesta = ApiClient::api('GET', 'tipo=grupos&id=0');

        $this->assertSame(200, $respuesta['status']);
        $this->assertIsArray($respuesta['data']);
        $this->assertTrue(
            in_array($grupoId, array_map('intval', array_column($respuesta['data'], 'IdGrupos')), true),
            'El grupo fixture debe aparecer en la lista.'
        );
    }

    #[Test]
    public function obtener_grupo_inexistente_devuelve_404(): void
    {
        $respuesta = ApiClient::api('GET', 'tipo=grupos&id=99999999');

        $this->assertSame(404, $respuesta['status']);
    }

    #[Test]
    public function crear_grupo_devuelve_201(): void
    {
        $nombre = self::$prefijo . '-NUEVO';
        $respuesta = ApiClient::api('POST', 'tipo=grupos', ['grupo' => $nombre]);
        $this->gruposCreados[] = (int) ($respuesta['data']['id'] ?? 0);

        $this->assertSame(201, $respuesta['status']);
        $this->assertTrue($respuesta['data']['ok'] ?? false);
        $this->assertGreaterThan(0, (int) ($respuesta['data']['id'] ?? 0));
    }

    /**
     * @param array<string, mixed> $cuerpo
     */
    #[Test]
    #[DataProvider('cuerposInvalidos')]
    public function crear_grupo_rechaza_datos_invalidos(array $cuerpo, string $mensajeEsperado): void
    {
        $respuesta = ApiClient::api('POST', 'tipo=grupos', $cuerpo);

        $this->assertSame(400, $respuesta['status']);
        $this->assertStringContainsString(
            $mensajeEsperado,
            implode(' ', $respuesta['data']['errores'] ?? [])
        );
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function cuerposInvalidos(): array
    {
        return [
            'vacío' => [[], 'El nombre del grupo es obligatorio.'],
            'nombre vacío' => [['grupo' => '   '], 'El nombre del grupo es obligatorio.'],
            'nombre largo' => [['grupo' => str_repeat('g', 51)], 'no puede superar los 50 caracteres.'],
        ];
    }

    #[Test]
    public function crear_grupo_duplicado_devuelve_400(): void
    {
        $nombre = self::$prefijo . '-DUP';
        $primero = ApiClient::api('POST', 'tipo=grupos', ['grupo' => $nombre]);
        $this->gruposCreados[] = (int) ($primero['data']['id'] ?? 0);

        $respuesta = ApiClient::api('POST', 'tipo=grupos', ['grupo' => $nombre]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertStringContainsString('Ya existe', implode(' ', $respuesta['data']['errores'] ?? []));
    }

    #[Test]
    public function put_actualiza_el_nombre_del_grupo(): void
    {
        $grupoId = $this->crearGrupo();
        $nuevo = self::$prefijo . '-PUT';

        $respuesta = ApiClient::api('PUT', 'tipo=grupos&id=' . $grupoId, ['grupo' => $nuevo]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame($nuevo, ApiClient::api('GET', 'tipo=grupos&id=' . $grupoId)['data']['grupo'] ?? null);
    }

    #[Test]
    public function patch_actualiza_parcialmente_el_grupo(): void
    {
        $grupoId = $this->crearGrupo();
        $nuevo = self::$prefijo . '-PATCH';

        $respuesta = ApiClient::api('PATCH', 'tipo=grupos&id=' . $grupoId, ['grupo' => $nuevo]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame($nuevo, ApiClient::api('GET', 'tipo=grupos&id=' . $grupoId)['data']['grupo'] ?? null);
    }

    #[Test]
    public function actualizar_grupo_inexistente_devuelve_404(): void
    {
        $respuesta = ApiClient::api('PUT', 'tipo=grupos&id=99999999', ['grupo' => 'Nadie']);

        $this->assertSame(404, $respuesta['status']);
    }

    #[Test]
    public function accion_duplicar_no_esta_disponible_para_grupos(): void
    {
        $respuesta = ApiClient::api('POST', 'tipo=grupos&id=1&accion=duplicar');

        $this->assertSame(400, $respuesta['status']);
        $this->assertStringContainsString(
            'solo está disponible para alumnos',
            (string) ($respuesta['data']['mensaje'] ?? '')
        );
    }

    #[Test]
    public function eliminar_grupo_con_alumnos_devuelve_409(): void
    {
        $grupoId = $this->crearGrupo();
        $this->crearAlumno($grupoId);

        $respuesta = ApiClient::api('DELETE', 'tipo=grupos&id=' . $grupoId);

        $this->assertSame(409, $respuesta['status']);
        $this->assertStringContainsString('alumnos asignados', (string) ($respuesta['data']['mensaje'] ?? ''));
    }

    #[Test]
    public function eliminar_grupo_vacio_devuelve_200(): void
    {
        $grupoId = $this->crearGrupo();

        $respuesta = ApiClient::api('DELETE', 'tipo=grupos&id=' . $grupoId);

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame(404, ApiClient::api('GET', 'tipo=grupos&id=' . $grupoId)['status']);
        $this->gruposCreados = array_values(array_diff($this->gruposCreados, [$grupoId]));
    }

    #[Test]
    public function eliminar_grupo_inexistente_devuelve_404(): void
    {
        $respuesta = ApiClient::api('DELETE', 'tipo=grupos&id=99999999');

        $this->assertSame(404, $respuesta['status']);
    }

    #[Test]
    public function recurso_desconocido_devuelve_404(): void
    {
        $respuesta = ApiClient::api('GET', 'tipo=profesores');

        $this->assertSame(404, $respuesta['status']);
    }

    #[Test]
    public function sin_tipo_usa_alumnos_por_defecto(): void
    {
        $respuesta = ApiClient::api('GET');

        $this->assertSame(200, $respuesta['status']);
        $this->assertIsArray($respuesta['data']);
    }
}
