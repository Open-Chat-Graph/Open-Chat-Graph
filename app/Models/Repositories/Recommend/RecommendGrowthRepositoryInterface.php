<?php

declare(strict_types=1);

namespace App\Models\Repositories\Recommend;

/**
 * /recommend テーマページの「勢いグラフ」用データ取得(集計のみ・副作用なし)。
 *
 * 「直近 N 日」の起点は現在時刻ではなく呼び出し側が渡す $anchorDate(最終 cron 時刻)を使う。
 * 詳細・指標の意味は実装 {@see RecommendGrowthRepository} の docブロックを参照。
 */
interface RecommendGrowthRepositoryInterface
{
    /**
     * テーマの勢いを 1 つの配列にまとめて返す(ja/tw/th 全ロケール対応)。
     *
     * @param int[] $openChatIds 掲載部屋の ID 群
     * @param \DateTime $anchorDate 「直近 N 日」の起点(最終 cron 時刻)
     * @param int $days 遡る日数
     * @return array{
     *   spanDays: int,
     *   rank: array{points: array{date:string, value:int}[], current:int, first:int, leaderId:int},
     *   member: array{points: array{date:string, value:int}[], increase:int, rooms:int}
     * }|array{} データ不足時は空配列
     */
    public function themeMomentum(array $openChatIds, \DateTime $anchorDate, int $days = 7): array;

    /**
     * 同一部屋コホートの合計メンバー数を日次で返す。
     *
     * @param int[] $openChatIds 掲載部屋の ID 群
     * @param \DateTime $anchorDate 「直近 N 日」の起点(最終 cron 時刻)
     * @param int $days 遡る日数
     * @return array{points: array{date:string, value:int}[], rooms:int}
     */
    public function themeGrowth(array $openChatIds, \DateTime $anchorDate, int $days = 21): array;
}
