<?php

declare(strict_types=1);

namespace App\Services\RankingPosition;

use App\Config\AppConfig;
use App\Services\RankingPosition\Dto\RankingPositionHourChartDto;
use App\Models\Repositories\RankingPosition\Dto\RankingPositionHourPageRepoDto;
use App\Models\Repositories\RankingPosition\RankingPositionHourPageRepositoryInterface;
use App\Services\OpenChat\Enum\RankingType;

class RankingPositionHourChartArrayService
{
    private const INTERVAL_HOUR = 24;

    function __construct(
        private RankingPositionHourPageRepositoryInterface $rankingPositionHourPageRepository,
    ) {
    }

    function getPositionHourChartArray(RankingType $type, int $open_chat_id, int $category): RankingPositionHourChartDto
    {
        $updatedAt = file_get_contents(getStorageFilePath(AppConfig::STORAGE_FILES['hourlyCronUpdatedAtDatetime']));
        
        $repoDto = $this->rankingPositionHourPageRepository->getHourPosition(
            $type,
            $open_chat_id,
            $category,
            self::INTERVAL_HOUR,
            new \DateTime($updatedAt)
        );

        return $this->generateChartArray($this->generateTimeArray($repoDto->firstTime), $repoDto);
    }

    /**  
     *  @return string[]
     */
    private function generateTimeArray(string $firstTime): array
    {
        $first = new \DateTime($firstTime);

        for ($i = 0; $i <= self::INTERVAL_HOUR; $i++) {
            $timeArray[] = $first->format('Y-m-d H:i:s');
            $first->modify('+1 hour');
        }

        return $timeArray;
    }

    /**
     * @param string[] $timeArray
     */
    private function generateChartArray(array $timeArray, RankingPositionHourPageRepoDto $repoDto): RankingPositionHourChartDto
    {
        $dto = new RankingPositionHourChartDto;

        $getRepoDtoCurTime = fn (int $key): string => isset($repoDto->time[$key]) ? $repoDto->time[$key] : '';

        $curKeyRepoDto = 0;
        $repoDtoCurTime = $getRepoDtoCurTime(0);

        foreach ($timeArray as $key => $time) {
            $timeStr = (new \DateTime($time))->format('m/d H:i');

            if ($repoDtoCurTime !== $time) {
                $dto->addValue(
                    $timeStr,
                    null,
                    !$repoDto->member || $curKeyRepoDto ? 0 : null,
                    null,
                );

                continue;
            }

            $dto->addValue(
                $timeStr,
                $repoDto->member[$curKeyRepoDto],
                $repoDto->position[$curKeyRepoDto],
                $repoDto->totalCount[$curKeyRepoDto],
            );

            $curKeyRepoDto++;
            $repoDtoCurTime = $getRepoDtoCurTime($curKeyRepoDto);
        }

        return $dto;
    }
}
