<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\StaticData\UpdateOcPageCacheService;
use Shared\MimimalCmsConfig;

// ルーム個別ページキャッシュ(oc_page_cache)生成。モード詳細は UpdateOcPageCacheService 参照
set_time_limit(3600 * 6);

if (isset($argv[1]) && $argv[1]) {
    MimimalCmsConfig::$urlRoot = $argv[1];
}

(new BatchScriptLauncher)->run(function () use ($argv) {
    /** @var UpdateOcPageCacheService $service */
    $service = app(UpdateOcPageCacheService::class);
    $service->handle($argv[2] ?? '');
});
