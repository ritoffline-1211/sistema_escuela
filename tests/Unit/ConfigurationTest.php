<?php

declare(strict_types=1);

namespace Escuela\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    #[Test]
    public function entorno_de_pruebas_esta_forzado(): void
    {
        $this->assertSame('testing', getenv('APP_ENV'));
        $this->assertSame('testing', getenv('DB_NAME'));
        $this->assertSame('test', getenv('DB_USER'));
        $this->assertSame('test', getenv('DB_PASS'));
    }

    #[Test]
    public function configuracion_de_base_de_datos_apunta_a_la_db_desechable(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';

        $this->assertSame('127.0.0.1', $config['host']);
        $this->assertSame('testing', $config['name']);
        $this->assertSame('test', $config['user']);
        $this->assertSame('test', $config['pass']);
    }

    #[Test]
    public function configuracion_de_autenticacion_expone_token_y_sesion(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/auth.php';

        $this->assertIsString($config['api_token']);
        $this->assertNotSame('', $config['api_token']);
        $this->assertSame('escuela_sesion', $config['session_name']);
    }
}
