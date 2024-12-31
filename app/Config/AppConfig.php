<?php

namespace App\Config;

use App\Config\Shadow\DatabaseConfig;

class AppConfig
{
    const RankingHourTable = 'statistics_ranking_hour';
    const RankingDayTable =  'statistics_ranking_hour24';
    const RankingWeekTable = 'statistics_ranking_week';
    const OpenChatTable =    'open_chat';

    const RECOMMEND_LIST_LIMIT =    50;
    const MIN_MEMBER_DIFF_HOUR =    3;
    const MIN_MEMBER_DIFF_H24 =     8;
    const MIN_MEMBER_DIFF_WEEK =    10;

    const SITE_ICON_FILE_PATH = 'assets/icon-192x192.png';
    const DEFAULT_OGP_IMAGE_FILE_PATH = 'assets/ogp.png';

    const GTM_ID = 'GTM-NTK2GPTF';

    const ETAG_ARG = [300, 3600 * 24, false];

    const LINE_URL = 'https://line.me/ti/g2/';

    const LINE_APP_URL = 'line://ti/g2/';
    const LINE_APP_SUFFIX = '?utm_source=line-openchat-seo&utm_medium=category&utm_campaign=default';

    const OPENCHAT_IMG_PATH = 'oc-img';
    const OPENCHAT_IMG_PREVIEW_PATH = 'preview';
    const OPENCHAT_IMG_PREVIEW_SUFFIX = '_p';

    const OPEN_CHAT_LIST_LIMIT =      100;
    const MY_LIST_LIMIT =             50;
    const RECENT_COMMENT_LIST_LIMIT = 50;
    const TOP_RANKING_LIST_LIMIT =    5;

    const CRON_START_MINUTE = 30;
    const CRON_MERGER_HOUR_RANGE_START = 23;
    const CRON_MERGER_HOUR_RANGE_END = 0;

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

    // TODO:多言語対応
    const LINE_OPEN_URL = 'https://openchat.line.me/jp/cover/';
    const LINE_OPEN_URL_SUFFIX = '?utm_source=line-openchat-seo&utm_medium=category&utm_campaign=default';

    public static array $OPEN_CHAT_CATEGORY = [];

    const ROOT_PATH =   __DIR__ . '/../../';
    const SITEMAP_DIR = __DIR__ . '/../../public/sitemap.xml';

    const STORAGE_DIR = [
        '' =>    __DIR__ . '/../../storage/ja',
        '/tw' => __DIR__ . '/../../storage/tw',
        '/th' => __DIR__ . '/../../storage/th',
    ];
    const STORAGE_FILES = [
        'addCronLogDest' =>               '/logs/cron.log',
        'sqliteStatisticsDb' =>           '/SQLite/statistics/statistics.db',
        'sqliteRankingPositionDb' =>      '/SQLite/ranking_position/ranking_position.db',
        'openChatSubCategories' =>        '/open_chat_sub_categories/subcategories.json',
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

    const DB_CONFIG_CLASS = [
        '' =>    DatabaseConfig::class,
        '/tw' => DatabaseConfigTw::class,
        '/th' => DatabaseConfigTh::class,
    ];
    const RANKING_POSITION_DB_CONFIG_CLASS = [
        '' =>    RankingPositionDBConfig::class,
        '/tw' => RankingPositionDBConfigTw::class,
        '/th' => RankingPositionDBConfigTh::class,
    ];
    
    const FURIGANA_CACHE_DIR = __DIR__ . '/../../storage/furigana';
}
