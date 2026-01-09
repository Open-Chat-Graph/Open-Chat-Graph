<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Config\AppConfig;
use App\Exceptions\ApplicationException;
use App\Models\Repositories\ParallelDownloadOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\OpenChat\OpenChatApiDataParallelDownloader;
use App\Services\OpenChat\OpenChatApiDbMergerWithParallelDownloader;
use Shared\MimimalCmsConfig;

class ParallelDownloadOpenChat
{
    function __construct(
        private OpenChatApiDataParallelDownloader $downloader,
        private ParallelDownloadOpenChatStateRepositoryInterface $stateRepository
    ) {
        set_time_limit(600);
    }

    /**
     * @param array{ type: string, category: int }[] $args
     */
    function handle(array $args)
    {
        try {
            foreach ($args as $api) {
                $type = RankingType::from($api['type']);
                $category = $api['category'];
                $this->download($type, $category);
            }
        } catch (ApplicationException $e) {
            $this->handleDetectStopFlag($args, $e);
        } catch (\Throwable $e) {
            $this->handleGeneralException($api['type'] ?? null, $api['category'] ?? null, $e);
        }
    }

    private function download(RankingType $type, int $category)
    {
        $categoryStr = array_flip(AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot])[$category];
        $typeStr = $type->value;

        $typeLabel = $typeStr === 'rising' ? '急上昇' : 'メンバー数';
        addCronLog("ダウンロード開始: {$typeLabel}ランキング「{$categoryStr}」");

        $count = $this->downloader->fetchOpenChatApi($type, $category);
        $this->stateRepository->updateDownloaded($type, $category);

        addCronLog("ダウンロード完了: {$typeLabel}ランキング「{$categoryStr}」（{$count}件）");
    }

    private function handleDetectStopFlag(array $args, ApplicationException $e)
    {
        foreach ($args as $api) {
            $type = $api['type'];
            $category = array_flip(AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot])[$api['category']] ?? 'UNDIFINED';
            addCronLog("ParallelDownloadOpenChat {$type} {$category}: " . $e->getMessage());
        }
    }

    private function handleGeneralException(string|null $type, int|null $category, \Throwable $e)
    {
        // 全てのダウンロードプロセスを強制終了する
        OpenChatApiDbMergerWithParallelDownloader::setKillFlagTrue();

        $categoryStr = $category !== null ? (array_flip(AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot])[$category] ?? $category) : 'null';
        $typeStr = $type ?? 'null';
        $error = "ParallelDownloadOpenChat {$typeStr} {$categoryStr}: " . $e->__toString();

        AdminTool::sendDiscordNotify($error);
        addCronLog($error);
    }
}
