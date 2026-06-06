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

use App\Models\ApiRepositories\Alpha\AlphaAccessRankingRepository;
use App\Models\UserLogRepositories\UserLogDB;
use Shared\MimimalCmsConfig;

// Alpha は ja のみ稼働
MimimalCmsConfig::$urlRoot = '';

$from = null;
$to = null;
foreach ($argv as $arg) {
    if (preg_match('/^--from=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
        $from = $m[1];
    }
    if (preg_match('/^--to=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
        $to = $m[1];
    }
}

// αテーブル（alpha_xxx_ja）はすべて userlog DB（UserLogDB）。
UserLogDB::connect();

// alpha_room_referrer_daily_ja に存在する日付一覧を取得
$whereSql = '';
$params = [];
if ($from !== null) {
    $whereSql .= ' AND `date` >= :from';
    $params['from'] = $from;
}
if ($to !== null) {
    $whereSql .= ' AND `date` <= :to';
    $params['to'] = $to;
}

$dates = UserLogDB::fetchAll(
    "SELECT DISTINCT `date` FROM alpha_room_referrer_daily_ja WHERE 1=1{$whereSql} ORDER BY `date` ASC",
    $params
);

if ($dates === []) {
    echo "alpha_room_referrer_daily_ja にデータが見つかりませんでした。\n";
    exit(0);
}

$repo = new AlphaAccessRankingRepository();
$total = count($dates);
$done = 0;
$errors = [];

foreach ($dates as $row) {
    $date = (string)$row['date'];
    try {
        $repo->rebuildPageJumpDaily($date);
        $done++;
        if ($done % 10 === 0) {
            echo "  {$done}/{$total} 完了 (直近: {$date})\n";
        }
    } catch (\Throwable $e) {
        $errors[] = "{$date}: " . $e->getMessage();
        echo "  ERROR {$date}: " . $e->getMessage() . "\n";
    }
}

echo "\n完了: {$done}/{$total} 日分を alpha_page_jump_daily_ja に集計しました。\n";
if (!empty($errors)) {
    echo "エラー " . count($errors) . " 件:\n";
    foreach ($errors as $err) {
        echo "  {$err}\n";
    }
    exit(1);
}
