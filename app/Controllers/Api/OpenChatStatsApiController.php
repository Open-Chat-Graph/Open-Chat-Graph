<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\Statistics\Dto\StatisticsChartDto;
use App\Services\Statistics\StatisticsChartArrayService;

/**
 * ルーム個別ページの統計チャートデータ（メンバー数推移・期間タブ可用性）を返す Api。
 *
 * graph(React) が初回ロード時にこれを fetch する。これにより bot が叩く /oc 本体の
 * サーバーレンダリングから統計の重い SQLite 読み取り（member/OHLC/順位）を外す。
 * （順位 OHLC・メンバー OHLC・時間別順位は RankingPositionApiController が担当）
 */
class OpenChatStatsApiController
{
    function stats(
        StatisticsChartArrayService $statisticsChartArrayService,
        int $open_chat_id,
        int $category,
    ) {
        $dto = $statisticsChartArrayService->buildStatisticsChartArray(
            $open_chat_id,
            $category > 0 ? $category : null
        );
        if (!$dto) {
            $dto = new StatisticsChartDto(
                (new \DateTime('-1day'))->format('Y-m-d'),
                (new \DateTime('now'))->format('Y-m-d')
            );
        }

        return response($dto);
    }
}
