<?php

declare(strict_types=1);

namespace Escuela\Tests\Support;

use RuntimeException;

/** Cliente cURL mínimo para las pruebas HTTP contra localhost. */
final class Http
{
    /**
     * @param array<int, string> $cabeceras
     *
     * @return array{status: int, data: mixed, raw: string, headers: array<string, string>}
     */
    public static function request(
        string $metodo,
        string $url,
        ?array $cuerpo = null,
        array $cabeceras = [],
        ?string $cookieJar = null,
        string $tipoContenido = 'json'
    ): array {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException('No se pudo crear la petición cURL.');
        }

        $cabecerasPeticion = array_merge(['Accept: application/json, text/html'], $cabeceras);
        $respuestaCabeceras = [];

        if ($cuerpo !== null) {
            $payload = $tipoContenido === 'json'
                ? json_encode($cuerpo, JSON_UNESCAPED_UNICODE)
                : http_build_query($cuerpo);
            $cabecerasPeticion[] = 'Content-Type: '
                . ($tipoContenido === 'json' ? 'application/json' : 'application/x-www-form-urlencoded');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $cabecerasPeticion,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $linea) use (&$respuestaCabeceras): int {
                $partes = explode(':', $linea, 2);

                if (count($partes) === 2) {
                    $respuestaCabeceras[strtolower(trim($partes[0]))] = trim($partes[1]);
                }

                return strlen($linea);
            },
        ]);

        if ($cookieJar !== null) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
        }

        $respuesta = curl_exec($ch);
        $error = curl_error($ch);
        $estado = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($respuesta === false) {
            throw new RuntimeException("No se pudo conectar con {$url}: {$error}");
        }

        return [
            'status' => $estado,
            'data' => json_decode($respuesta, true),
            'raw' => $respuesta,
            'headers' => $respuestaCabeceras,
        ];
    }
}
