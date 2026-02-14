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
     * @param string $interval MySQL INTERVAL形式（例: '1 HOUR', '7 DAY', '1 MONTH'）
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
}
