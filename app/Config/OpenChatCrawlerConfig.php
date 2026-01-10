<?php

namespace App\Config;

class OpenChatCrawlerConfig
{
    const USER_AGENT = 'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/pika-0203/Open-Chat-Graph)';

    const LINE_URL_MATCH_PATTERN = [
        '' =>    '{(?<=https:\/\/openchat\.line\.me\/jp\/cover\/).+?(?=\?|$)}',
        '/tw' => '{(?<=https:\/\/openchat\.line\.me\/tw\/cover\/).+?(?=\?|$)}',
        '/th' => '{(?<=https:\/\/openchat\.line\.me\/th\/cover\/).+?(?=\?|$)}',
    ];
    const LINE_IMG_URL = 'https://obs.line-scdn.net/';
    const LINE_IMG_PREVIEW_PATH = '/preview';
    const IMG_MIME_TYPE = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    const LINE_INTERNAL_URL_MATCH_PATTERN = '{(?<=https:\/\/line\.me\/ti\/g2\/).+?(?=\?|$)}';
    const DOM_CLASS_NAME = '.MdMN04Txt';
    const DOM_CLASS_MEMBER = '.MdMN05Txt';
    const DOM_CLASS_DESCRIPTION = '.MdMN06Desc';
    const DOM_CLASS_IMG = '.mdMN01Img';

    const STORE_IMG_QUALITY = 50;

    static function generateOpenChatApiOcDataFromEmidUrl(string $emid)
    {
        return "https://openchat.line.me/api/square/{$emid}?limit=1";
    }

    static function generateOpenChatApiRankigDataUrl(string $category, string $ct)
    {
        return "https://openchat.line.me/api/category/{$category}?sort=RANKING&limit=40&ct={$ct}";
    }

    static function generateOpenChatApiRisingDataUrl(string $category, string $ct)
    {
        return "https://openchat.line.me/api/category/{$category}?sort=RISING&limit=40&ct={$ct}";
    }

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
}
