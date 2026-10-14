<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class HealthTest extends TestCase
{
    public static function healthPaths(): array
    {
        return [['/health'], ['/api/v1/health']];
    }

    #[DataProvider('healthPaths')]
    public function test_health_contract_is_public_and_uses_the_real_http_kernel(string $path): void
    {
        $this->getJson($path)->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_an_unknown_resource_uses_the_contract_error_shape(): void
    {
        $this->getJson('/api/v1/unknown')->assertNotFound()->assertExactJson([
            'code' => 'NOT_FOUND', 'message' => 'No se encontró el recurso.', 'details' => [],
        ]);
    }
}
