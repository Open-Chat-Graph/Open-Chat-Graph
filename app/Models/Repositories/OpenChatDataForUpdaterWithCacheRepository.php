<?php

declare(strict_types=1);

namespace App\Models\Repositories;

use App\Models\Repositories\DB;
use App\Services\OpenChat\Dto\OpenChatRepositoryDto;
use RuntimeException;

class OpenChatDataForUpdaterWithCacheRepository implements OpenChatDataForUpdaterWithCacheRepositoryInterface
{
    /**
     * @var null|OpenChatRepositoryDto[] $openChatDataCache
     */
    private static ?array $openChatDataCache = null;

    /**
     * @var null|string[] $openChatIdCache
     */
    private static ?array $openChatEmidCache = null;

    /**
     * @var null|int[] $openChatIdCache
     */
    private static ?array $openChatIdCache = null;

    public static function cacheOpenChatData(bool $excludeData = false): void
    {
        $dataColumn = $excludeData
            ? ''
            : ",
                name,
                description,
                img_url,
                member,
                api_created_at,
                category,
                emblem,
                join_method_type,
                url";

        $query =
            "SELECT
                id,
                emid{$dataColumn}
            FROM
                open_chat
            ORDER BY
                id ASC";

        /**
         * @var array{ id: int, emid: string, name: string, description: string, img_url: string, member: string, api_created_at: int|null, category: int|null, emblem: int|null }[] $dataArray
         */
        $dataArray = DB::fetchAll($query);
        if (!$dataArray) {
            self::$openChatDataCache = [];
            self::$openChatIdCache = [];
            self::$openChatEmidCache = [];
            return;
        }

        self::$openChatIdCache = array_column($dataArray, 'id');
        self::$openChatEmidCache = array_column($dataArray, 'emid');

        if ($excludeData) {
            return;
        }

        self::$openChatDataCache = [];
        foreach ($dataArray as $data) {
            self::$openChatDataCache[] = new OpenChatRepositoryDto($data['id'], $data);
        }
    }

    public static function clearCache(): void
    {
        self::$openChatDataCache = null;
        self::$openChatEmidCache = null;
        self::$openChatIdCache = null;
    }

    public function getOpenChatDataById(int $id): OpenChatRepositoryDto|false
    {
        if (!isset(self::$openChatIdCache, self::$openChatDataCache)) {
            $this->cacheOpenChatData();
        }

        $key = array_search($id, self::$openChatIdCache);
        if ($key === false) {
            return false;
        }

        return self::$openChatDataCache[$key];
    }

    public function getOpenChatDataByEmid(string $emid): OpenChatRepositoryDto|false
    {
        if (!isset(self::$openChatEmidCache, self::$openChatDataCache)) {
            $this->cacheOpenChatData();
        }

        $key = array_search($emid, self::$openChatEmidCache);
        if ($key === false) {
            return false;
        }

        return self::$openChatDataCache[$key];
    }

    public function getOpenChatIdByEmid(string $emid): int|false
    {
        if (!isset(self::$openChatIdCache, self::$openChatEmidCache)) {
            $this->cacheOpenChatData();
        }

        $key = array_search($emid, self::$openChatEmidCache);
        if ($key === false) {
            return false;
        }

        return self::$openChatIdCache[$key];
    }
}
