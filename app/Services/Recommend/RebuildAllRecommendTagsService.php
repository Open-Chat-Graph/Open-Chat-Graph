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
 * 一気通貫で実行する。二重実行時の挙動は $cancelPrevious で切り替える（既定は待機）。
 */
class RebuildAllRecommendTagsService
{
    function __construct(
        private SyncOpenChatStateRepositoryInterface $state,
        private RecommendUpdater $recommendUpdater,
        private BatchScriptLauncher $launcher,
    ) {}

    /**
     * @param bool $cancelPrevious 既に実行中だった場合の挙動。
     *   false（既定）: 実行中ならスキップして待機する（前のランの完走を優先）。
     *                  毎時処理など定期トリガでは、反映がトリガ間隔より長くかかっても
     *                  後発が前を kill し続けて永遠に完走しない事態を避けるためこちらを使う。
     *   true: 実行中の前プロセスを kill して後発で再実行する（最新の定義を優先）。
     *         GUI/admin からの手動実行のように、最新の編集を確実に反映したいときに使う。
     */
    function handle(bool $cancelPrevious = false): void
    {
        $now = date('Y-m-d H:i:s');

        try {
            if ($this->state->getBool(StateType::isRecommendTagRebuildActive)) {
                if (!$cancelPrevious) {
                    // 既定（待機）: 実行中なら何もせず終了し、前のランを完走させる。
                    $message = 'rebuildAllViaShadowSwap: 既に実行中のためスキップ at ' . $now;
                    CronUtility::addCronLog($message);
                    AdminTool::sendDiscordNotify($message);
                    return;
                }

                // 後発優先: 実行中の前プロセスを kill して引き継ぐ。
                // タグ反映は時間がかかるため、古い定義のまま走る先行ランをスキップで待つと
                // 最新の編集が黙って取りこぼされる。最新で上書きするため前ランを止めて再実行する。
                // shadow-swap の build テーブルは毎回 DROP→作り直し、RENAME は原子的なので、
                // 先行ランを途中 kill しても live は不整合にならない（本ランが全件作り直す）。
                $message = 'rebuildAllViaShadowSwap: 既に実行中のため前回の処理をkillして再実行 at ' . $now;
                CronUtility::addCronLog($message);
                AdminTool::sendDiscordNotify($message);

                // 自分以外の tag_update.php をkill（killされた側は finally が走らずフラグを
                // 下ろせないため、この後こちらで立て直して所有する）
                CronUtility::addCronLog('kill結果: ' . $this->launcher->killOtherInstances(BatchScript::tagUpdate));
                $this->state->setFalse(StateType::isRecommendTagRebuildActive);
                sleep(5); // プロセス終了を待つ
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
