<?php

declare(strict_types=1);

use App\Services\Recommend\RecommendGenarator;
use PHPUnit\Framework\TestCase;

class RecommendGenaratorTest extends TestCase
{
    private RecommendGenarator $inst;

    public function test()
    {
        $this->inst = app(RecommendGenarator::class);

        $this->inst->getRecomendRanking(2);

        $this->assertTrue(true);
    }
}
