<?php

declare(strict_types=1);

namespace App\Services\RankingPosition;

use App\Config\AppConfig;
use App\Services\RankingPosition\Dto\RankingPositionChartDto;
use App\Models\Repositories\RankingPosition\Dto\RankingPositionPageRepoDto;
use App\Models\Repositories\RankingPosition\RankingPositionPageRepositoryInterface;
use App\Services\OpenChat\Enum\RankingType;
use Shared\MimimalCmsConfig;

class RankingPositionChartArrayService
{
    private const SUBSTR_YMD_LEN = 10;
    private const SUBSTR_HI_OFFSET = 11;
    private const SUBSTR_HI_LEN = 5;
    private const START_DATE = '2024-01-19';
    private const MAX_RETRIES = 5;

    function __construct(
        private RankingPositionPageRepositoryInterface $rankingPositionPageRepository,
    ) {}

    function getRankingPositionChartArray(
        RankingType $type,
        int $open_chat_id,
        int $category,
        \DateTime $startDate,
        \DateTime $endDate
    ): RankingPositionChartDto {
        return $this->generateChartArray(
            $this->generateDateArray($startDate, $endDate),
            $startDate,
            $this->getDailyPositionWithRetry($type, $open_chat_id, $category),
            $type === RankingType::Rising
        );
    }

    private function getDailyPositionWithRetry(
        RankingType $type,
        int $open_chat_id,
        int $category,
        int $maxRetries = self::MAX_RETRIES
    ): RankingPositionPageRepoDto {
        $attempts = 0;

        while ($attempts < $maxRetries) {
            try {
                return $this->rankingPositionPageRepository->getDailyPosition($type, $open_chat_id, $category);
            } catch (\PDOException $e) {
                if (strpos($e->getMessage(), 'database disk image is malformed') === false) {
                    throw $e;
                }

                usleep(100000); // Wait for 0.1 seconds
                $attempts++;
            }
        }

        throw new \RuntimeException("Failed to get daily position after {$maxRetries} attempts.");
    }

    /**
     *  @return string[]
     */
    private function generateDateArray(\DateTime $startDate, \DateTime $endDate): array
    {
        $first = new \DateTime($startDate->format('Y-m-d'));
        $interval = $first->diff($endDate)->days;

        $dateArray = [];
        $i = 0;

        while ($i <= $interval) {
            $dateArray[] = $first->format('Y-m-d');
            $first->modify('+1 day');
            $i++;
        }

        return $dateArray;
    }

    /**
     * @param string[] $dateArray
     * @param array{ date:string, member:int }[] $memberStats
     */
    private function generateChartArray(array $dateArray, \DateTime $startDate, RankingPositionPageRepoDto $repoDto, bool $includeTime): RankingPositionChartDto
    {
        $dto = new RankingPositionChartDto;
        $startTime = strtotime($startDate->format('Y-m-d'));

        $getRepoDtoCurDate = fn(int $key): string => isset($repoDto->time[$key]) ? substr($repoDto->time[$key], 0, self::SUBSTR_YMD_LEN) : '';
        $getIsBeforeStartDate = fn(string $date) => strtotime($date) < strtotime(self::START_DATE) || strtotime($date) < $startTime;

        $curKeyRepoDto = 0;
        $repoDtoCurDate = $getRepoDtoCurDate(0);
        $isBeforeStartDate = true;

        foreach ($dateArray as $date) {
            if ($isBeforeStartDate) {
                $isBeforeStartDate = $getIsBeforeStartDate($date);
            }

            $matchRepoDto = $repoDtoCurDate === $date;

            $dto->addValue(
                false,
                false,
                $matchRepoDto && $includeTime ? substr(
                    MimimalCmsConfig::$urlRoot === ''
                        ? $repoDto->time[$curKeyRepoDto]
                        : (new \DateTime($repoDto->time[$curKeyRepoDto], new \DateTimeZone('Asia/Tokyo')))
                        ->setTimezone(new \DateTimeZone(AppConfig::DATE_TIME_ZONE[MimimalCmsConfig::$urlRoot]))
                        ->format('Y-m-d H:i:s'),
                    self::SUBSTR_HI_OFFSET,
                    self::SUBSTR_HI_LEN
                ) : null,
                $matchRepoDto ? $repoDto->position[$curKeyRepoDto] : ($isBeforeStartDate ? null : 0),
                $matchRepoDto ? $repoDto->totalCount[$curKeyRepoDto] : null,
            );

            if ($matchRepoDto) {
                $curKeyRepoDto++;
                $repoDtoCurDate = $getRepoDtoCurDate($curKeyRepoDto);
            }
        }

        return $dto;
    }
}
