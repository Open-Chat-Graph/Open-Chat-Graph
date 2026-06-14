<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Services\Admin\AdminTool;
use App\Services\Cron\Utility\CronUtility;
use App\Services\Recommend\StaticData\RecommendStaticDataGenerator;
use App\Services\StaticData\StaticDataGenerator;
use App\Services\StaticData\UpdateOcPageCacheService;
use App\Services\Storage\FileStorageInterface;
use Shared\MimimalCmsConfig;

try {
    set_time_limit(3600);

    if (isset($argv[1]) && $argv[1]) {
        MimimalCmsConfig::$urlRoot = $argv[1];
    }

    /**
     * @var StaticDataGenerator $staticDataGenerator
     * @var RecommendStaticDataGenerator $recommendStaticDataGenerator
     */
    $staticDataGenerator = app(StaticDataGenerator::class);
    $recommendStaticDataGenerator = app(RecommendStaticDataGenerator::class);
    $updateOcPageCacheService = app(UpdateOcPageCacheService::class);

    AdminTool::sendDiscordNotify('staticDataGenerator start');
    $staticDataGenerator->updateStaticData();
    AdminTool::sendDiscordNotify("staticDataGenerator done\nrecommendStaticDataGenerator start");
    $recommendStaticDataGenerator->updateStaticData();
    AdminTool::sendDiscordNotify('recommendStaticDataGenerator done');

    // ルーム個別ページの事前計算キャッシュ（分析文 narrative ＋ グラフ可用性メタ chart_meta）を
    // 全ルーム再生成する。mode='' = 全ルーム(getOpenChatIdAll)。毎時順位は一括集計なので
    // 全件でも MySQL gone away は起きない。これで genetop 実行＝全室の chart_meta が揃い、
    // グラフ初回表示が窓・差分取得の最適化経路に乗る（フォールバックの全件取得を避けられる）。
    AdminTool::sendDiscordNotify('ocPageCache (全件) start');
    $updateOcPageCacheService->handle('');
    AdminTool::sendDiscordNotify('ocPageCache (全件) done');

    touch(app(FileStorageInterface::class)->getStorageFilePath('hourlyCronUpdatedAtDatetime'));
} catch (\Throwable $e) {
    AdminTool::sendDiscordNotify($e->__toString());
    CronUtility::addCronLog($e->__toString());
}
