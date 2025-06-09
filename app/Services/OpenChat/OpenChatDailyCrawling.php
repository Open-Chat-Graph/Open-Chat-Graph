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
        $this->setKillFlagFalse();

        foreach ($openChatIdArray as $key => $id) {
            if ($key % self::CHECK_KILL_FLAG_INTERVAL === 0) {
                $this->checkKillFlag();
            }

            $result = $this->openChatUpdater->fetchUpdateOpenChat($id);

            if ($result === false) {
                $this->errorCounter->increaseCount();
            } else {
                $this->errorCounter->resetCount();
            }

            if ($this->errorCounter->hasExceededMaxErrors()) {
                $message = 'crawlingProcess: 連続エラー回数が上限を超えました ' . $this->logRepository->getRecentLog();
                throw new \RuntimeException($message);
            }

            if ($intervalSecond) {
                CrawlerFactory::sleepInIntervalWithElapsedTime($intervalSecond);
            }
        }

        return count($openChatIdArray);
    }

    private function checkKillFlag()
    {
        $this->syncOpenChatStateRepository->getBool(SyncOpenChatStateType::openChatDailyCrawlingKillFlag)
            && throw new ApplicationException('OpenChatDailyCrawling: 強制終了しました', AppConfig::DAILY_UPDATE_EXCEPTION_ERROR_CODE);
    }

    static function setKillFlagTrue()
    {
        /** @var SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository */
        $syncOpenChatStateRepository = app(SyncOpenChatStateRepositoryInterface::class);
        $syncOpenChatStateRepository->setTrue(SyncOpenChatStateType::openChatDailyCrawlingKillFlag);
    }

    static function setKillFlagFalse()
    {
        /** @var SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository */
        $syncOpenChatStateRepository = app(SyncOpenChatStateRepositoryInterface::class);
        $syncOpenChatStateRepository->setFalse(SyncOpenChatStateType::openChatDailyCrawlingKillFlag);
    }
}
