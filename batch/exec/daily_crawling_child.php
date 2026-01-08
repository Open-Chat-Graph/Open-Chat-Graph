<?php

/**
 * デイリークローリング子プロセス
 *
 * 親プロセス（OpenChatDailyCrawlingParallel）から呼び出され、
 * 割り当てられたOpenChat IDのリストを順次クローリングする
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Admin\AdminTool;
use App\Services\OpenChat\Updater\OpenChatUpdaterFromApi;
use App\Models\Repositories\Log\LogRepositoryInterface;
use App\Services\Utility\ErrorCounter;
use App\Services\Crawler\CrawlerFactory;
use Shared\MimimalCmsConfig;

try {
    // 引数チェック
    if (!isset($argv[1]) || !isset($argv[2])) {
        throw new \InvalidArgumentException('引数が不足しています');
    }

    // URL Rootの設定
    if ($argv[1]) {
        MimimalCmsConfig::$urlRoot = $argv[1];
    }

    // クローリング対象のIDリストをデコード
    $openChatIdArray = unserialize(base64_decode($argv[2]));
    if (!is_array($openChatIdArray)) {
        throw new \InvalidArgumentException('IDリストのデコードに失敗しました');
    }

    $processIndex = isset($argv[3]) ? (int)$argv[3] : 0;

    addCronLog("DailyCrawlingChild[{$processIndex}] start: " . count($openChatIdArray) . " items");

    // サービスの取得
    /** @var OpenChatUpdaterFromApi $openChatUpdater */
    $openChatUpdater = app(OpenChatUpdaterFromApi::class);

    /** @var LogRepositoryInterface $logRepository */
    $logRepository = app(LogRepositoryInterface::class);

    /** @var ErrorCounter $errorCounter */
    $errorCounter = app(ErrorCounter::class);

    // クローリング実行
    $successCount = 0;
    foreach ($openChatIdArray as $id) {
        $result = $openChatUpdater->fetchUpdateOpenChat($id);

        if ($result === false) {
            $errorCounter->increaseCount();
        } else {
            $errorCounter->resetCount();
            $successCount++;
        }

        // 連続エラー上限チェック
        if ($errorCounter->hasExceededMaxErrors()) {
            $message = "DailyCrawlingChild[{$processIndex}]: 連続エラー回数が上限を超えました " . $logRepository->getRecentLog();
            throw new \RuntimeException($message);
        }

        // API負荷軽減のための待機（100ms）
        usleep(100000);
    }

    addCronLog("DailyCrawlingChild[{$processIndex}] done: {$successCount}/" . count($openChatIdArray));
    exit(0);

} catch (\Throwable $e) {
    $errorMessage = "DailyCrawlingChild error: " . $e->__toString();
    addCronLog($errorMessage);
    AdminTool::sendDiscordNotify($errorMessage);
    exit(1);
}
