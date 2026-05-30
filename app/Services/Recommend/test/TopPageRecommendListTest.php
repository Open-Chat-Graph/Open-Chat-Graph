<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Recommend/test/TopPageRecommendListTest.php
 */

declare(strict_types=1);

use App\Services\Recommend\TopPageRecommendList;
use PHPUnit\Framework\TestCase;

class TopPageRecommendListTest extends TestCase
{
    private TopPageRecommendList $inst;
    public function test()
    {
        $this->inst = app(TopPageRecommendList::class);
        $r = $this->inst->getList(30);

        debug($r);

        $this->assertTrue(true);
    }
}
