<?php

declare(strict_types=1);

namespace App\Models\Repositories\Recommend;

/**
 * トップページ「いま人数急増中のテーマ」の集計に使う、いま伸びている部屋の取得。
 *
 * recommend（部屋→タグ）と statistics_ranking_*（増減）を結合し、
 * 各部屋の tag と増加量(diff_member)を返す。テーマ別の集計・並びは利用側
 * (App\Services\Recommend\TopPageRecommendList) の責務とし、本リポジトリは取得のみを担う。
 *
 * 戻り値の各行: array{tag: ?string, diff_member: int|null}
 */
interface TrendingThemeRepositoryInterface
{
    /** 直近1hと24hの両方で増加している部屋。 */
    public function fetchRisingRoomsByHour(): array;

    /** 直近1hで増加し、かつ24hで強く増加している部屋。 */
    public function fetchRisingRoomsByHourAnd24h(): array;

    /** 24h（または週）で増加している部屋。 */
    public function fetchRisingRoomsByDay(): array;
}
