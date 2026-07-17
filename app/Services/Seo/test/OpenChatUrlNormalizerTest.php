<?php

declare(strict_types=1);

namespace App\Services\Seo\test;

use App\Services\Seo\OpenChatUrlNormalizer;
use PHPUnit\Framework\TestCase;

final class OpenChatUrlNormalizerTest extends TestCase
{
    public function testMovesValidGraphStateToFragment(): void
    {
        self::assertSame('/oc/123#graph?limit=hour', OpenChatUrlNormalizer::normalizeRequestUri('/oc/123?limit=hour'));
        self::assertSame(
            '/tw/oc/123#graph?limit=all&bar=ranking&category=in&chart=candlestick',
            OpenChatUrlNormalizer::normalizeRequestUri('/tw/oc/000123?limit=all&bar=ranking&category=in&chart=candlestick')
        );
    }

    public function testKeepsTrackingAndDropsUnknownOrInvalidParameters(): void
    {
        self::assertSame(
            '/th/oc/9?utm_source=newsletter&gclid=abc#graph?limit=week',
            OpenChatUrlNormalizer::normalizeRequestUri('/th/oc/09?utm_source=newsletter&gclid=abc&limit=week&bar=bad&secret=1')
        );
    }

    public function testCanonicalRequestAndNonRoomOrPostDoNotRedirect(): void
    {
        self::assertNull(OpenChatUrlNormalizer::normalizeRequestUri('/oc/123?utm_source=x'));
        self::assertNull(OpenChatUrlNormalizer::normalizeRequestUri('/ranking?limit=hour'));
        self::assertNull(OpenChatUrlNormalizer::normalizeRequestUri('/oc/123?limit=hour', 'POST'));
    }
}
