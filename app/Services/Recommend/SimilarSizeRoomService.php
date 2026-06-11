<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\StaticData\RecommendStaticDataGenerator;

/**
 * 「関連ルーム」を取得する Service。
 *
 * recommend 静的キャッシュ(.dat)だけで組み立てる（部屋ごとの MySQL クエリは使わない）。
 * .dat は人数降順の裾を LIST_LIMIT_RECOMMEND_POOL(300)件保持しており、
 * そこから人数レンジで絞り込めば取りこぼしはほぼ無い。
 *
 * タグを最優先 (LINE 公式カテゴリは「団体」「同世代」など粒度が荒く関連性が低いため)。
 *
 * カスケード:
 *   1. tag_member : 同タグの .dat 母集団 × メンバー 0.5〜2倍 × メンバー数が近い順
 *   2. tag_top    : 同タグの recommend 上位 (いま伸びているルーム TOP5)
 *   3. cat_member : 同カテゴリの .dat 母集団 × メンバー 0.5〜2倍 × メンバー数が近い順
 *
 * 各段で MIN_COUNT 以上集まればその段で確定。
 * 全段で足りなければ null（呼び元はカテゴリおすすめ枠にフォールバック）。
 */
class SimilarSizeRoomService
{
    private const MIN_COUNT = 3;
    private const LIMIT = 5;

    public function __construct(
        private RecommendStaticDataGenerator $recommendStaticDataGenerator,
    ) {}

    /**
     * @return array{rooms: array<int, array<string,mixed>>, recommend: RecommendListDto|null, mode: 'tag_member'|'tag_top'|'cat_member'}|null
     */
    public function fetch(int $currentId, int $member, ?string $tag, ?int $category): ?array
    {
        if ($member <= 0) {
            return null;
        }

        $minMember = (int)floor($member * 0.5);
        $maxMember = (int)ceil($member * 2);

        if ($tag !== null && $tag !== '') {
            $dto = $this->recommendStaticDataGenerator->getRecomendRanking($tag);

            $rooms = $dto->findByMemberRange($currentId, $member, $minMember, $maxMember, self::LIMIT);
            if (count($rooms) >= self::MIN_COUNT) {
                return ['rooms' => $rooms, 'recommend' => null, 'mode' => 'tag_member'];
            }

            $rooms = $dto->getList(false, self::LIMIT, $currentId);
            if (count($rooms) >= self::MIN_COUNT) {
                return ['rooms' => $rooms, 'recommend' => $dto, 'mode' => 'tag_top'];
            }
        }

        if ($category !== null && $category > 0) {
            $dto = $this->recommendStaticDataGenerator->getCategoryRanking($category);

            $rooms = $dto->findByMemberRange($currentId, $member, $minMember, $maxMember, self::LIMIT);
            if (count($rooms) >= self::MIN_COUNT) {
                return ['rooms' => $rooms, 'recommend' => null, 'mode' => 'cat_member'];
            }
        }

        return null;
    }
}
