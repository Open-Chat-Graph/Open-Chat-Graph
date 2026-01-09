<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Services\Cron\Enum\SyncOpenChatStateType;
use App\Models\Repositories\DB;

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
            "INSERT INTO sync_open_chat_state (type, bool, extra) VALUES (:type, 1, '')
             ON DUPLICATE KEY UPDATE bool = 1",
            ['type' => $type->value]
        );
    }

    public function setFalse(SyncOpenChatStateType $type): void
    {
        DB::execute(
            "INSERT INTO sync_open_chat_state (type, bool, extra) VALUES (:type, 0, '')
             ON DUPLICATE KEY UPDATE bool = 0",
            ['type' => $type->value]
        );
    }

    public function getString(SyncOpenChatStateType $type): string
    {
        $result = DB::fetchColumn(
            "SELECT extra FROM sync_open_chat_state WHERE type = :type",
            ['type' => $type->value]
        );

        return is_string($result) ? $result : '';
    }

    public function setString(SyncOpenChatStateType $type, string $value): void
    {
        DB::execute(
            "INSERT INTO sync_open_chat_state (type, bool, extra) VALUES (:type, 0, :value)
             ON DUPLICATE KEY UPDATE extra = :value",
            ['type' => $type->value, 'value' => $value]
        );
    }
}
