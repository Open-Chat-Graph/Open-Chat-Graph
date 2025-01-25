<?php

declare(strict_types=1);

use App\Services\Recommend\RecommendUpdater;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

class RecommendUpdaterTest extends TestCase
{
    private RecommendUpdater $inst;

    public function test2()
    {
        MimimalCmsConfig::$urlRoot = '/tw';

        $this->inst = app(RecommendUpdater::class);

        $r = $this->inst->updateRecommendTables();
        debug($r);

        $this->assertTrue(true);
    }
}
