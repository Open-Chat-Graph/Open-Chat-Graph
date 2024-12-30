<?php

use App\Config\AppConfig;
use App\Config\Shadow\DatabaseConfig;
use App\Config\DatabaseConfigTh;
use App\Config\DatabaseConfigTw;
use App\Config\RankingPositionDBConfig;
use App\Config\RankingPositionDBConfigTh;
use App\Config\RankingPositionDBConfigTw;
use App\Config\OpenChatCrawlerConfig;

(function (string $overwriteUrlRoot) {
    if (URL_ROOT === '/tw' || $overwriteUrlRoot === '/tw') {
        $STORAGE_DIR = __DIR__ . '/../../storage/tw';

        AppConfig::$addCronLogDestination = __DIR__ . '/../../logs/cron_tw.log';
        AppConfig::$DatabaseConfigClass = DatabaseConfigTw::class;
        AppConfig::$RankingPositionDBConfigClass = RankingPositionDBConfigTw::class;
        AppConfig::$OPEN_CHAT_CATEGORY = [
            '流行／美妝' => 20,
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
            '全部' => 0,
        ];

        OpenChatCrawlerConfig::$OPEN_CHAT_API_OC_DATA_FROM_EMID_DOWNLOADER_HEADER[] = "x-lal: tw";
        OpenChatCrawlerConfig::$parallelDownloaderCategoryOrder = [
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
        ];
    } elseif (URL_ROOT === '/th' || $overwriteUrlRoot === '/th') {
        $STORAGE_DIR = __DIR__ . '/../../storage/th';

        AppConfig::$addCronLogDestination = __DIR__ . '/../../logs/cron_th.log';
        AppConfig::$DatabaseConfigClass = DatabaseConfigTh::class;
        AppConfig::$RankingPositionDBConfigClass = RankingPositionDBConfigTh::class;
        AppConfig::$OPEN_CHAT_CATEGORY = [
            'แฟนคลับ' => 10,
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
            'ทั้งหมด' => 0,
        ];

        OpenChatCrawlerConfig::$OPEN_CHAT_API_OC_DATA_FROM_EMID_DOWNLOADER_HEADER[] = "x-lal: th";
        OpenChatCrawlerConfig::$parallelDownloaderCategoryOrder = [
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
        ];
    } else {
        $STORAGE_DIR = __DIR__ . '/../../storage';

        AppConfig::$addCronLogDestination = __DIR__ . '/../../logs/cron.log';
        AppConfig::$DatabaseConfigClass = DatabaseConfig::class;
        AppConfig::$RankingPositionDBConfigClass = RankingPositionDBConfig::class;
        AppConfig::$OPEN_CHAT_CATEGORY = [
            'ゲーム' => 17,
            'スポーツ' => 16,
            '芸能人・有名人' => 26,
            '同世代' => 7,
            'アニメ・漫画' => 22,
            '金融・ビジネス' => 40,
            '音楽' => 33,
            '地域・暮らし' => 8,
            'ファッション・美容' => 20,
            'イラスト' => 41,
            '研究・学習' => 11,
            '働き方・仕事' => 5,
            '学校・同窓会' => 2,
            '料理・グルメ' => 12,
            '健康' => 23,
            '団体' => 6,
            '妊活・子育て' => 28,
            '乗り物' => 19,
            '写真' => 37,
            '旅行' => 18,
            '動物・ペット' => 27,
            'TV・VOD' => 24,
            '本' => 29,
            '映画・舞台' => 30,
            'すべて' => 0,
        ];

        OpenChatCrawlerConfig::$parallelDownloaderCategoryOrder = [
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
        ];
    }

    AppConfig::$DAILY_CRON_UPDATED_AT_DATE =            $STORAGE_DIR . '/static_data_top/daily_updated_at.dat';
    AppConfig::$HOURLY_CRON_UPDATED_AT_DATETIME =       $STORAGE_DIR . '/static_data_top/hourly_updated_at.dat';
    AppConfig::$HOURLY_REAL_UPDATED_AT_DATETIME =       $STORAGE_DIR . '/static_data_top/real_updated_at.dat';

    AppConfig::$OPEN_CHAT_RANKING_POSITION_DIR =        $STORAGE_DIR . '/ranking_position/ranking';
    AppConfig::$OPEN_CHAT_RISING_POSITION_DIR =         $STORAGE_DIR . '/ranking_position/rising';
    AppConfig::$OPEN_CHAT_HOUR_FILTER_ID_DIR =          $STORAGE_DIR . '/ranking_position/filter.dat';

    AppConfig::$SQLiteStatisticsDbfile =                $STORAGE_DIR . '/SQLite/statistics/statistics.db';
    AppConfig::$SQLiteRankingPositionDbfile =           $STORAGE_DIR . '/SQLite/ranking_position/ranking_position.db';

    AppConfig::$OPEN_CHAT_SUB_CATEGORIES_FILE_PATH =    $STORAGE_DIR . '/open_chat_sub_categories/subcategories.json';
})($overwriteUrlRoot ?? '');
