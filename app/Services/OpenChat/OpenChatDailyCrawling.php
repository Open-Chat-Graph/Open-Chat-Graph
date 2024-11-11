<?php

declare(strict_types=1);

namespace App\Services\OpenChat;

use App\Config\AppConfig;
use App\Exceptions\ApplicationException;
use App\Models\Repositories\Log\LogRepositoryInterface;
use App\Services\Utility\ErrorCounter;
use App\Services\OpenChat\Updater\OpenChatUpdaterFromApi;
use App\Services\Crawler\CrawlerFactory;

class OpenChatDailyCrawling
{
    function __construct(

        private OpenChatUpdaterFromApi $openChatUpdater,
        private LogRepositoryInterface $logRepository,
        private ErrorCounter $errorCounter,
    ) {
    }

    /**
     * @param int[] $openChatIdArray
     * @throws \RuntimeException
     */
    function crawling(array $openChatIdArray, ?int $intervalSecond = null): int
    {
        foreach ($openChatIdArray as $id) {
            $this->checkKillFlag();
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
        clearstatcache(true, AppConfig::OPEN_CHAT_API_CRAWLING_KILL_FLAG_PATH);
        if (file_get_contents(AppConfig::OPEN_CHAT_API_CRAWLING_KILL_FLAG_PATH) === '1') {
            throw new ApplicationException('OpenChatDailyCrawling: 強制終了しました');
        }
    }

    static function disableKillFlag()
    {
        file_put_contents(AppConfig::OPEN_CHAT_API_CRAWLING_KILL_FLAG_PATH, '0');
    }

    static function enableKillFlag()
    {
        file_put_contents(AppConfig::OPEN_CHAT_API_CRAWLING_KILL_FLAG_PATH, '1');
    }
}
