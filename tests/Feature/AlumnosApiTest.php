<?php

declare(strict_types=1);

namespace Escuela\Tests\Feature;

use Escuela\Tests\Support\ApiClient;
use Escuela\Tests\Support\AppTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class AlumnosApiTest extends AppTestCase
{
    #[Test]
    public function listar_alumnos_devuelve_un_arreglo_json(): void
    {
        // Arrange: existe al menos un alumno fixture.
        $alumno = $this->crearAlumno();

        // Act.
        $respuesta = ApiClient::api('GET', 'tipo=alumnos');

        // Assert.
        $this->assertSame(200, $respuesta['status']);
        $this->assertIsArray($respuesta['data']);
        $this->assertNotEmpty($respuesta['data']);
        $this->assertArrayHasKey('IdAlumnos', $respuesta['data'][0]);
        $this->assertArrayHasKey('grupo', $respuesta['data'][0]);
        $this->assertTrue(
            in_array($alumno['id'], array_column($respuesta['data'], 'IdAlumnos'), false),
            'El alumno creado debe aparecer en la lista.'
        );
    }

    #[Test]
    public function obtener_alumno_por_id_devuelve_su_grupo(): void
    {
        $alumno = $this->crearAlumno();

        $respuesta = ApiClient::api('GET', 'tipo=alumnos&id=' . $alumno['id']);

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame($alumno['nombre'], $respuesta['data']['nombre'] ?? null);
        $this->assertSame($alumno['matricula'], $respuesta['data']['matricula'] ?? null);
        $this->assertSame($alumno['grado'], $respuesta['data']['grado'] ?? null);
        $this->assertSame((string) $alumno['grupoId'], (string) ($respuesta['data']['IdGrupos'] ?? null));
        $this->assertIsString($respuesta['data']['grupo'] ?? null);
    }

    #[Test]
    public function obtener_alumno_con_id_cero_devuelve_la_lista(): void
    {
        // Arrange: existe un alumno fixture.
        $alumno = $this->crearAlumno();

        // Act: un id no positivo se trata como "sin id" según el enrutador.
        $respuesta = ApiClient::api('GET', 'tipo=alumnos&id=0');

        // Assert.
        $this->assertSame(200, $respuesta['status']);
        $this->assertIsArray($respuesta['data']);
        $this->assertTrue(
            in_array($alumno['id'], array_column($respuesta['data'], 'IdAlumnos'), false),
            'El alumno fixture debe aparecer en la lista.'
        );
    }

    #[Test]
    public function obtener_alumno_inexistente_devuelve_404(): void
    {
        $respuesta = ApiClient::api('GET', 'tipo=alumnos&id=99999999');

        $this->assertSame(404, $respuesta['status']);
        $this->assertStringContainsString('no encontrado', (string) ($respuesta['data']['mensaje'] ?? ''));
    }

    #[Test]
    public function crear_alumno_devuelve_201_y_su_id(): void
    {
        $grupoId = $this->crearGrupo();
        $datos = [
            'nombre' => 'Juan Perez',
            'matricula' => self::$prefijo . '-A1',
            'grado' => 'Segundo',
            'IdGrupos' => $grupoId,
        ];

        $respuesta = ApiClient::api('POST', 'tipo=alumnos', $datos);
        $this->alumnosCreados[] = (int) ($respuesta['data']['id'] ?? 0);

        $this->assertSame(201, $respuesta['status']);
        $this->assertTrue($respuesta['data']['ok'] ?? false);
        $this->assertGreaterThan(0, (int) ($respuesta['data']['id'] ?? 0));
    }

    /**
     * @param array<string, mixed> $cuerpo
     */
    #[Test]
    #[DataProvider('cuerposInvalidos')]
    public function crear_alumno_rechaza_datos_invalidos(array $cuerpo, string $mensajeEsperado): void
    {
        $respuesta = ApiClient::api('POST', 'tipo=alumnos', $cuerpo);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['data']['ok'] ?? null);
        $this->assertIsArray($respuesta['data']['errores'] ?? null);
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
            'cuerpo vacío' => [[], 'El nombre es obligatorio.'],
            'sin matrícula' => [
                ['nombre' => 'Ana', 'grado' => 'Primero', 'IdGrupos' => 99999999],
                'La matrícula es obligatoria.',
            ],
            'sin grado' => [
                ['nombre' => 'Ana', 'matricula' => 'TST-X', 'IdGrupos' => 99999999],
                'El grado es obligatorio.',
            ],
            'grupo inexistente' => [
                ['nombre' => 'Ana', 'matricula' => 'TST-X', 'grado' => 'Primero', 'IdGrupos' => 99999999],
                'Selecciona un grupo válido.',
            ],
            'nombre demasiado largo' => [
                ['nombre' => str_repeat('a', 101), 'matricula' => 'TST-X', 'grado' => 'Primero', 'IdGrupos' => 99999999],
                'El nombre no puede superar los 100 caracteres.',
            ],
            'matrícula demasiado larga' => [
                ['nombre' => 'Ana', 'matricula' => str_repeat('m', 21), 'grado' => 'Primero', 'IdGrupos' => 99999999],
                'La matrícula no puede superar los 20 caracteres.',
            ],
        ];
    }

    #[Test]
    public function crear_alumno_acepta_formulario_urlencoded(): void
    {
        $grupoId = $this->crearGrupo();
        $respuesta = ApiClient::api('POST', 'tipo=alumnos', [
            'nombre' => 'Alumno Formulario',
            'matricula' => self::$prefijo . '-FORM',
            'grado' => 'Primero',
            'IdGrupos' => $grupoId,
        ], ['content_type' => 'form']);
        $this->alumnosCreados[] = (int) ($respuesta['data']['id'] ?? 0);

        $this->assertSame(201, $respuesta['status']);
    }

    #[Test]
    public function put_actualiza_todos_los_campos(): void
    {
        $alumno = $this->crearAlumno();
        $grupoId = $this->crearGrupo();

        $respuesta = ApiClient::api('PUT', 'tipo=alumnos&id=' . $alumno['id'], [
            'nombre' => 'Nombre Editado',
            'matricula' => $alumno['matricula'],
            'grado' => 'Tercero',
            'IdGrupos' => $grupoId,
        ]);

        $this->assertSame(200, $respuesta['status']);

        $verificacion = ApiClient::api('GET', 'tipo=alumnos&id=' . $alumno['id']);
        $this->assertSame('Nombre Editado', $verificacion['data']['nombre'] ?? null);
        $this->assertSame('Tercero', $verificacion['data']['grado'] ?? null);
        $this->assertSame((string) $grupoId, (string) ($verificacion['data']['IdGrupos'] ?? null));
    }

    #[Test]
    public function patch_actualiza_solo_los_campos_enviados(): void
    {
        $alumno = $this->crearAlumno();

        $respuesta = ApiClient::api('PATCH', 'tipo=alumnos&id=' . $alumno['id'], ['grado' => 'Cuarto']);

        $this->assertSame(200, $respuesta['status']);

        $verificacion = ApiClient::api('GET', 'tipo=alumnos&id=' . $alumno['id']);
        $this->assertSame('Cuarto', $verificacion['data']['grado'] ?? null);
        $this->assertSame($alumno['nombre'], $verificacion['data']['nombre'] ?? null);
        $this->assertSame($alumno['matricula'], $verificacion['data']['matricula'] ?? null);
    }

    #[Test]
    public function actualizar_alumno_inexistente_devuelve_404(): void
    {
        $respuesta = ApiClient::api('PUT', 'tipo=alumnos&id=99999999', [
            'nombre' => 'Nadie',
            'matricula' => 'TST-NADIE',
            'grado' => 'Primero',
            'IdGrupos' => 99999999,
        ]);

        $this->assertSame(404, $respuesta['status']);
    }

    #[Test]
    public function duplicar_alumno_crea_una_copia(): void
    {
        $alumno = $this->crearAlumno();
        $respuesta = ApiClient::api('POST', 'tipo=alumnos&id=' . $alumno['id'] . '&accion=duplicar');
        $copiaId = (int) ($respuesta['data']['id'] ?? 0);
        $this->alumnosCreados[] = $copiaId;

        $this->assertSame(201, $respuesta['status']);
        $this->assertGreaterThan(0, $copiaId);
        $this->assertNotSame($alumno['id'], $copiaId);

        $copia = ApiClient::api('GET', 'tipo=alumnos&id=' . $copiaId);
        $this->assertSame($alumno['nombre'] . ' (copia)', $copia['data']['nombre'] ?? null);
        $this->assertStringStartsWith('COPIA-', (string) ($copia['data']['matricula'] ?? ''));
        $this->assertSame($alumno['grado'], $copia['data']['grado'] ?? null);
        $this->assertSame((string) $alumno['grupoId'], (string) ($copia['data']['IdGrupos'] ?? null));
    }

    #[Test]
    public function duplicar_alumno_inexistente_devuelve_404(): void
    {
        $respuesta = ApiClient::api('POST', 'tipo=alumnos&id=99999999&accion=duplicar');

        $this->assertSame(404, $respuesta['status']);
    }

    #[Test]
    public function duplicar_alumno_sin_id_devuelve_400(): void
    {
        $respuesta = ApiClient::api('POST', 'tipo=alumnos&accion=duplicar');

        $this->assertSame(400, $respuesta['status']);
    }

    #[Test]
    public function eliminar_alumno_lo_quita_de_la_lista(): void
    {
        $alumno = $this->crearAlumno();

        $respuesta = ApiClient::api('DELETE', 'tipo=alumnos&id=' . $alumno['id']);

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame(404, ApiClient::api('GET', 'tipo=alumnos&id=' . $alumno['id'])['status']);
        $this->alumnosCreados = array_values(array_diff($this->alumnosCreados, [$alumno['id']]));
    }

    #[Test]
    public function eliminar_alumno_inexistente_devuelve_404(): void
    {
        $respuesta = ApiClient::api('DELETE', 'tipo=alumnos&id=99999999');

        $this->assertSame(404, $respuesta['status']);
    }

    #[Test]
    public function post_con_method_delete_funciona(): void
    {
        $alumno = $this->crearAlumno();

        $respuesta = ApiClient::api('POST', 'tipo=alumnos&id=' . $alumno['id'] . '&_method=DELETE');

        $this->assertSame(200, $respuesta['status']);
        $this->alumnosCreados = array_values(array_diff($this->alumnosCreados, [$alumno['id']]));
    }

    #[Test]
    public function accion_desconocida_devuelve_404(): void
    {
        $respuesta = ApiClient::api('POST', 'tipo=alumnos&accion=no_existe');

        $this->assertSame(404, $respuesta['status']);
        $this->assertStringContainsString('Acción no encontrada', (string) ($respuesta['data']['mensaje'] ?? ''));
    }

    #[Test]
    public function metodo_no_permitido_devuelve_405(): void
    {
        $respuesta = ApiClient::api('POST', 'tipo=alumnos&_method=INVALIDO');

        $this->assertSame(405, $respuesta['status']);
    }
}
