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
     * 指定期間内に閉鎖されたルーム数を取得
     *
     * @param string $interval MySQL INTERVAL形式（例: '1 hour', '7 day', '1 month'）
     */
    public function getDeletedRoomCountSince(string $interval): int;

    /**
     * 全ルーム合計メンバー数の増減数を取得（SQLite sqlapi.db daily_member_statistics から）
     *
     * 計算式: SUM(member WHERE date=today) - SUM(member WHERE date=past)
     * 削除ルームのデータも含む正確な純増減を返す
     *
     * @param string $modifier SQLite date modifier形式（例: '-1 day', '-7 day', '-1 month'）
     * @return int メンバー純増減数
     */
    public function getMemberTrend(string $modifier): int;

    /**
     * 指定期間内に閉鎖されたルームの合計メンバー数を取得（SQLite sqlapi.db参照）
     *
     * @param string $interval MySQL INTERVAL形式（例: '1 hour', '7 day', '1 month'）
     */
    public function getDeletedMemberCountSince(string $interval): int;

    /**
     * 指定期間内にオプチャグラフから掲載終了となったルーム数と合計メンバー数を取得（SQLite sqlapi.db参照）
     *
     * 過去日にdaily_member_statisticsがあるが今日にはないルーム = 掲載終了
     *
     * @param string $modifier SQLite date modifier形式（例: '-1 day', '-7 day', '-1 month'）
     * @return array{rooms: int, members: int}
     */
    public function getDelistedStats(string $modifier): array;

    /**
     * 参加者数の分布を7段階の人数帯で取得（MySQL open_chat テーブルから）
     *
     * @return array{ band_id: int, band_label: string, room_count: int, total_members: int }[]
     */
    public function getMemberDistribution(): array;

    /**
     * 全ルームの参加者数の中央値を取得（MySQL open_chat テーブルから）
     */
    public function getOverallMedian(): int;

    /**
     * カテゴリー別のルーム数・参加者数・中央値・1ヶ月増減を一括取得
     *
     * MySQL: カテゴリー別 room_count, total_members, median
     * SQLite: カテゴリー別 1ヶ月増減（openchat_master JOIN daily_member_statistics）
     * PHP側でマージして返す
     *
     * @return array{ category: int, room_count: int, total_members: int, median: int, monthly_trend: int }[]
     */
    public function getCategoryStatsWithMedianAndTrend(): array;
}
