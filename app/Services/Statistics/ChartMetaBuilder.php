<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\Repositories\RankingPosition\RankingPositionRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsOhlcRepositoryInterface;
use App\Models\Repositories\Statistics\StatisticsPageRepositoryInterface;

/**
 * グラフ初回ロードのタブ/ボタン出し分け「可用性メタ」を1部屋分だけ事前計算するサービス。
 *
 * これを cron（ページキャッシュ生成 OcPageCacheGenerator）が呼び、結果を MySQL の
 * oc_page_cache.chart_meta に JSON で置く。/oc 表示時はそれを HTML に埋め込み、フロントは
 * 初回 XHR(/oc/{id}/chart?meta=1) を撃たずに済む（4DBへのCOUNTを表示経路から外す）。
 *
 * しきい値と try/catch は StatisticsChartArrayService の setOhlcAvailability /
 * setPositionAvailability と完全に同じ（判定は ChartAvailabilityCalculator に集約）。
 * 違いは表示ウィンドウ（週/月/全）の開始日を、$dto->date でなく統計の MIN/MAX 日付から
 * 出す点だけ（既に等価性を検証済み）。meta=1 のライブ計算はフォールバックとして残る。
 *
 * 最新24時間タブの集計(hourEntry)は per-room で取らず、呼び出し側(OcPageCacheGenerator)が
 * 一括 GROUP BY(getHourPositionCountsAll)で全部屋分を1回取得し、その部屋の分を渡す。
 * バックフィルで部屋数ぶんの毎時クエリが出て MySQL gone away を誘発するのを防ぐため。
 *
 * 返す配列は OpenChatChartApiService::buildChartResponse の meta ブロックと同形。
 */
class ChartMetaBuilder
{
    public function __construct(
        private StatisticsPageRepositoryInterface $statisticsPageRepository,
        private StatisticsOhlcRepositoryInterface $statisticsOhlcRepository,
        private RankingPositionRepositoryInterface $rankingPositionRepository,
    ) {}

    /**
     * 1部屋分の可用性メタを組み立てる。統計レコードが無ければ null（埋め込まない）。
     *
     * @param int|null $category 部屋のカテゴリID（未掲載・その他はnull/0）
     * @param null|array{member: bool, ranking: int[], rising: int[]} $hourEntry
     *   呼び出し側が一括取得した「この部屋の最新24時間集計」。直近24hに出現が無ければ null。
     * @return null|array{
     *   startDate: string,
     *   endDate: string,
     *   dateCount: int,
     *   hourAvailability: bool,
     *   positionAvailability: array<'hour'|'week'|'month'|'all', array{ranking_in: bool, ranking_all: bool, rising_in: bool, rising_all: bool}>,
     *   ohlcAvailability: array{week: bool, month: bool, all: bool}
     * }
     */
    public function build(int $open_chat_id, ?int $category, ?array $hourEntry): ?array
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
        try {
            $posCounts = $this->rankingPositionRepository->getPositionCountsByPeriod(
                $open_chat_id,
                $inCategory,
                $weekStart,
                $monthStart,
            );
            $daily = ChartAvailabilityCalculator::dailyPosition($posCounts);
        } catch (\PDOException) {
            // DBファイル未作成の環境は「データ無し」として扱う
            $daily = ['week' => $none, 'month' => $none, 'all' => $none];
        }

        // 最新24時間タブ（メンバー数有無＋その中の順位/急上昇 in/all）。
        // hourEntry は呼び出し側が一括取得済み（直近24hに出現が無ければ null＝全 false）。
        // in 判定: $category が未設定(null/0)の部屋は in_category=-1 相当でカテゴリ内に出現しえない
        // ＝ in は常に false（getHourPositionCounts の category=:in_category と等価）。
        if ($hourEntry === null) {
            $hourAvailability = false;
            $hour = $none;
        } else {
            $inCat = ($category !== null && $category > 0) ? $category : null;
            $hourAvailability = $hourEntry['member'];
            $hour = [
                'ranking_in' => $inCat !== null && in_array($inCat, $hourEntry['ranking'], true),
                'ranking_all' => in_array(0, $hourEntry['ranking'], true),
                'rising_in' => $inCat !== null && in_array($inCat, $hourEntry['rising'], true),
                'rising_all' => in_array(0, $hourEntry['rising'], true),
            ];
        }

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
        ];
    }
}
