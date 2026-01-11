<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Services\Cron\Enum\SyncOpenChatStateType;

interface SyncOpenChatStateRepositoryInterface
{
    /**レコードが存在しない場合はfalseを返す */
    public function getBool(SyncOpenChatStateType $type): bool;
    public function setTrue(SyncOpenChatStateType $type): void;
    public function setFalse(SyncOpenChatStateType $type): void;
    /** レコードが存在しない場合は空文字を返す */
    public function getString(SyncOpenChatStateType $type): string;
    public function setString(SyncOpenChatStateType $type, string $value): void;
    /** レコードが存在しない場合は空配列を返す */
    public function getArray(SyncOpenChatStateType $type): array;
    public function setArray(SyncOpenChatStateType $type, array $value): void;
}
