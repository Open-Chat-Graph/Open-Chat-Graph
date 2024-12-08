<?php

use App\Config\AppConfig;
use App\Config\RankingPositionDBConfig;
use App\Config\Shadow\DatabaseConfig;
use App\Config\DatabaseConfigTw;
use App\Config\DatabaseConfigTh;
use App\Config\RankingPositionDBConfigTw;
use App\Config\RankingPositionDBConfigTh;

if (URL_ROOT === '/tw') {
    AppConfig::$DAILY_CRON_UPDATED_AT_DATE =        __DIR__ . '/../../storage/tw/static_data_top/daily_updated_at.dat';
    AppConfig::$HOURLY_CRON_UPDATED_AT_DATETIME =   __DIR__ . '/../../storage/tw/static_data_top/hourly_updated_at.dat';
    AppConfig::$HOURLY_REAL_UPDATED_AT_DATETIME =   __DIR__ . '/../../storage/tw/static_data_top/real_updated_at.dat';

    AppConfig::$OPEN_CHAT_RANKING_POSITION_DIR =    __DIR__ . '/../../storage/tw/ranking_position/ranking';
    AppConfig::$OPEN_CHAT_RISING_POSITION_DIR =     __DIR__ . '/../../storage/tw/ranking_position/rising';
    AppConfig::$OPEN_CHAT_HOUR_FILTER_ID_DIR =      __DIR__ . '/../../storage/tw/ranking_position/filter.dat';

    AppConfig::$DatabaseConfigClass =               DatabaseConfigTw::class;
    AppConfig::$RankingPositionDBConfigClass =      RankingPositionDBConfigTw::class;
} elseif (URL_ROOT === '/th') {
    AppConfig::$DAILY_CRON_UPDATED_AT_DATE =        __DIR__ . '/../../storage/th/static_data_top/daily_updated_at.dat';
    AppConfig::$HOURLY_CRON_UPDATED_AT_DATETIME =   __DIR__ . '/../../storage/th/static_data_top/hourly_updated_at.dat';
    AppConfig::$HOURLY_REAL_UPDATED_AT_DATETIME =   __DIR__ . '/../../storage/th/static_data_top/real_updated_at.dat';

    AppConfig::$OPEN_CHAT_RANKING_POSITION_DIR =    __DIR__ . '/../../storage/th/ranking_position/ranking';
    AppConfig::$OPEN_CHAT_RISING_POSITION_DIR =     __DIR__ . '/../../storage/th/ranking_position/rising';
    AppConfig::$OPEN_CHAT_HOUR_FILTER_ID_DIR =      __DIR__ . '/../../storage/th/ranking_position/filter.dat';

    AppConfig::$DatabaseConfigClass =               DatabaseConfigTh::class;
    AppConfig::$RankingPositionDBConfigClass =      RankingPositionDBConfigTh::class;
} else {
    AppConfig::$DAILY_CRON_UPDATED_AT_DATE =        __DIR__ . '/../../storage/static_data_top/daily_updated_at.dat';
    AppConfig::$HOURLY_CRON_UPDATED_AT_DATETIME =   __DIR__ . '/../../storage/static_data_top/hourly_updated_at.dat';
    AppConfig::$HOURLY_REAL_UPDATED_AT_DATETIME =   __DIR__ . '/../../storage/static_data_top/real_updated_at.dat';

    AppConfig::$OPEN_CHAT_RANKING_POSITION_DIR =    __DIR__ . '/../../storage/ranking_position/ranking';
    AppConfig::$OPEN_CHAT_RISING_POSITION_DIR =     __DIR__ . '/../../storage/ranking_position/rising';
    AppConfig::$OPEN_CHAT_HOUR_FILTER_ID_DIR =      __DIR__ . '/../../storage/ranking_position/filter.dat';

    AppConfig::$DatabaseConfigClass =               DatabaseConfig::class;
    AppConfig::$RankingPositionDBConfigClass =      RankingPositionDBConfig::class;
}
