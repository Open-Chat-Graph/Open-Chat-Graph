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

    const PARALLEL_DOWNLOADER_CATEGORY_ORDER = [
        '' =>    [
            'ゲーム' => 17,
            'すべて' => 0,
            '芸能人・有名人' => 26,
            'アニメ・漫画' => 22,
            'スポーツ' => 16,
            '働き方・仕事' => 5,
            '音楽' => 33,
            '地域・暮らし' => 8,
            '同世代' => 7,
            '乗り物' => 19,
            '金融・ビジネス' => 40,
            '研究・学習' => 11,
            'ファッション・美容' => 20,
            '健康' => 23,
            'イラスト' => 41,
            '学校・同窓会' => 2,
            '団体' => 6,
            '料理・グルメ' => 12,
            '妊活・子育て' => 28,
            '写真' => 37,
            '旅行' => 18,
            '映画・舞台' => 30,
            '動物・ペット' => 27,
            'TV・VOD' => 24,
            '本' => 29,
        ],
        '/tw' => [
            '流行／美妝' => 20,
            '全部' => 0,
            '金融／商業' => 40,
            '美食' => 12,
            '團體／組織' => 6,
            '旅遊' => 18,
            '娛樂' => 42,
            '家庭／親子' => 4,
            '工作' => 44,
            '學習' => 11,
            '遊戲' => 17,
            '興趣' => 14,
            '運動／健身' => 16,
            '寵物' => 27,
            '公司／企業' => 5,
            '心情' => 43,
            '科技' => 34,
            '健康' => 23,
            '學校／校友' => 2,
            '動畫／漫畫' => 22,
            '其他' => 35,
        ],
        '/th' => [
            'แฟนคลับ' => 10,
            'ทั้งหมด' => 0,
            'การศึกษา' => 11,
            'การเงิน & ธุรกิจ' => 40,
            'งานอดิเรก' => 14,
            'ท่องเที่ยว' => 18,
            'เทคโนโลยี' => 34,
            'เกม' => 17,
            'ท้องถิ่น' => 8,
            'สัตว์เลี้ยง' => 27,
            'รถยนต์' => 19,
            'เพลง' => 33,
            'แฟชั่น & บิวตี้' => 20,
            'กีฬา' => 16,
            'อาหาร' => 12,
            'อนิเมะ & การ์ตูน' => 22,
            'ภาพยนตร์' => 30,
            'โรงเรียน' => 2,
            'การถ่ายภาพ' => 37,
            'รายการทีวี' => 24,
            'เด็ก' => 28,
            'อื่นๆ' => 35,
        ],
    ];
}
