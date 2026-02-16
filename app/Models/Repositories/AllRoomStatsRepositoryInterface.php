<?php

declare(strict_types=1);

namespace App\Models\Repositories;

/**
 * オープンチャット全体統計ページ用リポジトリのインターフェース
 *
 * 全ルームの統計情報（総数・新規登録・閉鎖・カテゴリー別・メンバー増減）を取得する
 * データソース: MySQL (open_chat, open_chat_deleted) + SQLite sqlapi.db (daily_member_statistics, openchat_master, open_chat_deleted)
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
     * @param string $interval MySQL INTERVAL形式（例: '1 hour', '7 day', '1 month'）
     */
    public function getNewRoomCountSince(string $interval): int;

    /**
     * メンバー増減の内訳を4分類で取得
     *
     * - increased: 現存ルームのうち増加したルームの合計（>= 0）
     * - decreased: 現存ルームのうち減少したルームの合計（<= 0）
     * - lost: 消滅ルーム（過去にあったが今日にない）の過去メンバー合計（<= 0）
     * - gained: 新規ルーム（今日にあるが過去にない）の現在メンバー合計（>= 0）
     *
     * 純増数 = increased + decreased + lost + gained
     *
     * @param string $modifier SQLite date modifier形式（例: '-1 month'）
     * @return array{increased: int, decreased: int, lost: int, gained: int}
     */
    public function getMemberTrendBreakdown(string $modifier): array;

    /**
     * 消滅ルーム（過去にあるが今日にない）を閉鎖/掲載終了に分割して取得
     *
     * SQLiteのdaily_member_statisticsとopen_chat_deletedを使い、
     * 消滅ルーム全体を「閉鎖（open_chat_deletedに存在）」と「掲載終了（存在しない）」に分割する。
     * ルーム数と人数が同じ母集団から算出されるため、常に整合する。
     *
     * @param string $modifier SQLite date modifier形式（例: '-1 month'）
     * @return array{closed_rooms: int, closed_members: int, delisted_rooms: int, delisted_members: int}
     */
    public function getDisappearedRoomBreakdown(string $modifier): array;

    /**
     * 参加者数の分布を8段階の人数帯で取得（MySQL open_chat テーブルから）
     *
     * @return array{ band_id: int, band_label: string, room_count: int, total_members: int }[]
     */
    public function getMemberDistribution(): array;

    /**
     * 全ルームの参加者数の中央値を取得（MySQL open_chat テーブルから）
     */
    public function getOverallMedian(): int;

    /**
     * カテゴリー別のルーム数・参加者数・1ヶ月増減を一括取得
     *
     * MySQL: カテゴリー別 room_count, total_members
     * SQLite: カテゴリー別 1ヶ月増減（openchat_master JOIN daily_member_statistics）
     * PHP側でマージして返す
     *
     * @return array{ category: int, room_count: int, total_members: int, median: int, monthly_trend: int }[]
     */
    public function getCategoryStatsWithTrend(): array;
}
