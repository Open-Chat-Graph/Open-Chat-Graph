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
     * @param ?string $fromDate `Y-m-d` 取得範囲の開始日（$toDate と併用時のみ有効、空文字はnull扱い）
     * @param ?string $toDate   `Y-m-d` 取得範囲の終了日（$fromDate と併用時のみ有効、空文字はnull扱い）
     */
    function buildChartResponse(
        int $open_chat_id,
        int $category,
        string $span,
        string $sort,
        string $scope,
        string $mode,
        bool $withMeta,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): array {
        $positionCategory = ($scope === 'in' && $category > 0) ? $category : 0;
        $type = RankingType::from($sort === 'none' ? 'ranking' : $sort);

        // 範囲モードの判定: from/to が両方とも妥当(Y-m-d 形式, from<=to)なときだけ。
        // 片方/不正/未指定は従来の全期間挙動を厳密に維持する（リグレッション防止）。
        // meta=1 は埋め込み無し新規室のフォールバック用で範囲取得と同時には来ない想定のため、
        // withMeta が要求されたら従来の全期間+メタ動作を優先し、範囲は無視する。
        [$from, $to] = $this->resolveDateRange($fromDate, $toDate);
        $useRange = !$withMeta && $from !== null && $to !== null;

        // 日次系列はローソク足・日次ビューの描画とメタ生成に必要（純粋なhourビューでは取得しない）
        $statsDto = null;
        if ($mode === 'candlestick' || $span === 'day' || $withMeta) {
            $statsDto = $this->statisticsChartArrayService->buildStatisticsChartArray(
                $open_chat_id,
                $category > 0 ? $category : null,
                $withMeta,
                $useRange ? $from : null,
                $useRange ? $to : null,
            ) ?: new StatisticsChartDto(
                (new \DateTime('-1day'))->format('Y-m-d'),
                (new \DateTime('now'))->format('Y-m-d')
            );
        }

        if ($mode === 'candlestick') {
            $response = $this->buildCandlestickSeries($open_chat_id, $positionCategory, $sort, $type, $statsDto, $useRange ? $from : null, $useRange ? $to : null);
        } elseif ($span === 'hour') {
            // hour系列(最新24時間固定・MariaDB)は範囲化対象外。from/to は無視する。
            $response = $this->buildHourSeries($open_chat_id, $positionCategory, $sort, $type);
        } else {
            $response = $this->buildDaySeries($open_chat_id, $positionCategory, $sort, $type, $statsDto, $useRange ? $from : null, $useRange ? $to : null);
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
        ?string $from = null,
        ?string $to = null,
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
            // 範囲モードでは statsDto.start/end ではなく from/to を取得範囲(DB絞り込み)として渡す。
            // 日付軸の境界(generateDateArray)は range モードで statsDto.startDate/endDate が
            // from/to と一致しているため従来どおり statsDto を使う。
            $positionDto = $this->rankingPositionChartArrayService->getRankingPositionChartArray(
                $type,
                $open_chat_id,
                $positionCategory,
                new \DateTime($statsDto->startDate),
                new \DateTime($statsDto->endDate),
                $from,
                $to
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
        ?string $from = null,
        ?string $to = null,
    ): array {
        $response = [
            'date' => $statsDto->date,
            'member' => $statsDto->member,
            'time' => [],
            'position' => [],
            'totalCount' => [],
            'memberOhlc' => $this->statisticsOhlcRepository->getOhlcDateAsc($open_chat_id, $from, $to),
        ];

        if ($sort !== 'none') {
            $response['positionOhlc'] = $this->rankingPositionOhlcRepository->getOhlcDateAsc(
                $open_chat_id,
                $positionCategory,
                $type,
                $from,
                $to
            );
        }

        return $response;
    }

    /**
     * 入口の from/to を「範囲モードに使える妥当な Y-m-d ペア」へ正規化する。
     *
     * - 空文字は未指定(null)扱い
     * - 両方が妥当な Y-m-d 形式で from<=to のときだけ [from, to] を返す
     * - それ以外（片方のみ・不正な日付・from>to）は [null, null] を返し、
     *   呼び出し側で従来の全期間挙動にフォールバックさせる（不正値で 500 にしない）
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveDateRange(?string $fromDate, ?string $toDate): array
    {
        $from = ($fromDate !== null && $fromDate !== '') ? $fromDate : null;
        $to = ($toDate !== null && $toDate !== '') ? $toDate : null;

        if ($from === null || $to === null) {
            return [null, null];
        }

        if (!$this->isValidYmd($from) || !$this->isValidYmd($to) || $from > $to) {
            return [null, null];
        }

        return [$from, $to];
    }

    /** 厳密に Y-m-d 形式で実在する日付かを判定する（'2026-13-40' や 'abc' を弾く） */
    private function isValidYmd(string $value): bool
    {
        $dt = \DateTime::createFromFormat('!Y-m-d', $value);

        return $dt !== false && $dt->format('Y-m-d') === $value;
    }
}
