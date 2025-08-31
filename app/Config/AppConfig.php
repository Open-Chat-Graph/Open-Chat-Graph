<?php

namespace App\Config;

use Shared\MimimalCmsConfig;

class AppConfig
{
    static string $phpBinary = '/usr/bin/php8.3';

    const SITE_ICON_FILE_PATH = 'assets/icon-192x192.png';
    const DEFAULT_OGP_IMAGE_FILE_PATH = 'assets/ogp.png';

    const GTM_ID = 'GTM-NTK2GPTF';

    const ETAG_ARG = [300, 3600 * 24, false];

    const LINE_URL = 'https://line.me/ti/g2/';
    const LINE_APP_URL = 'https://line.me/ti/g2/';
    const LINE_APP_SUFFIX = '?utm_source=openchat-graph&utm_medium=referral&utm_campaign=default';

    const LINE_APP_URL_SP = 'https://liff.line.me/1573545970-LlNdaE20?to=squareCover&id=';
    const LINE_APP_SUFFIX_SP = '&isJoinImmediately=1&utm_source=openchat-graph&utm_medium=referral&utm_campaign=default';

    const LINE_IMG_URL = 'https://obs.line-scdn.net/';
    const LINE_IMG_URL_PREVIEW_PATH = '/preview';

    const OPENCHAT_IMG_PREVIEW_PATH = 'preview';
    const OPENCHAT_IMG_PREVIEW_SUFFIX = '_p';

    const LINE_OPEN_URL = [
        '' =>    'https://openchat.line.me/jp/cover/',
        '/tw' => 'https://openchat.line.me/tw/cover/',
        '/th' => 'https://openchat.line.me/th/cover/',
    ];
    const LINE_OPEN_URL_SUFFIX = '?utm_source=openchat-graph&utm_medium=referral&utm_campaign=default';

    static int $listLimitTopRanking = 10;
    static int $tagListLimit = 3;
    const LIST_LIMIT_MY_LIST = 50;
    const LIST_LIMIT_RECENT_COMMENT = 50;
    const LIST_LIMIT_RECENTLY_REGISTERED = 100;
    const LIST_LIMIT_RECOMMEND = 100;

    const RECOMMEND_MIN_MEMBER_DIFF_HOUR = 3;
    const RECOMMEND_MIN_MEMBER_DIFF_H24 = 8;
    const RECOMMEND_MIN_MEMBER_DIFF_WEEK = 10;

    const OFFICIAL_EMBLEMS = [
        '' => [
            1 => 'スペシャルオープンチャット',
            2 => '公式認証バッジ',
            3 => 'すべて',
        ],
        '/tw' => [
            1 => 'Special Open Chat',
            2 => '官方認證徽章',
            3 => 'All',
        ],
        '/th' => [
            1 => 'Super OpenChat',
            2 => 'Official Badge',
            3 => 'All',
        ],
    ];

    const OPEN_CHAT_CATEGORY = [
        '' => [
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
        ],
        '/tw' => [
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
        ],
        '/th' => [
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
        ]
    ];

    static bool $isDevlopment = false;
    static bool $isStaging = false;
    static bool $disableStaticDataFile = false;
    static bool $disableAds = false;
    static bool $verboseCronLog = false;
    static bool $enableCloudflare = false;
    static bool $disableAdTags = true;

    /** @var array<string,int> */
    static array $developmentEnvUpdateLimit = [
        'OpenChatImageUpdater' => 10,
        'OpenChatHourlyInvitationTicketUpdater' => 10,
        'DailyUpdateCronService' => 10,
        'RankingBanTableUpdater' => 10,
    ];



    const CRON_START_MINUTE = [
        '' =>    30,
        '/tw' => 35,
        '/th' => 40,
    ];

    const CRON_MERGER_HOUR_RANGE_START = [
        '' =>    23,
        '/tw' => 0,
        '/th' => 1,
    ];

    const DATE_TIME_ZONE = [
        '' =>   'Asia/Tokyo',
        '/tw' => 'Asia/Taipei',
        '/th' => 'Asia/Bangkok',
    ];

    const OPENCHAT_IMG_PATH = [
        '' =>    'oc-img',
        '/tw' => 'oc-img-tw',
        '/th' => 'oc-img-th',
    ];

    const ROOT_PATH =   __DIR__ . '/../../';
    const SITEMAP_DIR = __DIR__ . '/../../public/sitemap.xml';
    const TRANSLATION_FILE = __DIR__ . '/../../storage/translation.json';

    const FURIGANA_CACHE_DIR = __DIR__ . '/../../storage/furigana';

    private const STORAGE_DIR = [
        '' =>    __DIR__ . '/../../storage/ja',
        '/tw' => __DIR__ . '/../../storage/tw',
        '/th' => __DIR__ . '/../../storage/th',
    ];
    const STORAGE_FILES = [
        'addCronLogDest' =>               '/logs/cron.log',
        'sqliteStatisticsDb' =>           '/SQLite/statistics/statistics.db',
        'sqliteRankingPositionDb' =>      '/SQLite/ranking_position/ranking_position.db',
        'openChatSubCategories' =>        '/open_chat_sub_categories/subcategories.json',
        'openChatSubCategoriesSample' =>  '/open_chat_sub_categories/sample/subcategories.json',
        'openChatSubCategoriesTag' =>     '/open_chat_sub_categories/subcategories_tag.json',
        'openChatRankingPositionDir' =>   '/ranking_position/ranking',
        'openChatRisingPositionDir' =>    '/ranking_position/rising',
        'openChatHourFilterId' =>         '/ranking_position/filter.dat',
        'dailyCronUpdatedAtDate' =>       '/static_data_top/daily_updated_at.dat',
        'hourlyCronUpdatedAtDatetime' =>  '/static_data_top/hourly_updated_at.dat',
        'hourlyRealUpdatedAtDatetime' =>  '/static_data_top/real_updated_at.dat',
        'commentUpdatedAtMicrotime' =>    '/static_data_top/comment_updated_at.dat',
        'tagUpdatedAtDatetime' =>         '/static_data_top/tag_updated_at.dat',
        'topPageRankingData' =>           '/static_data_top/ranking_list.dat',
        'rankingArgDto' =>                '/static_data_top/ranking_arg_dto.dat',
        'recommendPageDto' =>             '/static_data_top/recommend_page_dto.dat',
        'tagList' =>                      '/static_data_top/tag_list.dat',
        'recommendStaticDataDir' =>       '/static_data_recommend/tag',
        'categoryStaticDataDir' =>        '/static_data_recommend/category',
        'officialStaticDataDir' =>        '/static_data_recommend/official',
    ];

    static function getStorageFilePath(string $storageFileName): string
    {
        return self::STORAGE_DIR[MimimalCmsConfig::$urlRoot] . self::STORAGE_FILES[$storageFileName];
    }

    /** @var array<string,string> */
    static array $dbName = [
        '' =>    'ocreview',
        '/tw' => 'ocreviewtw',
        '/th' => 'ocreviewth',
    ];
    const RANKING_HOUR_TABLE_NAME = 'statistics_ranking_hour';
    const RANKING_DAY_TABLE_NAME =  'statistics_ranking_hour24';
    const RANKING_WEEK_TABLE_NAME = 'statistics_ranking_week';

    /** @var array<string,string> */
    static array $rankingPositionDbName = [
        '' =>    'ranking',
        '/tw' => 'rankingtw',
        '/th' => 'rankingth',
    ];

    // TODO:多言語対応
    /** @var array<string,string> */
    static array $userLogDbName = [
        '' =>    'userlog',
        '/tw' => 'userlog',
        '/th' => 'userlog',
    ];

    // TODO:多言語対応
    /** @var array<string,string> */
    static array $commentDbName = [
        '' =>    'comment',
        '/tw' => 'commenttw',
        '/th' => 'commentth',
    ];

    const DEFAULT_OPENCHAT_IMG_URL = [
        '0h6tJf0hQsaVt3H0eLAsAWDFheczgHd3wTCTx2eApNKSoefHNVGRdwfgxbdgUMLi8MSngnPFMeNmpbLi8MSngnPFMeNmpbLi8MSngnOA',
        '0h6tJf8QWOaVt3H0eLAsEWDFheczgHd3wTCTx2eApNKSoefHNVGRdwfgxbdgUMLi8MSngnPFMeNmpbLi8MSngnPFMeNmpbLi8MSngnOQ',
        '0h6tJfJfGJaVt3H0eLAsAWDFheczgHd3wTCTx2eApNKSoefHNVGRdwfgxbdgUMLi8MSngnPFMeNmpbLi8MSngnPFMeNmpbLi8MSngnPg',
        '0h6tJfahRYaVt3H0eLAsAWDFheczgHd3wTCTx2eApNKSoefHNVGRdwfgxbdgUMLi8MSngnPFMeNmpbLi8MSngnPFMeNmpbLi8MSngnPQ',
        '0h6tJfzfJQaVt3H0eLAsAWDFheczgHd3wTCTx2eApNKSoefHNVGRdwfgxbdgUMLi8MSngnPFMeNmpbLi8MSngnPFMeNmpbLi8MSngnPw',
    ];
    const DEFAULT_OPENCHAT_IMG_URL_HASH = [
        '2AtTNcODU67',
        '3SXDWf2OXqcY',
        '2pPOKy2ldZWl',
        '3y6jWliwCg1',
        '4DceVI1KwU1k',
    ];
    const ADD_OPEN_CHAT_DEFAULT_OPENCHAT_IMG_URL_HASH = '2AtTNcODU67';

    const DAILY_UPDATE_EXCEPTION_ERROR_CODE = 1001;
}
