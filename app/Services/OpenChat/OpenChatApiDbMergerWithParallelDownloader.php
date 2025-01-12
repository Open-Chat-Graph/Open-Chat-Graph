<?php

declare(strict_types=1);

namespace App\Services\OpenChat;

use App\Config\AppConfig;
use App\Config\OpenChatCrawlerConfig;
use App\Exceptions\ApplicationException;
use App\Models\Repositories\OpenChatDataForUpdaterWithCacheRepository;
use App\Models\Repositories\ParallelDownloadOpenChatStateRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\OpenChat\Updater\Process\OpenChatApiDbMergerProcess;
use App\Services\RankingPosition\Store\RankingPositionStore;
use App\Services\RankingPosition\Store\RisingPositionStore;
use Shared\MimimalCmsConfig;

class OpenChatApiDbMergerWithParallelDownloader
{
    function __construct(
        private ParallelDownloadOpenChatStateRepositoryInterface $stateRepository,
        private RankingPositionStore $rankingStore,
        private RisingPositionStore $risingStore,
        private OpenChatApiDbMergerProcess $process,
        private SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository,
    ) {}
    function fetchOpenChatApiRankingAll()
    {
        $this->setKillFlagFalse();
        $this->stateRepository->cleanUpAll();

        $categoryArray = array_values(OpenChatCrawlerConfig::PARALLEL_DOWNLOADER_CATEGORY_ORDER[MimimalCmsConfig::$urlRoot]);
        $categoryReverse = array_reverse($categoryArray);
        foreach ($categoryArray as $key => $category) {
            $this->download([[RankingType::Ranking, $category], [RankingType::Rising, $categoryReverse[$key]]]);
        }

        $flag = false;
        while (!$flag) {
            sleep(10);
            foreach ([RankingType::Ranking, RankingType::Rising] as $type)
                foreach ($categoryReverse as $category)
                    $this->mergeProcess($type, $category);

            $flag = $this->stateRepository->isCompletedAll();
        }

        OpenChatDataForUpdaterWithCacheRepository::clearCache();
    }

    /**
     * @param array{ 0: RankingType, 1: int }[] $args
     */
    private function download(array $args)
    {
        $arg = escapeshellarg(json_encode(
            array_map(fn($arg) => ['type' => $arg[0]->value, 'category' => $arg[1]], $args)
        ));

        $arg2 = escapeshellarg(MimimalCmsConfig::$urlRoot);

        $path = AppConfig::ROOT_PATH . 'batch/exec/exec_parallel_downloader.php';
        exec(PHP_BINARY . " {$path} {$arg} {$arg2} >/dev/null 2>&1 &");
    }

    function mergeProcess(RankingType $type, int $category)
    {
        if (!$this->stateRepository->isDownloaded($type, $category)) return;

        $this->checkKillFlag();

        $dtos = match ($type) {
            RankingType::Rising => $this->risingStore->getStorageData((string)$category)[1],
            RankingType::Ranking => $this->rankingStore->getStorageData((string)$category)[1],
        };

        $log = $type->value . " " . array_flip(AppConfig::OPEN_CHAT_CATEGORY[MimimalCmsConfig::$urlRoot])[$category];
        addCronLog("merge start: {$log}");

        foreach ($dtos as $dto)
            $this->process->validateAndMapToOpenChatDtoCallback($dto);

        $this->stateRepository->updateComplete($type, $category);

        addCronLog("merge complete: {$log}");
    }

    /** @throws ApplicationException */
    private function checkKillFlag()
    {
        $this->syncOpenChatStateRepository->getBool(SyncOpenChatStateType::openChatApiDbMergerKillFlag)
            && throw new ApplicationException('OpenChatApiDbMergerWithParallelDownloader: 強制終了しました');
    }

    static function setKillFlagTrue()
    {
        /** @var SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository */
        $syncOpenChatStateRepository = app(SyncOpenChatStateRepositoryInterface::class);
        $syncOpenChatStateRepository->setTrue(SyncOpenChatStateType::openChatApiDbMergerKillFlag);
    }

    static function setKillFlagFalse()
    {
        /** @var SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository */
        $syncOpenChatStateRepository = app(SyncOpenChatStateRepositoryInterface::class);
        $syncOpenChatStateRepository->setFalse(SyncOpenChatStateType::openChatApiDbMergerKillFlag);
    }
}
