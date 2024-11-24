<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Accreditation\Recommend\StaticData\AccreditationStaticDataGenerator;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\OpenChat\OpenChatApiDbMergerWithParallelDownloader;
use App\Services\DailyUpdateCronService;
use App\Services\OpenChat\OpenChatDailyCrawling;
use App\Services\OpenChat\OpenChatHourlyInvitationTicketUpdater;
use App\Services\OpenChat\OpenChatImageUpdater;
use App\Services\RankingBan\RankingBanTableUpdater;
use App\Services\RankingPosition\Persistence\RankingPositionHourPersistence;
use App\Services\RankingPosition\Persistence\RankingPositionHourPersistenceLastHourChecker;
use App\Services\Recommend\RecommendUpdater;
use App\Services\SitemapGenerator;
use App\Services\UpdateHourlyMemberColumnService;
use App\Services\UpdateHourlyMemberRankingService;

class SyncOpenChat
{
    function __construct(
        private OpenChatApiDbMergerWithParallelDownloader $merger,
        private SitemapGenerator $sitemap,
        private RankingPositionHourPersistence $rankingPositionHourPersistence,
        private RankingPositionHourPersistenceLastHourChecker $rankingPositionHourChecker,
        private UpdateHourlyMemberRankingService $hourlyMemberRanking,
        private UpdateHourlyMemberColumnService $hourlyMemberColumn,
        private OpenChatImageUpdater $OpenChatImageUpdater,
        private OpenChatHourlyInvitationTicketUpdater $invitationTicketUpdater,
        private RecommendUpdater $recommendUpdater,
        private RankingBanTableUpdater $rankingBanUpdater,
        private AccreditationStaticDataGenerator $acrreditationCacheUpdater,
        private SyncOpenChatStateRepositoryInterface $state,
    ) {
        set_exception_handler($this->exceptionHandler(...));
    }

    private function isFailedDailyUpdate(): bool
    {
        return isDailyUpdateTime(new \DateTime('-2 hour'), nowStart: new \DateTime('-1day'), nowEnd: new \DateTime('-1day'))
            && $this->state->getBool(StateType::isDailyTaskActive);
    }

    private function init()
    {
        checkLineSiteRobots();

        if ($this->state->getBool(StateType::isHourlyTaskActive)) {
            AdminTool::sendLineNofity('SyncOpenChat: hourlyTask is active');
            addCronLog('SyncOpenChat: hourlyTask is active');
        }

        if ($this->state->getBool(StateType::isDailyTaskActive)) {
            AdminTool::sendLineNofity('SyncOpenChat: dailyTask is active');
            addCronLog('SyncOpenChat: dailyTask is active');
        }
    }

    function handle(bool $dailyTest = false, bool $retryDailyTest = false)
    {
        $this->init();

        if (isDailyUpdateTime() || ($dailyTest && !$retryDailyTest)) {
            $this->dailyTask();
        } else if ($this->isFailedDailyUpdate() || $retryDailyTest) {
            addCronLog('Retry dailyTask');
            AdminTool::sendLineNofity('Retry dailyTask');
            OpenChatApiDbMergerWithParallelDownloader::setKillFlagTrue();
            OpenChatDailyCrawling::setKillFlagTrue();
            sleep(30);
            $this->dailyTask();
            AdminTool::sendLineNofity('Done retrying dailyTask');
        } else {
            $this->hourlyTask();
        }

        $this->sitemap->generate();
    }

    function hourlyTask()
    {
        $this->state->setTrue(StateType::isHourlyTaskActive);

        set_time_limit(1620);
        $this->merger->fetchOpenChatApiRankingAll();

        $this->state->setFalse(StateType::isHourlyTaskActive);

        if (!$this->rankingPositionHourChecker->isLastHourPersistenceCompleted()) {
            $this->executeAndCronLog([
                fn() => $this->rankingPositionHourPersistence->persistStorageFileToDb(),
                'persistStorageFileToDb'
            ]);
        }

        $this->executeAndCronLog(
            [fn() => $this->OpenChatImageUpdater->hourlyImageUpdate(), 'hourlyImageUpdate'],
            [fn() => $this->hourlyMemberColumn->update(), 'hourlyMemberColumnUpdate'],
            [fn() => $this->hourlyMemberRanking->update(), 'hourlyMemberRankingUpdate'],
            [fn() => purgeCacheCloudFlare(), 'purgeCacheCloudFlare'],
            [fn() => $this->invitationTicketUpdater->updateInvitationTicketAll(), 'updateInvitationTicketAll'],
            [fn() => $this->rankingBanUpdater->updateRankingBanTable(), 'updateRankingBanTable'],
            [fn() => $this->recommendUpdater->updateRecommendTables(), 'updateRecommendTables'],
        );
    }

    function dailyTask()
    {
        $this->state->setTrue(StateType::isDailyTaskActive);
        $this->hourlyTask();

        set_time_limit(5400);

        /** @var DailyUpdateCronService $updater */
        $updater = app(DailyUpdateCronService::class);
        $updater->update();

        $this->state->setFalse(StateType::isDailyTaskActive);

        $this->executeAndCronLog(
            [fn() => $this->OpenChatImageUpdater->imageUpdateAll(), 'dailyImageUpdate'],
            [fn() => purgeCacheCloudFlare(), 'purgeCacheCloudFlare'],
        );
    }

    function handleHalfHourCheck()
    {
        if ($this->state->getBool(StateType::isHourlyTaskActive)) {
            addCronLog('Retry hourlyTask');
            OpenChatApiDbMergerWithParallelDownloader::setKillFlagTrue();
            OpenChatDailyCrawling::setKillFlagTrue();
            sleep(20);
            $this->handle();
            return;
        }

        if (!$this->rankingPositionHourChecker->isLastHourPersistenceCompleted()) {
            $this->executeAndCronLog(
                [
                    fn() => $this->rankingPositionHourPersistence->persistStorageFileToDb(),
                    'Retry persistStorageFileToDb'
                ],
                [fn() => $this->hourlyMemberColumn->update(), 'Retry hourlyMemberColumnUpdate'],
                [fn() => $this->hourlyMemberRanking->update(), 'Retry hourlyMemberRankingUpdate'],
                [fn() => purgeCacheCloudFlare(), 'Retry purgeCacheCloudFlare'],
            );

            return;
        }
    }
    
    /**
     * @param array{ 0:callable, 1:string } ...$tasks
     */
    private function executeAndCronLog(array ...$tasks)
    {
        foreach ($tasks as $task) {
            addCronLog('Start ' . $task[1]);
            $task[0]();
            addCronLog('Done ' . $task[1]);
        }
    }

    private function exceptionHandler(\Throwable $e)
    {
        OpenChatApiDbMergerWithParallelDownloader::setKillFlagTrue();
        AdminTool::sendLineNofity($e->__toString());
        addCronLog($e->__toString());
    }
}
