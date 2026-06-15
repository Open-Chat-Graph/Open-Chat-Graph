<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Models\Repositories\DB;
use App\Models\Repositories\OcSitemapLastmodRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Enum\BatchScript;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\Cron\Utility\BatchScriptLauncher;
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
    /** 毎時処理がDB接続障害で落ちた場合の再試行回数（初回実行を除く） */
    private const HOURLY_RETRY_LIMIT = 3;

    /** 毎時処理を再試行する前の待機秒数 */
    private const HOURLY_RETRY_INTERVAL_SEC = 60;

    function __construct(
        private OpenChatApiDbMerger $merger,
        private SitemapGenerator $sitemap,
        private RankingPositionHourPersistence $rankingPositionHourPersistence,
        private UpdateHourlyMemberRankingService $hourlyMemberRanking,
        private UpdateHourlyMemberColumnService $hourlyMemberColumn,
        private OpenChatHourlyInvitationTicketUpdater $invitationTicketUpdater,
        private RankingBanTableUpdater $rankingBanUpdater,
        private SyncOpenChatStateRepositoryInterface $state,
        private OcSitemapLastmodRepositoryInterface $lastmodRepo,
        private BatchScriptLauncher $batchScriptLauncher,
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
            $this->hourlyTaskWithRetry();
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

    /**
     * 毎時処理を実行する。MySQLの瞬断（サーバ再起動・接続断・接続数スパイク）で落ちた場合は、
     * 次の毎時cron（約1時間後）を待たずに、同一プロセス内で少し待って毎時処理を丸ごと再試行する。
     * これにより、DBが短時間だけ落ちても、その時間帯のデータ欠損を防ぐ。
     *
     * 再試行のたびに init() を通すことで、中断した前回分の残骸（isHourlyTaskActive フラグ・
     * 残存バックグラウンドDB反映プロセス）を掃除してからやり直す。これは次の毎時cronが行う
     * 復帰処理（init → hourlyTask）と同一であり、実績のある経路にそのまま乗せている。
     * 毎時処理は冪等で、再実行するとその時間帯のスナップショットを取り直す。
     *
     * 接続障害以外の例外は再試行せず即座に投げ直す（恒久的な不具合を握りつぶさないため）。
     */
    private function hourlyTaskWithRetry()
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                // 2回目以降は前回の残骸を掃除してからやり直す（初回は handle() で init 済み）
                if ($attempt > 0) {
                    $this->init();
                }

                $this->hourlyTask();
                return;
            } catch (\Throwable $e) {
                if ($attempt >= self::HOURLY_RETRY_LIMIT || !DB::isConnectionException($e)) {
                    throw $e;
                }

                CronUtility::addCronLog(sprintf(
                    '[警告] 毎時処理がDB接続障害で中断。%d秒後に再試行します（%d/%d回目）: %s',
                    self::HOURLY_RETRY_INTERVAL_SEC,
                    $attempt + 1,
                    self::HOURLY_RETRY_LIMIT,
                    $e->getMessage(),
                ));

                sleep(self::HOURLY_RETRY_INTERVAL_SEC);
            }
        }
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

        // ルーム個別ページの分析文キャッシュ(oc_page_cache)を毎時更新する。
        // 直近1時間でメンバー数が変動したルームだけを対象にバックグラウンドで再生成
        // （実行中のバックフィルがあればバッチ側でスキップされる）。
        $this->batchScriptLauncher->launchInBackground(BatchScript::updateOcPageCache, MimimalCmsConfig::$urlRoot, 'hourly');
        CronUtility::addVerboseCronLog('ページキャッシュ毎時更新をバックグラウンドで開始');

        // アーカイブ用DBインポート処理をバックグラウンドで実行（日本のみ）
        if (!MimimalCmsConfig::$urlRoot) {
            $this->batchScriptLauncher->launchInBackground(BatchScript::ocreviewApiDataImportBackground);
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
            // 全 member 確定後に sitemap lastmod を最新化。直後 (handle 末尾) の
            // sitemap->generate() がこの lastmod を読む。
            [fn() => $this->lastmodRepo->refreshLastmod(), 'sitemap lastmod 更新'],
            [
                function () {
                    $result = purgeCacheCloudFlare(
                        prefixes: [
                            getCdnPrefixUrl('oc'),
                            getCdnPrefixUrl('ranking'),
                            getCdnPrefixUrl('oclist'),
                        ]
                    );
                    CronUtility::addVerboseCronLog($result);
                },
                'CDNキャッシュ削除'
            ],
        );

        // 日次クロール対象（変動・新規・週次更新の部屋）のページキャッシュを再生成する。
        // ランキング外の部屋は毎時フックでは拾えないため、日次クロールと同じ対象を追従させる
        // （週次更新部屋も含むため、全部屋のキャッシュが最長でも約1週間周期で更新される）。
        $this->batchScriptLauncher->launchInBackground(BatchScript::updateOcPageCache, MimimalCmsConfig::$urlRoot, 'daily');
        CronUtility::addVerboseCronLog('ページキャッシュ日次更新をバックグラウンドで開始');

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
