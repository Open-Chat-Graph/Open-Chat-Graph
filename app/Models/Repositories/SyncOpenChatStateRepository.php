<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Services\Cron\Enum\SyncOpenChatStateType;
use Shadow\DB;

class SyncOpenChatStateRepository implements SyncOpenChatStateRepositoryInterface
{
    public function getBool(SyncOpenChatStateType $type): bool
    {
        return !!DB::fetchColumn(
            "SELECT bool FROM sync_open_chat_state WHERE type = :type",
            ['type' => $type->value]
        );
    }

    public function setTrue(SyncOpenChatStateType $type): void
    {
        DB::execute(
            "UPDATE sync_open_chat_state SET bool = 1 WHERE type = :type",
            ['type' => $type->value]
        );
    }

    public function setFalse(SyncOpenChatStateType $type): void
    {
        DB::execute(
            "UPDATE sync_open_chat_state SET bool = 0 WHERE type = :type",
            ['type' => $type->value]
        );
    }
}
