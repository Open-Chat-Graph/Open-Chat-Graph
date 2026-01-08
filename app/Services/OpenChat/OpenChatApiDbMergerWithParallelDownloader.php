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
    function fetchOpenChatApiRankingAll(?int $batchSize = null)
    {
        $this->setKillFlagFalse();
        $this->stateRepository->cleanUpAll();

        // カテゴリ配列とその逆順配列を準備
        // 順方向配列と逆方向配列を組み合わせることで、データ量の多いカテゴリと少ないカテゴリをペアにして負荷分散
        $categoryArray = array_values(OpenChatCrawlerConfig::PARALLEL_DOWNLOADER_CATEGORY_ORDER[MimimalCmsConfig::$urlRoot]);
        $categoryReverse = array_reverse($categoryArray);

        // 並列ダウンロード数を取得（例：2なら2ペア＝4カテゴリを同時実行）
        // テスト用に引数で渡された場合はそれを使用
        $batchSize = $batchSize ?? AppConfig::$parallelDownloadBatchSize[MimimalCmsConfig::$urlRoot];

        // カテゴリインデックス（キー）をバッチサイズごとに分割
        // 例：[0,1,2,3,4...] → [[0,1], [2,3], [4,5], ...]
        $batchKeys = array_chunk(array_keys($categoryArray), $batchSize);

        foreach ($batchKeys as $batch) {
            // 各バッチ内のカテゴリペアを同時にダウンロード開始
            // categoryArray[key]とcategoryReverse[key]の組み合わせで負荷分散
            foreach ($batch as $key) {
                $this->download([
                    [RankingType::Ranking, $categoryArray[$key]],
                    [RankingType::Rising, $categoryReverse[$key]]
                ]);
            }

            // バッチ内の全ダウンロードが完了するまで待機
            $batchComplete = false;
            while (!$batchComplete) {
                sleep(10);

                // バッチ内のカテゴリに対してマージ処理
                foreach ($batch as $key) {
                    if ($this->stateRepository->isDownloaded(RankingType::Ranking, $categoryArray[$key])) {
                        $this->mergeProcess(RankingType::Ranking, $categoryArray[$key]);
                    }
                    if ($this->stateRepository->isDownloaded(RankingType::Rising, $categoryReverse[$key])) {
                        $this->mergeProcess(RankingType::Rising, $categoryReverse[$key]);
                    }
                }

                // バッチ内の全カテゴリが完了したかチェック
                $batchComplete = true;
                foreach ($batch as $key) {
                    if (!$this->stateRepository->isDownloaded(RankingType::Ranking, $categoryArray[$key]) ||
                        !$this->stateRepository->isDownloaded(RankingType::Rising, $categoryReverse[$key])) {
                        $batchComplete = false;
                        break;
                    }
                }
            }
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
