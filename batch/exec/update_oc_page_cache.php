<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\Repositories\OpenChatRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\Cron\Utility\CronUtility;
use App\Services\StaticData\OcPageCacheGenerator;
use ExceptionHandler\ExceptionHandler;
use Shared\MimimalCmsConfig;

/**
 * ルーム個別ページの「分析文/関連ルーム」事前計算キャッシュ(oc_page_cache)を生成する。
 *
 * 使い方:
 *   php batch/exec/update_oc_page_cache.php <urlRoot> [idCsv]
 *     - <urlRoot>: '' | 'tw' | 'th'（保存先SQLite・HTMLの言語を決める）
 *     - [idCsv]  : 省略時は全ルーム(getOpenChatIdAll)をバックフィル。
 *                  カンマ区切りID指定時はそのルームだけ再生成（write-through用）。
 *
 * 出力HTMLは url()/t() が urlRoot に依存するため、必ず対象言語の urlRoot を渡すこと。
 */

set_time_limit(3600 * 6);

try {
    if (isset($argv[1]) && $argv[1]) {
        MimimalCmsConfig::$urlRoot = $argv[1];
    }

    /** @var SyncOpenChatStateRepositoryInterface $state */
    $state = app(SyncOpenChatStateRepositoryInterface::class);

    // 既に実行中なら前回プロセスをkill（多重生成防止）
    if ($state->getBool(StateType::isUpdateOcPageCacheActive)) {
        $myPid = getmypid();
        $cmd = "ps aux | grep update_oc_page_cache.php | grep -v grep | grep -v '{$myPid}' | awk '{print \$2}' | xargs -r kill";
        exec($cmd);
        CronUtility::addCronLog('ページキャッシュ生成: 前回プロセスをkillして再開');
        $state->setFalse(StateType::isUpdateOcPageCacheActive);
        sleep(3);
    }

    $state->setTrue(StateType::isUpdateOcPageCacheActive);

    // 対象ID: 引数指定があればそれ、無ければ全ルーム
    if (isset($argv[2]) && $argv[2] !== '') {
        $ids = array_values(array_filter(array_map('intval', explode(',', $argv[2])), fn($v) => $v > 0));
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
