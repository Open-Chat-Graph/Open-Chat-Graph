<?php

declare(strict_types=1);

namespace App\Services\RankingPosition\Persistence;

use App\Config\AppConfig;
use App\Models\Repositories\OpenChatDataForUpdaterWithCacheRepositoryInterface;
use App\Models\Repositories\RankingPosition\Dto\RankingPositionHourInsertDto;
use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\RankingPosition\Store\RankingPositionStore;
use App\Services\RankingPosition\Store\RisingPositionStore;
use Shared\MimimalCmsConfig;

class RankingPositionHourPersistence
{
    function __construct(
        private OpenChatDataForUpdaterWithCacheRepositoryInterface $openChatDataWithCache,
        private RankingPositionHourRepositoryInterface $rankingPositionHourRepository,
        private RisingPositionStore $risingPositionStore,
        private RankingPositionStore $rankingPositionStore
    ) {
    }

    private function formatElapsedTime(float $startTime): string
    {
        $elapsedSeconds = microtime(true) - $startTime;
        $minutes = (int) floor($elapsedSeconds / 60);
        $seconds = (int) round($elapsedSeconds - ($minutes * 60));
        return $minutes > 0 ? "{$minutes}分{$seconds}秒" : "{$seconds}秒";
    }

    private function getCategoryLabelWithCount(string $categoryName, string $typeLabel, int $count): string
    {
        return "カテゴリ {$categoryName}の{$typeLabel} {$count}件";
    }

    function persistStorageFileToDb(): void
    {
        $fileTime = $this->persist();

        $this->rankingPositionHourRepository->insertTotalCount($fileTime);
        addCronLog("毎時ランキング全データをデータベースに反映完了（{$fileTime}）");

        $deleteTime = new \DateTime($fileTime);
        $deleteTime->modify('- 1day');
        $this->rankingPositionHourRepository->delete($deleteTime);

        $deleteTimeStr = $deleteTime->format('Y-m-d H:i:s');
        addCronLog("古いランキングデータを削除（{$deleteTimeStr}以前）");
    }

    private function persist(): string
    {
        $this->openChatDataWithCache->clearCache();
        $this->openChatDataWithCache->cacheOpenChatData(true);

        $fileTime = '';
        foreach (AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot] as $key => $category) {
            // 急上昇
            $risingStartTime = microtime(true);

            [$risingFileTime, $risingOcDtoArray] = $this->risingPositionStore->getStorageData((string)$category);
            $risingInsertDtoArray = $this->createInsertDtoArray($risingOcDtoArray);
            
            $risingLabel = $this->getCategoryLabelWithCount($key, '急上昇', count($risingInsertDtoArray));
            addVerboseCronLog("{$risingLabel}をデータベースに反映中");
            
            unset($risingOcDtoArray);

            $this->rankingPositionHourRepository->insertFromDtoArray(RankingType::Rising, $risingFileTime, $risingInsertDtoArray);
            if ($category === 0) {
                $this->rankingPositionHourRepository->insertHourMemberFromDtoArray($risingFileTime, $risingInsertDtoArray);
            }

            unset($risingInsertDtoArray);
            addVerboseCronLog("{$risingLabel}をデータベースに反映完了（{$this->formatElapsedTime($risingStartTime)}）");

            // ランキング
            $rankingStartTime = microtime(true);

            [$rankingFileTime, $rankingOcDtoArray] = $this->rankingPositionStore->getStorageData((string)$category);
            $rankingInsertDtoArray = $this->createInsertDtoArray($rankingOcDtoArray);

            $rankingLabel = $this->getCategoryLabelWithCount($key, 'ランキング', count($rankingInsertDtoArray));
            addVerboseCronLog("{$rankingLabel}をデータベースに反映中");

            unset($rankingOcDtoArray);

            $this->rankingPositionHourRepository->insertFromDtoArray(RankingType::Ranking, $rankingFileTime, $rankingInsertDtoArray);
            $this->rankingPositionHourRepository->insertHourMemberFromDtoArray($rankingFileTime, $rankingInsertDtoArray);

            unset($rankingInsertDtoArray);
            addVerboseCronLog("{$rankingLabel}をデータベースに反映完了（{$this->formatElapsedTime($rankingStartTime)}）");

            $fileTime = $rankingFileTime;
        }

        $this->openChatDataWithCache->clearCache();
        return $fileTime;
    }

    /**
     * @param OpenChatDto[] $data 
     * @return RankingPositionHourInsertDto[]
     */
    private function createInsertDtoArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $dto) {
            $id = $this->openChatDataWithCache->getOpenChatIdByEmid($dto->emid);
            if (!$id) {
                continue;
            }

            $result[] = new RankingPositionHourInsertDto(
                $id,
                $key + 1,
                $dto->category ?? 0,
                $dto->memberCount
            );
        }

        return $result;
    }
}
