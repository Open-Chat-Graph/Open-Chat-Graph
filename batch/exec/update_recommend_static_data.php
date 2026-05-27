<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\Cron\Utility\CronUtility;
use App\Services\Recommend\RecommendUpdater;
use App\Services\Recommend\StaticData\RecommendStaticDataGenerator;
use ExceptionHandler\ExceptionHandler;
use Shared\MimimalCmsConfig;

set_time_limit(3600 * 2);

try {
    if (isset($argv[1]) && $argv[1]) {
        MimimalCmsConfig::$urlRoot = $argv[1];
    }

    /**
     * @var SyncOpenChatStateRepositoryInterface $state
     */
    $state = app(SyncOpenChatStateRepositoryInterface::class);

    // 既に実行中の場合はkill
    if ($state->getBool(StateType::isUpdateRecommendStaticDataActive)) {
        $message = 'おすすめ静的データ生成: 既に実行中のため前回の処理をkill';
        CronUtility::addCronLog($message);
        AdminTool::sendDiscordNotify($message);

        // 自分以外のバックグラウンドプロセスをkill
        $myPid = getmypid();
        $cmd = "ps aux | grep update_recommend_static_data.php | grep -v grep | grep -v '{$myPid}' | awk '{print \$2}' | xargs -r kill";
        exec($cmd, $output, $returnCode);
        CronUtility::addCronLog('kill結果: ' . implode(' ', $output) . ' (return code: ' . $returnCode . ')');

        $state->setFalse(StateType::isUpdateRecommendStaticDataActive);
        sleep(5); // プロセス終了を待つ
    }

    // 実行中フラグを立てる
    $state->setTrue(StateType::isUpdateRecommendStaticDataActive);

    /**
     * @var RecommendUpdater $recommendUpdater
     */
    $recommendUpdater = app(RecommendUpdater::class);

    // ja のタグ定義(ja.json)に変更があれば全レコードを無停止で再適用、無ければ通常の差分更新。
    $didRebuild = false;
    if (MimimalCmsConfig::$urlRoot === '') {
        $jsonPath = \App\Services\Recommend\TagDefinition\JaTagMetadata::jsonPath();
        $currentHash = is_file($jsonPath) ? hash('sha256', (string)file_get_contents($jsonPath)) : '';
        $storedHash = $state->getString(StateType::recommendTagsJsonHash);
        if ($currentHash !== '' && $currentHash !== $storedHash) {
            if ($state->getBool(StateType::isRecommendTagRebuildActive)) {
                CronUtility::addCronLog('タグ定義変更を検知したが再適用が実行中のためスキップ（次回CRONで再試行）');
            } else {
                $state->setTrue(StateType::isRecommendTagRebuildActive);
                try {
                    CronUtility::addCronLog('タグ定義(ja.json)の変更を検知 → 全レコード再適用（無停止）開始');
                    $recommendUpdater->rebuildAllViaShadowSwap();
                    $state->setString(StateType::recommendTagsJsonHash, $currentHash);
                    $didRebuild = true;
                    CronUtility::addCronLog('全レコード再適用 完了');
                } finally {
                    $state->setFalse(StateType::isRecommendTagRebuildActive);
                }
            }
        }
    }
    if (!$didRebuild) {
        CronUtility::addVerboseCronLog('おすすめ情報更新中（バックグラウンド）');
        $recommendUpdater->updateRecommendTables();
        CronUtility::addVerboseCronLog('おすすめ情報更新完了（バックグラウンド）');
    }

    /**
     * @var RecommendStaticDataGenerator $recommendStaticDataGenerator
     */
    $recommendStaticDataGenerator = app(RecommendStaticDataGenerator::class);

    CronUtility::addVerboseCronLog('おすすめ静的データを生成中（バックグラウンド）');

    $recommendStaticDataGenerator->updateStaticData();

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
    $state->setFalse(StateType::isUpdateRecommendStaticDataActive);
} catch (\Throwable $e) {
    // エラー時もフラグを下ろす
    if (isset($state)) {
        $state->setFalse(StateType::isUpdateRecommendStaticDataActive);
    }

    CronUtility::addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
    ExceptionHandler::errorLog($e);
}
