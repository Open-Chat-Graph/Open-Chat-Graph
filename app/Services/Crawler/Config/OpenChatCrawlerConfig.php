<?php

namespace App\Services\Crawler\Config;

class OpenChatCrawlerConfig implements OpenChatCrawlerConfigInterface
{
    protected const LINE_INTERNAL_URL = 'https://line.me/ti/g2/';

    protected const USER_AGENT = 'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/mimimiku778/Open-Chat-Graph)';

    public const LINE_URL_MATCH_PATTERN = [
        '' =>    '{(?<=https:\/\/openchat\.line\.me\/jp\/cover\/).+?(?=\?|$)}',
        '/tw' => '{(?<=https:\/\/openchat\.line\.me\/tw\/cover\/).+?(?=\?|$)}',
        '/th' => '{(?<=https:\/\/openchat\.line\.me\/th\/cover\/).+?(?=\?|$)}',
    ];
    protected const LINE_IMG_URL = 'https://obs.line-scdn.net/';
    protected const LINE_IMG_PREVIEW_PATH = '/preview';
    protected const IMG_MIME_TYPE = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    protected const LINE_INTERNAL_URL_MATCH_PATTERN = '{(?<=https:\/\/line\.me\/ti\/g2\/).+?(?=\?|$)}';
    protected const DOM_CLASS_NAME = '.MdMN04Txt';
    protected const DOM_CLASS_MEMBER = '.MdMN05Txt';
    protected const DOM_CLASS_DESCRIPTION = '.MdMN06Desc';
    protected const DOM_CLASS_IMG = '.mdMN01Img';
    protected const STORE_IMG_QUALITY = 50;

    protected const OPEN_CHAT_API_OC_DATA_FROM_EMID_DOWNLOADER_HEADER =
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
        return "https://openchat.line.me/api/square/{$emid}?limit=1";
    }

    public function generateOpenChatApiRankingDataUrl(string $category, string $ct): string
    {
        return "https://openchat.line.me/api/category/{$category}?sort=RANKING&limit=40&ct={$ct}";
    }

    public function generateOpenChatApiRisingDataUrl(string $category, string $ct): string
    {
        return "https://openchat.line.me/api/category/{$category}?sort=RISING&limit=40&ct={$ct}";
    }

    public function getOpenChatApiOcDataFromEmidDownloaderHeader(): array
    {
        return self::OPEN_CHAT_API_OC_DATA_FROM_EMID_DOWNLOADER_HEADER;
    }
}
