<?php

/**
 * Alpha Labs: alpha_page_jump_daily_ja の派生バックフィル CLI。
 *
 * alpha_room_referrer_daily_ja に存在する全日付（または --from/--to 指定範囲）について
 * AlphaAccessRankingRepository::rebuildPageJumpDaily を走らせ、
 * 既存蓄積データから alpha_page_jump_daily_ja を一括生成する。
 *
 * GA 再取得は不要・DB のみで完結。
 *
 * 使い方:
 *   docker compose exec app php batch/exec/alpha_rebuild_page_jump.php
 *   docker compose exec app php batch/exec/alpha_rebuild_page_jump.php --from=2025-01-01 --to=2025-12-31
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Alpha\AlphaGaSyncService;
use Shared\MimimalCmsConfig;

// Alpha は ja のみ稼働
MimimalCmsConfig::$urlRoot = '';

$from = null;
$to = null;
foreach ($argv as $arg) {
    if (preg_match('/^--from=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
        $from = $m[1];
    } elseif (preg_match('/^--to=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
        $to = $m[1];
    }
}

/** @var AlphaGaSyncService $service */
$service = app(AlphaGaSyncService::class);

$result = $service->rebuildPageJumpRange($from, $to, function (int $done, int $total, string $date, ?string $error) {
    if ($error !== null) {
        echo "  ERROR {$date}: {$error}\n";
    } elseif ($done % 10 === 0) {
        echo "  {$done}/{$total} 完了 (直近: {$date})\n";
    }
});

if ($result['days'] === 0 && $result['errors'] === []) {
    echo "alpha_room_referrer_daily_ja にデータが見つかりませんでした。\n";
    exit(0);
}

$total = $result['days'] + count($result['errors']);
echo "\n完了: {$result['days']}/{$total} 日分を alpha_page_jump_daily_ja に集計しました。\n";

if (!empty($result['errors'])) {
    echo "エラー " . count($result['errors']) . " 件:\n";
    foreach ($result['errors'] as $err) {
        echo "  {$err}\n";
    }
    exit(1);
}
