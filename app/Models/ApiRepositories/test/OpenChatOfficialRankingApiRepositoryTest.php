<?php

declare(strict_types=1);

use App\Models\ApiRepositories\OpenChatApiArgs;
use App\Models\ApiRepositories\OpenChatOfficialRankingApiRepository;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\Storage\FileStorageInterface;
use PHPUnit\Framework\TestCase;

class OpenChatOfficialRankingApiRepositoryTest extends TestCase
{
    public function test()
    {
        /**
         * @var OpenChatOfficialRankingApiRepository $repo
         */
        $repo = app(OpenChatOfficialRankingApiRepository::class);
        /** @var FileStorageInterface $fileStorage */
        $fileStorage = app(FileStorageInterface::class);
        $args = new OpenChatApiArgs;

        $args->category = 0;
        $args->limit = 10;
        $args->page = 0;

        $time = new \DateTime($fileStorage->getContents('@hourlyCronUpdatedAtDatetime'));
        $time->modify('-1hour');
        $timeStr = $time->format('Y-m-d H:i:s');

        $result = $repo->findOfficialRanking($args, RankingType::Rising, $timeStr);

        debug($result);

        $this->assertTrue(true);
    }
}
