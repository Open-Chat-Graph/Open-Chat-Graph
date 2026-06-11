<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Admin\AdminTool;
use App\Services\Cron\Utility\CronUtility;
use App\Services\StaticData\UpdateOcPageCacheService;
use Shared\MimimalCmsConfig;

// ルーム個別ページキャッシュ(oc_page_cache)生成。モード詳細は UpdateOcPageCacheService 参照
set_time_limit(3600 * 6);

if (isset($argv[1]) && $argv[1]) {
    MimimalCmsConfig::$urlRoot = $argv[1];
}

try {
    /** @var UpdateOcPageCacheService $service */
    $service = app(UpdateOcPageCacheService::class);
    $service->handle($argv[2] ?? '');
} catch (\Throwable $e) {
    // サービス解決自体の失敗（デプロイ中のクラス入れ替わり等）に備えた最終防衛のみ
    CronUtility::addCronLog($e->__toString());
    AdminTool::sendDiscordNotify($e->__toString());
}
