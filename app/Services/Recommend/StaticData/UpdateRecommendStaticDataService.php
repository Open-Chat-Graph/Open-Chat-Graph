<?php

declare(strict_types=1);

namespace App\Services\Recommend\StaticData;

use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\BatchScript;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\Cron\Utility\CronUtility;
use App\Services\Recommend\RecommendUpdater;
use App\Services\Recommend\TagDefinition\TagMetadata;
use ExceptionHandler\ExceptionHandler;
use Shared\MimimalCmsConfig;

/**
 * おすすめ静的データ生成ジョブ（batch/exec/update_recommend_static_data.php のエントリから起動）。
 *
 * タグ定義JSONの変更検知（変更時は全レコード再適用）→ 差分タグ更新 → 静的データ(.dat)生成
 * → CDN キャッシュ削除までを行う。多重実行時は前回プロセスを kill して引き継ぐ。
 */
class UpdateRecommendStaticDataService
{
    function __construct(
        private SyncOpenChatStateRepositoryInterface $state,
        private RecommendUpdater $recommendUpdater,
        private RecommendStaticDataGenerator $recommendStaticDataGenerator,
        private BatchScriptLauncher $launcher,
    ) {}

    function handle(): void
    {
        try {
            // 既に実行中の場合はkill
            if ($this->state->getBool(StateType::isUpdateRecommendStaticDataActive)) {
                $message = 'おすすめ静的データ生成: 既に実行中のため前回の処理をkill';
                CronUtility::addCronLog($message);
                AdminTool::sendDiscordNotify($message);

                // 自分以外のバックグラウンドプロセスをkill
                CronUtility::addCronLog('kill結果: ' . $this->launcher->killOtherInstances(BatchScript::updateRecommendStaticData));

                $this->state->setFalse(StateType::isUpdateRecommendStaticDataActive);
                sleep(5); // プロセス終了を待つ
            }

            // 実行中フラグを立てる
            $this->state->setTrue(StateType::isUpdateRecommendStaticDataActive);

            // タグ定義JSON(ja.json/th.json/tw.json)に変更があれば全レコードを再適用、無ければ通常の差分更新。
            //  - ja: 無停止シャドウスワップ (rebuildAllViaShadowSwap)
            //  - th/tw: data/{lang}.json を JSON 駆動マッチングで運用するため、定義変更を検知したら
            //        フル再構築 (updateRecommendTables(false))。shadow-swap は ja 専用のため使わない。
            //        これによりデプロイ後やGUI編集後に、CRON が自動で th/tw タグを反映する。
            $didRebuild = false;
            // ja/th/tw とも TagMetadata::jsonPath() でロケール別パスを解決（パス組み立てを一本化）。
            $tagJsonPath = TagMetadata::jsonPath(MimimalCmsConfig::$urlRoot);
            {
                $currentHash = is_file($tagJsonPath) ? hash('sha256', (string)file_get_contents($tagJsonPath)) : '';
                $storedHash = $this->state->getString(StateType::recommendTagsJsonHash);
                if ($currentHash !== '' && $currentHash !== $storedHash) {
                    if ($this->state->getBool(StateType::isRecommendTagRebuildActive)) {
                        CronUtility::addCronLog('タグ定義変更を検知したが再適用が実行中のためスキップ（次回CRONで再試行）');
                    } else {
                        $this->state->setTrue(StateType::isRecommendTagRebuildActive);
                        try {
                            CronUtility::addCronLog('タグ定義の変更を検知 → 全レコード再適用開始 (urlRoot=' . MimimalCmsConfig::$urlRoot . ')');
                            if (MimimalCmsConfig::$urlRoot === '') {
                                $this->recommendUpdater->rebuildAllViaShadowSwap();
                            } else {
                                // th/tw: PRIMARY KEY(id) 付きテーブルへフル再構築（差分でなく全件）
                                $this->recommendUpdater->updateRecommendTables(false);
                            }
                            $this->state->setString(StateType::recommendTagsJsonHash, $currentHash);
                            $didRebuild = true;
                            CronUtility::addCronLog('全レコード再適用 完了');
                        } finally {
                            $this->state->setFalse(StateType::isRecommendTagRebuildActive);
                        }
                    }
                }
            }
            if (!$didRebuild) {
                CronUtility::addVerboseCronLog('おすすめ情報更新中（バックグラウンド）');
                $this->recommendUpdater->updateRecommendTables();
                CronUtility::addVerboseCronLog('おすすめ情報更新完了（バックグラウンド）');
            }

            CronUtility::addVerboseCronLog('おすすめ静的データを生成中（バックグラウンド）');

            $this->recommendStaticDataGenerator->updateStaticData();

            CronUtility::addVerboseCronLog('おすすめ静的データ生成完了（バックグラウンド）');

            // CDNキャッシュ削除
            CronUtility::addVerboseCronLog('CDNキャッシュ削除中（バックグラウンド）');

            $result = purgeCacheCloudFlare(
                files: [
                    ltrim(getSiteDomainUrl(), "/"),
                ],
                prefixes: [
                    getCdnPrefixUrl('recommend'),
                    getCdnPrefixUrl('oc'),
                    getCdnPrefixUrl('ranking'),
                    getCdnPrefixUrl('oclist'),
                    getCdnPrefixUrl('recently-registered'),
                    getCdnPrefixUrl('labs'),
                    getCdnPrefixUrl('policy'),
                    getCdnPrefixUrl('furigana'),
                ]
            );

            CronUtility::addVerboseCronLog($result . '（バックグラウンド）');

            CronUtility::addVerboseCronLog('【毎時処理】バックグラウンド処理完了');
            // 実行中フラグを下ろす
            $this->state->setFalse(StateType::isUpdateRecommendStaticDataActive);
        } catch (\Throwable $e) {
            // エラー時もフラグを下ろす
            $this->state->setFalse(StateType::isUpdateRecommendStaticDataActive);

            CronUtility::addCronLog($e->__toString());
            AdminTool::sendDiscordNotify($e->__toString());
            ExceptionHandler::errorLog($e);
        }
    }
}
