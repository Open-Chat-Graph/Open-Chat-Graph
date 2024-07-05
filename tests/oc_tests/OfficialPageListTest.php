<?php

declare(strict_types=1);

use App\Services\Recommend\OfficialPageList;
use PHPUnit\Framework\TestCase;

class OfficialPageListTest extends TestCase
{
    public function test()
    {
        $res = app(OfficialPageList::class)->getListDto();
        debug($res);
        $this->assertTrue(true);
    }
}
