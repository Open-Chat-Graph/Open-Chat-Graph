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
            // 適用済みハッシュを更新（毎時CRONの自動検知が直後に再度フル再適用しないように）
            $jsonPath = \App\Services\Recommend\TagDefinition\JaTagMetadata::jsonPath();
            if (is_file($jsonPath)) {
                $state->setString(StateType::recommendTagsJsonHash, hash('sha256', (string)file_get_contents($jsonPath)));
            }
        } else {
            // tw/th: recommend系テーブルにユニークキーが無くシャドウ方式が使えないため従来のフル再構築
            $recommendUpdater->updateRecommendTables(false);
        }
        AdminTool::sendDiscordNotify('rebuildAllViaShadowSwap done at ' . $now);
    } finally {
        // 成否に関わらず必ずフラグを下ろす
        $state->setFalse(StateType::isRecommendTagRebuildActive);
    }

    // ──────────────────────────────────────────────
    // 続けて静的データ生成 + CDN キャッシュ削除をチェイン実行する。
    // tag rebuild だけだと .dat キャッシュと CDN が古いまま残り、/recommend ページ
    // の見た目は次回毎時 CRON まで更新されない。GUI からの「全レコードに即時反映」が
    // 押されたらここまで一気にやる。
    //
    // ja の場合、上で ja.json のハッシュは更新済みなので
    // update_recommend_static_data.php 側の再構築はスキップされ、
    // 差分の updateRecommendTables → 静的データ生成 → CDN purge のみが動く。
    // tw/th の場合も同様に静的データ生成 → CDN purge が走る。
    // ──────────────────────────────────────────────
    $phpBinary = \App\Config\AppConfig::$phpBinary;
    $staticScript = realpath(__DIR__ . '/update_recommend_static_data.php');
    $urlRootArg = escapeshellarg((string)MimimalCmsConfig::$urlRoot);
    AdminTool::sendDiscordNotify('chain to update_recommend_static_data.php at ' . date('Y-m-d H:i:s'));
    exec("{$phpBinary} {$staticScript} {$urlRootArg}");
    AdminTool::sendDiscordNotify('update_recommend_static_data.php done at ' . date('Y-m-d H:i:s'));
} catch (\Throwable $e) {
    CronUtility::addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
    AdminTool::sendDiscordNotify('rebuildAllViaShadowSwap failed ' . $now);
}
