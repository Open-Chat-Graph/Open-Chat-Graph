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

    function persistStorageFileToDb(): void
    {
        $fileTime = $this->persist();

        $this->rankingPositionHourRepository->insertTotalCount($fileTime);
        addCronLog("毎時ランキングデータを保存（{$fileTime}）");

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
            [$risingFileTime, $risingOcDtoArray] = $this->risingPositionStore->getStorageData((string)$category);
            $risingInsertDtoArray = $this->createInsertDtoArray($risingOcDtoArray);
            unset($risingOcDtoArray);

            $this->rankingPositionHourRepository->insertFromDtoArray(RankingType::Rising, $risingFileTime, $risingInsertDtoArray);
            if ($category === 0) {
                $this->rankingPositionHourRepository->insertHourMemberFromDtoArray($risingFileTime, $risingInsertDtoArray);
            }

            unset($risingInsertDtoArray);
            addCronLog("「急上昇」を保存: {$key}");

            [$rankingFileTime, $rankingOcDtoArray] = $this->rankingPositionStore->getStorageData((string)$category);
            $rankingInsertDtoArray = $this->createInsertDtoArray($rankingOcDtoArray);
            unset($rankingOcDtoArray);

            $this->rankingPositionHourRepository->insertFromDtoArray(RankingType::Ranking, $rankingFileTime, $rankingInsertDtoArray);
            $this->rankingPositionHourRepository->insertHourMemberFromDtoArray($rankingFileTime, $rankingInsertDtoArray);

            unset($rankingInsertDtoArray);
            addCronLog("「ランキング」を保存: {$key}");

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
