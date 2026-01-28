<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Config\AppConfig;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\Cron\Utility\CronUtility;
use App\Services\OpenChat\OpenChatApiDbMerger;
use App\Services\DailyUpdateCronService;
use App\Services\OpenChat\OpenChatDailyCrawling;
use App\Services\OpenChat\OpenChatHourlyInvitationTicketUpdater;
use App\Services\RankingBan\RankingBanTableUpdater;
use App\Services\RankingPosition\Persistence\RankingPositionHourPersistence;
use App\Services\SitemapGenerator;
use App\Services\UpdateHourlyMemberColumnService;
use App\Services\UpdateHourlyMemberRankingService;
use Shared\MimimalCmsConfig;

class SyncOpenChat
{
    function __construct(
        private OpenChatApiDbMerger $merger,
        private SitemapGenerator $sitemap,
        private RankingPositionHourPersistence $rankingPositionHourPersistence,
        private UpdateHourlyMemberRankingService $hourlyMemberRanking,
        private UpdateHourlyMemberColumnService $hourlyMemberColumn,
        private OpenChatHourlyInvitationTicketUpdater $invitationTicketUpdater,
        private RankingBanTableUpdater $rankingBanUpdater,
        private SyncOpenChatStateRepositoryInterface $state,
    ) {
        ini_set('memory_limit', '2G');
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
            CronUtility::addCronLog('[警告] 毎時処理が実行中または中断のためリトライ処理を開始します。');
            OpenChatApiDbMerger::setKillFlagTrue();
            sleep(5);
        }

        if ($this->state->getBool(StateType::isDailyTaskActive)) {
            CronUtility::addCronLog('日次処理が実行中です');
        }
    }

    private function isFailedDailyUpdate(): bool
    {
        return $this->state->getBool(StateType::isDailyTaskActive);
    }

    private function hourlyTask()
    {
        CronUtility::addCronLog('【毎時処理】開始');

        set_time_limit(3600);

        // バックグラウンドでDB反映を開始
        $this->rankingPositionHourPersistence->startBackgroundPersistence();

        // ダウンロード処理（バックグラウンドと並列実行）
        $this->state->setTrue(StateType::isHourlyTaskActive);
        $this->merger->fetchOpenChatApiRankingAll();
        $this->state->setFalse(StateType::isHourlyTaskActive);

        // バックグラウンドDB反映の完了を待機
        $this->rankingPositionHourPersistence->waitForBackgroundCompletion();

        $this->hourlyTaskAfterDbMerge();

        CronUtility::addCronLog('【毎時処理】完了');
    }

    private function hourlyTaskAfterDbMerge()
    {
        $this->executeAndCronLog(
            // 毎時ランキングDB反映はバックグラウンドバッチに移行（persist_ranking_position_background.php）
            [fn() => $this->hourlyMemberColumn->update(), '毎時メンバーカラム更新'],
            [fn() => $this->hourlyMemberRanking->update(), '毎時メンバーランキング関連の処理'],
            // CDNキャッシュ削除はバックグラウンドバッチに移行（update_recommend_static_data.php）
            [function () {
                if ($this->state->getBool(StateType::isUpdateInvitationTicketActive)) {
                    // 既に実行中の場合は1回だけスキップする
                    CronUtility::addCronLog('参加URL取得をスキップ（実行中のため）');
                    // スキップした場合は、次回実行時に実行するようにする
                    $this->state->setFalse(StateType::isUpdateInvitationTicketActive);
                    return;
                }

                $this->state->setTrue(StateType::isUpdateInvitationTicketActive);
                $this->invitationTicketUpdater->updateInvitationTicketAll();
                $this->state->setFalse(StateType::isUpdateInvitationTicketActive);
            }, '参加URL一括取得'],
            [fn() => $this->rankingBanUpdater->updateRankingBanTable(), 'ランキングBAN情報更新'],
        );

        // アーカイブ用DBインポート処理をバックグラウンドで実行（日本のみ）
        if (!MimimalCmsConfig::$urlRoot) {
            $path = AppConfig::ROOT_PATH . 'batch/exec/ocreview_api_data_import_background.php';
            exec(PHP_BINARY . " {$path} >/dev/null 2>&1 &");
            CronUtility::addVerboseCronLog('アーカイブ用DBインポート処理をバックグラウンドで開始');
        }
    }

    private function dailyTask()
    {
        CronUtility::addCronLog('【日次処理】開始');

        $this->state->setTrue(StateType::isDailyTaskActive);
        $this->hourlyTask();

        set_time_limit(5400);

        /**
         * @var DailyUpdateCronService $updater
         */
        $updater = app(DailyUpdateCronService::class);
        $updater->update(fn() => $this->state->setFalse(StateType::isDailyTaskActive));

        $this->executeAndCronLog(
            [
                function () {
                    $result = purgeCacheCloudFlare(
                        prefixes: [
                            getSiteDomainUrl('oc'),
                            getSiteDomainUrl('ranking'),
                            getSiteDomainUrl('oclist'),
                        ]
                    );
                    CronUtility::addVerboseCronLog($result);
                },
                'CDNキャッシュ削除'
            ],
        );

        CronUtility::addCronLog('【日次処理】完了');
    }

    private function retryDailyTask()
    {
        CronUtility::addCronLog('【日次処理】リトライ開始');
        OpenChatApiDbMerger::setKillFlagTrue();
        OpenChatDailyCrawling::setKillFlagTrue();
        sleep(5);

        $this->dailyTask();
        CronUtility::addCronLog('【日次処理】リトライ完了');
    }

    /**
     * @param null|array{ 0:callable, 1:string } ...$tasks
     */
    private function executeAndCronLog(null|array ...$tasks)
    {
        foreach ($tasks as $task) {
            if (!$task)
                continue;

            CronUtility::addCronLog($task[1] . 'を開始');
            $task[0]();
            CronUtility::addCronLog($task[1] . 'が完了');
        }
    }
}
