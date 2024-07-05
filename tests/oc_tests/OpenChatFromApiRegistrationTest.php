<?php

use PHPUnit\Framework\TestCase;
use App\Services\OpenChat\Dto\OpenChatApiDtoFactory;
use App\Services\OpenChat\Dto\OpenChatDto;
use App\Services\OpenChat\Crawler\OpenChatApiRankingDownloader;
use App\Services\OpenChat\Crawler\OpenChatApiFromEmidDownloader;
use App\Services\OpenChat\Registration\OpenChatFromApiRegistration;
use App\Models\Repositories\OpenChatRepositoryInterface;

class OpenChatFromApiRegistrationTest extends TestCase
{
    private OpenChatApiDtoFactory $openChatApiDtoFactory;
    private OpenChatRepositoryInterface $openChatRepository;
    private OpenChatFromApiRegistration $openChatFromApiRegistration;
    private OpenChatApiFromEmidDownloader $openChatApiOcDataFromEmidDownloader;
    private $errors;

    public function test()
    {
        set_time_limit(1200);

        $this->openChatApiDtoFactory = app(OpenChatApiDtoFactory::class);
        $this->openChatRepository = app(OpenChatRepositoryInterface::class);
        $this->openChatFromApiRegistration = app(OpenChatFromApiRegistration::class);
        $this->openChatApiOcDataFromEmidDownloader = app(OpenChatApiFromEmidDownloader::class);

        /**
         * @var OpenChatApiRankingDownloader $openChatApiRankingDataDownloader
         */
        $openChatApiRankingDataDownloader = app(OpenChatApiRankingDownloader::class);

        $res = $openChatApiRankingDataDownloader->fetchOpenChatApiRankingAll(1, 16, function (array $apiData) {
            $this->errors = $this->openChatApiDtoFactory->validateAndMapToOpenChatDto($apiData, function (OpenChatDto $dto) {

                $existingOpenChat = $this->openChatRepository->findDuplicateOpenChat($dto);

                if ($existingOpenChat) {
                    return;
                }

                $ocApiData = $this->openChatApiOcDataFromEmidDownloader->fetchOpenChatApiFromEmidDtoElement($dto->emid);
                if (is_string($ocApiData)) {
                    var_dump($ocApiData);
                    exit;
                }

                $dto->setOpenChatApiFromEmidDtoElement($ocApiData);

                $result = $this->openChatFromApiRegistration->registerOpenChatFromApi($dto);
                if($result === true) {
                    return 'true';
                }
            });
        });

        var_dump($this->errors);
        var_dump($res);
        $this->assertTrue($res > 0);
    }
}
