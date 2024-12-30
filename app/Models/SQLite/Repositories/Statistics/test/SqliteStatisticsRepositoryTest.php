<?php

declare(strict_types=1);

use App\Models\SQLite\Repositories\Statistics\SqliteStatisticsRepository;
use App\Services\DailyUpdateCronService;
use PHPUnit\Framework\TestCase;
use App\Models\Repositories\DB;

class DailyUpdateCronServiceTest extends TestCase
{
    private DailyUpdateCronService $inst;

    function test()
    {
        $this->inst = app(DailyUpdateCronService::class);

        $res = $this->inst->getTargetOpenChatIdArray();

        debug(count($res));

        $this->assertIsBool(true);
    }
}