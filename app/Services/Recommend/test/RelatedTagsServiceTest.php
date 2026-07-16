<?php

declare(strict_types=1);

namespace App\Services\Recommend\test;

use App\Models\RecommendRepositories\RecommendRankingRepository;
use App\Services\Recommend\RelatedTagsService;
use PHPUnit\Framework\TestCase;

final class RelatedTagsServiceTest extends TestCase
{
    public function testNormalizesCooccurrenceByTagVolume(): void
    {
        $repository = $this->createMock(RecommendRankingRepository::class);
        $repository->method('getRelatedTagPairs')->willReturn([
            ['tag' => 'A', 'related' => 'generic', 'cnt' => 20],
            ['tag' => 'A', 'related' => 'niche', 'cnt' => 10],
            ['tag' => 'B', 'related' => 'generic', 'cnt' => 1000],
        ]);

        $related = (new RelatedTagsService($repository))->build();

        self::assertGreaterThan(
            $related['A']['generic'],
            $related['A']['niche'],
            'A smaller but specific cooccurrence should outrank a generic high-volume tag.',
        );
        self::assertSame($related['A']['niche'], $related['niche']['A']);
    }
}
