<?php

declare(strict_types=1);

use App\Services\Recommend\RecommendUpdater;
use PHPUnit\Framework\TestCase;

class RecommendUpdaterTest extends TestCase
{
    private RecommendUpdater $inst;

    public function test2()
    {
        $this->inst = app(RecommendUpdater::class);

        $r = $this->inst->getAllTagName();
        debug($r);

        $this->assertTrue(true);
    }
}
