<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Crawler;

use App\Services\Crawler\Config\OpenChatCrawlerConfigInterface;
use App\Services\Crawler\CrawlerFactory;
use App\Services\OpenChat\Dto\OpenChatCrawlerDtoFactory;
use App\Services\OpenChat\Dto\OpenChatDto;

class OpenChatCrawler
{
    function __construct(
        private CrawlerFactory $crawlerFactory,
        private OpenChatCrawlerDtoFactory $openChatCrawlerDtoFactory,
        private OpenChatCrawlerConfigInterface $config
    ) {
    }

    /**
     * @throws \RuntimeException
     */
    function fetchOpenChatDto(string $invitationTicket): OpenChatDto|false
    {
        /**
         *  @var string $url オープンチャットの招待ページ
         *                   https://line.me/ti/g2/{$invitationTicket}
         */
        $url = $this->config->getLineInternalUrl() . $invitationTicket;

        /**
         *  @var string $ua クローリング用のユーザーエージェント
         *                  Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/mimimiku778/Open-Chat-Graph)
         */
        $ua = $this->config->getUserAgent();

        // クローラーを初期化
        $crawler = $this->crawlerFactory->createCrawler($url, $ua);
        if ($crawler === false) {
            return false;
        }

        // クローラーからデータを取得する
        try {
            $name = $crawler->filter($this->config->getDomClassName())->text();
            $img_url = $crawler->filter($this->config->getDomClassImg())->children()->attr('src');
            $description = $crawler->filter($this->config->getDomClassDescription())->text(null, false);
            $member = $crawler->filter($this->config->getDomClassMember())->text();

            return $this->openChatCrawlerDtoFactory->validateAndMapToDto($invitationTicket, $name, $img_url, $description, $member);
        } catch (\Throwable $e) {
            throw new \RuntimeException("invitationTicket: {$invitationTicket}: " . $e->__toString());
        }
    }

    function parseInvitationTicketFromUrl(string $url): string
    {
        if (!preg_match($this->config->getLineInternalUrlMatchPattern(), $url, $match)) {
            throw new \LogicException('URLのパターンがマッチしませんでした。');
        }

        return $match[0];
    }
}
