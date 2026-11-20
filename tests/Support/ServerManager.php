<?php

declare(strict_types=1);

namespace Escuela\Tests\Support;

use RuntimeException;

/**
 * Arranca (si hace falta) el servidor PHP embebido usado por los tests HTTP.
 * Solo escucha en loopback: no se hacen llamadas a servicios externos.
 */
final class ServerManager
{
    /** @var resource|null */
    private static $proceso = null;

    private static string $archivoLog;

    public static function ensureRunning(string $url): void
    {
        if (self::isUp($url)) {
            return;
        }

        $partes = parse_url($url);
        $host = (string) ($partes['host'] ?? '127.0.0.1');
        $puerto = (int) ($partes['port'] ?? 80);

        if (!in_array($host, ['127.0.0.1', 'localhost'], true) || $puerto !== 8000) {
            throw new RuntimeException(
                "TEST_BASE_URL debe apuntar al servidor local 127.0.0.1:8000. Recibido: {$url}"
            );
        }

        self::$archivoLog = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'escuela_phpunit_server.log';
        $comando = escapeshellarg(PHP_BINARY)
            . ' -S ' . escapeshellarg($host . ':' . $puerto)
            . ' -t ' . escapeshellarg(dirname(__DIR__, 2));

        $descriptores = [
            0 => ['pipe', 'r'],
            1 => ['file', self::$archivoLog, 'a'],
            2 => ['file', self::$archivoLog, 'a'],
        ];

        // bypass_shell evita que quede un proceso hijo huérfano en Windows.
        $proceso = proc_open($comando, $descriptores, $pipes, null, null, ['bypass_shell' => true]);

        if (!is_resource($proceso)) {
            throw new RuntimeException('No se pudo iniciar el servidor PHP embebido.');
        }

        fclose($pipes[0]);
        self::$proceso = $proceso;

        // Espera de arranque de infraestructura (no se usa dentro de los tests).
        for ($intento = 0; $intento < 100; $intento++) {
            if (self::isUp($url)) {
                return;
            }

            usleep(50_000);
        }

        throw new RuntimeException(
            'El servidor PHP embebido no respondió. Revisa ' . self::$archivoLog
        );
    }

    public static function isUp(string $url): bool
    {
        $ch = curl_init(rtrim($url, '/') . '/index.php');

        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 800,
            CURLOPT_CONNECTTIMEOUT_MS => 800,
            CURLOPT_NOBODY => true,
        ]);

        curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);

        return $error === 0;
    }

    public static function stop(): void
    {
        if (self::$proceso === null) {
            return;
        }

        $proceso = self::$proceso;
        proc_terminate($proceso);
        proc_close($proceso);
        self::$proceso = null;
    }
}
