<?php

declare(strict_types=1);

namespace App\Services\Chart;

use App\Models\Repositories\RankingPosition\RankingPositionOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Services\OpenChat\Enum\RankingType;
use App\Services\RankingPosition\RankingPositionChartArrayService;
use App\Services\RankingPosition\RankingPositionHourChartArrayService;
use App\Services\StaticData\OcPageCacheDataBuilder;
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
        private OcPageCacheDataBuilder $cacheDataBuilder,
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

        // 日次統計は日次ビュー(折れ線)の描画とメタの空フォールバックに使う。
        // ローソク足は OHLC 専用軸(ohlcDate)だけで描くので日次統計は引かない（無駄な統計DBアクセスを避ける）。
        // メタは OcPageCacheDataBuilder（ライブ）で別途組むため、ここでは可用性を計算しない。
        $statsDto = null;
        if ($mode !== 'candlestick' && ($span === 'day' || $withMeta)) {
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
            $response = $this->buildCandlestickSeries($open_chat_id, $positionCategory, $sort, $type, $useRange ? $from : null, $useRange ? $to : null);
        } elseif ($span === 'hour') {
            // hour系列(最新24時間固定・MariaDB)は範囲化対象外。from/to は無視する。
            $response = $this->buildHourSeries($open_chat_id, $positionCategory, $sort, $type);
        } else {
            $response = $this->buildDaySeries($open_chat_id, $positionCategory, $sort, $type, $statsDto, $useRange ? $from : null, $useRange ? $to : null);
        }

        if ($withMeta) {
            // 埋め込み(cron, OcPageCacheDataBuilder の一括取得)と同一の Builder でライブ計算し、乖離させない。
            $response['meta'] = $this->cacheDataBuilder->build($open_chat_id, $category > 0 ? $category : null)
                ?? $this->emptyMeta($statsDto);
        }

        return $response;
    }

    /**
     * series 指定時の層単位レスポンスを組み立てる。要求された層だけを載せて返す（メタは付けない）。
     *
     * - member / position（折れ線）は共通の日次 date 軸に揃える。どちらかを要求されたときだけ
     *   `date` を1本載せ、統計(buildStatisticsChartArray)も取得する。
     * - OHLC（ローソク足）は OHLC 専用の `ohlcDate` 軸で返す（appendOhlcLayers）。日次 date とは別系列。
     *   OHLC だけの要求なら統計取得も `date` も無し（無駄な統計DBアクセスと date/ohlcDate の二重を避ける）。
     *
     * @param array<string, true> $layers resolveSeries の結果（要求された層の集合）
     * @return array{date?: string[], member?: (int|null)[], time?: array, position?: array, totalCount?: array, ohlcDate?: string[], memberOhlc?: array, positionOhlc?: array}
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
        $response = [];

        // member / position（折れ線）を要求されたときだけ、共通の日次 date 軸＋統計を取得する。
        $wantMember = isset($layers[self::SERIES_MEMBER]);
        $wantPosition = isset($layers[self::SERIES_POSITION]);
        if ($wantMember || $wantPosition) {
            $statsDto = $this->statisticsChartArrayService->buildStatisticsChartArray($open_chat_id, null, $from, $to);

            $response['date'] = $statsDto === false ? [] : $statsDto->date;

            if ($wantMember) {
                $response['member'] = $statsDto === false ? [] : $statsDto->member;
            }

            if ($wantPosition) {
                // 順位層は member と同じ date 軸（statsDto.start/end）で生成し、from/to を DB 絞り込みに渡す。
                $position = $statsDto === false
                    ? ['time' => [], 'position' => [], 'totalCount' => []]
                    : $this->buildPositionLayer($open_chat_id, $positionCategory, $sort, $type, $statsDto, $from, $to);
                // time は急上昇(rising)のみ返す（ランキングは終日時刻なし＝null配列になるので省く）。
                if ($type === RankingType::Rising) {
                    $response['time'] = $position['time'];
                }
                $response['position'] = $position['position'];
                $response['totalCount'] = $position['totalCount'];
            }
        }

        // OHLC（ローソク足）は ohlcDate 軸で別途載せる（日次 date とは独立）。
        $this->appendOhlcLayers(
            $response,
            $open_chat_id,
            $positionCategory,
            $sort,
            $type,
            isset($layers[self::SERIES_MEMBER_OHLC]),
            isset($layers[self::SERIES_POSITION_OHLC]),
            $from,
            $to,
        );

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

        // hour ビューは x 軸自体が時刻（最新24時間）なので、順位の時刻ラベル time は持たない。
        return [
            'date' => $dto->date,
            'member' => $dto->member,
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

            // time は急上昇(rising)のみ（ランキングは終日時刻なし＝null配列のため省く）
            if ($type === RankingType::Rising) {
                $response['time'] = $positionDto->time;
            }
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
        ?string $from = null,
        ?string $to = null,
    ): array {
        // ローソク足は OHLC 専用の ohlcDate 軸だけで描く。日次の date / member 折れ線は使わないので返さない
        // （member を引くための統計DBアクセスと、date と ohlcDate の二重を避ける）。
        $response = [];
        $this->appendOhlcLayers($response, $open_chat_id, $positionCategory, $sort, $type, true, $sort !== 'none', $from, $to);
        return $response;
    }

    /**
     * member / position の OHLC を「共通の ohlcDate 軸」に整合させて $response に載せる。
     *
     * OHLC は member 統計の日次 date 軸とは別系列（記録開始が後発で件数が少ない）なので、各要素に
     * date を持たせると date が二重・三重になる。そこで OHLC 専用の日付配列 ohlcDate を1本だけ返し、
     * memberOhlc / positionOhlc は ohlcDate と同順・同長の「date 抜き」値配列にする。
     *
     * - ohlcDate    … member OHLC の日付昇順（＝マスター軸）。member 未要求で position のみのときは position の日付。
     * - memberOhlc  … ohlcDate と 1:1（open/high/low/close_member）。
     * - positionOhlc… ohlcDate と 1:1。その日に順位OHLCが無ければ null（フロントは圏外0で描画）。
     *                 sort=none（順位を重ねない）のときは空配列。
     *
     * member / position いずれの OHLC も要求されていなければキーを足さない。
     */
    private function appendOhlcLayers(
        array &$response,
        int $open_chat_id,
        int $positionCategory,
        string $sort,
        RankingType $type,
        bool $wantMember,
        bool $wantPosition,
        ?string $from,
        ?string $to,
    ): void {
        $ohlcDate = null;

        if ($wantMember) {
            $rows = $this->statisticsOhlcRepository->getOhlcDateAsc($open_chat_id, $from, $to);
            $ohlcDate = array_column($rows, 'date');
            $response['memberOhlc'] = array_map(fn(array $r): array => [
                'open_member' => $r['open_member'],
                'high_member' => $r['high_member'],
                'low_member' => $r['low_member'],
                'close_member' => $r['close_member'],
            ], $rows);
        }

        if ($wantPosition) {
            if ($sort === 'none') {
                // 順位を重ねないビュー（メンバー数ローソク足のみ）
                $response['positionOhlc'] = [];
            } else {
                $prows = $this->rankingPositionOhlcRepository->getOhlcDateAsc($open_chat_id, $positionCategory, $type, $from, $to);

                if ($ohlcDate !== null) {
                    // member OHLC の ohlcDate に整合させる（順位OHLCの無い日は null＝圏外）
                    $pmap = [];
                    foreach ($prows as $r) {
                        $pmap[$r['date']] = [
                            'open_position' => $r['open_position'],
                            'high_position' => $r['high_position'],
                            'low_position' => $r['low_position'],
                            'close_position' => $r['close_position'],
                        ];
                    }
                    $response['positionOhlc'] = array_map(fn(string $d) => $pmap[$d] ?? null, $ohlcDate);
                } else {
                    // member OHLC 未要求（防御経路）。position の日付を ohlcDate にする。
                    $ohlcDate = array_column($prows, 'date');
                    $response['positionOhlc'] = array_map(fn(array $r): array => [
                        'open_position' => $r['open_position'],
                        'high_position' => $r['high_position'],
                        'low_position' => $r['low_position'],
                        'close_position' => $r['close_position'],
                    ], $prows);
                }
            }
        }

        if ($ohlcDate !== null) {
            $response['ohlcDate'] = $ohlcDate;
        }
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
     * 統計レコードが無い等で OcPageCacheDataBuilder が null を返したときの空メタ。
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
