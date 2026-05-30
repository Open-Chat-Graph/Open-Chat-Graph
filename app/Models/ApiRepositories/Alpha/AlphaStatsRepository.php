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
        $this->queryBuilder = new AlphaQueryBuilder();
    }

    /**
     * ID指定で詳細データ取得（stats API用）
     */
    public function findById(int $id): ?array
    {
        $hourlyCronUpdatedAtDatetime = (new \DateTime(file_get_contents($this->fileStorage->getStorageFilePath('hourlyCronUpdatedAtDatetime'))))
            ->format('Y-m-d H:i:s');

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
                @is_in_ranking := (SELECT COUNT(*) FROM ocgraph_ranking.member WHERE open_chat_id = oc.id AND time = '{$hourlyCronUpdatedAtDatetime}'),
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
                    WHEN d.diff_member IS NULL AND TIMESTAMPDIFF(HOUR, oc.created_at, NOW()) >= 24 THEN 0
                    ELSE d.diff_member
                END AS daily_diff_member,
                CASE
                    WHEN @is_in_ranking = 0 THEN NULL
                    WHEN d.percent_increase IS NULL AND TIMESTAMPDIFF(HOUR, oc.created_at, NOW()) >= 24 THEN 0
                    ELSE d.percent_increase
                END AS daily_percent_increase,
                CASE
                    WHEN w.diff_member IS NULL AND TIMESTAMPDIFF(DAY, oc.created_at, NOW()) >= 7 THEN 0
                    ELSE w.diff_member
                END AS weekly_diff_member,
                CASE
                    WHEN w.percent_increase IS NULL AND TIMESTAMPDIFF(DAY, oc.created_at, NOW()) >= 7 THEN 0
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
