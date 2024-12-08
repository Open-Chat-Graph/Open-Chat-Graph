<?php

use App\Config\AppConfig;
use App\Config\Shadow\DatabaseConfig;
use App\Config\DatabaseConfigTh;
use App\Config\DatabaseConfigTw;
use App\Config\RankingPositionDBConfig;
use App\Config\RankingPositionDBConfigTh;
use App\Config\RankingPositionDBConfigTw;

(function () {
    if (URL_ROOT === '/tw') {
        $STORAGE_DIR = __DIR__ . '/../../storage/tw';

        AppConfig::$DatabaseConfigClass = DatabaseConfigTw::class;
        AppConfig::$RankingPositionDBConfigClass = RankingPositionDBConfigTw::class;
    } elseif (URL_ROOT === '/th') {
        $STORAGE_DIR = __DIR__ . '/../../storage/th';

        AppConfig::$DatabaseConfigClass = DatabaseConfigTh::class;
        AppConfig::$RankingPositionDBConfigClass = RankingPositionDBConfigTh::class;
    } else {
        $STORAGE_DIR = __DIR__ . '/../../storage';

        AppConfig::$DatabaseConfigClass = DatabaseConfig::class;
        AppConfig::$RankingPositionDBConfigClass = RankingPositionDBConfig::class;
    }

    AppConfig::$DAILY_CRON_UPDATED_AT_DATE =      $STORAGE_DIR . '/static_data_top/daily_updated_at.dat';
    AppConfig::$HOURLY_CRON_UPDATED_AT_DATETIME = $STORAGE_DIR . '/static_data_top/hourly_updated_at.dat';
    AppConfig::$HOURLY_REAL_UPDATED_AT_DATETIME = $STORAGE_DIR . '/static_data_top/real_updated_at.dat';

    AppConfig::$OPEN_CHAT_RANKING_POSITION_DIR =  $STORAGE_DIR . '/ranking_position/ranking';
    AppConfig::$OPEN_CHAT_RISING_POSITION_DIR =   $STORAGE_DIR . '/ranking_position/rising';
    AppConfig::$OPEN_CHAT_HOUR_FILTER_ID_DIR =    $STORAGE_DIR . '/ranking_position/filter.dat';

    AppConfig::$SQLiteStatisticsDbfile =          $STORAGE_DIR . '/SQLite/statistics/statistics.db';
    AppConfig::$SQLiteRankingPositionDbfile =     $STORAGE_DIR . '/SQLite/ranking_position/ranking_position.db';
})();
