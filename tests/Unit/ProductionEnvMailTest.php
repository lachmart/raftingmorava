<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the deployed .env.production template against dev-only mail settings.
 *
 * Shipping Mailpit's SMTP endpoint (127.0.0.1:1025) to the hosting means every send
 * throws a TransportException. With QUEUE_CONNECTION=sync that surfaces as a 500.
 */
class ProductionEnvMailTest extends TestCase
{
    /** @return array<string, string> */
    private function productionEnv(): array
    {
        $path = dirname(__DIR__, 2) . '/.env.production';
        $this->assertFileExists($path, '.env.production is missing.');

        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim(trim($value), '"\'');
        }

        return $values;
    }

    public function test_production_smtp_does_not_point_at_a_local_dev_mailbox(): void
    {
        $env = $this->productionEnv();

        $this->assertSame('smtp', $env['MAIL_MAILER'] ?? null);

        $this->assertNotContains($env['MAIL_HOST'] ?? '', ['127.0.0.1', 'localhost', '::1'],
            'MAIL_HOST still points at the local machine (Mailpit), not a reachable SMTP server.');

        $this->assertNotContains($env['MAIL_PORT'] ?? '', ['1025', '8025'],
            'MAIL_PORT is a Mailpit port; the hosting has nothing listening there.');
    }

    public function test_production_smtp_has_authentication_credentials(): void
    {
        $env = $this->productionEnv();

        foreach (['MAIL_USERNAME', 'MAIL_PASSWORD'] as $key) {
            $this->assertArrayHasKey($key, $env);
            $this->assertNotSame('', $env[$key], "{$key} is empty.");
            $this->assertNotSame('null', $env[$key], "{$key} is literally \"null\" – SMTP auth will be skipped.");
        }
    }
}
