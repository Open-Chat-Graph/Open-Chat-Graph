<?php

declare(strict_types=1);

namespace App\Models\Repositories;

/**
 * /oc/{id} narrative セクション生成に必要なデータ取得 Interface。
 *
 * 内部で OHLC Repository を組み合わせ、Service 層を時系列クエリ実装から切り離す。
 */
interface OcNarrativeRepositoryInterface
{
    /**
     * narrative 用のメンバー数メトリクス（OHLC 集約）。
     *
     * @param ?string $baseDate 「現在の基準日」('Y-m-d')。指定時はこの日基準で m1/m7/m30/m90 等を
     *                          算出する。null 時は SQLite 実行時刻 (date('now')、UTC) 基準。
     * @return array{
     *     curr: ?int,
     *     curr_date: ?string,
     *     m1: ?int,
     *     m7: ?int,
     *     m30: ?int,
     *     m90: ?int,
     *     sample_n: int,
     *     peak_high: ?int,
     *     peak_date: ?string,
     *     max_single_day_growth: ?int,
     *     max_growth_date: ?string,
     *     first_date: ?string,
     *     all_time_peak: ?int,
     *     all_time_peak_date: ?string
     * }
     */
    public function getMemberMetrics(int $openChatId, ?string $baseDate = null): array;

    /**
     * 直近 N 日のカテゴリ内 (Ranking type) 順位推移サマリ。
     *
     * @return array{
     *     oldest_close: ?int,
     *     oldest_date: ?string,
     *     latest_close: ?int,
     *     latest_date: ?string,
     *     best_high: ?int,
     *     sample_n: int
     * }
     */
    public function getPositionMovement(int $openChatId, int $category, int $days = 30): array;

    /**
     * 指定 category / type における close_position の平均と観測日数。
     * narrative で以下の判定に使う:
     * - category=0, type='ranking' → 全体での規模 (大規模代表)
     * - category=0, type='rising'  → 全体での活発度 (最高クラス)
     * - category>0, type='rising'  → カテゴリ内での活発度
     *
     * @param int    $openChatId
     * @param int    $category 0 = 全体カテゴリ
     * @param string $type    'ranking' or 'rising'
     * @param int    $days    直近 N 日 (デフォルト 30)
     * @return array{avg_position: ?float, sample_n: int}
     */
    public function getAveragePosition(int $openChatId, int $category, string $type, int $days = 30): array;

    /**
     * オプチャグラフ独自の成長ランキング (1時間 / 24時間 / 1週間) における
     * 現在のランキング位置を取得。各テーブルに該当行が無ければ null。
     *
     * 用途: summary の「いま伸びているルーム」「過去 1 週間で 1 位」等の
     * トップ評価ラベル選定。
     *
     * @return array{hour: ?int, day: ?int, week: ?int}
     */
    public function getGrowthRankingPositions(int $openChatId): array;
}
