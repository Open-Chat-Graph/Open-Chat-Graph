<?php

/**
 * Alpha 通知/アラートの毎時算出バッチ。
 *
 * 既存の毎時クロール（cron_crawling.php / SyncOpenChat::hourlyTask）の「後に」呼ばれ、
 * 確定済みの statistics_ranking_hour と open_chat を参照して全ユーザーの
 * ウォッチ（キーワード新規検出 / 部屋人数±%± / マイリスト%±）を算出し、
 * alpha_notification_ja に保存する。
 *
 * ja（urlRoot==''）専用。引数なしで起動する（cron_crawling.php 末尾から
 * バックグラウンド起動される想定）。エラーは Discord 通知してクロール本体は止めない。
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Alpha\AlphaAlertService;
use App\Services\Alpha\AlphaPushService;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Utility\CronUtility;
use ExceptionHandler\ExceptionHandler;
use Shared\MimimalCmsConfig;

// Alpha は ja のみ稼働
MimimalCmsConfig::$urlRoot = '';

try {
    /** @var AlphaAlertService $service */
    $service = app(AlphaAlertService::class);
    $result = $service->run();

    // 新規通知が入ったユーザーへ Web Push tickle を送る（失敗しても通知算出は成功扱い）
    $pushLog = '';
    if (!empty($result['notifiedUserIds'])) {
        try {
            /** @var AlphaPushService $push */
            $push = app(AlphaPushService::class);
            $pushResult = $push->notifyUsers($result['notifiedUserIds']);
            if ($pushResult['subscriptions'] > 0) {
                $pushLog = ' push=' . $pushResult['sent'] . '/' . $pushResult['subscriptions']
                    . ($pushResult['removed'] > 0 ? ' removed=' . $pushResult['removed'] : '')
                    . ($pushResult['failed'] > 0 ? ' pushFailed=' . $pushResult['failed'] : '');
            }
        } catch (\Throwable $e) {
            ExceptionHandler::errorLog($e);
            $pushLog = ' pushError=' . $e->getMessage();
        }
    }

    CronUtility::addCronLog(
        '【Alpha通知】完了 keywordHits=' . $result['keywordHits']
            . ' movements=' . $result['movements']
            . ' signals=' . ($result['signals'] ?? 0)
            . ' folderAdds=' . ($result['folderAdds'] ?? 0)
            . $pushLog
            . (empty($result['errors']) ? '' : ' errors=' . implode(' | ', $result['errors']))
    );
} catch (\Throwable $e) {
    ExceptionHandler::errorLog($e);
    $message = CronUtility::addCronLog('【Alpha通知】失敗: ' . $e->getMessage());
    AdminTool::sendDiscordNotify($message);
}
