<?php

use PHPUnit\Framework\TestCase;
use App\Services\OpenChat\Dto\OpenChatApiDtoFactory;
use App\Services\OpenChat\Dto\OpenChatDto;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloader;
use App\Models\Repositories\OpenChatRepositoryInterface;

class OpenChatApiDtoFactoryTest extends TestCase
{
    private OpenChatApiDtoFactory $openChatApiDtoFactory;
    private OpenChatRepositoryInterface $openChatRepository;

    public function test()
    {
        $this->openChatApiDtoFactory = app(OpenChatApiDtoFactory::class);
        $this->openChatRepository = app(OpenChatRepositoryInterface::class);

        /**
         * @var OpenChatApiRankingDownloader $openChatApiRankingDataDownloader
         */
        $openChatApiRankingDataDownloader = app(OpenChatApiRankingDownloader::class);

        $res = $openChatApiRankingDataDownloader->fetchOpenChatApiRankingAll(1, 21, function (array $apiData) {
            $errors = $this->openChatApiDtoFactory->validateAndMapToOpenChatDto($apiData, function (OpenChatDto $dto) {

                $existingOpenChat = $this->openChatRepository->findDuplicateOpenChat($dto);

                if ($existingOpenChat) {
                    var_dump($dto);
                    var_dump($existingOpenChat);
                    exit;
                }
            });

            var_dump($errors);
        });

        var_dump($res);

        $this->assertTrue($res > 0);
    }
}
