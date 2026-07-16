<?php

declare(strict_types=1);

namespace App\Services\PublicApi\test;

use App\Services\PublicApi\CursorCodec;
use PHPUnit\Framework\TestCase;

final class CursorCodecTest extends TestCase
{
    public function testSignedCursorContainsSnapshotAndRejectsChangedFilters(): void
    {
        $codec = new CursorCodec('test-secret', 60);
        $cursor = $codec->encode(20, 'ja', '2026-07-17 00:00:00', ['q' => 'game'], 100);
        $decoded = $codec->decode($cursor, 'ja', ['q' => 'game'], 120);
        self::assertSame(20, $decoded['offset']);
        self::assertSame('2026-07-17 00:00:00', $decoded['snapshot']);

        $this->expectException(\InvalidArgumentException::class);
        $codec->decode($cursor, 'ja', ['q' => 'other'], 120);
    }

    public function testExpiredOrTamperedCursorIsRejected(): void
    {
        $codec = new CursorCodec('test-secret', 10);
        $cursor = $codec->encode(0, 'th', '2026-07-17 00:00:00', [], 100);
        $this->expectException(\InvalidArgumentException::class);
        $codec->decode($cursor . 'x', 'th', [], 111);
    }
}
