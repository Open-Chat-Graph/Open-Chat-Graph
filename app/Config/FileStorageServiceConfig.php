<?php

namespace App\Config;

class FileStorageServiceConfig
{
    /** @var array<string,string> */
    static array $storageDir = [
        '' =>    __DIR__ . '/../../storage/ja',
        '/tw' => __DIR__ . '/../../storage/tw',
        '/th' => __DIR__ . '/../../storage/th',
    ];

    /** @var array<string,string> */
    static array $sqliteSchemaFiles = [
        'sqliteStatisticsOhlcDb' =>      __DIR__ . '/../../setup/schema/sqlite/statistics_ohlc.sql',
        'sqliteRankingPositionOhlcDb' => __DIR__ . '/../../setup/schema/sqlite/ranking_position_ohlc.sql',
    ];

    /** @var array<string,string> */
    static array $storageFiles = [
        'addCronLogDest' =>               '/logs/cron.log',
        'sqliteStatisticsDb' =>           '/SQLite/statistics/statistics.db',
        'sqliteStatisticsOhlcDb' =>       '/SQLite/statistics_ohlc/statistics_ohlc.db',
        'sqliteRankingPositionOhlcDb' =>  '/SQLite/ranking_position_ohlc/ranking_position_ohlc.db',
        'sqliteRankingPositionDb' =>      '/SQLite/ranking_position/ranking_position.db',
        'openChatSubCategories' =>        '/open_chat_sub_categories/subcategories.json',
        'openChatSubCategoriesSample' =>  '/open_chat_sub_categories/sample/subcategories.json',
        'openChatSubCategoriesTag' =>     '/open_chat_sub_categories/subcategories_tag.json',
        'openChatRankingPositionDir' =>   '/ranking_position/ranking',
        'openChatRisingPositionDir' =>    '/ranking_position/rising',
        'openChatHourFilterId' =>         '/ranking_position/filter.dat',
        'filterMemberChange' =>           '/ranking_position/filter_member_change.dat',
        'filterNewRooms' =>               '/ranking_position/filter_new_rooms.dat',
        'filterWeeklyUpdate' =>           '/ranking_position/filter_weekly_update.dat',
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
}
