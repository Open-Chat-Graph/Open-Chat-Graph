<?php

declare(strict_types=1);

namespace App\Services\PublicApi\test;

use App\Config\AppConfig;
use App\Services\PublicApi\PublicResourceFactory;
use PHPUnit\Framework\TestCase;

final class OpenApiContractTest extends TestCase
{
    private array $document;

    protected function setUp(): void
    {
        $json = file_get_contents(AppConfig::ROOT_PATH . 'app/OpenApi/openapi.json');
        self::assertIsString($json);
        $this->document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testEveryPublishedEndpointHasAResponseSchema(): void
    {
        self::assertSame('3.1.0', $this->document['openapi']);
        $expected = [
            '/api/v1/rooms/{id}', '/api/v1/rooms', '/api/v1/rankings',
            '/api/v1/themes', '/api/v1/themes/{tag}', '/api/v1/stats',
        ];
        self::assertSame($expected, array_keys($this->document['paths']));

        foreach ($this->document['paths'] as $path => $item) {
            $response = $item['get']['responses']['200'] ?? null;
            self::assertNotNull($response, $path);
            self::assertArrayHasKey('schema', $response['content']['application/json'], $path);
        }
    }

    public function testRoomResourceMatchesContractAndNeverExposesPrivateFields(): void
    {
        $resource = (new PublicResourceFactory())->room([
            'id' => 123,
            'name' => 'Room',
            'description' => 'Description',
            'member' => 50,
            'category' => null,
            'emblem' => 0,
            'join_method_type' => 1,
            'api_created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-07-17 00:00:00',
            'data_updated_at' => '2026-07-17 00:00:00',
            'change_1h' => null,
            'change_24h' => 0,
            'change_7d' => -2,
            // Even if a repository row accidentally contains these, the DTO allow-list drops them.
            'user_id' => 999,
            'ip' => '192.0.2.1',
            'emid' => 'internal',
            'invite_url' => 'https://example.invalid/invite',
            'comment' => 'private',
        ], '2026-07-17 01:00:00');

        $payload = $resource->jsonSerialize();
        $required = $this->document['components']['schemas']['Room']['required'];
        foreach ($required as $key) self::assertArrayHasKey($key, $payload);
        self::assertNull($payload['changes']['one_hour']);
        self::assertSame(0, $payload['changes']['twenty_four_hours']);
        self::assertSame(-2, $payload['changes']['seven_days']);

        $keys = [];
        array_walk_recursive($payload, static function (mixed $value, string|int $key) use (&$keys): void {
            if (is_string($key)) $keys[] = $key;
        });
        foreach (['comment', 'user_id', 'ip', 'emid', 'invite_url'] as $forbidden) {
            self::assertNotContains($forbidden, $keys);
        }
    }
}
