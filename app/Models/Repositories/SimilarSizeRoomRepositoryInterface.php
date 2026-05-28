<?php

declare(strict_types=1);

namespace App\Models\Repositories;

/**
 * /oc/{id} の「関連ルーム」セクション用に、近い属性のルームを取得する Repository。
 *
 * Service 側のカスケード判定で 2 つを使い分け:
 *   - findByTagWithMemberRange     : 同タグ × メンバー範囲 × 人数が近い順 (主候補)
 *   - findByCategoryWithMemberRange: 同カテゴリ × メンバー範囲 × 人数が近い順 (フォールバック)
 *
 * 「同タグで人数範囲に届かない」場合は recommend 上位を流用する設計のため、
 * Repository としては 2 メソッドのみ提供する。
 */
interface SimilarSizeRoomRepositoryInterface
{
    /**
     * 同タグ × メンバー [minMember, maxMember] × メンバー数が近い順 LIMIT 5。
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByTagWithMemberRange(int $excludeId, int $currentMember, string $tag, int $minMember, int $maxMember): array;

    /**
     * 同カテゴリ × メンバー [minMember, maxMember] × メンバー数が近い順 LIMIT 5。
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByCategoryWithMemberRange(int $excludeId, int $currentMember, int $category, int $minMember, int $maxMember): array;
}
