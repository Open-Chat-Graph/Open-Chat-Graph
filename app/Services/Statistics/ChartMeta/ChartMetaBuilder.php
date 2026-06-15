<?php

declare(strict_types=1);

namespace App\Services\Statistics\ChartMeta;

use App\Models\Repositories\RankingPosition\RankingPositionHourRepositoryInterface;
use App\Models\Repositories\RankingPosition\RankingPositionRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface;
use App\Services\Storage\FileStorageInterface;

/**
 * グラフ初回ロードのタブ/ボタン出し分け「可用性メタ」を1部屋分だけ組み立てる、唯一のサービス。
 *
 * 可用性メタの計算経路はこの1本に集約されている:
 *  - ページ埋め込み(cron): OcPageCacheGenerator が呼び、結果を MySQL の oc_page_cache.chart_meta
 *    に JSON で置く。/oc 表示時はそれを HTML に埋め込み、フロントは初回 XHR(meta=1) を撃たずに済む。
 *  - meta=1 のライブ計算: OpenChatChartApiService が呼ぶ（埋め込み未生成の新規室フォールバック）。
 * どちらも同じ build() を通るのでしきい値・判定が乖離しない（判定は ChartAvailabilityCalculator）。
 *
 * 最新24時間タブの集計だけは取得経路が2通りある:
 *  - cron は呼び出し側が一括 GROUP BY(getHourPositionCountsAll)で全部屋分を1回取得し、その部屋の
 *    分を $hourEntry として渡す（バックフィルで部屋数ぶんの毎時クエリを撃たず gone away を防ぐ）。
 *    直近24hに出現が無い部屋は「空の集計」を渡す（呼び出し側で hourEntryNone() を使う）。
 *  - ライブ(1部屋)は $hourEntry を省略し、build() 内で per-room の getHourPositionCounts を撃つ。
 * いずれの経路でも in/all 判定は等価（getHourPositionCounts の category=:in_category と、
 * 一括版の in_array(category, ...) が同じ意味）。$hourEntry===null は「ライブ＝自分で取る」を意味し、
 * 「出現なし」とは区別する（出現なしは空の集計を明示的に渡す）。
 *
 * 返す配列は OpenChatChartApiService::buildChartResponse の meta ブロックと同形。
 */
class ChartMetaBuilder
{
    /** 最新24時間タブのウィンドウ幅（StatisticsChartArrayService/UpdateOcPageCacheServiceと同じ） */
    private const HOUR_INTERVAL = 24;

    public function __construct(
        private StatisticsPageRepositoryInterface $statisticsPageRepository,
        private StatisticsOhlcRepositoryInterface $statisticsOhlcRepository,
        private RankingPositionRepositoryInterface $rankingPositionRepository,
        private RankingPositionHourRepositoryInterface $rankingPositionHourRepository,
        private FileStorageInterface $fileStorage,
    ) {}

    /**
     * 「最新24時間に出現が無い部屋」を表す空の集計。cron がフルマップに居ない部屋へ渡す。
     * （build() に null を渡すと「ライブ＝per-room で取得」を意味してしまうため区別する）
     *
     * @return array{member: bool, ranking: int[], rising: int[]}
     */
    public static function hourEntryNone(): array
    {
        return ['member' => false, 'ranking' => [], 'rising' => []];
    }

    /**
     * 1部屋分の可用性メタを組み立てる。統計レコードが無ければ null（埋め込まない）。
     *
     * @param int|null $category 部屋のカテゴリID（未掲載・その他はnull/0）
     * @param null|array{member: bool, ranking: int[], rising: int[]} $hourEntry
     *   cron が一括取得した「この部屋の最新24時間集計」。出現が無い部屋は hourEntryNone() を渡す。
     *   ライブ(1部屋)は省略（null）し、build() 内で per-room 取得する。
     * @return null|array{
     *   startDate: string,
     *   endDate: string,
     *   dateCount: int,
     *   hourAvailability: bool,
     *   positionAvailability: array<'hour'|'week'|'month'|'all', array{ranking_in: bool, ranking_all: bool, rising_in: bool, rising_all: bool}>,
     *   ohlcAvailability: array{week: bool, month: bool, all: bool},
     *   risingStatus: array{on_ranking_week: bool, on_rising_week: bool, top5_all_week: bool}
     * }
     */
    public function build(int $open_chat_id, ?int $category, ?array $hourEntry = null): ?array
    {
        $range = $this->statisticsPageRepository->getMemberDateRange($open_chat_id);
        if ($range === null) {
            return null;
        }

        $maxDate = new \DateTime($range['max']);
        $len = (int)$maxDate->diff(new \DateTime($range['min']))->days + 1;

        $weekWindow = min(8, $len);
        $monthWindow = min(31, $len);

        // ウィンドウ開始日＝末尾(max)から (window-1) 日前。$dto->date[$len - $window] と等価。
        $weekStart = (clone $maxDate)->modify('-' . ($weekWindow - 1) . ' day')->format('Y-m-d');
        $monthStart = (clone $maxDate)->modify('-' . ($monthWindow - 1) . ' day')->format('Y-m-d');

        // ローソク足(OHLC)の期間タブ可用性
        $ohlcCounts = $this->statisticsOhlcRepository->getOhlcCounts($open_chat_id, $weekStart, $monthStart);
        $ohlcAvailability = ChartAvailabilityCalculator::dailyOhlc($ohlcCounts, $weekWindow, $monthWindow);

        // カテゴリ未設定(その他/未掲載)の部屋はカテゴリ内ランキングを持たない（-1=不一致値）
        $inCategory = ($category !== null && $category > 0) ? $category : -1;

        $none = ['ranking_in' => false, 'ranking_all' => false, 'rising_in' => false, 'rising_all' => false];

        // 順位(ranking/rising × in/all)の週/月/全タブ可用性
        $risingBestPosAllWeek = null;
        try {
            $posCounts = $this->rankingPositionRepository->getPositionCountsByPeriod(
                $open_chat_id,
                $inCategory,
                $weekStart,
                $monthStart,
            );
            $daily = ChartAvailabilityCalculator::dailyPosition($posCounts);
            // 「すべて」急上昇で週内に到達した最良順位（top5判定用・同一クエリに相乗り）
            $risingBestPosAllWeek = $posCounts['rising_all_week_best_pos'] ?? null;
        } catch (\PDOException) {
            // DBファイル未作成の環境は「データ無し」として扱う
            $daily = ['week' => $none, 'month' => $none, 'all' => $none];
        }

        // 最新24時間タブ（メンバー数有無＋その中の順位/急上昇 in/all）。
        [$hourAvailability, $hour] = $hourEntry === null
            ? $this->buildHourAvailabilityLive($open_chat_id, $inCategory, $none)
            : $this->buildHourAvailabilityFromEntry($category, $hourEntry);

        // 急上昇/ランキングの掲載状態（週窓）。ブログ導線(OcBlogContextLinkResolver)の状態駆動に使う。
        // 同種の派生データの相乗りが今後増える想定（perf都合で per-room 取得に密結合する）。
        $risingStatus = [
            'on_ranking_week' => $daily['week']['ranking_all'] || $daily['week']['ranking_in'],
            'on_rising_week' => $daily['week']['rising_all'] || $daily['week']['rising_in'],
            'top5_all_week' => $risingBestPosAllWeek !== null && $risingBestPosAllWeek <= 5,
        ];

        // StatisticsChartDto::$positionAvailability と同じキー順（hour, week, month, all）で組む
        return [
            'startDate' => $range['min'],
            'endDate' => $range['max'],
            'dateCount' => $len,
            'hourAvailability' => $hourAvailability,
            'positionAvailability' => [
                'hour' => $hour,
                'week' => $daily['week'],
                'month' => $daily['month'],
                'all' => $daily['all'],
            ],
            'ohlcAvailability' => $ohlcAvailability,
            'risingStatus' => $risingStatus,
        ];
    }

    /**
     * cron が一括取得した集計から最新24時間タブの可用性を判定する。
     *
     * in 判定: $category が未設定(null/0)の部屋はカテゴリ内に出現しえない＝ in は常に false
     * （getHourPositionCounts の category=:in_category と等価）。
     *
     * @param array{member: bool, ranking: int[], rising: int[]} $hourEntry
     * @return array{0: bool, 1: array{ranking_in: bool, ranking_all: bool, rising_in: bool, rising_all: bool}}
     */
    private function buildHourAvailabilityFromEntry(?int $category, array $hourEntry): array
    {
        $inCat = ($category !== null && $category > 0) ? $category : null;

        return [
            $hourEntry['member'],
            [
                'ranking_in' => $inCat !== null && in_array($inCat, $hourEntry['ranking'], true),
                'ranking_all' => in_array(0, $hourEntry['ranking'], true),
                'rising_in' => $inCat !== null && in_array($inCat, $hourEntry['rising'], true),
                'rising_all' => in_array(0, $hourEntry['rising'], true),
            ],
        ];
    }

    /**
     * ライブ(1部屋)経路: per-room で毎時カウントを取り、最新24時間タブの可用性を判定する。
     * 旧 StatisticsChartArrayService::setPositionAvailability の hour 部分と完全に同じクエリ・しきい値。
     * 毎時クロール未実行・DB未作成の環境は「データ無し」として扱う。
     *
     * @param array{ranking_in: bool, ranking_all: bool, rising_in: bool, rising_all: bool} $none
     * @return array{0: bool, 1: array{ranking_in: bool, ranking_all: bool, rising_in: bool, rising_all: bool}}
     */
    private function buildHourAvailabilityLive(int $open_chat_id, int $inCategory, array $none): array
    {
        try {
            $updatedAt = $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime');
            $hourCounts = $this->rankingPositionHourRepository->getHourPositionCounts(
                $open_chat_id,
                $inCategory,
                self::HOUR_INTERVAL,
                new \DateTime($updatedAt),
            );
        } catch (\Throwable) {
            return [false, $none];
        }

        return [
            $hourCounts['member'] > 0,
            [
                'ranking_in' => $hourCounts['ranking_in'] > 0,
                'ranking_all' => $hourCounts['ranking_all'] > 0,
                'rising_in' => $hourCounts['rising_in'] > 0,
                'rising_all' => $hourCounts['rising_all'] > 0,
            ],
        ];
    }
}
