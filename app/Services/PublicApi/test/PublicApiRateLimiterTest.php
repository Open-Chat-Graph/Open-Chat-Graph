<?php

declare(strict_types=1);

namespace App\Services\PublicApi\test;

use App\Services\PublicApi\PublicApiRateLimiter;
use PHPUnit\Framework\TestCase;

final class PublicApiRateLimiterTest extends TestCase
{
    public function testReturnsRetryAfterAtLimitAndResets(): void
    {
        $dir = sys_get_temp_dir() . '/ocg-rate-test-' . bin2hex(random_bytes(4));
        $limiter = new PublicApiRateLimiter($dir);
        self::assertNull($limiter->hit('client', 2, 60, 100));
        self::assertNull($limiter->hit('client', 2, 60, 100));
        self::assertSame(60, $limiter->hit('client', 2, 60, 100));
        self::assertNull($limiter->hit('client', 2, 60, 161));
        @unlink($dir . '/' . hash('sha256', 'client') . '.json');
        @rmdir($dir);
    }
}
