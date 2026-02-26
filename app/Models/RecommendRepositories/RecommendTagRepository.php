<?php

declare(strict_types=1);

namespace App\Models\RecommendRepositories;

use App\Models\Repositories\DB;

class RecommendTagRepository implements RecommendTagRepositoryInterface
{
    function fetchTargetRows(string $targetIdJoinClause, string $start, string $end): array
    {
        $query = "SELECT oc.id, oc.name, oc.description, oc.category
                  FROM open_chat AS oc
                  {$targetIdJoinClause}
                  WHERE oc.updated_at BETWEEN :start AND :end";

        $stmt = DB::execute($query, ['start' => $start, 'end' => $end]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $indexed = [];
        foreach ($rows as $row) {
            $row['id'] = (int)$row['id'];
            $row['category'] = (int)$row['category'];
            $indexed[$row['id']] = $row;
        }

        return $indexed;
    }

    function fetchModifyRecommendByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // IDは呼び出し元でint型が保証されているため、直接埋め込みで安全
        $idList = implode(',', array_map('intval', $ids));
        $stmt = DB::execute(
            "SELECT id, tag FROM modify_recommend WHERE id IN ({$idList})"
        );

        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row['id']] = $row['tag'];
        }

        return $result;
    }

    function bulkInsertViaTemp(string $targetTable, array $data): void
    {
        $tempTable = $targetTable . '_temp';

        DB::execute("CREATE TEMPORARY TABLE {$tempTable} LIKE {$targetTable}");

        $chunks = array_chunk($data, 1000, true);
        foreach ($chunks as $chunk) {
            $placeholders = [];
            $params = [];
            $i = 0;
            foreach ($chunk as $id => $tag) {
                $placeholders[] = "(:id{$i}, :tag{$i})";
                $params["id{$i}"] = $id;
                $params["tag{$i}"] = $tag;
                $i++;
            }
            if ($placeholders) {
                $sql = "INSERT INTO {$tempTable} (id, tag) VALUES " . implode(', ', $placeholders);
                DB::execute($sql, $params);
            }
        }

        DB::transaction(function () use ($targetTable, $tempTable) {
            DB::execute("DELETE FROM {$targetTable} WHERE id IN (SELECT id FROM {$tempTable})");
            DB::execute("INSERT INTO {$targetTable} SELECT * FROM {$tempTable}");
        });

        DB::execute("DROP TEMPORARY TABLE {$tempTable}");
    }

    function createTargetIdTable(string $start, string $end, int $limit): void
    {
        DB::execute("CREATE TEMPORARY TABLE IF NOT EXISTS target_oc_ids (id INT PRIMARY KEY)");
        DB::execute(
            "INSERT INTO target_oc_ids
             SELECT id FROM open_chat
             WHERE updated_at BETWEEN :start AND :end
             LIMIT :limit",
            ['start' => $start, 'end' => $end, 'limit' => $limit]
        );
    }

    function dropTargetIdTable(): void
    {
        DB::execute("DROP TEMPORARY TABLE IF EXISTS target_oc_ids");
    }
}
