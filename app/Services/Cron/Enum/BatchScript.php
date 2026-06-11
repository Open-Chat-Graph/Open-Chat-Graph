<?php

declare(strict_types=1);

namespace App\Services\Cron\Enum;

use App\Config\AppConfig;

/**
 * PHPコードから起動するバッチスクリプトの一覧。
 *
 * スクリプトのパス解決をここに一元化し、呼び出し側での生パス組み立てを無くす。
 * 起動には BatchScriptLauncher を使うこと。
 */
enum BatchScript: string
{
    case cronCrawling = 'batch/cron/cron_crawling.php';
    case updateOcPageCache = 'batch/exec/update_oc_page_cache.php';
    case updateRecommendStaticData = 'batch/exec/update_recommend_static_data.php';
    case tagUpdate = 'batch/exec/tag_update.php';
    case ocreviewApiDataImportBackground = 'batch/exec/ocreview_api_data_import_background.php';
    case persistRankingPositionBackground = 'batch/exec/persist_ranking_position_background.php';
    case genetopExec = 'batch/exec/admin/genetop_exec.php';
    case rankingBanTest = 'batch/exec/ranking_ban_test.php';
    case retryDailyTask = 'batch/exec/admin/retry_daily_task.php';
    case updateApiDb = 'batch/exec/update_api_db.php';

    /**
     * リポジトリルートからの絶対パス
     */
    public function absolutePath(): string
    {
        return AppConfig::ROOT_PATH . $this->value;
    }

    /**
     * ps コマンドでのプロセス識別に使うファイル名
     */
    public function basename(): string
    {
        return basename($this->value);
    }
}
