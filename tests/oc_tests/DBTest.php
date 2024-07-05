<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Models\Repositories\RankingPosition\Dto\RankingPositionHourMemberDto;
use App\Models\Repositories\Statistics\StatisticsRepositoryInterface;
use App\Models\SQLite\SQLiteRankingPositionHour;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Services\StaticData\StaticTopPageDataGenerator;
use PHPUnit\Framework\TestCase;
use Shadow\DB;

class DBTest extends TestCase
{
    private StatisticsRepositoryInterface $statisticsRepository;

    public function test()
    {
        $this->statisticsRepository = app(StatisticsRepositoryInterface::class);

        $dateTime = new \DateTime('now');

        saveSerializedFile(
            AppConfig::OPEN_CHAT_HOUR_FILTER_ID_DIR,
            $this->statisticsRepository->getHourMemberChangeWithinLastWeekArray($dateTime->format('Y-m-d')),
            true
        );
    } 
}
