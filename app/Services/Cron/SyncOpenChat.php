<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\OpenChat\OpenChatApiDbMerger;
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
        private OpenChatApiDbMerger $merger,
        private SitemapGenerator $sitemap,
        private RankingPositionHourPersistence $rankingPositionHourPersistence,
        private RankingPositionHourPersistenceLastHourChecker $rankingPositionHourChecker,
        private UpdateHourlyMemberRankingService $hourlyMemberRanking,
        private UpdateHourlyMemberColumnService $hourlyMemberColumn,
        private OpenChatImageUpdater $OpenChatImageUpdater,
        private OpenChatHourlyInvitationTicketUpdater $invitationTicketUpdater,
        private RecommendUpdater $recommendUpdater,
        private RankingBanTableUpdater $rankingBanUpdater,
        private SyncOpenChatStateRepositoryInterface $state,
    ) {
        ini_set('memory_limit', '2G');

        set_exception_handler(function (\Throwable $e) {
            OpenChatApiDbMerger::setKillFlagTrue();
            AdminTool::sendDiscordNotify($e->__toString());
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
            addCronLog('SyncOpenChat: hourlyTask is active');
        }

        if ($this->state->getBool(StateType::isDailyTaskActive)) {
            addCronLog('SyncOpenChat: dailyTask is active');
        }
    }

    private function isFailedDailyUpdate(): bool
    {
        return $this->state->getBool(StateType::isDailyTaskActive);
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
            [function () {
                $saveNextFiltersCache  = !$this->state->getBool(StateType::isDailyTaskActive);
                if (!$saveNextFiltersCache) {
                    addCronLog('Skip saveNextFiltersCache because dailyTask is active');
                }

                $this->hourlyMemberRanking->update($saveNextFiltersCache);
            }, 'hourlyMemberRankingUpdate'],
            [fn() => purgeCacheCloudFlare(), 'purgeCacheCloudFlare'],
            [function () {
                if ($this->state->getBool(StateType::isUpdateInvitationTicketActive)) {
                    // 既に実行中の場合は1回だけスキップする
                    addCronLog('Skip updateInvitationTicketAll because it is active');
                    // スキップした場合は、次回実行時に実行するようにする
                    $this->state->setFalse(StateType::isUpdateInvitationTicketActive);
                    return;
                }

                $this->state->setTrue(StateType::isUpdateInvitationTicketActive);
                $this->invitationTicketUpdater->updateInvitationTicketAll();
                $this->state->setFalse(StateType::isUpdateInvitationTicketActive);
            }, 'updateInvitationTicketAll'],
            [fn() => $this->rankingBanUpdater->updateRankingBanTable(), 'updateRankingBanTable'],
            [function () {
                if ($this->state->getBool(StateType::isDailyTaskActive)) {
                    addCronLog('Skip updateRecommendTables because dailyTask is active');
                    return;
                }

                $this->recommendUpdater->updateRecommendTables();
            }, 'updateRecommendTables'],
        );
    }

    private function retryHourlyTask()
    {
        addCronLog('Retry hourlyTask');
        OpenChatApiDbMerger::setKillFlagTrue();
        sleep(30);

        $this->handle();
        addCronLog('Done retrying hourlyTask');
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
        $updater->update(fn() => $this->state->setFalse(StateType::isDailyTaskActive));

        $this->executeAndCronLog(
            [fn() => $this->OpenChatImageUpdater->imageUpdateAll(), 'dailyImageUpdate'],
            [fn() => purgeCacheCloudFlare(), 'purgeCacheCloudFlare'],
        );
    }

    private function retryDailyTask()
    {
        AdminTool::sendDiscordNotify('Retrying dailyTask');

        addCronLog('Retry dailyTask');
        OpenChatApiDbMerger::setKillFlagTrue();
        OpenChatDailyCrawling::setKillFlagTrue();
        sleep(30);

        $this->dailyTask();
        addCronLog('Done Retry dailyTask');

        AdminTool::sendDiscordNotify('Done retrying dailyTask');
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
