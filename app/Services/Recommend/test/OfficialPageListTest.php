<?php

/**
 * テスト実行コマンド:
 * docker compose exec app vendor/bin/phpunit app/Services/Recommend/test/OfficialPageListTest.php
 */

declare(strict_types=1);

use App\Services\Recommend\OfficialPageList;
use PHPUnit\Framework\TestCase;

class OfficialPageListTest extends TestCase
{
    public function test()
    {
        $res = app(OfficialPageList::class)->getListDto(1);
        debug($res);
        $this->assertTrue(true);
    }
}
