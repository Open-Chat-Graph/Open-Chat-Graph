<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\Cron\Utility\CronUtility;
use App\Services\Recommend\RecommendUpdater;
use Shared\MimimalCmsConfig;

set_time_limit(3600 * 10);

$now = date('Y-m-d H:i:s');

try {
    if (isset($argv[1]) && $argv[1]) {
        MimimalCmsConfig::$urlRoot = $argv[1];
    }

    /**
     * @var SyncOpenChatStateRepositoryInterface $state
     */
    $state = app(SyncOpenChatStateRepositoryInterface::class);

    // 二重実行防止: 既に実行中なら何もせず終了
    if ($state->getBool(StateType::isRecommendTagRebuildActive)) {
        $message = 'rebuildAllViaShadowSwap: 既に実行中のためスキップ at ' . $now;
        CronUtility::addCronLog($message);
        AdminTool::sendDiscordNotify($message);
        return;
    }

    $state->setTrue(StateType::isRecommendTagRebuildActive);

    try {
        /**
         * @var RecommendUpdater $recommendUpdater
         */
        $recommendUpdater = app(RecommendUpdater::class);
        AdminTool::sendDiscordNotify('rebuildAllViaShadowSwap start at ' . $now);
        if (MimimalCmsConfig::$urlRoot === '') {
            // base/ja: ロック競合を避ける安全なシャドウスワップ方式
            $recommendUpdater->rebuildAllViaShadowSwap();
        } else {
            // tw/th: recommend系テーブルにユニークキーが無くシャドウ方式が使えないため従来のフル再構築
            $recommendUpdater->updateRecommendTables(false);
        }
        AdminTool::sendDiscordNotify('rebuildAllViaShadowSwap done at ' . $now);
    } finally {
        // 成否に関わらず必ずフラグを下ろす
        $state->setFalse(StateType::isRecommendTagRebuildActive);
    }
} catch (\Throwable $e) {
    CronUtility::addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
    AdminTool::sendDiscordNotify('rebuildAllViaShadowSwap failed ' . $now);
}
