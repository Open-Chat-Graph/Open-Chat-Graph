<?php

declare(strict_types=1);

namespace App\Models\ApiRepositories\Alpha;

use App\Models\Repositories\DB;
use App\Models\SQLite\SQLiteStatistics;
use App\Models\SQLite\SQLiteRankingPosition;
use App\Services\Storage\FileStorageInterface;

/**
 * Alpha統計データ専用リポジトリ
 * stats()とbatchStats()のSQLロジックを担当
 */
class AlphaStatsRepository
{
    private AlphaQueryBuilder $queryBuilder;

    public function __construct(
        private FileStorageInterface $fileStorage,
    ) {
        $this->queryBuilder = new AlphaQueryBuilder($fileStorage);
    }

    /**
     * ID指定で詳細データ取得（stats API用）
     */
    public function findById(int $id): ?array
    {
        try {
            $raw = $this->fileStorage->getContents('@hourlyCronUpdatedAtDatetime');
            $cronValue = ($raw !== '') ? (new \DateTime($raw))->format('Y-m-d H:i:s') : null;
        } catch (\Exception) {
            $cronValue = null;
        }

        $cronForRanking = $cronValue !== null ? "'{$cronValue}'" : 'NOW()';
        $cronDatetime   = $cronValue !== null ? "'{$cronValue}'" : 'NOW()';

        $sql = "
            SELECT
                oc.id,
                oc.name,
                oc.member,
                oc.category,
                oc.description,
                oc.img_url,
                oc.emblem,
                oc.api_created_at,
                oc.created_at,
                oc.join_method_type,
                oc.url,
                @is_in_ranking := (SELECT COUNT(*) FROM ocgraph_ranking.member WHERE open_chat_id = oc.id AND time = {$cronForRanking}),
                CASE
                    WHEN @is_in_ranking = 0 THEN NULL
                    WHEN h.diff_member IS NULL THEN 0
                    ELSE h.diff_member
                END AS hourly_diff_member,
                CASE
                    WHEN @is_in_ranking = 0 THEN NULL
                    WHEN h.percent_increase IS NULL THEN 0
                    ELSE h.percent_increase
                END AS hourly_percent_increase,
                CASE
                    WHEN @is_in_ranking = 0 THEN NULL
                    WHEN d.diff_member IS NULL AND TIMESTAMPDIFF(HOUR, oc.created_at, {$cronDatetime}) >= 24 THEN 0
                    ELSE d.diff_member
                END AS daily_diff_member,
                CASE
                    WHEN @is_in_ranking = 0 THEN NULL
                    WHEN d.percent_increase IS NULL AND TIMESTAMPDIFF(HOUR, oc.created_at, {$cronDatetime}) >= 24 THEN 0
                    ELSE d.percent_increase
                END AS daily_percent_increase,
                CASE
                    WHEN w.diff_member IS NULL AND TIMESTAMPDIFF(DAY, oc.created_at, {$cronDatetime}) >= 7 THEN 0
                    ELSE w.diff_member
                END AS weekly_diff_member,
                CASE
                    WHEN w.percent_increase IS NULL AND TIMESTAMPDIFF(DAY, oc.created_at, {$cronDatetime}) >= 7 THEN 0
                    ELSE w.percent_increase
                END AS weekly_percent_increase,
                CASE
                    WHEN @is_in_ranking = 0 THEN 0
                    ELSE 1
                END AS is_in_ranking
            FROM
                open_chat AS oc
            LEFT JOIN statistics_ranking_hour AS h ON oc.id = h.open_chat_id
            LEFT JOIN statistics_ranking_hour24 AS d ON oc.id = d.open_chat_id
            LEFT JOIN statistics_ranking_week AS w ON oc.id = w.open_chat_id
            WHERE
                oc.id = :id
        ";

        DB::connect();
        $stmt = DB::$pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * ID一括取得（batchStats用）
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        DB::connect();
        $query = $this->queryBuilder->buildBatchQuery($ids);
        $result = DB::fetchAll($query['sql'], $query['params']);

        return $result;
    }

    /**
     * SQLiteから統計データ取得（グラフ用）
     *
     * @return array{dates: string[], members: int[]}
     */
    public function getStatisticsData(int $openChatId): array
    {
        $pdo = SQLiteStatistics::connect();

        $sql = "
            SELECT
                date,
                member
            FROM
                statistics
            WHERE
                open_chat_id = :open_chat_id
            ORDER BY
                date ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['open_chat_id' => $openChatId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // dates と members の配列に分割
        $dates = [];
        $members = [];
        foreach ($rows as $row) {
            $dates[] = $row['date'];
            $members[] = (int)$row['member'];
        }

        return [
            'dates' => $dates,
            'members' => $members
        ];
    }

    /**
     * 複数部屋の直近メンバー数系列を一括取得（スパークライン用・各部屋直近7点）
     *
     * N+1を避けるため IN (...) で一括取得し、PHP側で open_chat_id ごとにグループ化する。
     * 固定の日付窓ではなく各部屋の「直近7点」を返す（クロール停止部屋やデータ遅延で空になるのを防ぐ）。
     *
     * @param int[] $ids open_chat_id の配列（最大50件）
     * @return array<int, list<array{date: string, member: int}>> id => [{date, member}, ...]
     */
    public function getSparklineByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $pdo = SQLiteStatistics::connect();

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "
            SELECT
                open_chat_id,
                date,
                member
            FROM (
                SELECT
                    open_chat_id,
                    date,
                    member,
                    ROW_NUMBER() OVER (PARTITION BY open_chat_id ORDER BY date DESC) AS rn
                FROM
                    statistics
                WHERE
                    open_chat_id IN ({$placeholders})
            )
            WHERE
                rn <= 7
            ORDER BY
                open_chat_id ASC,
                date ASC
        ";

        $params = array_values($ids);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // open_chat_id でグループ化
        $result = [];
        foreach ($rows as $row) {
            $id = (int)$row['open_chat_id'];
            $result[$id][] = [
                'date' => $row['date'],
                'member' => (int)$row['member'],
            ];
        }

        return $result;
    }

    /**
     * ランキングデータ取得
     *
     * @param string $type 'ranking' or 'rising'
     * @return int[]|null[] datesに合わせたランキング配列
     */
    public function getRankingData(int $openChatId, int $category, string $type, array $dates): array
    {
        $rankingPdo = SQLiteRankingPosition::connect();
        $table = $type === 'ranking' ? 'ranking' : 'rising';

        $rankingSql = "
            SELECT
                date,
                position
            FROM
                {$table}
            WHERE
                open_chat_id = :open_chat_id
                AND category = :category
            ORDER BY
                date ASC
        ";

        $rankingStmt = $rankingPdo->prepare($rankingSql);
        $rankingStmt->execute([
            'open_chat_id' => $openChatId,
            'category' => $category
        ]);
        $rankingRows = $rankingStmt->fetchAll(\PDO::FETCH_ASSOC);

        // datesに合わせてランキングデータをマッピング
        $rankingMap = [];
        foreach ($rankingRows as $row) {
            $rankingMap[$row['date']] = (int)$row['position'];
        }

        $rankings = [];
        foreach ($dates as $date) {
            $rankings[] = $rankingMap[$date] ?? null;
        }

        return $rankings;
    }
}
