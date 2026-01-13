<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Crawler;

use App\Config\AppConfig;
use Shared\MimimalCmsConfig;

class OpenChatApiRankingDownloader
{
    function __construct(
        private OpenChatApiDownloaderProcess $openChatApiDownloaderProcess
    ) {
    }

    /**
     * @param \Closure $callback 1件毎にループ内で呼び出すコールバック `$callback(array $apiData, string $category)`
     * @param ?\Closure $callbackByCategoryBefore 1カテゴリーごとに呼び出すコールバック `$callbackByCategoryBefore(string $category): bool`
     * @param ?\Closure $callbackByCategoryAfter 1カテゴリーごとに呼び出すコールバック `$callbackByCategoryAfter(string $category)`
     * 
     * @return array{ count: int, category: string, dateTime: \DateTime }[] 取得済件数とカテゴリ
     * 
     * @throws \RuntimeException
     */
    function fetchOpenChatApiRankingAll(\Closure $callback, ?\Closure $callbackByCategoryBefore, ?\Closure $callbackByCategoryAfter): array
    {
        $result = [];
        foreach (AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] as $key => $category) {
            if ($callbackByCategoryBefore && $callbackByCategoryBefore((string)$category)) {
                continue;
            }

            $count = $this->fetchOpenChatApiRanking((string)$category, $callback);

            if ($callbackByCategoryAfter) {
                $callbackByCategoryAfter((string)$category);
            }

            $result[] = ['count' => $count, 'category' => $key, 'dateTime' => new \DateTime()];
        }

        return $result;
    }

    /**
     * @throws \RuntimeException
     */
    function fetchOpenChatApiRanking(string $category, \Closure $callback): int
    {
        $resultCount = 0;
        $ct = '';

        while ($ct !== false) {
            $response = $this->openChatApiDownloaderProcess->fetchOpenChatApiRankingProcess($category, $ct, $callback);

            if ($response === false) {
                break;
            }

            [$ct, $count] = $response;
            $resultCount += $count;
        }

        return $resultCount;
    }
}
