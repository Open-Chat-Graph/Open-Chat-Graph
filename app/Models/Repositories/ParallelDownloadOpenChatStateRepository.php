<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Services\OpenChat\Enum\RankingType;
use App\Models\Repositories\DB;

class ParallelDownloadOpenChatStateRepository implements ParallelDownloadOpenChatStateRepositoryInterface
{
    private function fetchValue(string $column, int $category): int
    {
        return DB::fetchColumn("SELECT {$column} FROM api_data_download_state WHERE category = {$category}");
    }

    private function updateValue(string $column, int $value, int $category): void
    {
        DB::execute("UPDATE api_data_download_state SET {$column} = {$value} WHERE category = {$category}");
    }

    public function isCompleted(RankingType $type, int $category): bool
    {
        return $this->fetchValue($type->value, $category) === 2;
    }

    public function updateComplete(RankingType $type, int $category): void
    {
        $this->updateValue($type->value, 2, $category);
    }

    public function isDownloaded(RankingType $type, int $category): bool
    {
        return $this->fetchValue($type->value, $category) === 1;
    }

    public function updateDownloaded(RankingType $type, int $category): void
    {
        $this->updateValue($type->value, 1, $category);
    }

    public function isCompletedAll(): bool
    {
        return !DB::fetchAll("SELECT * FROM api_data_download_state WHERE NOT rising = 2 OR NOT ranking = 2");
    }

    public function isDownloadedAll(): bool
    {
        return !DB::fetchAll("SELECT * FROM api_data_download_state WHERE NOT rising = 1 OR NOT ranking = 1");
    }

    public function cleanUpAll(): void
    {
        DB::execute("UPDATE api_data_download_state SET ranking = 0, rising = 0");
    }
}
