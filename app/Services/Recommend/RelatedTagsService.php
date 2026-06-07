<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Models\RecommendRepositories\RecommendRankingRepository;

/**
 * タグ間の意味的な近さ（関連タグ）を事前計算する。
 *
 * 根拠: タグ付けエンジンは 1部屋 = 1タグ（recommend）を優先順位で確定させるが、
 * 2・3番手にマッチしたタグも oc_tag / oc_tag2 に残っている。
 * 「優先順位次第でどちらにも転び得た部屋」を多く共有するタグ同士は意味的に近い
 * （例: ガチャガチャ ↔ シール / トレカ）。同一カテゴリよりも強いシグナル。
 *
 * 結果は静的データ生成（StaticDataGenerator::updateStaticData → @relatedTags）で
 * 毎時キャッシュされ、表示側（ThemeDiscoveryService）は読むだけにする。
 */
class RelatedTagsService
{
    /** 1タグあたり保存する関連タグ数（表示側の棚上限より少し多めに持つ） */
    private const RELATED_LIMIT = 16;

    public function __construct(
        private RecommendRankingRepository $recommendRankingRepository,
    ) {}

    /**
     * 全タグの関連タグマップを構築する。
     * スコアは共起部屋数（recommend×oc_tag + recommend×oc_tag2 を対称化した合計）。
     *
     * @return array<string, array<string, int>> [タグ => [関連タグ => スコア（降順）]]
     */
    public function build(): array
    {
        $score = [];
        foreach ($this->recommendRankingRepository->getRelatedTagPairs() as $row) {
            $a = (string)$row['tag'];
            $b = (string)$row['related'];
            $cnt = (int)$row['cnt'];
            if ($a === '' || $b === '') continue;
            // 対称化: A の2番手が B でも、B の2番手が A でも「近い」事実は同じ
            $score[$a][$b] = ($score[$a][$b] ?? 0) + $cnt;
            $score[$b][$a] = ($score[$b][$a] ?? 0) + $cnt;
        }

        foreach ($score as $tag => $related) {
            arsort($related);
            $score[$tag] = array_slice($related, 0, self::RELATED_LIMIT, true);
        }

        return $score;
    }
}
