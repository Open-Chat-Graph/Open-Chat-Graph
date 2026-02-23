<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/StaticData/test/StaticDataGeneratorTest.php
 */

declare(strict_types=1);

use App\Services\StaticData\StaticDataGenerator;
use PHPUnit\Framework\TestCase;

class StaticDataGeneratorTest extends TestCase
{
    public function test(): void
    {
        /**
         * @var StaticDataGenerator $ssg
         */
        $ssg = app(StaticDataGenerator::class);
        $ssg->updateStaticData();

        $this->assertTrue(is_object($ssg));
    }

}
