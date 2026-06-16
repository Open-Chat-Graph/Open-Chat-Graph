<?php

declare(strict_types=1);

namespace App\Models\Repositories\Analysis;

use App\Models\Repositories\DB;

class AnalysisRoomRepository implements AnalysisRoomRepositoryInterface
{
    public function getMaxOpenChatId(): int
    {
        DB::connect();
        return (int)DB::fetchColumn("SELECT MAX(id) FROM open_chat");
    }

    public function getRoomsInRange(int $lo, int $hi): array
    {
        DB::connect();
        $rows = DB::fetchAll(
            "SELECT id, COALESCE(category, 0) AS category, member
             FROM open_chat
             WHERE id >= :lo AND id < :hi AND member IS NOT NULL",
            ['lo' => $lo, 'hi' => $hi]
        );

        $result = [];
        foreach ($rows as $r) {
            $result[(int)$r['id']] = [
                'category' => (int)$r['category'],
                'member' => (int)$r['member'],
            ];
        }

        return $result;
    }

    public function findIdsByKeyword(string $keyword): array
    {
        DB::connect();
        // LIKE のワイルドカード(%, _)と ESCAPE 文字を無効化してから前後に % を付ける
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword);
        $rows = DB::fetchAll(
            "SELECT id FROM open_chat WHERE name LIKE :kw ESCAPE '\\\\'",
            ['kw' => '%' . $escaped . '%']
        );

        return array_map(static fn($r) => (int)$r['id'], $rows);
    }

    public function hydrate(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        DB::connect();
        $ids = array_values(array_map('intval', $ids));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::fetchAll(
            "SELECT id, name, description, member, img_url, emblem, join_method_type, COALESCE(category, 0) AS category
             FROM open_chat
             WHERE id IN ({$placeholders})",
            $ids
        );

        $result = [];
        foreach ($rows as $r) {
            $result[(int)$r['id']] = [
                'name' => (string)$r['name'],
                'desc' => (string)($r['description'] ?? ''),
                'member' => (int)$r['member'],
                'img' => (string)($r['img_url'] ?? ''),
                'emblem' => (int)($r['emblem'] ?? 0),
                'joinMethodType' => (int)($r['join_method_type'] ?? 0),
                'category' => (int)$r['category'],
            ];
        }

        return $result;
    }
}
