<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Services\Recommend\RecommendUpdater;
use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

class RecommendUpdaterTest extends TestCase
{
    private RecommendUpdater $inst;

    public function test2()
    {
        MimimalCmsConfig::$urlRoot = '/tw';

        safeFileRewrite(
            AppConfig::getStorageFilePath('tagUpdatedAtDatetime'),
            (new \DateTime)->modify('-1year')->format('Y-m-d H:i:s')
        );

        $this->inst = app(RecommendUpdater::class);

        $r = $this->inst->updateRecommendTables();
        debug($r);

        $this->assertTrue(true);
    }
}
