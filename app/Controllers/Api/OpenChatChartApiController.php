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
 *
 * from/to (?from=YYYY-MM-DD&to=YYYY-MM-DD) を両方妥当な値で付けると日次系列を
 * その範囲だけに絞って返す（フロントが見えている窓だけ取得するための土台）。
 * 未指定・不正・片方のみ・span=hour・meta=1 のときは従来どおり全期間を返す。
 *
 * series (?series=member,position,memberOhlc,positionOhlc) を付けると、共通 date 軸＋要求された
 * 層だけを返す（層単位の最小取得）。未指定時は従来どおり span/mode/sort に応じたレスポンスを返す。
 */
class OpenChatChartApiController
{
    /**
     * from/to/series はルート未定義の任意クエリ。Reception の引数バインドは未指定時に null を渡すため
     * nullable で受ける（妥当性チェックと空文字→null 正規化はサービス側で行う）。
     */
    function chart(
        OpenChatChartApiService $openChatChartApiService,
        int $open_chat_id,
        int $category,
        string $span,
        string $sort,
        string $scope,
        string $mode,
        int $meta,
        ?string $from = null,
        ?string $to = null,
        ?string $series = null,
    ) {
        return response($openChatChartApiService->buildChartResponse(
            $open_chat_id,
            $category,
            $span,
            $sort,
            $scope,
            $mode,
            $meta === 1,
            $from,
            $to,
            $series,
        ));
    }
}
