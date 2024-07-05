<?php

use PHPUnit\Framework\TestCase;
use App\Services\OpenChat\Dto\OpenChatApiDtoFactory;
use App\Services\OpenChat\Dto\OpenChatDto;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloader;
use App\Models\Repositories\OpenChatDataForUpdaterWithCacheRepository;
use App\Models\Repositories\UpdateOpenChatRepository;

class OpenChatDataForUpdaterWithCacheRepositoryTest extends TestCase
{
    private OpenChatApiDtoFactory $openChatApiDtoFactory;
    private OpenChatDataForUpdaterWithCacheRepository $openChatRepository;

    private $errors;
    private $repository;

    public function test()
    {
        set_time_limit(1200);

        $this->openChatApiDtoFactory = app(OpenChatApiDtoFactory::class);
        $this->openChatRepository = app(OpenChatDataForUpdaterWithCacheRepository::class);


        $this->repository = app(UpdateOpenChatRepository::class);

        /**
         * @var OpenChatApiRankingDownloader $openChatApiRankingDataDownloader
         */
        $openChatApiRankingDataDownloader = app(OpenChatApiRankingDownloader::class);

        $res = $openChatApiRankingDataDownloader->fetchOpenChatApiRankingAll(1, 2, function (array $apiData) {
            $this->errors = $this->openChatApiDtoFactory->validateAndMapToOpenChatDto($apiData, function (OpenChatDto $apiDto) {

                $openChatByEmid = $this->openChatRepository->getOpenChatIdByEmid($apiDto->emid);
                if (is_array($openChatByEmid) && !$openChatByEmid['next_update']) {
                    //debug($openChatByEmid);
                    return null;
                }

                if ($openChatByEmid !== false) {

                    $result = $this->openChatRepository->getMemberChangeWithinLastWeek($openChatByEmid['id']);
                    //debug($result, $openChatByEmid);

                    return null;
                }


                $existingOpenChatId = $this->openChatRepository->findDuplicateOpenChat($apiDto);
                if ($existingOpenChatId !== false) {

                    $result = $this->openChatRepository->getMemberChangeWithinLastWeek($existingOpenChatId);
                    //debug($result, $existingOpenChatId);

                    return null;
                }

            });
        });

        debug($this->errors);
        debug("result $res");
        $this->assertIsInt($res);
    }
}
