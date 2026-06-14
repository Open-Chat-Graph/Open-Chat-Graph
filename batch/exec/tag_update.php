<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Admin\AdminTool;
use App\Services\Cron\Utility\CronUtility;
use App\Services\Recommend\RebuildAllRecommendTagsService;
use Shared\MimimalCmsConfig;

set_time_limit(3600 * 10);

// 引数: 位置引数 = urlRoot（ロケール, '' なら ja）/ フラグ --cancel-previous。
// --cancel-previous を付けたときだけ、実行中の前プロセスを kill して再実行する。
// 付けない既定では実行中ならスキップして待機する（定期トリガで永遠に kill され続けないように）。
$cancelPrevious = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--cancel-previous') {
        $cancelPrevious = true;
    } elseif ($arg !== '') {
        MimimalCmsConfig::$urlRoot = $arg;
    }
}

try {
    /**
     * @var RebuildAllRecommendTagsService $service
     */
    $service = app(RebuildAllRecommendTagsService::class);
    $service->handle($cancelPrevious);
} catch (\Throwable $e) {
    // サービス解決自体の失敗（デプロイ中のクラス入れ替わり等）に備えた最終防衛のみ
    CronUtility::addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
}
