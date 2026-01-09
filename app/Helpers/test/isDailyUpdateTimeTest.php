<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

class isDailyUpdateTimeTest extends TestCase
{
    public function test()
    {
        MimimalCmsConfig::$urlRoot = '/tw';

        // /tw: 0:35 〜 1:35 が日次更新時間
        $this->assertTrue(
            isDailyUpdateTime(
                (new DateTime)->setTime(0, 35)  // 開始時刻
            )
        );

        $this->assertFalse(
            isDailyUpdateTime(
                (new DateTime)->setTime(0, 34)  // 開始直前
            )
        );

        $this->assertTrue(
            isDailyUpdateTime(
                (new DateTime)->setTime(1, 20)  // 範囲内
            )
        );

        $this->assertFalse(
            isDailyUpdateTime(
                (new DateTime)->setTime(1, 35)  // 終了時刻（範囲外）
            )
        );
    }
}
