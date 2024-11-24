<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Services\Cron\Enum\SyncOpenChatStateType;

interface SyncOpenChatStateRepositoryInterface
{
    public function getBool(SyncOpenChatStateType $type): bool;
    public function setTrue(SyncOpenChatStateType $type): void;
    public function setFalse(SyncOpenChatStateType $type): void;
}
