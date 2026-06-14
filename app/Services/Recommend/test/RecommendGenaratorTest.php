<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Recommend/test/RecommendGenaratorTest.php
 */

declare(strict_types=1);

use App\Services\Recommend\RecommendGenarator;
use PHPUnit\Framework\TestCase;

class RecommendGenaratorTest extends TestCase
{
    private RecommendGenarator $inst;

    public function test()
    {
        $this->inst = app(RecommendGenarator::class);

        $result = $this->inst->getRecommend('オプチャ サポート', null, null, null);

        $this->assertIsArray($result);
    }
}
