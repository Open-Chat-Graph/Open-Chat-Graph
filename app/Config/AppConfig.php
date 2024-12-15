<?php

namespace App\Config;

class AppConfig
{
    const RankingHourTable = 'statistics_ranking_hour';
    const RankingDayTable = 'statistics_ranking_hour24';
    const RankingWeekTable = 'statistics_ranking_week';
    const OpenChatTable = 'open_chat';

    const RECOMMEND_LIST_LIMIT = 50;
    const MIN_MEMBER_DIFF_HOUR = 3;
    const MIN_MEMBER_DIFF_H24 = 8;
    const MIN_MEMBER_DIFF_WEEK = 10;

    const SITE_ICON_FILE_PATH = 'assets/icon-192x192.png';
    const DEFAULT_OGP_IMAGE_FILE_PATH = 'assets/ogp.png';

    const GTM_ID = 'GTM-NTK2GPTF';

    const DEVICE_COOKIE_EXPIRES = 60 * 60 * 24 * 30;

    const ETAG_ARG = [300, 3600 * 24, false];

    const LINE_URL = 'https://line.me/ti/g2/';

    const LINE_APP_URL = 'line://ti/g2/';
    const LINE_APP_SUFFIX = '?utm_source=line-openchat-seo&utm_medium=category&utm_campaign=default';

    const OPENCHAT_IMG_PATH = 'oc-img';
    const OPENCHAT_IMG_PREVIEW_PATH = 'preview';
    const OPENCHAT_IMG_PREVIEW_SUFFIX = '_p';

    const OPEN_CHAT_LIST_LIMIT = 100;
    const MY_LIST_LIMIT = 50;
    const RECENT_COMMENT_LIST_LIMIT = 50;

    const TOP_RANKING_LIST_LIMIT = 5;
    const TOP_MENBER_RANKING_EXCLUDE_ID = [145145, 3454];

    const CRON_EXECUTE_COUNT = 500;

    const CRON_START_MINUTE = 30;
    const CRON_MERGER_HOUR_RANGE_START = 23;
    const CRON_MERGER_HOUR_RANGE_END = 0;

    const LINE_OPEN_URL = 'https://openchat.line.me/jp/cover/';
    const LINE_OPEN_URL_SUFFIX = '?utm_source=line-openchat-seo&utm_medium=category&utm_campaign=default';

    const ROOT_PATH = __DIR__ . '/../../';

    public static array $OPEN_CHAT_CATEGORY = [];

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

    const SITEMAP_DIR = __DIR__ . '/../../public/sitemap.xml';

    public static string $DAILY_CRON_UPDATED_AT_DATE = '';
    public static string $HOURLY_CRON_UPDATED_AT_DATETIME = '';
    public static string $HOURLY_REAL_UPDATED_AT_DATETIME =  '';

    const COMMENT_UPDATED_AT_MICROTIME = __DIR__ . '/../../storage/static_data_top/comment_updated_at.dat';
    const TAG_UPDATED_AT_DATETIME = __DIR__ . '/../../storage/static_data_top/tag_updated_at.dat';

    const OPEN_CHAT_SUB_CATEGORIES_FILE_PATH = __DIR__ . '/../../storage/open_chat_sub_categories/subcategories.json';
    const OPEN_CHAT_SUB_CATEGORIES_TAG_FILE_PATH = __DIR__ . '/../../storage/open_chat_sub_categories/subcategories_tag.json';

    public static string $OPEN_CHAT_RANKING_POSITION_DIR = '';
    public static string $OPEN_CHAT_RISING_POSITION_DIR = '';
    public static string $OPEN_CHAT_HOUR_FILTER_ID_DIR = '';

    const OPEN_CHAT_ID_DATA_FILE_PATH = __DIR__ . '/../../storage/OpenChatBackupData';
    const COMMENT_DATA_FILE_PATH = __DIR__ . '/../../storage/CommentBackupData';
    const ACCREDITATION_DATA_FILE_PATH = __DIR__ . '/../../storage/AccreditationBackupData';

    public static string $DatabaseConfigClass = '';
    public static string $RankingPositionDBConfigClass = '';

    public static string $SQLiteRankingPositionDbfile = '';
    public static string $SQLiteStatisticsDbfile = '';
}
