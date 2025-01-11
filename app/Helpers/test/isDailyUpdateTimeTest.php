<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shared\MimimalCmsConfig;

class isDailyUpdateTimeTest extends TestCase
{
    public function test()
    {
        MimimalCmsConfig::$urlRoot = '/tw';

        $this->assertTrue(
            isDailyUpdateTime(
                (new DateTime)->setTime(0, 30)
            )
        );

        $this->assertFalse(
            isDailyUpdateTime(
                (new DateTime)->setTime(0, 20)
            )
        );

        $this->assertTrue(
            isDailyUpdateTime(
                (new DateTime)->setTime(1, 20)
            )
        );
    }
}
