<?php

declare(strict_types=1);

namespace App\Services\Chart;

use App\Models\Repositories\RankingPosition\RankingPositionOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\RankingPosition\RankingPositionChartArrayService;
use App\Services\RankingPosition\RankingPositionHourChartArrayService;
use App\Services\Statistics\Dto\StatisticsChartDto;
use App\Services\Statistics\StatisticsChartArrayService;
use App\Services\Storage\FileStorageInterface;

/**
 * ルーム個別ページの統計グラフAPIレスポンスを組み立てるサービス。
 *
 * グラフの表示ビュー（期間×順位種別×カテゴリ×モード）はどれも
 * 「初期表示する系列データが何か」が違うだけなので、ビューをパラメータで受け取り
 * 描画に必要な系列を1レスポンスで返す。初回ロード（meta=1）はタブ・ボタンの
 * 出し分けに使う可用性メタデータを同梱し、グラフ初期化を1リクエストで完結させる。
 */
class OpenChatChartApiService
{
    function __construct(
        private StatisticsChartArrayService $statisticsChartArrayService,
        private RankingPositionChartArrayService $rankingPositionChartArrayService,
        private RankingPositionHourChartArrayService $rankingPositionHourChartArrayService,
        private StatisticsOhlcRepositoryInterface $statisticsOhlcRepository,
        private RankingPositionOhlcRepositoryInterface $rankingPositionOhlcRepository,
        private FileStorageInterface $fileStorage,
    ) {}

    /**
     * @param int    $category 部屋のカテゴリID（未掲載・その他は0）
     * @param string $span     hour: 最新24時間 / day: 日次
     * @param string $sort     順位の重ね描き種別（none: メンバー数のみ）
     * @param string $scope    順位の集計範囲（in: カテゴリ内 / all: すべて）
     * @param string $mode     line: 折れ線 / candlestick: ローソク足
     * @param bool   $withMeta 初回ロード時のみtrue（タブ可用性メタを同梱）
     */
    function buildChartResponse(
        int $open_chat_id,
        int $category,
        string $span,
        string $sort,
        string $scope,
        string $mode,
        bool $withMeta,
    ): array {
        $positionCategory = ($scope === 'in' && $category > 0) ? $category : 0;
        $type = RankingType::from($sort === 'none' ? 'ranking' : $sort);

        // 日次系列はローソク足・日次ビューの描画とメタ生成に必要（純粋なhourビューでは取得しない）
        $statsDto = null;
        if ($mode === 'candlestick' || $span === 'day' || $withMeta) {
            $statsDto = $this->statisticsChartArrayService->buildStatisticsChartArray(
                $open_chat_id,
                $category > 0 ? $category : null,
                $withMeta,
            ) ?: new StatisticsChartDto(
                (new \DateTime('-1day'))->format('Y-m-d'),
                (new \DateTime('now'))->format('Y-m-d')
            );
        }

        if ($mode === 'candlestick') {
            $response = $this->buildCandlestickSeries($open_chat_id, $positionCategory, $sort, $type, $statsDto);
        } elseif ($span === 'hour') {
            $response = $this->buildHourSeries($open_chat_id, $positionCategory, $sort, $type);
        } else {
            $response = $this->buildDaySeries($open_chat_id, $positionCategory, $sort, $type, $statsDto);
        }

        if ($withMeta) {
            $response['meta'] = [
                'startDate' => $statsDto->startDate,
                'endDate' => $statsDto->endDate,
                'dateCount' => count($statsDto->date),
                'hourAvailability' => $statsDto->hourAvailability,
                'positionAvailability' => $statsDto->positionAvailability,
                'ohlcAvailability' => $statsDto->ohlcAvailability,
            ];
        }

        return $response;
    }

    private function buildHourSeries(int $open_chat_id, int $positionCategory, string $sort, RankingType $type): array
    {
        $dto = $this->rankingPositionHourChartArrayService->getPositionHourChartArray(
            $type,
            $open_chat_id,
            $positionCategory
        );

        return [
            'date' => $dto->date,
            'member' => $dto->member,
            'time' => [],
            'position' => $sort !== 'none' ? $dto->position : [],
            'totalCount' => $sort !== 'none' ? $dto->totalCount : [],
        ];
    }

    private function buildDaySeries(
        int $open_chat_id,
        int $positionCategory,
        string $sort,
        RankingType $type,
        StatisticsChartDto $statsDto,
    ): array {
        $response = [
            'date' => $statsDto->date,
            'member' => $statsDto->member,
            'time' => [],
            'position' => [],
            'totalCount' => [],
        ];

        // 順位データは日次クロールで更新されるため、統計の開始日が最終実行日より後の場合は順位なしで返す
        $hasPosition = $sort !== 'none'
            && $statsDto->date
            && strtotime($statsDto->startDate) <= strtotime($this->fileStorage->getContents('@dailyCronUpdatedAtDate'));

        if ($hasPosition) {
            $positionDto = $this->rankingPositionChartArrayService->getRankingPositionChartArray(
                $type,
                $open_chat_id,
                $positionCategory,
                new \DateTime($statsDto->startDate),
                new \DateTime($statsDto->endDate)
            );

            $response['time'] = $positionDto->time;
            $response['position'] = $positionDto->position;
            $response['totalCount'] = $positionDto->totalCount;
        }

        return $response;
    }

    private function buildCandlestickSeries(
        int $open_chat_id,
        int $positionCategory,
        string $sort,
        RankingType $type,
        StatisticsChartDto $statsDto,
    ): array {
        $response = [
            'date' => $statsDto->date,
            'member' => $statsDto->member,
            'time' => [],
            'position' => [],
            'totalCount' => [],
            'memberOhlc' => $this->statisticsOhlcRepository->getOhlcDateAsc($open_chat_id),
        ];

        if ($sort !== 'none') {
            $response['positionOhlc'] = $this->rankingPositionOhlcRepository->getOhlcDateAsc(
                $open_chat_id,
                $positionCategory,
                $type
            );
        }

        return $response;
    }
}
