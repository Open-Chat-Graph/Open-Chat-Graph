<?php

declare(strict_types=1);

use App\Services\Recommend\StaticData\RecommendStaticDataGenerator;
use PHPUnit\Framework\TestCase;

class RecommendStaticDataGeneratorTest extends TestCase
{
    private RecommendStaticDataGenerator $inst;

    public function test()
    {
        
        $this->inst = app(RecommendStaticDataGenerator::class);
        $this->inst->updateStaticData();

        $this->assertIsBool(true);
    }
}
