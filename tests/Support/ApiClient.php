<?php

declare(strict_types=1);

namespace Escuela\Tests\Support;

/** Cliente de la API y de las páginas del sistema para los tests de características. */
final class ApiClient
{
    private static function baseUrl(): string
    {
        return rtrim((string) getenv('TEST_BASE_URL'), '/');
    }

    private static function token(): string
    {
        $config = require dirname(__DIR__, 2) . '/config/auth.php';

        return (string) $config['api_token'];
    }

    /**
     * Opciones: token (true|false|string), headers, content_type, cookies.
     *
     * @param array<string, mixed> $opciones
     *
     * @return array{status: int, data: mixed, raw: string, headers: array<string, string>}
     */
    public static function api(
        string $metodo,
        string $consulta = '',
        ?array $cuerpo = null,
        array $opciones = []
    ): array {
        $cabeceras = $opciones['headers'] ?? [];
        $token = $opciones['token'] ?? true;

        if ($token !== false) {
            $valor = is_string($token) ? $token : self::token();
            $cabeceras[] = 'Authorization: Bearer ' . $valor;
        }

        $url = self::baseUrl() . '/endpoints.php' . ($consulta !== '' ? '?' . $consulta : '');

        return Http::request(
            $metodo,
            $url,
            $cuerpo,
            $cabeceras,
            $opciones['cookies'] ?? null,
            (string) ($opciones['content_type'] ?? 'json')
        );
    }

    /**
     * @param array<string, mixed> $opciones
     *
     * @return array{status: int, data: mixed, raw: string, headers: array<string, string>}
     */
    public static function web(string $ruta, array $opciones = []): array
    {
        return Http::request(
            (string) ($opciones['metodo'] ?? 'GET'),
            self::baseUrl() . '/' . ltrim($ruta, '/'),
            $opciones['cuerpo'] ?? null,
            $opciones['headers'] ?? [],
            $opciones['cookies'] ?? null,
            (string) ($opciones['content_type'] ?? 'form')
        );
    }

    public static function tokenPorDefecto(): string
    {
        return self::token();
    }
}
