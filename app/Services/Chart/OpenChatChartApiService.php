<?php

declare(strict_types=1);

namespace App\Services\Chart;

use App\Models\Repositories\RankingPosition\RankingPositionOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\RankingPosition\RankingPositionChartArrayService;
use App\Services\RankingPosition\RankingPositionHourChartArrayService;
use App\Services\Statistics\ChartMeta\ChartMetaBuilder;
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
 *
 * series（カンマ区切り: member,position,memberOhlc,positionOhlc）を指定すると、共通の date 軸と
 * 要求された層だけを返す（後方互換のため series 未指定時は従来のレスポンスを完全維持する）。
 */
class OpenChatChartApiService
{
    /** series で要求できる層 */
    private const SERIES_MEMBER = 'member';
    private const SERIES_POSITION = 'position';
    private const SERIES_MEMBER_OHLC = 'memberOhlc';
    private const SERIES_POSITION_OHLC = 'positionOhlc';

    function __construct(
        private StatisticsChartArrayService $statisticsChartArrayService,
        private RankingPositionChartArrayService $rankingPositionChartArrayService,
        private RankingPositionHourChartArrayService $rankingPositionHourChartArrayService,
        private StatisticsOhlcRepositoryInterface $statisticsOhlcRepository,
        private RankingPositionOhlcRepositoryInterface $rankingPositionOhlcRepository,
        private ChartMetaBuilder $chartMetaBuilder,
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
     * @param ?string $series   カンマ区切りの層指定（member,position,memberOhlc,positionOhlc）。
     *                          指定時は共通 date 軸＋要求層だけを返す（メタは付けない）。
     *                          未指定（null/空）は従来の span/mode/sort に応じたレスポンスを返す。
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
        ?string $series = null,
    ): array {
        $positionCategory = ($scope === 'in' && $category > 0) ? $category : 0;
        $type = RankingType::from($sort === 'none' ? 'ranking' : $sort);

        // 範囲モードの判定: from/to が両方とも妥当(Y-m-d 形式, from<=to)なときだけ。
        // 片方/不正/未指定は従来の全期間挙動を厳密に維持する（リグレッション防止）。
        [$from, $to] = $this->resolveDateRange($fromDate, $toDate);

        // series 指定時は「層単位の最小取得」モード（後方互換のため従来経路とは完全に分ける）。
        $layers = $this->resolveSeries($series);
        if ($layers !== null) {
            return $this->buildSeriesResponse($open_chat_id, $positionCategory, $sort, $type, $layers, $from, $to);
        }

        // ここから下は従来どおり（series 未指定）。
        // meta=1 は埋め込み無し新規室のフォールバック用で範囲取得と同時には来ない想定のため、
        // withMeta が要求されたら従来の全期間+メタ動作を優先し、範囲は無視する。
        $useRange = !$withMeta && $from !== null && $to !== null;

        // 日次系列はローソク足・日次ビューの描画に必要（純粋なhourビューでは取得しない）。
        // メタは ChartMetaBuilder（ライブ）で別途組むため、ここでは可用性を計算しない。
        $statsDto = null;
        if ($mode === 'candlestick' || $span === 'day' || $withMeta) {
            $statsDto = $this->statisticsChartArrayService->buildStatisticsChartArray(
                $open_chat_id,
                $category > 0 ? $category : null,
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
            // 埋め込み(cron, ChartMetaBuilder の一括取得)と同一の Builder でライブ計算し、乖離させない。
            $response['meta'] = $this->chartMetaBuilder->build($open_chat_id, $category > 0 ? $category : null)
                ?? $this->emptyMeta($statsDto);
        }

        return $response;
    }

    /**
     * series 指定時の層単位レスポンスを組み立てる。
     *
     * 共通の date 軸（member 統計の from/to もしくは全期間から生成。順位層もこの軸に整合させる）に、
     * 要求された層だけを載せて返す。メタは付けない。
     *
     * @param array<string, true> $layers resolveSeries の結果（要求された層の集合）
     * @return array{date: string[], member?: (int|null)[], time?: array, position?: array, totalCount?: array, memberOhlc?: array, positionOhlc?: array}
     */
    private function buildSeriesResponse(
        int $open_chat_id,
        int $positionCategory,
        string $sort,
        RankingType $type,
        array $layers,
        ?string $from,
        ?string $to,
    ): array {
        // date 軸は全層で共通。member 統計の範囲（from/to もしくは実データ全期間）から生成する。
        // 統計レコードが無ければ空（date 空・各層空）で返す。
        $statsDto = $this->statisticsChartArrayService->buildStatisticsChartArray(
            $open_chat_id,
            null,
            $from,
            $to,
        );

        if ($statsDto === false) {
            $response = ['date' => []];
            if (isset($layers[self::SERIES_MEMBER])) {
                $response['member'] = [];
            }
            if (isset($layers[self::SERIES_POSITION])) {
                $response['time'] = [];
                $response['position'] = [];
                $response['totalCount'] = [];
            }
            if (isset($layers[self::SERIES_MEMBER_OHLC])) {
                $response['memberOhlc'] = $this->statisticsOhlcRepository->getOhlcDateAsc($open_chat_id, $from, $to);
            }
            if (isset($layers[self::SERIES_POSITION_OHLC])) {
                $response['positionOhlc'] = $sort !== 'none'
                    ? $this->rankingPositionOhlcRepository->getOhlcDateAsc($open_chat_id, $positionCategory, $type, $from, $to)
                    : [];
            }
            return $response;
        }

        $response = ['date' => $statsDto->date];

        if (isset($layers[self::SERIES_MEMBER])) {
            $response['member'] = $statsDto->member;
        }

        if (isset($layers[self::SERIES_POSITION])) {
            // 順位層は member と同じ date 軸（statsDto.start/end）で生成し、from/to を DB 絞り込みに渡す。
            $position = $this->buildPositionLayer($open_chat_id, $positionCategory, $sort, $type, $statsDto, $from, $to);
            $response['time'] = $position['time'];
            $response['position'] = $position['position'];
            $response['totalCount'] = $position['totalCount'];
        }

        if (isset($layers[self::SERIES_MEMBER_OHLC])) {
            $response['memberOhlc'] = $this->statisticsOhlcRepository->getOhlcDateAsc($open_chat_id, $from, $to);
        }

        if (isset($layers[self::SERIES_POSITION_OHLC])) {
            $response['positionOhlc'] = $sort !== 'none'
                ? $this->rankingPositionOhlcRepository->getOhlcDateAsc($open_chat_id, $positionCategory, $type, $from, $to)
                : [];
        }

        return $response;
    }

    /**
     * 順位系列（time/position/totalCount）を date 軸に整合させて取得する。
     * buildDaySeries の順位部分と同じロジック（日次クロール未到達なら順位なし）。
     *
     * @return array{time: array, position: array, totalCount: array}
     */
    private function buildPositionLayer(
        int $open_chat_id,
        int $positionCategory,
        string $sort,
        RankingType $type,
        StatisticsChartDto $statsDto,
        ?string $from,
        ?string $to,
    ): array {
        $empty = ['time' => [], 'position' => [], 'totalCount' => []];

        // 順位データは日次クロールで更新されるため、統計の開始日が最終実行日より後の場合は順位なし
        $hasPosition = $sort !== 'none'
            && $statsDto->date
            && strtotime($statsDto->startDate) <= strtotime($this->fileStorage->getContents('@dailyCronUpdatedAtDate'));

        if (!$hasPosition) {
            return $empty;
        }

        $positionDto = $this->rankingPositionChartArrayService->getRankingPositionChartArray(
            $type,
            $open_chat_id,
            $positionCategory,
            new \DateTime($statsDto->startDate),
            new \DateTime($statsDto->endDate),
            $from,
            $to
        );

        return [
            'time' => $positionDto->time,
            'position' => $positionDto->position,
            'totalCount' => $positionDto->totalCount,
        ];
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
     * series クエリを「要求された層の集合」に正規化する。
     *
     * - null/空文字（未指定）は null を返し、呼び出し側で従来レスポンスにフォールバックさせる
     * - カンマ区切りで member,position,memberOhlc,positionOhlc のみ受理（未知の値は無視）
     * - 妥当な層が1つも無ければ null（従来挙動にフォールバック。空レスポンスにはしない）
     *
     * @return array<string, true>|null
     */
    private function resolveSeries(?string $series): ?array
    {
        if ($series === null || $series === '') {
            return null;
        }

        $valid = [
            self::SERIES_MEMBER => true,
            self::SERIES_POSITION => true,
            self::SERIES_MEMBER_OHLC => true,
            self::SERIES_POSITION_OHLC => true,
        ];

        $layers = [];
        foreach (explode(',', $series) as $name) {
            $name = trim($name);
            if (isset($valid[$name])) {
                $layers[$name] = true;
            }
        }

        return $layers === [] ? null : $layers;
    }

    /**
     * 統計レコードが無い等で ChartMetaBuilder が null を返したときの空メタ。
     * 従来は statsDto（フォールバックの空 DTO）の既定値がそのまま meta に出ていたため、
     * その形（全 false・空日付）を維持する。
     *
     * @return array{startDate: string, endDate: string, dateCount: int, hourAvailability: bool, positionAvailability: array, ohlcAvailability: array}
     */
    private function emptyMeta(?StatisticsChartDto $statsDto): array
    {
        $dto = $statsDto ?? new StatisticsChartDto('', '');

        return [
            'startDate' => $dto->startDate,
            'endDate' => $dto->endDate,
            'dateCount' => count($dto->date),
            'hourAvailability' => $dto->hourAvailability,
            'positionAvailability' => $dto->positionAvailability,
            'ohlcAvailability' => $dto->ohlcAvailability,
        ];
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
