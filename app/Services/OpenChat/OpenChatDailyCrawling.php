<?php

declare(strict_types=1);

namespace App\Services\OpenChat;

use App\Config\AppConfig;
use App\Exceptions\ApplicationException;
use App\Models\Repositories\Log\LogRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Utility\ErrorCounter;
use App\Services\OpenChat\Updater\OpenChatUpdaterFromApi;
use App\Services\Crawler\CrawlerFactory;
use App\Services\Cron\Enum\SyncOpenChatStateType;

class OpenChatDailyCrawling
{
    // interval for checking kill flag
    const CHECK_KILL_FLAG_INTERVAL = 3;

    private string $startTime;

    function __construct(

        private OpenChatUpdaterFromApi $openChatUpdater,
        private LogRepositoryInterface $logRepository,
        private ErrorCounter $errorCounter,
        private SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository,
    ) {}

    /**
     * @param int[] $openChatIdArray
     * @throws \RuntimeException
     */
    function crawling(array $openChatIdArray, ?int $intervalSecond = null): int
    {
        // 実行開始日時を記録し、この日時をkillフラグの値として設定
        $this->startTime = date('Y-m-d H:i:s');
        $this->syncOpenChatStateRepository->setString(
            SyncOpenChatStateType::openChatDailyCrawlingKillFlag,
            $this->startTime
        );

        foreach ($openChatIdArray as $key => $id) {
            if ($key % self::CHECK_KILL_FLAG_INTERVAL === 0) {
                $this->checkKillFlag($key);
            }

            $result = $this->openChatUpdater->fetchUpdateOpenChat($id);

            if ($result === false) {
                $this->errorCounter->increaseCount();
            } else {
                $this->errorCounter->resetCount();
            }

            if ($this->errorCounter->hasExceededMaxErrors()) {
                $message = '連続エラー回数が上限を超えました ' . $this->logRepository->getRecentLog();
                throw new \RuntimeException($message);
            }

            if ($intervalSecond) {
                CrawlerFactory::sleepInIntervalWithElapsedTime($intervalSecond);
            }
        }

        return count($openChatIdArray);
    }

    private function checkKillFlag(int $key): void
    {
        $killFlagTime = $this->syncOpenChatStateRepository->getString(SyncOpenChatStateType::openChatDailyCrawlingKillFlag);

        // killフラグの時刻が自分の開始時刻より新しい場合、より新しいリトライが開始されたので終了
        if ($killFlagTime !== '' && $killFlagTime > $this->startTime) {
            throw new ApplicationException((string)$key, AppConfig::DAILY_UPDATE_EXCEPTION_ERROR_CODE);
        }
    }

    /**
     * 現在日時をkillフラグに設定して、既存のcrawling処理を停止させる
     */
    static function setKillFlagTrue()
    {
        /** @var SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository */
        $syncOpenChatStateRepository = app(SyncOpenChatStateRepositoryInterface::class);
        $syncOpenChatStateRepository->setString(
            SyncOpenChatStateType::openChatDailyCrawlingKillFlag,
            date('Y-m-d H:i:s')
        );
    }
}
