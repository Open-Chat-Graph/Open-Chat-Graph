<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\Repositories\MemberChangeFilterCacheRepositoryInterface;
use App\Models\Repositories\OpenChatRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\Cron\Utility\CronUtility;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Services\StaticData\OcPageCacheGenerator;
use ExceptionHandler\ExceptionHandler;
use Shared\MimimalCmsConfig;

/**
 * ルーム個別ページの「分析文/関連ルーム」事前計算キャッシュ(oc_page_cache)を生成する。
 *
 * 使い方:
 *   php batch/exec/update_oc_page_cache.php <urlRoot> [hourly|daily|idCsv]
 *     - <urlRoot>: '' | '/tw' | '/th'（保存先SQLite・HTMLの言語を決める）
 *     - hourly   : 毎時モード。直近1時間でメンバー数が変動した（またはランキングに
 *                  新規掲載された）ルームだけ再生成する（SyncOpenChat毎時処理から起動）。
 *     - daily    : 日次モード。日次クロールと同じ対象（変動・新規・週次更新の部屋）を
 *                  再生成する（SyncOpenChat日次処理から起動）。ランキング外の部屋は
 *                  毎時モードでは拾えないため、これで最長でも週次周期で全部屋が更新される。
 *     - idCsv    : カンマ区切りID指定時はそのルームだけ再生成（write-through用）。
 *     - 省略時   : 全ルーム(getOpenChatIdAll)をバックフィル。
 *
 * 出力HTMLは url()/t() が urlRoot に依存するため、必ず対象言語の urlRoot を渡すこと。
 */

set_time_limit(3600 * 6);

try {
    if (isset($argv[1]) && $argv[1]) {
        MimimalCmsConfig::$urlRoot = $argv[1];
    }

    $mode = $argv[2] ?? '';

    /** @var SyncOpenChatStateRepositoryInterface $state */
    $state = app(SyncOpenChatStateRepositoryInterface::class);

    if ($state->getBool(StateType::isUpdateOcPageCacheActive)) {
        if ($mode === 'hourly' || $mode === 'daily') {
            // 毎時/日次モードは実行中（フルバックフィル等）をkillせず今回をスキップする。
            // フラグも下ろしておく＝プロセス異常終了でフラグだけ残った場合に次回から自走再開できる
            // （実行中プロセスが本当に居れば完走時/エラー時に自分でフラグを下ろすため矛盾しない）。
            CronUtility::addCronLog("ページキャッシュ更新({$mode}): 実行中のためスキップ");
            $state->setFalse(StateType::isUpdateOcPageCacheActive);
            exit;
        }

        // バックフィル時は前回プロセスをkill（多重生成防止）
        $myPid = getmypid();
        $cmd = "ps aux | grep update_oc_page_cache.php | grep -v grep | grep -v '{$myPid}' | awk '{print \$2}' | xargs -r kill";
        exec($cmd);
        CronUtility::addCronLog('ページキャッシュ生成: 前回プロセスをkillして再開');
        $state->setFalse(StateType::isUpdateOcPageCacheActive);
        sleep(3);
    }

    $state->setTrue(StateType::isUpdateOcPageCacheActive);

    // 対象ID: hourly=直近1時間の変動ルーム / daily=日次クロール対象 / idCsv=指定ルーム / 省略=全ルーム
    if ($mode === 'daily') {
        // 日次クロールが使ったフィルター（変動・新規・週次更新）と同じ対象を再生成する。
        // 同一日付キーなら日次処理が保存した .dat キャッシュから読むだけで、
        // 重い statistics クエリは再実行されない（ja は翌日23時まで同一日付になる）。
        /** @var MemberChangeFilterCacheRepositoryInterface $filterRepo */
        $filterRepo = app(MemberChangeFilterCacheRepositoryInterface::class);
        $ids = $filterRepo->getForDaily(OpenChatServicesUtility::getCronModifiedStatsMemberDate());
    } elseif ($mode === 'hourly') {
        /** @var RankingPositionHourRepositoryInterface $hourRepo */
        $hourRepo = app(RankingPositionHourRepositoryInterface::class);

        $curTime = OpenChatServicesUtility::getModifiedCronTime('now');
        $prevTime = (clone $curTime)->modify('-1 hour');

        // 今時間と前時間のスナップショットをPHP側で突き合わせ、メンバー数が変動した
        // ルームと前時間に存在しない（ランキング新規掲載）ルームだけを対象にする。
        $prevMemberMap = array_column($hourRepo->getHourlyMemberColumn($prevTime), 'member', 'open_chat_id');

        $ids = [];
        foreach ($hourRepo->getHourlyMemberColumn($curTime) as $row) {
            $id = (int)$row['open_chat_id'];
            if (!isset($prevMemberMap[$id]) || (int)$prevMemberMap[$id] !== (int)$row['member']) {
                $ids[] = $id;
            }
        }
    } elseif ($mode !== '') {
        $ids = array_values(array_filter(array_map('intval', explode(',', $mode)), fn($v) => $v > 0));
    } else {
        /** @var OpenChatRepositoryInterface $ocRepo */
        $ocRepo = app(OpenChatRepositoryInterface::class);
        $ids = $ocRepo->getOpenChatIdAll();
    }

    /** @var OcPageCacheGenerator $generator */
    $generator = app(OcPageCacheGenerator::class);

    $total = 0;
    // 1チャンクごとにトランザクションでまとめて書き込む（fsync削減）
    foreach (array_chunk($ids, 300) as $chunk) {
        $total += $generator->generateForIds($chunk);
    }

    CronUtility::addVerboseCronLog("ページキャッシュ生成完了（urlRoot=" . MimimalCmsConfig::$urlRoot . " / {$total}件）");

    $state->setFalse(StateType::isUpdateOcPageCacheActive);
} catch (\Throwable $e) {
    if (isset($state)) {
        $state->setFalse(StateType::isUpdateOcPageCacheActive);
    }
    CronUtility::addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
    ExceptionHandler::errorLog($e);
}
