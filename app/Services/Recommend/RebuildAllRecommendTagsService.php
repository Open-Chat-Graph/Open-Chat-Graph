<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\BatchScript;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\Cron\Utility\CronUtility;
use App\Services\Recommend\TagDefinition\TagMetadata;
use Shared\MimimalCmsConfig;

/**
 * おすすめタグの全レコード再適用ジョブ（batch/exec/tag_update.php のエントリから起動）。
 *
 * GUI からの「全レコードに即時反映」で使われ、タグ再構築から静的データ生成・CDN purge までを
 * 一気通貫で実行する。実行中フラグによる二重実行防止と通知を含む。
 */
class RebuildAllRecommendTagsService
{
    function __construct(
        private SyncOpenChatStateRepositoryInterface $state,
        private RecommendUpdater $recommendUpdater,
        private BatchScriptLauncher $launcher,
    ) {}

    function handle(): void
    {
        $now = date('Y-m-d H:i:s');

        try {
            // 二重実行防止: 既に実行中なら何もせず終了
            if ($this->state->getBool(StateType::isRecommendTagRebuildActive)) {
                $message = 'rebuildAllViaShadowSwap: 既に実行中のためスキップ at ' . $now;
                CronUtility::addCronLog($message);
                AdminTool::sendDiscordNotify($message);
                return;
            }

            $this->state->setTrue(StateType::isRecommendTagRebuildActive);

            try {
                AdminTool::sendDiscordNotify('rebuildAllViaShadowSwap start at ' . $now);
                if (MimimalCmsConfig::$urlRoot === '') {
                    // base/ja: ロック競合を避ける安全なシャドウスワップ方式
                    $this->recommendUpdater->rebuildAllViaShadowSwap();
                } else {
                    // tw/th: シャドウスワップは ja 専用（rebuildAllViaShadowSwap が非ja で例外）のため
                    // フル再構築（bulkInsertViaTemp）で全件再適用する。
                    $this->recommendUpdater->updateRecommendTables(false);
                }
                // 適用済みハッシュ(data/{lang}.json)を更新し、毎時CRONの自動検知
                // (update_recommend_static_data.php) が直後に再度フル再適用しないようにする。
                $jsonPath = TagMetadata::jsonPath(MimimalCmsConfig::$urlRoot);
                if (is_file($jsonPath)) {
                    $this->state->setString(StateType::recommendTagsJsonHash, hash('sha256', (string)file_get_contents($jsonPath)));
                }
                AdminTool::sendDiscordNotify('rebuildAllViaShadowSwap done at ' . $now);
            } finally {
                // 成否に関わらず必ずフラグを下ろす
                $this->state->setFalse(StateType::isRecommendTagRebuildActive);
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
            AdminTool::sendDiscordNotify('chain to update_recommend_static_data.php at ' . date('Y-m-d H:i:s'));
            $this->launcher->launchSync(BatchScript::updateRecommendStaticData, MimimalCmsConfig::$urlRoot);
            AdminTool::sendDiscordNotify('update_recommend_static_data.php done at ' . date('Y-m-d H:i:s'));
        } catch (\Throwable $e) {
            CronUtility::addCronLog($e->__toString());
            AdminTool::sendDiscordNotify($e->__toString());
            AdminTool::sendDiscordNotify('rebuildAllViaShadowSwap failed ' . $now);
        }
    }
}
