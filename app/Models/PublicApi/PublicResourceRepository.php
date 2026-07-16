<?php

declare(strict_types=1);

namespace App\Models\PublicApi;

use App\Models\Repositories\DB;

final class PublicResourceRepository implements PublicResourceRepositoryInterface
{
    private const RANKING_TABLES = [
        'hour' => 'statistics_ranking_hour',
        'day' => 'statistics_ranking_hour24',
        'week' => 'statistics_ranking_week',
        'members' => null,
    ];

    public function findRoom(int $id): array|false
    {
        return DB::fetch(
            "SELECT o.id, o.name, o.description, o.member, o.category, o.emblem,
                    o.join_method_type, o.api_created_at, o.created_at, o.updated_at,
                    h.diff_member AS change_1h,
                    d.diff_member AS change_24h,
                    w.diff_member AS change_7d,
                    r.tag AS theme, t1.tag AS theme_secondary, t2.tag AS theme_tertiary,
                    COALESCE(s.lastmod, o.updated_at) AS data_updated_at
               FROM open_chat o
               LEFT JOIN statistics_ranking_hour h ON h.open_chat_id = o.id
               LEFT JOIN statistics_ranking_hour24 d ON d.open_chat_id = o.id
               LEFT JOIN statistics_ranking_week w ON w.open_chat_id = o.id
               LEFT JOIN recommend r ON r.id = o.id
               LEFT JOIN oc_tag t1 ON t1.id = o.id
               LEFT JOIN oc_tag2 t2 ON t2.id = o.id
               LEFT JOIN oc_sitemap_lastmod s ON s.open_chat_id = o.id
              WHERE o.id = :id",
            ['id' => $id],
        );
    }

    public function isDeletedRoom(int $id): bool
    {
        return (bool) DB::fetchColumn('SELECT 1 FROM open_chat_deleted WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function listRooms(int $limit, int $offset, ?string $search, string $snapshot): array
    {
        $params = ['snapshot' => $snapshot, 'offset' => $offset, 'limit' => $limit];
        $where = 'o.updated_at <= :snapshot';
        if ($search !== null && $search !== '') {
            $where .= ' AND (o.name LIKE :search OR o.description LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        return DB::fetchAll(
            "SELECT o.id, o.name, o.description, o.member, o.category, o.emblem,
                    o.join_method_type, o.api_created_at, o.created_at, o.updated_at,
                    h.diff_member AS change_1h, d.diff_member AS change_24h,
                    w.diff_member AS change_7d, r.tag AS theme,
                    COALESCE(s.lastmod, o.updated_at) AS data_updated_at
               FROM open_chat o
               LEFT JOIN statistics_ranking_hour h ON h.open_chat_id = o.id
               LEFT JOIN statistics_ranking_hour24 d ON d.open_chat_id = o.id
               LEFT JOIN statistics_ranking_week w ON w.open_chat_id = o.id
               LEFT JOIN recommend r ON r.id = o.id
               LEFT JOIN oc_sitemap_lastmod s ON s.open_chat_id = o.id
              WHERE {$where}
              ORDER BY o.updated_at DESC, o.id DESC
              LIMIT :offset, :limit",
            $params,
        );
    }

    public function countRooms(?string $search, string $snapshot): int
    {
        $params = ['snapshot' => $snapshot];
        $where = 'updated_at <= :snapshot';
        if ($search !== null && $search !== '') {
            $where .= ' AND (name LIKE :search OR description LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        return (int) DB::fetchColumn("SELECT COUNT(*) FROM open_chat WHERE {$where}", $params);
    }

    public function listRankings(string $period, int $category, int $limit, int $offset, string $snapshot): array
    {
        $table = self::RANKING_TABLES[$period] ?? self::RANKING_TABLES['day'];
        $params = ['snapshot' => $snapshot, 'offset' => $offset, 'limit' => $limit];
        $categorySql = '';
        if ($category > 0) {
            $categorySql = ' AND o.category = :category';
            $params['category'] = $category;
        }

        if ($table === null) {
            return DB::fetchAll(
                "SELECT o.id, o.name, o.description, o.member, o.category, o.emblem,
                        o.join_method_type, o.api_created_at, o.created_at, o.updated_at,
                        NULL AS ranking_change, r.tag AS theme,
                        COALESCE(s.lastmod, o.updated_at) AS data_updated_at
                   FROM open_chat o
                   LEFT JOIN recommend r ON r.id = o.id
                   LEFT JOIN oc_sitemap_lastmod s ON s.open_chat_id = o.id
                  WHERE o.updated_at <= :snapshot {$categorySql}
                  ORDER BY o.member DESC, o.id ASC
                  LIMIT :offset, :limit",
                $params,
            );
        }

        return DB::fetchAll(
            "SELECT o.id, o.name, o.description, o.member, o.category, o.emblem,
                    o.join_method_type, o.api_created_at, o.created_at, o.updated_at,
                    rank.diff_member AS ranking_change, r.tag AS theme,
                    COALESCE(s.lastmod, o.updated_at) AS data_updated_at
               FROM {$table} rank
               INNER JOIN open_chat o ON o.id = rank.open_chat_id
               LEFT JOIN recommend r ON r.id = o.id
               LEFT JOIN oc_sitemap_lastmod s ON s.open_chat_id = o.id
              WHERE o.updated_at <= :snapshot {$categorySql}
              ORDER BY rank.diff_member DESC, rank.id ASC
              LIMIT :offset, :limit",
            $params,
        );
    }

    public function countRankings(string $period, int $category, string $snapshot): int
    {
        $table = self::RANKING_TABLES[$period] ?? self::RANKING_TABLES['day'];
        $params = ['snapshot' => $snapshot];
        $categorySql = '';
        if ($category > 0) {
            $categorySql = ' AND o.category = :category';
            $params['category'] = $category;
        }
        $join = $table === null ? '' : " INNER JOIN {$table} rank ON rank.open_chat_id = o.id";
        return (int) DB::fetchColumn(
            "SELECT COUNT(*) FROM open_chat o {$join} WHERE o.updated_at <= :snapshot {$categorySql}",
            $params,
        );
    }

    public function listThemes(int $limit, int $offset, string $snapshot): array
    {
        return DB::fetchAll(
            "SELECT r.tag, COUNT(*) AS room_count, SUM(o.member) AS total_members,
                    SUM(COALESCE(d.diff_member, 0)) AS change_24h,
                    SUM(o.created_at >= DATE_SUB(:snapshot, INTERVAL 7 DAY)) AS new_rooms_7d,
                    MAX(o.member) AS largest_room_members,
                    MAX(COALESCE(d.diff_member, 0)) AS fastest_growth_24h,
                    MAX(COALESCE(s.lastmod, o.updated_at)) AS data_updated_at
               FROM recommend r
               INNER JOIN open_chat o ON o.id = r.id AND o.updated_at <= :snapshot
               LEFT JOIN statistics_ranking_hour24 d ON d.open_chat_id = o.id
               LEFT JOIN oc_sitemap_lastmod s ON s.open_chat_id = o.id
              WHERE TRIM(r.tag) <> ''
              GROUP BY r.tag
              HAVING COUNT(*) > 0
              ORDER BY total_members DESC, r.tag ASC
              LIMIT :offset, :limit",
            ['snapshot' => $snapshot, 'offset' => $offset, 'limit' => $limit],
        );
    }

    public function countThemes(string $snapshot): int
    {
        return (int) DB::fetchColumn(
            "SELECT COUNT(*) FROM (
                SELECT r.tag FROM recommend r
                INNER JOIN open_chat o ON o.id = r.id AND o.updated_at <= :snapshot
                WHERE TRIM(r.tag) <> '' GROUP BY r.tag HAVING COUNT(*) > 0
            ) themes",
            ['snapshot' => $snapshot],
        );
    }

    public function findTheme(string $tag): array|false
    {
        return DB::fetch(
            "SELECT r.tag, COUNT(*) AS room_count, SUM(o.member) AS total_members,
                    SUM(COALESCE(d.diff_member, 0)) AS change_24h,
                    SUM(o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS new_rooms_7d,
                    MAX(o.member) AS largest_room_members,
                    MAX(COALESCE(d.diff_member, 0)) AS fastest_growth_24h,
                    MAX(COALESCE(s.lastmod, o.updated_at)) AS data_updated_at
               FROM recommend r
               INNER JOIN open_chat o ON o.id = r.id
               LEFT JOIN statistics_ranking_hour24 d ON d.open_chat_id = o.id
               LEFT JOIN oc_sitemap_lastmod s ON s.open_chat_id = o.id
              WHERE LOWER(r.tag) = LOWER(:tag)
              GROUP BY r.tag
              ORDER BY room_count DESC
              LIMIT 1",
            ['tag' => $tag],
        );
    }

    public function findThemeHighlights(string $tag): array
    {
        $base = " FROM recommend r
                   INNER JOIN open_chat o ON o.id = r.id
                   LEFT JOIN statistics_ranking_hour24 d ON d.open_chat_id = o.id
                  WHERE LOWER(r.tag) = LOWER(:tag)";

        return [
            'largest' => DB::fetch(
                "SELECT o.id, o.name, o.member, COALESCE(d.diff_member, 0) AS change_24h
                 {$base} ORDER BY o.member DESC, o.id ASC LIMIT 1",
                ['tag' => $tag],
            ),
            'fastest' => DB::fetch(
                "SELECT o.id, o.name, o.member, COALESCE(d.diff_member, 0) AS change_24h
                 {$base} ORDER BY COALESCE(d.diff_member, 0) DESC, o.member DESC, o.id ASC LIMIT 1",
                ['tag' => $tag],
            ),
        ];
    }

    public function listThemeRooms(string $tag, int $limit, int $offset, string $snapshot): array
    {
        return DB::fetchAll(
            "SELECT o.id, o.name, o.description, o.member, o.category, o.emblem,
                    o.join_method_type, o.api_created_at, o.created_at, o.updated_at,
                    d.diff_member AS ranking_change, r.tag AS theme,
                    COALESCE(s.lastmod, o.updated_at) AS data_updated_at
               FROM recommend r
               INNER JOIN open_chat o ON o.id = r.id
               LEFT JOIN statistics_ranking_hour24 d ON d.open_chat_id = o.id
               LEFT JOIN oc_sitemap_lastmod s ON s.open_chat_id = o.id
              WHERE LOWER(r.tag) = LOWER(:tag) AND o.updated_at <= :snapshot
              ORDER BY COALESCE(d.diff_member, 0) DESC, o.member DESC, o.id ASC
              LIMIT :offset, :limit",
            ['tag' => $tag, 'snapshot' => $snapshot, 'offset' => $offset, 'limit' => $limit],
        );
    }

    public function getSiteStats(): array
    {
        return DB::fetch(
            "SELECT COUNT(*) AS room_count, SUM(member) AS total_members,
                    SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS new_rooms_7d,
                    MIN(created_at) AS tracking_started_at,
                    MAX(updated_at) AS data_updated_at
               FROM open_chat"
        ) ?: [];
    }

    public function latestUpdatedAt(): string
    {
        return (string) (DB::fetchColumn('SELECT MAX(updated_at) FROM open_chat') ?: date('Y-m-d H:i:s'));
    }
}
