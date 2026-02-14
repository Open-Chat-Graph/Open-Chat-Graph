<?php

declare(strict_types=1);

namespace App\Models\Repositories;

/**
 * オープンチャット全体統計ページ用リポジトリのインターフェース
 *
 * 全ルームの統計情報（総数・新規登録・閉鎖・カテゴリー別・メンバー増減）を取得する
 */
interface AllRoomStatsRepositoryInterface
{
    /** 現在登録中の総ルーム数を取得 */
    public function getTotalRoomCount(): int;

    /** 現在登録中の全ルームの合計メンバー数を取得 */
    public function getTotalMemberCount(): int;

    /** 最も古いルームの登録日時を取得（データなしの場合はnull） */
    public function getTrackingStartDate(): ?string;

    /**
     * 指定期間内に新規登録されたルーム数を取得
     *
     * @param string $interval MySQL INTERVAL形式（例: '1 HOUR', '7 DAY', '1 MONTH'）
     */
    public function getNewRoomCountSince(string $interval): int;

    /**
     * 指定期間内に閉鎖されたルーム数を取得
     *
     * @param string $interval MySQL INTERVAL形式（例: '1 HOUR', '7 DAY', '1 MONTH'）
     */
    public function getDeletedRoomCountSince(string $interval): int;

    /**
     * カテゴリー別のルーム数・参加者数を取得
     *
     * @return array{ category: int, room_count: int, total_members: int }[]
     */
    public function getCategoryStats(): array;

    /**
     * 時間単位のメンバー増減数を取得（RankingPositionDB member テーブルから）
     *
     * @param string $hourModifier DateTime::modify() 形式（例: '-1hour', '-24hour'）
     * @return array{net: int, delisted_members: int}
     */
    public function getHourlyMemberTrend(string $hourModifier): array;

    /**
     * 日単位のメンバー増減数を取得（SQLite statistics テーブルから）
     *
     * @param string $dateModifier SQLite date() 修飾子（例: '-7 day', '-1 month'）
     * @return array{net: int, delisted_members: int}
     */
    public function getDailyMemberTrend(string $dateModifier): array;

    /**
     * 指定期間内に閉鎖されたルームの合計メンバー数を取得（SQLite ocgraph_sqlapi参照）
     *
     * @param string $interval MySQL INTERVAL形式（例: '1 HOUR', '7 DAY', '1 MONTH'）
     */
    public function getDeletedMemberCountSince(string $interval): int;
}
