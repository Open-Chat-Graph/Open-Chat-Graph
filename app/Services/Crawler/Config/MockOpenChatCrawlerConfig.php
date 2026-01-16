<?php

namespace App\Services\Crawler\Config;

/**
 * Mock環境用のOpenChatクローラー設定
 * LINE APIの代わりにローカルのMock APIを使用
 * HTTPで通信
 */
class MockOpenChatCrawlerConfig implements OpenChatCrawlerConfigInterface
{
    // Mock APIのベースURL（docker-compose.mock.ymlで定義）
    // コンテナ内からはサービス名でアクセス、外部からはlocalhostでアクセス可能
    const MOCK_API_BASE_URL = 'http://line-mock-api';

    const LINE_INTERNAL_URL = 'http://line-mock-api/ti/g2/';

    const USER_AGENT = 'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot-Mock; +https://github.com/mimimiku778/Open-Chat-Graph)';

    public const LINE_URL_MATCH_PATTERN = [
        '' =>    '{(?<=https:\/\/line-mock-api\/jp\/cover\/).+?(?=\?|$)}',
        '/tw' => '{(?<=https:\/\/line-mock-api\/tw\/cover\/).+?(?=\?|$)}',
        '/th' => '{(?<=https:\/\/line-mock-api\/th\/cover\/).+?(?=\?|$)}',
    ];
    const LINE_IMG_URL = 'http://line-mock-api/obs/';
    const LINE_IMG_PREVIEW_PATH = '/preview';
    const IMG_MIME_TYPE = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    const LINE_INTERNAL_URL_MATCH_PATTERN = '{(?<=https:\/\/line-mock-api\/ti\/g2\/).+?(?=\?|$)}';
    const DOM_CLASS_NAME = '.MdMN04Txt';
    const DOM_CLASS_MEMBER = '.MdMN05Txt';
    const DOM_CLASS_DESCRIPTION = '.MdMN06Desc';
    const DOM_CLASS_IMG = '.mdMN01Img';

    const STORE_IMG_QUALITY = 50;

    const OPEN_CHAT_API_OC_DATA_FROM_EMID_DOWNLOADER_HEADER =
    [
        '' =>    [
            "x-line-seo-user: xc5c0f67600885ce88324a52e74ff6923",
        ],
        '/tw' => [
            "x-lal: tw",
            "x-line-seo-user: xc5c0f67600885ce88324a52e74ff6923",
        ],
        '/th' => [
            "x-lal: th",
            "x-line-seo-user: xc5c0f67600885ce88324a52e74ff6923",
        ],
    ];

    public function getLineInternalUrl(): string
    {
        return self::LINE_INTERNAL_URL;
    }

    public function getUserAgent(): string
    {
        return self::USER_AGENT;
    }

    public function getLineUrlMatchPattern(): array
    {
        return self::LINE_URL_MATCH_PATTERN;
    }

    public function getLineImgUrl(): string
    {
        return self::LINE_IMG_URL;
    }

    public function getLineImgPreviewPath(): string
    {
        return self::LINE_IMG_PREVIEW_PATH;
    }

    public function getImgMimeType(): array
    {
        return self::IMG_MIME_TYPE;
    }

    public function getLineInternalUrlMatchPattern(): string
    {
        return self::LINE_INTERNAL_URL_MATCH_PATTERN;
    }

    public function getDomClassName(): string
    {
        return self::DOM_CLASS_NAME;
    }

    public function getDomClassMember(): string
    {
        return self::DOM_CLASS_MEMBER;
    }

    public function getDomClassDescription(): string
    {
        return self::DOM_CLASS_DESCRIPTION;
    }

    public function getDomClassImg(): string
    {
        return self::DOM_CLASS_IMG;
    }

    public function getStoreImgQuality(): int
    {
        return self::STORE_IMG_QUALITY;
    }

    public function generateOpenChatApiOcDataFromEmidUrl(string $emid): string
    {
        return self::MOCK_API_BASE_URL . "/api/square/{$emid}?limit=1";
    }

    public function generateOpenChatApiRankingDataUrl(string $category, string $ct): string
    {
        return self::MOCK_API_BASE_URL . "/api/category/{$category}?sort=RANKING&limit=40&ct={$ct}";
    }

    public function generateOpenChatApiRisingDataUrl(string $category, string $ct): string
    {
        return self::MOCK_API_BASE_URL . "/api/category/{$category}?sort=RISING&limit=40&ct={$ct}";
    }

    public function getOpenChatApiOcDataFromEmidDownloaderHeader(): array
    {
        return self::OPEN_CHAT_API_OC_DATA_FROM_EMID_DOWNLOADER_HEADER;
    }
}
