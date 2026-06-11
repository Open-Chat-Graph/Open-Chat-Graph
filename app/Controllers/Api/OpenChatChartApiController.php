<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\Chart\OpenChatChartApiService;

/**
 * ルーム個別ページの統計グラフデータを返す Api。
 *
 * graph(React) が表示ビュー（期間×順位種別×カテゴリ×モード）をクエリで指定し、
 * 描画に必要な系列データを1リクエストで取得する。初回ロードは meta=1 を付けて
 * タブ・ボタン出し分け用の可用性メタデータも同時に受け取る。
 * （bot が叩く /oc 本体のサーバーレンダリングから統計の重い SQLite 読み取りを外している）
 */
class OpenChatChartApiController
{
    function chart(
        OpenChatChartApiService $openChatChartApiService,
        int $open_chat_id,
        int $category,
        string $span,
        string $sort,
        string $scope,
        string $mode,
        int $meta,
    ) {
        return response($openChatChartApiService->buildChartResponse(
            $open_chat_id,
            $category,
            $span,
            $sort,
            $scope,
            $mode,
            $meta === 1,
        ));
    }
}
