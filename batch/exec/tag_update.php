<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\Recommend\RebuildAllRecommendTagsService;
use Shared\MimimalCmsConfig;

set_time_limit(3600 * 10);

(new BatchScriptLauncher)->run(function () use ($argv) {
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

    /**
     * @var RebuildAllRecommendTagsService $service
     */
    $service = app(RebuildAllRecommendTagsService::class);
    $service->handle($cancelPrevious);
});
