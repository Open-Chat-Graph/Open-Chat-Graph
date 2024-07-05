<?php

use PHPUnit\Framework\TestCase;
use App\Services\OpenChat\Dto\OpenChatApiDtoFactory;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloader;
use App\Services\OpenChat\Crawler\OpenChatApiFromEmidDownloader;
use App\Models\Repositories\OpenChatDataForUpdaterWithCacheRepositoryInterface;
use App\Models\Repositories\OpenChatRepositoryWithCacheForUpdater;
use App\Services\OpenChat\Updater\OpenChatUpdaterInterface;
use App\Services\OpenChat\Registration\OpenChatFromApiRegistration;
use App\Services\OpenChat\Dto\OpenChatDto;
use App\Services\OpenChat\Utility\OpenChatServicesUtility;
use App\Services\GceDifferenceUpdater;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloaderProcess;

class OpenChatUpdaterForMergeTest extends TestCase
{
    private $openChatApiDtoFactory;

    private OpenChatApiFromEmidDownloader $openChatApiOcDataFromEmidDownloader;
    private OpenChatDataForUpdaterWithCacheRepositoryInterface $openChatRepository;
    private OpenChatUpdaterInterface $openChatUpdater;
    private OpenChatFromApiRegistration $openChatFromApiRegistration;

    private $errors;

    public function test()
    {
        set_time_limit(1200);

        $this->openChatApiOcDataFromEmidDownloader = app(OpenChatApiFromEmidDownloader::class);
        $this->openChatRepository = app(OpenChatDataForUpdaterWithCacheRepositoryInterface::class);
        $this->openChatUpdater = app(OpenChatUpdaterInterface::class);
        $this->openChatFromApiRegistration = app(OpenChatFromApiRegistration::class, ['openChatRepository' => app(OpenChatRepositoryWithCacheForUpdater::class)]);

        /**
         * @var OpenChatApiRankingDownloader $openChatApiRankingDataDownloader
         */
        $openChatApiRankingDataDownloader = app(OpenChatApiRankingDownloader::class, ['openChatApiRankingDownloaderProcess' => app(OpenChatApiRankingDownloaderProcess::class)]);
        $this->openChatApiDtoFactory = app(OpenChatApiDtoFactory::class);

        $res = $openChatApiRankingDataDownloader->fetchOpenChatApiRankingAll(1, 18, function (array $apiData) {
            $this->errors = $this->openChatApiDtoFactory->validateAndMapToOpenChatDto($apiData, function (OpenChatDto $apiDto) {
                // Emidが一致するオープンチャットを取得する
                $openChatByEmid = $this->openChatRepository->getOpenChatIdByEmid($apiDto->emid);
                if ($openChatByEmid && !$openChatByEmid['next_update']) {
                    // 一致したデータがあり更新対象ではない場合
                    return null;
                }

                // DBに一致するオープンチャットがある場合
                if ($openChatByEmid) {
                    $this->openChatUpdater->updateOpenChat($openChatByEmid['id'], $apiDto);
                    return null;
                }

                // APIから追加のデータをダウンロードする
                $ocApiElement = $this->openChatApiOcDataFromEmidDownloader->fetchOpenChatApiFromEmidDtoElement($apiDto->emid);
                if (is_string($ocApiElement)) {
                    // エラー文字列の場合
                    return $ocApiElement;
                }

                // DTOに要素を追加する
                $apiDto->setOpenChatApiFromEmidDtoElement($ocApiElement);

                // 一致するオープンチャットが無い場合、別の情報に一致するEmidを持たないオープンチャットを探す
                $existingOpenChatId = $this->openChatRepository->findDuplicateOpenChat($apiDto);
                // 一致するオープンチャットがある場合
                if ($existingOpenChatId) {
                    $this->openChatUpdater->updateOpenChat($existingOpenChatId, $apiDto);

                    return null;
                }

                // 収集拒否の場合
                if (OpenChatServicesUtility::containsHashtagNolog($apiDto)) {
                    return null;
                }

                // 一致するオープンチャットが無い場合、新しく登録する
                $this->openChatFromApiRegistration->registerOpenChatFromApi($apiDto);

                return null;
            });
        }, fn () => null);

        var_dump($this->errors);
        var_dump($res);
        $this->assertIsInt($res);
    }
}
