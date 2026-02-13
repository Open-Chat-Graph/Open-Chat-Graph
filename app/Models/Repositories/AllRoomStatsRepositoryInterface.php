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

    /** 最も古い閉鎖記録の日時を取得（データなしの場合はnull） */
    public function getEarliestDeletedDate(): ?string;

    /** 閉鎖されたルームの総数を取得 */
    public function getDeletedRoomCount(): int;

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

    /** 直近1時間のメンバー増加数合計（増加したルームのみ）を取得 */
    public function getHourlyMemberIncrease(): int;

    /** 直近24時間のメンバー増加数合計（増加したルームのみ）を取得 */
    public function getDailyMemberIncrease(): int;

    /** 直近1週間のメンバー増加数合計（増加したルームのみ）を取得 */
    public function getWeeklyMemberIncrease(): int;

    /** 閉鎖された全ルームの合計メンバー数を取得（SQLite ocgraph_sqlapi参照） */
    public function getDeletedMemberCountTotal(): int;

    /**
     * 指定期間内に閉鎖されたルームの合計メンバー数を取得（SQLite ocgraph_sqlapi参照）
     *
     * @param string $interval PHP strtotime形式（例: '1 hour', '7 days', '1 month'）
     */
    public function getDeletedMemberCountSince(string $interval): int;
}
