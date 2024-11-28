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
        ini_set('memory_limit', '2G');

        set_exception_handler(function (\Throwable $e) {
            OpenChatApiDbMergerWithParallelDownloader::setKillFlagTrue();
            AdminTool::sendLineNofity($e->__toString());
            addCronLog($e->__toString());
        });
    }

    // 毎時30分に実行
    function handle(bool $dailyTest = false, bool $retryDailyTest = false)
    {
        $this->init();

        if (isDailyUpdateTime() || ($dailyTest && !$retryDailyTest)) {
            // 毎日23:30に実行
            $this->dailyTask();
        } else if ($this->isFailedDailyUpdate() || $retryDailyTest) {
            // 毎日1:30にdailyTaskが実行中の場合は、前日のdailyTaskが失敗したとみなす
            $this->retryDailyTask();
        } else {
            // 23:30を除く毎時30分に実行
            $this->hourlyTask();
        }

        $this->sitemap->generate();
    }

    private function init()
    {
        checkLineSiteRobots();

        if ($this->state->getBool(StateType::isHourlyTaskActive)) {
            AdminTool::sendLineNofity('SyncOpenChat: hourlyTask is active');
            addCronLog('SyncOpenChat: hourlyTask is active');
        }

        if ($this->state->getBool(StateType::isDailyTaskActive)) {
            addCronLog('SyncOpenChat: dailyTask is active');
        }
    }
    
    private function isFailedDailyUpdate(): bool
    {
        return isDailyUpdateTime(new \DateTime('-2 hour'), nowStart: new \DateTime('-1day'), nowEnd: new \DateTime('-1day'))
            && $this->state->getBool(StateType::isDailyTaskActive);
    }

    // 毎時0分に実行
    function handleHalfHourCheck()
    {
        if ($this->state->getBool(StateType::isHourlyTaskActive)) {
            $this->retryHourlyTask();
        } elseif (!$this->rankingPositionHourChecker->isLastHourPersistenceCompleted()) {
            $this->hourlyTaskAfterDbMerge(true);
        }
    }

    private function hourlyTask()
    {
        set_time_limit(1620);

        $this->state->setTrue(StateType::isHourlyTaskActive);
        $this->merger->fetchOpenChatApiRankingAll();
        $this->state->setFalse(StateType::isHourlyTaskActive);

        $this->hourlyTaskAfterDbMerge(
            !$this->rankingPositionHourChecker->isLastHourPersistenceCompleted()
        );
    }

    private function hourlyTaskAfterDbMerge(bool $persistStorageFileToDb)
    {
        $this->executeAndCronLog(
            $persistStorageFileToDb ? [
                fn() => $this->rankingPositionHourPersistence->persistStorageFileToDb(),
                'rankingPositionHourPersistence'
            ] : null,
            [fn() => $this->OpenChatImageUpdater->hourlyImageUpdate(), 'hourlyImageUpdate'],
            [fn() => $this->hourlyMemberColumn->update(), 'hourlyMemberColumnUpdate'],
            [fn() => $this->hourlyMemberRanking->update(), 'hourlyMemberRankingUpdate'],
            [fn() => purgeCacheCloudFlare(), 'purgeCacheCloudFlare'],
            [fn() => $this->invitationTicketUpdater->updateInvitationTicketAll(), 'updateInvitationTicketAll'],
            [fn() => $this->rankingBanUpdater->updateRankingBanTable(), 'updateRankingBanTable'],
            [function () {
                if ($this->state->getBool(StateType::isDailyTaskActive)) {
                    AdminTool::sendLineNofity('hourlyTask: updateRecommendTables is skipped because dailyTask is active');
                    addCronLog('hourlyTask: updateRecommendTables is skipped because dailyTask is active');
                    return;
                }

                $this->recommendUpdater->updateRecommendTables();
            }, 'updateRecommendTables'],
        );
    }

    private function retryHourlyTask()
    {
        addCronLog('Retry hourlyTask');
        AdminTool::sendLineNofity('Retry hourlyTask');
        OpenChatApiDbMergerWithParallelDownloader::setKillFlagTrue();
        OpenChatDailyCrawling::setKillFlagTrue();
        sleep(30);

        $this->handle();
        addCronLog('Done retrying hourlyTask');
        AdminTool::sendLineNofity('Done retrying hourlyTask');
    }

    private function dailyTask()
    {
        $this->state->setTrue(StateType::isDailyTaskActive);
        $this->hourlyTask();

        set_time_limit(5400);

        /** 
         * @var DailyUpdateCronService $updater
         */
        $updater = app(DailyUpdateCronService::class);
        $updater->update(fn() => OpenChatDailyCrawling::setKillFlagTrue());

        $this->executeAndCronLog(
            [fn() => $this->OpenChatImageUpdater->imageUpdateAll(), 'dailyImageUpdate'],
            [fn() => purgeCacheCloudFlare(), 'purgeCacheCloudFlare'],
        );
    }

    private function retryDailyTask()
    {
        addCronLog('Retry dailyTask');
        AdminTool::sendLineNofity('Retry dailyTask');
        OpenChatApiDbMergerWithParallelDownloader::setKillFlagTrue();
        OpenChatDailyCrawling::setKillFlagTrue();
        sleep(30);
        
        $this->dailyTask();
        addCronLog('Done retrying dailyTask');
        AdminTool::sendLineNofity('Done retrying dailyTask');
    }

    /**
     * @param null|array{ 0:callable, 1:string } ...$tasks
     */
    private function executeAndCronLog(null|array ...$tasks)
    {
        foreach ($tasks as $task) {
            if (!$task)
                continue;

            addCronLog('Start ' . $task[1]);
            $task[0]();
            addCronLog('Done ' . $task[1]);
        }
    }
}
