<?php

use PHPUnit\Framework\TestCase;

use App\Models\Repositories\UpdateOpenChatRepositoryInterface;
use App\Services\OpenChat\Crawler\OpenChatCrawler;
use App\Services\OpenChat\Dto\OpenChatUpdaterDtoFactory;
use App\Services\OpenChat\Dto\OpenChatUpdaterDto;
use App\Services\OpenChat\Dto\OpenChatDto;

class OpenChatUpdaterDtoFactoryTest extends TestCase
{
    private OpenChatCrawler $openChatCrawler;
    private string $invitationTicket;

    protected function setUp(): void
    {
        $this->openChatCrawler = app(OpenChatCrawler::class);
        $this->invitationTicket = 'RZROR9WAY8';
    }

    public function testFromApiDto()
    {
        /**
         * @var UpdateOpenChatRepositoryInterface $Service
         */
        $Service = app()->make(UpdateOpenChatRepositoryInterface::class);
        $repoDto = $Service->getOpenChatDataById(335);
        var_dump($repoDto);

        /**
         * @var OpenChatUpdaterDtoFactory $inst
         */
        $inst = app(OpenChatUpdaterDtoFactory::class);

        $apiDto = $this->getApiDtoMock();
        var_dump($apiDto);

        $res = $inst->mapToDto(335, $repoDto, $apiDto);

        var_dump($res);

        $this->assertNull($res->name);
        $this->assertNull($res->desc);
        $this->assertNull($res->profileImageObsHash);
        $this->assertIsInt($res->emblem);
        $this->assertNull($res->invitationTicket);
        $this->assertIsString($res->emid);
        $this->assertIsInt($res->memberCount);
        $this->assertIsInt($res->createdAt);
        $this->assertIsInt($res->category);
    }

    public function testFromCrawlerDto()
    {
        /**
         * @var UpdateOpenChatRepositoryInterface $Service
         */
        $Service = app()->make(UpdateOpenChatRepositoryInterface::class);
        $repoDto = $Service->getOpenChatDataById(335);
        var_dump($repoDto);

        /**
         * @var OpenChatUpdaterDtoFactory $inst
         */
        $inst = app(OpenChatUpdaterDtoFactory::class);

        $apiDto = $this->openChatCrawler->fetchOpenChatDto($repoDto->invitationTicket);
        var_dump($apiDto);

        $res = $inst->mapToDto(335, $repoDto, $apiDto);

        var_dump($res);

        $this->assertNull($res->emblem);
        $this->assertNull($res->invitationTicket);
        $this->assertNull($res->emid);
        $this->assertNull($res->createdAt);
        $this->assertNull($res->category);
    }

    private function getApiDtoMock(): OpenChatDto
    {
        $file = getUnserializedFile(__DIR__ . '/OpenChatApiDtoMock.dat', true);
        $data = $file[0];

        /**
         * @var OpenChatDto $data
         */
        $data->invitationTicket = 'RZROR9WAY8';

        return $data;
    }

    private function getCrawlerDtoMock(): OpenChatDto
    {
        $data = new OpenChatDto;

        $data->desc = 'test';
        $data->memberCount = 901;
        $data->name = '猫好き&猫の飼い主の会';
        $data->profileImageObsHash = 'imgtest';

        return $data;
    }
}
