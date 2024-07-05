<?php

declare(strict_types=1);

use App\Services\StaticData\StaticDataGenerator;
use PHPUnit\Framework\TestCase;

class StaticDataGenerationTest extends TestCase
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
