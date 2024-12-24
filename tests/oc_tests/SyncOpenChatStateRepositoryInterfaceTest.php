<?php

declare(strict_types=1);

use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use PHPUnit\Framework\TestCase;

class SyncOpenChatStateRepositoryInterfaceTest extends TestCase
{
    private SyncOpenChatStateRepositoryInterface $inst;

    public function test()
    {
        $this->inst = app(SyncOpenChatStateRepositoryInterface::class);

        $r = $this->inst->getBool(SyncOpenChatStateType::isDailyTaskActive);
        debug($r);

        $this->inst->setTrue(SyncOpenChatStateType::isDailyTaskActive);
        $r = $this->inst->getBool(SyncOpenChatStateType::isDailyTaskActive);
        debug($r);

        $this->inst->setFalse(SyncOpenChatStateType::isDailyTaskActive);
        $r = $this->inst->getBool(SyncOpenChatStateType::isDailyTaskActive);
        debug($r);

        $this->assertTrue(true);
    }
}
