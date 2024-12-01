<?php

declare(strict_types=1);

namespace App\Services\OpenChat;

use App\Config\AppConfig;
use App\Exceptions\ApplicationException;
use App\Models\Repositories\OpenChatDataForUpdaterWithCacheRepository;
use App\Models\Repositories\ParallelDownloadOpenChatStateRepositoryInterface;
use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Cron\Enum\SyncOpenChatStateType;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\OpenChat\Updater\Process\OpenChatApiDbMergerProcess;
use App\Services\RankingPosition\Store\RankingPositionStore;
use App\Services\RankingPosition\Store\RisingPositionStore;

class OpenChatApiDbMergerWithParallelDownloader
{
    function __construct(
        private ParallelDownloadOpenChatStateRepositoryInterface $stateRepository,
        private RankingPositionStore $rankingStore,
        private RisingPositionStore $risingStore,
        private OpenChatApiDbMergerProcess $process,
        private SyncOpenChatStateRepositoryInterface $syncOpenChatStateRepository,
    ) {}

    private const OPEN_CHAT_CATEGORY = [
        'ゲーム' => 17,
        'すべて' => 0,
        '芸能人・有名人' => 26,
        'アニメ・漫画' => 22,
        'スポーツ' => 16,
        '働き方・仕事' => 5,
        '音楽' => 33,
        '地域・暮らし' => 8,
        '同世代' => 7,
        '乗り物' => 19,
        '金融・ビジネス' => 40,
        '研究・学習' => 11,
        'ファッション・美容' => 20,
        '健康' => 23,
        'イラスト' => 41,
        '学校・同窓会' => 2,
        '団体' => 6,
        '料理・グルメ' => 12,
        '妊活・子育て' => 28,
        '写真' => 37,
        '旅行' => 18,
        '映画・舞台' => 30,
        '動物・ペット' => 27,
        'TV・VOD' => 24,
        '本' => 29,
    ];

    function fetchOpenChatApiRankingAll()
    {
        $this->setKillFlagFalse();
        $this->stateRepository->cleanUpAll();

        $categoryArray = array_values(self::OPEN_CHAT_CATEGORY);
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

        $path = AppConfig::ROOT_PATH . 'exec_parallel_downloader.php';
        exec(PHP_BINARY . " {$path} {$arg} >/dev/null 2>&1 &");
    }

    function mergeProcess(RankingType $type, int $category)
    {
        if (!$this->stateRepository->isDownloaded($type, $category)) return;

        $this->checkKillFlag();

        $dtos = match ($type) {
            RankingType::Rising => $this->risingStore->getStorageData((string)$category)[1],
            RankingType::Ranking => $this->rankingStore->getStorageData((string)$category)[1],
        };

        $log = $type->value . " " . array_flip(AppConfig::$OPEN_CHAT_CATEGORY)[$category];
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
