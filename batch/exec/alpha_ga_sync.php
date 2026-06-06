<?php

/**
 * Alpha Labs: 部屋別アクセス/検索流入の日次同期バッチ（エントリポイント）。
 *
 * 本体ロジックは AlphaGaSyncService、SQL は AlphaGaSyncRepository に分離。
 * creds 未投入時の挙動・投入後の実行手順は AlphaGaSyncService のクラスdoc参照。
 *
 * 引数:
 *   --days=N                 取得する遡及日数（既定 AlphaGaSyncService::DEFAULT_DAYS_BACK）。前日を終端として N 日分。
 *   --from=Y-m-d --to=Y-m-d  期間を明示指定（両方指定時のみ有効。--days より優先）。
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Config\SecretsConfig;
use App\Services\Alpha\AlphaGaSyncService;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Utility\CronUtility;
use ExceptionHandler\ExceptionHandler;
use Shared\MimimalCmsConfig;

// Alpha は ja のみ稼働
MimimalCmsConfig::$urlRoot = '';

// creds 未設定なら何もせず正常終了（本番を一切触らない）
if (!SecretsConfig::isGoogleAnalyticsConfigured()) {
    CronUtility::addCronLog('【Alpha GA同期】スキップ: GA4/GSC creds 未設定');
    exit(0);
}

try {
    $daysBack = AlphaGaSyncService::DEFAULT_DAYS_BACK;
    $from = null;
    $to = null;
    foreach ($argv as $arg) {
        if (preg_match('/^--days=(\d+)$/', $arg, $m)) {
            $daysBack = max(1, (int)$m[1]);
        } elseif (preg_match('/^--from=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
            $from = $m[1];
        } elseif (preg_match('/^--to=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
            $to = $m[1];
        }
    }

    /** @var AlphaGaSyncService $service */
    $service = app(AlphaGaSyncService::class);
    $result = $service->sync($daysBack, $from, $to);

    CronUtility::addCronLog(
        "【Alpha GA同期】完了 period={$result['startDate']}〜{$result['endDate']} upserts={$result['upserts']}"
            . (empty($result['errors']) ? '' : ' errors=' . implode(' | ', $result['errors']))
    );

    // 部分エラー（特定日のAPI失敗）は Discord に通知だけして全体は成功扱い
    if (!empty($result['errors'])) {
        AdminTool::sendDiscordNotify('【Alpha GA同期】部分エラー: ' . implode(' | ', $result['errors']));
    }
} catch (\Throwable $e) {
    ExceptionHandler::errorLog($e);
    $message = CronUtility::addCronLog('【Alpha GA同期】失敗: ' . $e->getMessage());
    AdminTool::sendDiscordNotify($message);
    exit(1);
}
